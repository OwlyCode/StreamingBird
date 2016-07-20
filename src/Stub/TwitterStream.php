<?php

namespace OwlyCode\StreamingBird\Stub;

class TwitterStream
{
    /**
     * @var string
     */
    private $address;

    /**
     * @var int
     */
    private $port;

    /**
     * @var bool
     */
    private $run;

    public function __construct($address, $port = 80)
    {
        $this->address = $address;
        $this->port    = $port;

        $this->acceptOauth();
    }

    public function acceptOauth()
    {
        $this->oauth = "HTTP/1.1 200 OK
Transfer-Encoding: chunked
Content-Type: application/json";
    }

    public function import($path)
    {
        $this->stream = explode("\n", file_get_contents($path));
    }

    public function start()
    {
        $this->run = true;

        if (($server = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            throw new \RuntimeException(sprintf('Failed to create socket : "%s"', socket_strerror(socket_last_error())));
        }

        if (@socket_bind($server, $this->address, $this->port) === false) {
            throw new \RuntimeException(sprintf('Failed to bind socket : "%s"', socket_strerror(socket_last_error($server))));
        }

        if (@socket_listen($server, 5) === false) {
            throw new \RuntimeException(sprintf('Failed to listen on socket : "%s"', socket_strerror(socket_last_error($server))));
        }

        if (($client = @socket_accept($server)) === false) {
            throw new \RuntimeException(sprintf('Failed to accept socket : "%s"', socket_strerror(socket_last_error($server))));
        }

        do {
            if (false === ($buf = socket_read($client, 2048))) {
                throw new \RuntimeException(sprintf('Failed to read socket : "%s"', socket_strerror(socket_last_error($server))));
            }

            if (strpos($buf, 'POST /auth') === 0) {
                socket_write($client, $this->oauth."\r\n\r\n", strlen($this->oauth."\r\n\r\n"));

                foreach ($this->stream as $line) {
                    $size = dechex(strlen($line));
                    socket_write($client, $size."\r\n", strlen($size."\r\n"));
                    socket_write($client, $line."\r\n", strlen($line."\r\n"));
                }

                usleep(500000);
                $this->close();
            }
        } while ($this->run);

        socket_close($client);
        socket_close($server);
    }

    public function close()
    {
        $this->run = false;
    }
}
