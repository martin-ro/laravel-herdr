<?php

namespace Herdr\Tests\Unit;

use Herdr\Connection;
use Herdr\Exceptions\ConnectionException;
use Herdr\Exceptions\RequestException;
use Herdr\Testing\FakeResponse;
use Herdr\Transport\FakeTransport;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public function test_it_sends_a_well_formed_request_and_returns_the_result(): void
    {
        $transport = new FakeTransport([
            'ping' => ['type' => 'pong'],
        ]);
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
        $connection = new Connection(new FakeTransport());

        $connection->request('ping');
        $connection->request('ping');

        $this->assertSame('req_1', json_decode($connection->transport()->written[0], true)['id']);
        $this->assertSame('req_2', json_decode($connection->transport()->written[1], true)['id']);
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

    public function test_it_buffers_events_received_while_awaiting_a_response(): void
    {
        $transport = new FakeTransport();
        // Queue an unsolicited event *before* the matching response.
        $transport->pushEvent(['type' => 'pane.exited', 'pane_id' => 'w1:p1']);

        $connection = new Connection($transport);
        // The fake answers ping after the event, so the connection must skip
        // the event (no id), buffer it, and still resolve the ping.
        $result = $connection->request('ping');

        $this->assertSame([], $result);

        $event = $connection->nextEvent();
        $this->assertSame('pane.exited', $event['type']);
    }

    public function test_it_throws_when_the_connection_closes_before_responding(): void
    {
        // A transport that never answers: readLine returns null immediately.
        $transport = new FakeTransport();
        $connection = new Connection(new ClosingTransport());

        $this->expectException(ConnectionException::class);
        $connection->request('ping');

        unset($transport);
    }
}

class ClosingTransport extends FakeTransport
{
    public function write(string $line): void
    {
        // Record but never produce a response line.
        $this->written[] = $line;
    }

    public function readLine(): ?string
    {
        return null;
    }
}
