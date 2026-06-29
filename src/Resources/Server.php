<?php

namespace Herdr\Resources;

use Herdr\Connection;
use Herdr\Data\Result;
use Illuminate\Support\Collection;

/**
 * Server-level operations.
 */
class Server
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function ping(): Result
    {
        return Result::fromResult($this->connection->request('ping'), $this->connection);
    }

    public function stop(): Result
    {
        return Result::fromResult($this->connection->request('server.stop'), $this->connection);
    }

    public function reloadConfig(): Result
    {
        return Result::fromResult($this->connection->request('server.reload_config'), $this->connection);
    }

    /**
     * @return Collection<int, Result>
     */
    public function agentManifests(): Collection
    {
        return Result::collectionFromResult($this->connection->request('server.agent_manifests'), $this->connection);
    }

    public function reloadAgentManifests(): Result
    {
        return Result::fromResult($this->connection->request('server.reload_agent_manifests'), $this->connection);
    }
}
