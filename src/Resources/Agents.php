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

    /**
     * Fetch an agent by target (agent.get). A target is a terminal id, a
     * unique agent name, or a pane id.
     */
    public function get(string $target): Agent
    {
        return Agent::fromResult($this->connection->request('agent.get', ['target' => $target]), $this->connection);
    }

    public function find(string $target): ?Agent
    {
        try {
            return $this->get($target);
        } catch (RequestException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Start an agent of the given kind (agent.start), e.g. "claude".
     *
     * @param  array<string, mixed>  $options  e.g.
     *   ['workspace_id' => 'wA', 'split' => 'right', 'cwd' => '...', 'env' => [...]]
     */
    public function start(string $name, array $options = []): Agent
    {
        return Agent::fromResult(
            $this->connection->request('agent.start', array_merge(['name' => $name], $options)),
            $this->connection,
        );
    }
}
