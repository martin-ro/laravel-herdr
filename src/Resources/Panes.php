<?php

namespace Herdr\Resources;

use Herdr\Connection;
use Herdr\Data\Pane;
use Herdr\Exceptions\RequestException;
use Illuminate\Support\Collection;

/**
 * Query panes.
 */
class Panes
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * All panes (pane.list), optionally scoped, e.g. ['workspace_id' => 'w1'].
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Pane>
     */
    public function all(array $filters = []): Collection
    {
        return Pane::collectionFromResult($this->connection->request('pane.list', $filters), $this->connection);
    }

    /**
     * The currently focused pane (pane.current).
     */
    public function current(): Pane
    {
        return Pane::fromResult($this->connection->request('pane.current'), $this->connection);
    }

    /**
     * Fetch a pane by id (pane.get).
     */
    public function get(string $id): Pane
    {
        return Pane::fromResult($this->connection->request('pane.get', ['pane_id' => $id]), $this->connection);
    }

    public function find(string $id): ?Pane
    {
        try {
            return $this->get($id);
        } catch (RequestException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Panes that currently have an agent in the given status.
     *
     * @return Collection<int, Pane>
     */
    public function withAgentStatus(string $status): Collection
    {
        return $this->all()->filter(fn (Pane $pane) => $pane->agentStatus() === $status)->values();
    }
}
