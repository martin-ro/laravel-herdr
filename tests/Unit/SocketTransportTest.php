<?php

namespace MartinRo\Herdr\Tests\Unit;

use MartinRo\Herdr\Exceptions\ConnectionException;
use MartinRo\Herdr\Transport\SocketTransport;
use PHPUnit\Framework\TestCase;

class SocketTransportTest extends TestCase
{
    private ?string $path = null;

    /** @var resource|null */
    private $server = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('unix', stream_get_transports(), true)) {
            $this->markTestSkipped('Unix domain sockets are not available on this platform.');
        }

        $this->path = sys_get_temp_dir().'/herdr-test-'.bin2hex(random_bytes(6)).'.sock';
        $this->server = stream_socket_server('unix://'.$this->path, $errno, $errstr);

        if ($this->server === false) {
            $this->markTestSkipped("Could not create a unix socket server: {$errstr}");
        }
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            fclose($this->server);
        }

        if ($this->path !== null && file_exists($this->path)) {
            @unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_it_writes_newline_delimited_messages(): void
    {
        $transport = new SocketTransport($this->path);
        $transport->write('{"id":"req_1","method":"ping"}');

        $peer = stream_socket_accept($this->server, 2);
        $this->assertIsResource($peer);

        $line = fgets($peer);
        $this->assertSame("{\"id\":\"req_1\",\"method\":\"ping\"}\n", $line);

        fclose($peer);
        $transport->close();
    }

    public function test_it_splits_multiple_messages_arriving_in_one_chunk(): void
    {
        $transport = new SocketTransport($this->path);
        // Force the client to connect so the server can accept it.
        $transport->write('{"hello":true}');

        $peer = stream_socket_accept($this->server, 2);
        $this->assertIsResource($peer);

        // Two complete messages plus a partial third, all in a single write.
        fwrite($peer, "{\"a\":1}\n{\"b\":2}\n{\"c\":");

        $this->assertSame('{"a":1}', $transport->readLine());
        $this->assertSame('{"b":2}', $transport->readLine());

        // Completing the third message should then be readable.
        fwrite($peer, "3}\n");
        $this->assertSame('{"c":3}', $transport->readLine());

        fclose($peer);
        $transport->close();
    }

    public function test_it_returns_null_when_the_peer_closes(): void
    {
        $transport = new SocketTransport($this->path);
        $transport->write('{"hello":true}');

        $peer = stream_socket_accept($this->server, 2);
        $this->assertIsResource($peer);

        fwrite($peer, "{\"only\":1}\n");
        fclose($peer);

        $this->assertSame('{"only":1}', $transport->readLine());
        $this->assertNull($transport->readLine());

        $transport->close();
    }

    public function test_it_throws_when_the_socket_does_not_exist(): void
    {
        $transport = new SocketTransport(sys_get_temp_dir().'/herdr-missing-'.bin2hex(random_bytes(6)).'.sock');

        $this->expectException(ConnectionException::class);
        $transport->write('{"x":1}');
    }
}
