<?php

namespace MartinRo\Herdr\Tests\Unit;

use MartinRo\Herdr\Connection;
use MartinRo\Herdr\Exceptions\ConnectionException;
use MartinRo\Herdr\Exceptions\RequestException;
use MartinRo\Herdr\Testing\FakeResponse;
use MartinRo\Herdr\Transport\FakeTransport;
use MartinRo\Herdr\Transport\Transport;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public function test_it_sends_a_well_formed_request_and_returns_the_result(): void
    {
        $transport = new FakeTransport(['ping' => ['type' => 'pong']]);
        $connection = new Connection($transport);

        $result = $connection->request('ping');

        $this->assertSame(['type' => 'pong'], $result);

        $sent = json_decode($transport->written[0], true);
        $this->assertSame('req_1', $sent['id']);
        $this->assertSame('ping', $sent['method']);
        // Empty params must serialise as an object, not an array.
        $this->assertStringContainsString('"params":{}', $transport->written[0]);
    }

    public function test_it_increments_request_ids(): void
    {
        $transport = new FakeTransport();
        $connection = new Connection($transport);

        $connection->request('ping');
        $connection->request('ping');

        $this->assertSame('req_1', json_decode($transport->written[0], true)['id']);
        $this->assertSame('req_2', json_decode($transport->written[1], true)['id']);
    }

    public function test_each_request_uses_a_fresh_transport(): void
    {
        // Herdr is one-request-per-connection: the connection must open a new
        // transport for every request and close it afterwards.
        $opened = 0;
        $closed = 0;

        $connection = new Connection(function () use (&$opened, &$closed) {
            $opened++;

            return new class($closed) extends FakeTransport {
                public function __construct(private int &$closedCounter)
                {
                    parent::__construct(['ping' => ['type' => 'pong']]);
                }

                public function close(): void
                {
                    $this->closedCounter++;
                    parent::close();
                }
            };
        });

        $connection->request('ping');
        $connection->request('ping');

        $this->assertSame(2, $opened);
        $this->assertSame(2, $closed);
    }

    public function test_it_throws_a_request_exception_on_error_responses(): void
    {
        $transport = new FakeTransport([
            'pane.get' => FakeResponse::error('not_found', 'pane not found'),
        ]);
        $connection = new Connection($transport);

        try {
            $connection->request('pane.get', ['pane_id' => 'w9:p9']);
            $this->fail('Expected a RequestException.');
        } catch (RequestException $e) {
            $this->assertSame('not_found', $e->code());
            $this->assertSame('pane.get', $e->method);
            $this->assertStringContainsString('pane not found', $e->getMessage());
        }
    }

    public function test_it_reads_the_kind_of_a_herdr_core_error(): void
    {
        $transport = new class implements Transport
        {
            private ?string $reply = null;

            public function write(string $line): void
            {
                $id = json_decode($line, true)['id'];

                $this->reply = json_encode([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => ['code' => -32000, 'message' => 'no terminal "t9"', 'data' => ['kind' => 'not_found', 'hint' => 'see `hc ls --all`']],
                ]);
            }

            public function readLine(): ?string
            {
                return $this->reply;
            }

            public function isConnected(): bool
            {
                return true;
            }

            public function close(): void
            {
            }
        };

        try {
            (new Connection($transport))->request('terminal.get', ['terminal' => 't9']);
            $this->fail('Expected a RequestException.');
        } catch (RequestException $e) {
            $this->assertSame('not_found', $e->code());
            $this->assertTrue($e->isNotFound());
            $this->assertStringContainsString('no terminal "t9"', $e->getMessage());
        }
    }

    public function test_events_stream_over_a_held_connection(): void
    {
        $transport = new FakeTransport([
            'events.subscribe' => ['type' => 'subscription_started'],
        ]);
        $connection = new Connection($transport);

        $ack = $connection->openStream('events.subscribe', ['subscriptions' => [['type' => 'pane.exited']]]);
        $this->assertSame('subscription_started', $ack['type']);

        $transport->pushEvent(['type' => 'pane.exited', 'pane_id' => 'w1:p1']);

        $event = $connection->nextEvent();
        $this->assertSame('pane.exited', $event['type']);
        $this->assertSame('w1:p1', $event['pane_id']);
    }

    public function test_it_throws_when_the_connection_closes_before_responding(): void
    {
        $connection = new Connection(new ClosingTransport());

        $this->expectException(ConnectionException::class);
        $connection->request('ping');
    }
}

class ClosingTransport implements Transport
{
    /** @var list<string> */
    public array $written = [];

    public function write(string $line): void
    {
        $this->written[] = $line;
    }

    public function readLine(): ?string
    {
        return null;
    }

    public function isConnected(): bool
    {
        return false;
    }

    public function close(): void
    {
    }
}
