<?php

namespace OwlyCode\StreamingBird;

use OwlyCode\StreamingBird\Location\LocationInterface;

class StreamReader
{
    const METHOD_FILTER   = 'filter';
    const METHOD_SAMPLE   = 'sample';
    const METHOD_RETWEET  = 'retweet';
    const METHOD_FIREHOSE = 'firehose';
    const METHOD_LINKS    = 'links';
    const METHOD_USER     = 'user';
    const METHOD_SITE     = 'site';

    private $endpoints = [
        'site'     => 'https://sitestream.twitter.com/1.1/site.json',
        'user'     => 'https://userstream.twitter.com/2/user.json',
        'filter'   => 'https://stream.twitter.com/1.1/statuses/filter.json',
        'sample'   => 'https://stream.twitter.com/1.1/statuses/sample.json',
        'retweet'  => 'https://stream.twitter.com/1.1/statuses/retweet.json',
        'firehose' => 'https://stream.twitter.com/1.1/statuses/firehose.json',
        'links'    => 'https://stream.twitter.com/1.1/statuses/links.json'
    ];

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Oauth
     */
    private $oauth;

    /**
     * @var Monitor
     */
    private $monitor;

    /**
     * @var bool
     */
    private $running;

    /**
    * @internal Moved from being a const to a variable, because some methods (user and site) need to change it.
    */
    protected $baseUrl = 'https://stream.twitter.com/1.1/statuses/';

    protected $method;
    protected $count; //Can be -150,000 to 150,000. @see http://dev.twitter.com/pages/streaming_api_methods#count
    protected $followIds;
    protected $trackWords;
    protected $location;

    /**
     * @param Connection $connection
     * @param Oauth      $oauth
     * @param string     $method
     * @param boolean    $lang
     */
    public function __construct(Connection $connection, Oauth $oauth, $method = AbstractStream::METHOD_SAMPLE, $lang = false)
    {
        $this->monitor = new Monitor();

        $this->monitor->register(Monitor::TYPE_MAX, 'max_idle_time', 0);
        $this->monitor->register(Monitor::TYPE_LAST, 'idle_time', 0);
        $this->monitor->register(Monitor::TYPE_COUNT, 'tweets');

        $this->oauth  = $oauth;
        $this->method = $method;
        $this->lang   = $lang;

        $this->connection = $connection;
    }

    /**
     * Consume from the streaming API.
     *
     * Will retry on any connection loss.
     *
     * @param callable $handler
     */
    public function consume(callable $handler)
    {
        $this->running = true;

        while ($this->running) {
            $this->consumeOnce($handler);
        }
    }

    public function stop()
    {
        $this->connection->close();
        $this->running = false;
    }

    /**
     * Consume from the streaming API.
     *
     * Will not retry on connection loss.
     *
     * @param callable $handler
     */
    protected function consumeOnce(callable $handler)
    {
        $this->connection = $this->connect();

        $lastStreamActivity = time();

        $this->connection->read(function ($tweet) use (&$lastStreamActivity, $handler) {
            $idle = (time() - $lastStreamActivity);

            $this->monitor->stat('max_idle_time', $idle);
            $this->monitor->stat('idle_time', $idle);
            $this->monitor->stat('tweets', 1);

            $lastStreamActivity = time();

            call_user_func($handler, $tweet, $this->monitor);
        });

        $this->connection->close();
    }

    /**
     * @param int $timeout
     * @param int $attempts
     *
     * @return Connection
     */
    protected function connect($timeout = 5, $attempts = 10)
    {
        $url      = $this->endpoints[$this->method];
        $urlParts = parse_url($url);
        $scheme   = $urlParts['scheme'] == 'https' ? 'ssl://' : 'tcp://';
        $port     = $urlParts['scheme'] == 'https' ? 443 : 80;

        $requestParams = [];

        if ($this->lang) {
            $requestParams['language'] = $this->lang;
        }

        if (($this->method === self::METHOD_FILTER || $this->method === self::METHOD_USER) && count($this->trackWords) > 0) {
            $requestParams['track'] = implode(',', $this->trackWords);
        }
        if (($this->method === self::METHOD_FILTER || $this->method === self::METHOD_SITE) && count($this->followIds) > 0) {
            $requestParams['follow'] = implode(',', $this->followIds);
        }

        if ($this->method === self::METHOD_FILTER && $this->location) {
            $requestParams['locations'] = implode(',', $this->location->getBoundingBox());
        }

        if ($this->count <> 0) {
            $requestParams['count'] = $this->count;
        }

        $this->connection->open($scheme . $urlParts['host'], $port, $timeout, $attempts);
        $this->connection->authenticate($url, $requestParams, $this->oauth->getAuthorizationHeader($url, $requestParams));

        return $this->connection;
    }

    /**
     * @return Monitor
     */
    public function getMonitor()
    {
        return $this->monitor;
    }

    /**
    * @param string $host
    */
    public function setHostPort($port)
    {
        $this->hostPort = $port;

        return $this;
    }

    /**
    * @param int $port
    */
    public function setSecureHostPort($port)
    {
        $this->secureHostPort = $port;

        return $this;
    }

    /**
    * Returns public statuses from or in reply to a set of users. Mentions ("Hello @user!") and implicit replies
    * ("@user Hello!" created without pressing the reply button) are not matched. It is up to you to find the integer
    * IDs of each twitter user.
    * Applies to: METHOD_FILTER
    *
    * @param array $userIds Array of Twitter integer userIDs
    */
    public function setFollow(array $userIds = [])
    {
        sort($userIds);

        $this->followIds = $userIds;

        return $this;
    }

    /**
    * Returns an array of followed Twitter userIds (integers)
    *
    * @return array
    */
    public function getFollow()
    {
        return $this->followIds;
    }

    /**
    * Specifies keywords to track. Track keywords are case-insensitive logical ORs. Terms are exact-matched, ignoring
    * punctuation. Phrases, keywords with spaces, are not supported. Queries are subject to Track Limitations.
    * Applies to: METHOD_FILTER
    *
    * See: http://apiwiki.twitter.com/Streaming-API-Documentation#TrackLimiting
    *
    * @param array $trackWords
    */
    public function setTrack(array $trackWords = [])
    {
        sort($trackWords); // Non-optimal, but necessary

        $this->trackWords = $trackWords;

        return $this;
    }

    /**
    * @return array
    */
    public function getTrack()
    {
        return $this->trackWords;
    }

    /**
     * @param LocationInterface $location
     */
    public function setLocation(LocationInterface $location)
    {
        $this->location = $location;

        return $this;
    }

    /**
     * @return LocationInterface
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
    * Sets the number of previous statuses to stream before transitioning to the live stream. Applies only to firehose
    * and filter + track methods. This is generally used internally and should not be needed by client applications.
    * Applies to: METHOD_FILTER, METHOD_FIREHOSE, METHOD_LINKS
    *
    * @param int $count
    */
    public function setCount($count)
    {
        $this->count = $count;

        return $this;
    }

    /**
    * Restricts tweets to the given language, given by an ISO 639-1 code (http://en.wikipedia.org/wiki/List_of_ISO_639-1_codes).
    *
    * @param string $lang
    */
    public function setLang($lang)
    {
        $this->lang = $lang;

        return $this;
    }

    /**
    * Returns the ISO 639-1 code formatted language string of the current setting. (http://en.wikipedia.org/wiki/List_of_ISO_639-1_codes).
    *
    * @param string $lang
    */
    public function getLang()
    {
        return $this->lang;
    }
}
