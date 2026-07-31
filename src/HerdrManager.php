<?php

namespace MartinRo\Herdr;

use MartinRo\Herdr\Data\Agent;
use MartinRo\Herdr\Data\Pane;
use MartinRo\Herdr\Data\Result;
use MartinRo\Herdr\Data\Tab;
use MartinRo\Herdr\Data\Workspace;
use MartinRo\Herdr\Resources\Agents;
use MartinRo\Herdr\Resources\EventStream;
use MartinRo\Herdr\Resources\Integrations;
use MartinRo\Herdr\Resources\Layouts;
use MartinRo\Herdr\Resources\Panes;
use MartinRo\Herdr\Resources\Plugins;
use MartinRo\Herdr\Resources\Server;
use MartinRo\Herdr\Resources\Tabs;
use MartinRo\Herdr\Resources\Workspaces;
use MartinRo\Herdr\Resources\Worktrees;
use MartinRo\Herdr\Testing\FakeResponse;
use MartinRo\Herdr\Transport\FakeTransport;
use PHPUnit\Framework\Assert;

/**
 * The fluent entry point to the Herdr socket API. Resolved from the container
 * as the singleton "herdr" and exposed through the {@see \MartinRo\Herdr\Facades\Herdr}
 * facade.
 */
class HerdrManager
{
    private Connection $connection;

    private ?FakeTransport $fakeTransport = null;

    public function __construct(
        private readonly ConnectionFactory $factory,
        private readonly ?string $session = null,
    ) {
        $this->connection = $factory->make($session);
    }

