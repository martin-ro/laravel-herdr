<?php

namespace Herdr\Resources;

use Herdr\Connection;
use Herdr\Data\Result;
use Illuminate\Support\Collection;

/**
 * Manage plugins and invoke plugin actions.
 */
class Plugins
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * All linked plugins (plugin.list).
     *
     * @return Collection<int, Result>
     */
    public function all(): Collection
    {
        return Result::collectionFromResult($this->connection->request('plugin.list'), $this->connection);
    }

    public function link(string $path): Result
    {
        return Result::fromResult($this->connection->request('plugin.link', ['path' => $path]), $this->connection);
    }

    public function unlink(string $plugin): Result
    {
        return Result::fromResult($this->connection->request('plugin.unlink', ['plugin' => $plugin]), $this->connection);
    }

    public function enable(string $plugin): Result
    {
        return Result::fromResult($this->connection->request('plugin.enable', ['plugin' => $plugin]), $this->connection);
    }

    public function disable(string $plugin): Result
    {
        return Result::fromResult($this->connection->request('plugin.disable', ['plugin' => $plugin]), $this->connection);
    }

    /**
     * List a plugin's declared actions (plugin.action.list).
     *
     * @return Collection<int, Result>
     */
    public function actions(string $plugin): Collection
    {
        return Result::collectionFromResult(
            $this->connection->request('plugin.action.list', ['plugin' => $plugin]),
            $this->connection,
        );
    }

    /**
     * Invoke a plugin action (plugin.action.invoke).
     *
     * @param  array<string, mixed>  $params
     */
    public function invoke(string $plugin, string $action, array $params = []): Result
    {
        return Result::fromResult(
            $this->connection->request('plugin.action.invoke', array_merge([
                'plugin' => $plugin,
                'action' => $action,
            ], $params)),
            $this->connection,
        );
    }
}
