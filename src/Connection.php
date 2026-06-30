<?php

namespace Herdr;

use Closure;
use Herdr\Exceptions\ConnectionException;
use Herdr\Exceptions\RequestException;
use Herdr\Transport\Transport;

/**
 * Wraps the transport with Herdr's protocol semantics.
 *
 * Herdr is **one request per connection**: the server writes a single response
 * and then closes the socket. So every {@see request()} opens a fresh transport,
 * sends, reads the matching response and closes. Event subscriptions are the
 * exception — after {@see openStream()} the server keeps the socket open and
 * pushes id-less event lines, which {@see nextEvent()} consumes.
 */
class Connection
{
    /** @var Closure(): Transport */
    private Closure $factory;

    private int $sequence = 0;

    /**
     * The persistent transport used for event streaming, if any.
     */
    private ?Transport $stream = null;

    /**
     * Events seen while awaiting a response (kept for completeness; on a
     * one-shot RPC connection none normally arrive).
     *
     * @var list<array<string, mixed>>
     */
    private array $eventQueue = [];

    /**
     * @param  Closure(): Transport|Transport  $transport  A factory that opens a
     *         fresh transport per request, or a single transport to reuse (the
     *         latter is used for the in-memory fake in tests).
     */
    public function __construct(Closure|Transport $transport, private readonly string $idPrefix = 'req')
    {
        $this->factory = $transport instanceof Transport
            ? fn () => $transport
            : $transport;
    }

    /**
     * Send a request on a fresh connection and return its `result` payload.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws RequestException    when Herdr replies with an error.
     * @throws ConnectionException on transport failure.
     */
    public function request(string $method, array $params = []): array
    {
        $transport = ($this->factory)();

        try {
            $id = $this->writeMessage($transport, $method, $params);

            return $this->awaitResponse($transport, $id, $method);
        } finally {
            $transport->close();
        }
    }

    /**
     * Open a long-lived streaming connection (used for events.subscribe),
     * returning the acknowledgement result. The connection is held open so
     * {@see nextEvent()} can read pushed events from it.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function openStream(string $method, array $params = []): array
    {
        $this->closeStream();

        $this->stream = ($this->factory)();

        $id = $this->writeMessage($this->stream, $method, $params);

        return $this->awaitResponse($this->stream, $id, $method);
    }

    /**
     * Return the next event from the open stream, blocking until one arrives.
     * Returns null when no stream is open or the stream has closed.
     *
     * @return array<string, mixed>|null
     */
    public function nextEvent(): ?array
    {
        if ($this->eventQueue !== []) {
            return array_shift($this->eventQueue);
        }

        if (! $this->stream instanceof Transport) {
            return null;
        }

        while (true) {
            $line = $this->stream->readLine();

            if ($line === null) {
                return null;
            }

            $message = $this->decode($line);

            if ($message !== null) {
                return $message;
            }
        }
    }

    public function closeStream(): void
    {
        if ($this->stream instanceof Transport) {
            $this->stream->close();
            $this->stream = null;
        }

        $this->eventQueue = [];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function writeMessage(Transport $transport, string $method, array $params): string
    {
        $id = $this->nextId();

        $transport->write($this->encode([
            'id' => $id,
            'method' => $method,
            // Cast so empty params serialise as {} rather than [].
            'params' => (object) $params,
        ]));

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function awaitResponse(Transport $transport, string $id, string $method): array
    {
        while (true) {
            $line = $transport->readLine();

            if ($line === null) {
                throw ConnectionException::closed();
            }

            $message = $this->decode($line);

            if ($message === null) {
                continue;
            }

            if (($message['id'] ?? null) === $id) {
                if (isset($message['error']) && is_array($message['error'])) {
                    throw RequestException::fromError($message['error'], $method);
                }

                return (array) ($message['result'] ?? []);
            }

            // An unsolicited event or a stray line: park it.
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
