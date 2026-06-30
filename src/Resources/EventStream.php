<?php

namespace MartinRo\Herdr\Resources;

use MartinRo\Herdr\Connection;
use MartinRo\Herdr\Data\Event;
use MartinRo\Herdr\Data\Result;

/**
 * Subscribe to and consume Herdr's pushed event stream.
 *
 * Events are delivered as unsolicited JSON lines after a subscription is
 * acknowledged. Because consuming events blocks on socket reads, run an event
 * loop on a dedicated connection (e.g. a queue worker or artisan command)
 * rather than inside a web request.
 */
class EventStream
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Subscribe to one or more event selectors (events.subscribe).
     *
     * Each selector may be a plain type string ("pane.exited") or an
     * associative filter (["type" => "pane.agent_status_changed",
     * "pane_id" => "w1:p1", "agent_status" => "blocked"]).
     *
     * @param  array<int, string|array<string, mixed>>  $subscriptions
     */
    public function subscribe(array $subscriptions): Result
    {
        // Unlike ordinary requests, a subscription holds the connection open so
        // the server can push events down it afterwards.
        return Result::fromResult(
            $this->connection->openStream('events.subscribe', [
                'subscriptions' => $this->normalize($subscriptions),
            ]),
            $this->connection,
        );
    }

    /**
     * Close the underlying event stream.
     */
    public function close(): void
    {
        $this->connection->closeStream();
    }

    /**
     * Read the next event, blocking until one arrives. Returns null when the
     * connection closes.
     */
    public function next(): ?Event
    {
        $message = $this->connection->nextEvent();

        return $message === null ? null : new Event($message, $this->connection);
    }

    /**
     * Subscribe (optionally) then invoke the callback for each event until the
     * connection closes or the callback returns false.
     *
     * @param  callable(Event): (bool|void)  $callback
     * @param  array<int, string|array<string, mixed>>  $subscriptions
     */
    public function listen(callable $callback, array $subscriptions = []): void
    {
        if ($subscriptions !== []) {
            $this->subscribe($subscriptions);
        }

        while (($event = $this->next()) !== null) {
            if ($callback($event) === false) {
                break;
            }
        }
    }

    /**
     * Subscribe then block until the first event (optionally matching a
     * predicate) arrives, returning it. Returns null if the stream ends first.
     *
     * @param  array<int, string|array<string, mixed>>  $subscriptions
     * @param  (callable(Event): bool)|null  $until
     */
    public function wait(array $subscriptions, ?callable $until = null): ?Event
    {
        $this->subscribe($subscriptions);

        while (($event = $this->next()) !== null) {
            if ($until === null || $until($event)) {
                return $event;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|array<string, mixed>>  $subscriptions
     * @return list<array<string, mixed>>
     */
    private function normalize(array $subscriptions): array
    {
        return array_values(array_map(
            fn ($subscription) => is_string($subscription)
                ? ['type' => $subscription]
                : $subscription,
            $subscriptions,
        ));
    }
}
