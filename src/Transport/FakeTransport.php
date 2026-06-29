<?php

namespace Herdr\Transport;

use Closure;
use Herdr\Testing\FakeResponse;

/**
 * An in-memory transport for tests. It records every request written to it and
 * replays scripted responses, mirroring Herdr's request/response framing. It
 * can also queue unsolicited events to exercise the event stream.
 */
class FakeTransport implements Transport
{
    /**
     * Raw request lines written by the client, in order.
     *
     * @var list<string>
     */
    public array $written = [];

    /**
     * Decoded requests written by the client, in order.
     *
     * @var list<array<string, mixed>>
     */
    public array $requests = [];

    /**
     * Lines queued to be returned by readLine(), oldest first.
     *
     * @var list<string>
     */
    private array $outgoing = [];

    private bool $connected = true;

    /**
     * Method => stub. A stub is an array (used as `result`), a FakeResponse, or
     * a Closure(array $params, array $request): array|FakeResponse. The "*" key
     * matches any method not otherwise stubbed.
     *
     * @var array<string, mixed>
     */
    private array $stubs;

    /**
     * @param  array<string, mixed>  $stubs
     */
    public function __construct(array $stubs = [])
    {
        $this->stubs = $stubs;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function write(string $line): void
    {
        $this->written[] = $line;

        $request = json_decode($line, true);

        if (! is_array($request)) {
            return;
        }

        $this->requests[] = $request;

        $id = (string) ($request['id'] ?? '');
        $method = (string) ($request['method'] ?? '');
        $params = (array) ($request['params'] ?? []);

        $stub = $this->stubs[$method] ?? $this->stubs['*'] ?? null;

        $response = $this->resolve($stub, $params, $request);

        $this->outgoing[] = json_encode($response->envelope($id));
    }

    public function readLine(): ?string
    {
        if ($this->outgoing === []) {
            return null;
        }

        return array_shift($this->outgoing);
    }

    public function close(): void
    {
        $this->connected = false;
    }

    /**
     * Queue an unsolicited event line to be delivered to the client.
     *
     * @param  array<string, mixed>  $event
     */
    public function pushEvent(array $event): void
    {
        $this->outgoing[] = json_encode($event);
    }

    /**
     * @param  mixed  $stub
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $request
     */
    private function resolve($stub, array $params, array $request): FakeResponse
    {
        if ($stub instanceof Closure) {
            $stub = $stub($params, $request);
        }

        if ($stub instanceof FakeResponse) {
            return $stub;
        }

        if (is_array($stub)) {
            return FakeResponse::ok($stub);
        }

        // No stub configured: reply with an empty success so calls never hang.
        return FakeResponse::ok();
    }
}
