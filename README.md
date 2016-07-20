StreamingBird is an automatically tested, PSR-4 compliant client for Twitter's streaming API.

[![Build Status](https://travis-ci.org/OwlyCode/StreamingBird.svg?branch=master)](https://travis-ci.org/OwlyCode/StreamingBird)

How to install
--------------

With composer : `composer require owlycode/streaming-bird`.

Example usage
-------------

```php
use OwlyCode\StreamingBird\StreamReader;
use OwlyCode\StreamingBird\StreamingBird;

 // Change these with yours.
$oauthToken = 'my token';
$oauthSecret = 'secret';
$consumerKey = 'my key';
$consumerSecret = 'secret';

$bird = new StreamingBird($consumerKey, $consumerSecret, $oauthToken, $oauthSecret);

$bird
    ->createStreamReader(StreamReader::METHOD_FILTER)
    ->setTrack(['hello', 'hola', 'bonjour']) // Fetch every tweet containing one of the following words
    ->consume(function ($tweet) { // Now we provide a callback to execute on every received tweet.
        echo '------------------------' . "\n";
        echo $tweet['text'] . "\n";
    })
;

```

Monitoring
----------

StreamingBird comes with some statistics about the stream that you can access. At the moment
it is an early approach with few informations but it is meant to grow in the future.

```php
$reader->consume(function ($tweet, $monitor) use ($output) {
    echo '------------------------' . "\n";
    echo $monitor->get('tweets') . "\n"; // The total number of received tweets
    echo $monitor->get('idle_time') . "\n"; // Elapsed seconds between the last two tweets.
    echo $monitor->get('max_idle_time') . "\n"; // The maximum idle time since the beginning.
    echo $tweet['text'] . "\n";
});
```

Running the tests
-----------------

Simply run :

```bash
composer install
./bin/vendor/phpunit
```