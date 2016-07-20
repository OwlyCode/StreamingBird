<?php

namespace OwlyCode\StreamingBird\Location\Tests;

use OwlyCode\StreamingBird\Connection;
use Symfony\Component\Process\Process;

class ConnectionTest extends \PHPUnit_Framework_TestCase
{
    private $server;

    /**
     * This ensure the headers stay consistent.
     */
    public function testAuthentication()
    {
        $this->runServer('success');

        $connection = new Connection();

        $connection->open('127.0.0.1', 9999, 2, 1);
        $connection->authenticate('http://stub/auth', ['key' => '123'], 'CREDENTIALS');

        $readCount = 0;

        $connection->read(function ($tweet) use (&$readCount) {
            $this->assertArrayHasKey('foo', $tweet);
            $readCount++;
        });

        $this->assertSame(3, $readCount);
    }

    /**
     * @param string $name
     */
    protected function runServer($name)
    {
        $this->server = new Process(sprintf('php %s/Resources/bin/stub-%s.php', __DIR__, $name));
        $this->server->start();
        sleep(1);
    }

    protected function tearDown()
    {
        if ($this->server) {
            $this->server->stop();
            $this->server = null;
        }
    }
}
