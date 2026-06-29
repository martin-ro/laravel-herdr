<?php

namespace Herdr\Resources;

use Herdr\Connection;
use Herdr\Data\Workspace;
use Herdr\Exceptions\RequestException;
use Illuminate\Support\Collection;

/**
 * Query and create workspaces.
 */
class Workspaces
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * All workspaces (workspace.list).
     *
     * @return Collection<int, Workspace>
     */
    public function all(): Collection
    {
        return Workspace::collectionFromResult($this->connection->request('workspace.list'), $this->connection);
    }

    /**
     * Fetch a single workspace by id (workspace.get).
     */
    public function get(string $id): Workspace
    {
        return Workspace::fromResult(
            $this->connection->request('workspace.get', ['workspace_id' => $id]),
            $this->connection,
        );
    }

    /**
     * Fetch a workspace by id, or null when it does not exist.
     */
    public function find(string $id): ?Workspace
    {
        try {
            return $this->get($id);
        } catch (RequestException $e) {
            if ($e->code() === 'not_found') {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Create a workspace (workspace.create).
     *
     * @param  array<string, mixed>  $params
     */
    public function create(array $params = []): Workspace
    {
        return Workspace::fromResult($this->connection->request('workspace.create', $params), $this->connection);
    }

    /**
     * The currently focused workspace, if any.
     */
    public function focused(): ?Workspace
    {
        return $this->all()->first(fn (Workspace $workspace) => (bool) $workspace->get('focused'));
    }
}
