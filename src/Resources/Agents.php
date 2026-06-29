<?php

namespace Herdr\Resources;

use Herdr\Connection;
use Herdr\Data\Agent;
use Herdr\Exceptions\RequestException;
use Illuminate\Support\Collection;

/**
 * Query, start and manage agents.
 */
class Agents
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * All agents (agent.list).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Agent>
     */
    public function all(array $filters = []): Collection
    {
        return Agent::collectionFromResult($this->connection->request('agent.list', $filters), $this->connection);
    }

    public function get(string $id): Agent
    {
        return Agent::fromResult($this->connection->request('agent.get', ['agent_id' => $id]), $this->connection);
    }

    public function find(string $id): ?Agent
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
     * Start an agent (agent.start).
     *
     * @param  array<string, mixed>  $params
     */
    public function start(array $params = []): Agent
    {
        return Agent::fromResult($this->connection->request('agent.start', $params), $this->connection);
    }
}
