<?php

namespace Herdr;

use Herdr\Exceptions\ConnectionException;
use Herdr\Exceptions\RequestException;
use Herdr\Transport\Transport;

/**
 * Wraps a {@see Transport} with Herdr's JSON-RPC-ish semantics: it assigns a
 * unique id to every request, reads lines until the matching response arrives,
 * and parks any unsolicited events (which carry no id) on a queue for the
 * events stream to consume later.
 */
class Connection
{
    private int $sequence = 0;

    /**
     * Events received while awaiting a response, in arrival order.
     *
     * @var list<array<string, mixed>>
     */
    private array $eventQueue = [];

    public function __construct(
        private readonly Transport $transport,
        private readonly string $idPrefix = 'req',
    ) {
    }

    public function transport(): Transport
    {
        return $this->transport;
    }

    /**
     * Send a request and return its `result` payload.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws RequestException   when Herdr replies with an error.
     * @throws ConnectionException on transport failure.
     */
    public function request(string $method, array $params = []): array
    {
        $id = $this->nextId();

        $message = [
            'id' => $id,
            'method' => $method,
            // Cast so empty params serialise as {} rather than [].
            'params' => (object) $params,
        ];

        $this->transport->write($this->encode($message));

        return $this->awaitResponse($id, $method);
    }

    /**
     * Return the next event, draining the buffer before reading the socket.
     * Returns null when the connection closes with no events left.
     *
     * @return array<string, mixed>|null
     */
    public function nextEvent(): ?array
    {
        if ($this->eventQueue !== []) {
            return array_shift($this->eventQueue);
        }

        while (true) {
            $line = $this->transport->readLine();

            if ($line === null) {
                return null;
            }

            $message = $this->decode($line);

            if ($message === null) {
                continue;
            }

            return $message;
        }
    }

    /**
     * Drain and return every event buffered so far without blocking.
     *
     * @return list<array<string, mixed>>
     */
    public function flushEvents(): array
    {
        $events = $this->eventQueue;
        $this->eventQueue = [];

        return $events;
    }

    public function close(): void
    {
        $this->transport->close();
    }

    /**
     * @return array<string, mixed>
     */
    private function awaitResponse(string $id, string $method): array
    {
        while (true) {
            $line = $this->transport->readLine();

            if ($line === null) {
                throw ConnectionException::closed();
            }

            $message = $this->decode($line);

            if ($message === null) {
                continue;
            }

            // A response for our request id.
            if (($message['id'] ?? null) === $id) {
                if (isset($message['error']) && is_array($message['error'])) {
                    throw RequestException::fromError($message['error'], $method);
                }

                return (array) ($message['result'] ?? []);
            }

            // Anything else (an unsolicited event, or a late reply for another
            // request) is parked for the event stream.
            $this->eventQueue[] = $message;
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function encode(array $message): string
    {
        return json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $line): ?array
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        $decoded = json_decode($line, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function nextId(): string
    {
        return $this->idPrefix.'_'.(++$this->sequence);
    }
}
