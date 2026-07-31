<?php

namespace MartinRo\Herdr\Resources;

use MartinRo\Herdr\Connection;
use MartinRo\Herdr\Data\Result;
use Illuminate\Support\Collection;

/**
 * Server-level operations.
 */
class Server
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * The pong result carries "version", "protocol" and "capabilities"
     * (e.g. live_handoff, detached_server_daemon).
     */
    public function ping(): Result
    {
        return Result::fromResult($this->connection->request('ping'), $this->connection);
    }

    /**
     * Hand the live session over to a new server binary (server.live_handoff).
     *
     * @param  array<string, mixed>  $options  e.g.
     *   ['expected_version' => '0.7.5', 'expected_protocol' => 17, 'import_exe' => '/path/to/herdr']
     */
    public function liveHandoff(array $options = []): Result
    {
        return Result::fromResult($this->connection->request('server.live_handoff', $options), $this->connection);
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