    /* ----------------------------------------------------------------------
     | Connection management
     |---------------------------------------------------------------------- */

    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * Use a specific connection instance (advanced / testing).
     */
    public function usingConnection(Connection $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Talk to a different named Herdr session, returning a new manager bound to
     * that session's socket.
     */
    public function session(string $name): static
    {
        return new static($this->factory, $name);
    }

    /* ----------------------------------------------------------------------
     | Low-level escape hatch
     |---------------------------------------------------------------------- */

    /**
     * Call any Herdr method and get the raw result array. Useful for methods
     * not yet covered by a dedicated helper.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = []): array
    {
        return $this->connection->request($method, $params);
    }

    /**
     * Like {@see call()} but wraps the result in a permissive {@see Result}.
     *
     * @param  array<string, mixed>  $params
     */
    public function result(string $method, array $params = []): Result
    {
        return Result::fromResult($this->call($method, $params), $this->connection);
    }

    public function ping(): Result
    {
        return $this->result('ping');
    }

    /**
     * The full session snapshot in one call (session.snapshot): workspaces,
     * tabs, panes, agents, layouts and focus state.
     */
    public function snapshot(): Result
    {
        $result = $this->call('session.snapshot');

        return Result::fromResult(
            is_array($result['snapshot'] ?? null) ? $result['snapshot'] : $result,
            $this->connection,
        );
    }

    /* ----------------------------------------------------------------------
     | Resource accessors
     |---------------------------------------------------------------------- */

    public function workspaces(): Workspaces
    {
        return new Workspaces($this->connection);
    }

    public function workspace(?string $id = null): Workspace
    {
        return new Workspace($id === null ? [] : ['workspace_id' => $id], $this->connection);
    }

    public function tabs(): Tabs
    {
        return new Tabs($this->connection);
    }

    public function tab(?string $id = null): Tab
    {
        return new Tab($id === null ? [] : ['tab_id' => $id], $this->connection);
    }

    public function panes(): Panes
    {
        return new Panes($this->connection);
    }

    /**
     * A handle to a pane. Pass null to target Herdr's focused pane.
     */
    public function pane(?string $id = null): Pane
    {
        return new Pane($id === null ? [] : ['pane_id' => $id], $this->connection);
    }

    public function agents(): Agents
    {
        return new Agents($this->connection);
    }

    /**
     * A handle to an agent by target (unique agent name, or the id of the
     * pane hosting it).
     */
    public function agent(?string $target = null): Agent
    {
        return new Agent($target === null ? [] : ['target' => $target], $this->connection);
    }

    public function worktrees(): Worktrees
    {
        return new Worktrees($this->connection);
    }

    public function plugins(): Plugins
    {
        return new Plugins($this->connection);
    }

    public function layouts(): Layouts
    {
        return new Layouts($this->connection);
    }

    public function integrations(): Integrations
    {
        return new Integrations($this->connection);
    }

    public function events(): EventStream
    {
        return new EventStream($this->connection);
    }

    public function server(): Server
    {
        return new Server($this->connection);
    }

    /* ----------------------------------------------------------------------
     | Convenience commands
     |---------------------------------------------------------------------- */

    /**
     * Show a desktop notification (notification.show).
     *
     * @param  array<string, mixed>  $options  e.g.
     *   ['position' => 'top-left', 'sound' => 'request']
     */
    public function notify(string $title, ?string $body = null, array $options = []): Result
    {
        $params = array_merge(['title' => $title], $options);

        if ($body !== null) {
            $params['body'] = $body;
        }

        return $this->result('notification.show', $params);
    }

    /**
     * Set or clear the client window title (client.window_title.set / .clear).
     */
    public function windowTitle(?string $title): Result
    {
        if ($title === null || $title === '') {
            return $this->result('client.window_title.clear');
        }

        return $this->result('client.window_title.set', ['title' => $title]);
    }

    /**
     * Close the currently open popup pane, if any (popup.close).
     */
    public function closePopup(): Result
    {
        return $this->result('popup.close');
    }

    /* ----------------------------------------------------------------------
     | Testing helpers
     |---------------------------------------------------------------------- */

    /**
     * Swap in an in-memory transport and script responses. Keys are method
     * names (or "*"); values are result arrays, {@see FakeResponse} instances,
     * or closures returning either.
     *
     * @param  array<string, mixed>  $stubs
     */
    public function fake(array $stubs = []): static
    {
        $this->fakeTransport = new FakeTransport($stubs);
        $this->connection = $this->factory->fromTransport($this->fakeTransport);

        return $this;
    }

    /**
     * Queue an unsolicited event to be delivered to the (faked) event stream.
     *
     * @param  array<string, mixed>  $event
     */
    public function pushEvent(array $event): static
    {
        $this->fakeTransport()->pushEvent($event);

        return $this;
    }

    /**
     * The requests recorded by the fake, optionally filtered by a callback
     * receiving (string $method, array $params, array $request).
     *
     * @param  (callable(string, array<string, mixed>, array<string, mixed>): bool)|null  $filter
     * @return list<array<string, mixed>>
     */
    public function recorded(?callable $filter = null): array
    {
        $requests = $this->fakeTransport()->requests;

        if ($filter === null) {
            return $requests;
        }

        return array_values(array_filter(
            $requests,
            fn (array $request) => $filter(
                (string) ($request['method'] ?? ''),
                (array) ($request['params'] ?? []),
                $request,
            ),
        ));
    }

    /**
     * Assert that a request matching the callback was sent. With no callback,
     * asserts a request to the given method name was sent.
     *
     * @param  string|callable(string, array<string, mixed>, array<string, mixed>): bool  $callback
     */
    public function assertSent(string|callable $callback): static
    {
        $filter = is_string($callback)
            ? fn (string $method) => $method === $callback
            : $callback;

        Assert::assertNotEmpty(
            $this->recorded($filter),
            'Expected a matching Herdr request to have been sent, but none was.',
        );

        return $this;
    }

    /**
     * @param  string|callable(string, array<string, mixed>, array<string, mixed>): bool  $callback
     */
    public function assertNotSent(string|callable $callback): static
    {
        $filter = is_string($callback)
            ? fn (string $method) => $method === $callback
            : $callback;

        Assert::assertEmpty(
            $this->recorded($filter),
            'Expected no matching Herdr request to have been sent, but one was.',
        );

        return $this;
    }

    public function assertSentCount(int $count): static
    {
        Assert::assertCount($count, $this->fakeTransport()->requests);

        return $this;
    }

    public function assertNothingSent(): static
    {
        Assert::assertEmpty(
            $this->fakeTransport()->requests,
            'Expected no Herdr requests to have been sent.',
        );

        return $this;
    }

    private function fakeTransport(): FakeTransport
    {
        if (! $this->fakeTransport instanceof FakeTransport) {
            throw new \LogicException('Herdr is not faked. Call Herdr::fake() first.');
        }

        return $this->fakeTransport;
    }
}
