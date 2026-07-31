<?php

namespace MartinRo\Herdr\Resources;

use MartinRo\Herdr\Connection;
use MartinRo\Herdr\Data\Agent;
use MartinRo\Herdr\Data\Pane;
use MartinRo\Herdr\Data\Result;
use MartinRo\Herdr\Exceptions\RequestException;
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
     * @return Collection<int, Agent>
     */
    public function all(): Collection
    {
        return Agent::collectionFromResult($this->connection->request('agent.list'), $this->connection);
    }

    /**
     * Fetch an agent by target (agent.get). A target is the agent's unique
     * live name, or the id of the pane hosting it.
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
     * Start an agent inside an existing pane (agent.start). The pane keeps its
     * place in the layout; Herdr launches the agent binary in it. The name
     * becomes the agent's unique live target.
     *
     * @param  string  $kind  the agent kind, e.g. "claude"
     * @param  string  $name  unique live agent name, e.g. "reviewer"
     * @param  string|Pane  $pane  the pane (or pane id) to start the agent in
     * @param  array<string, mixed>  $options  e.g. ['args' => ['--continue'], 'timeout_ms' => 30000]
     */
    public function start(string $kind, string $name, string|Pane $pane, array $options = []): Agent
    {
        $paneId = $pane instanceof Pane ? $pane->id() : $pane;

        if ($paneId === null) {
            throw new \InvalidArgumentException(
                'agent.start requires a pane id; the given Pane handle has none. '
                .'Pass a pane id or a hydrated Pane (e.g. from panes()->current()).',
            );
        }

        $result = $this->connection->request('agent.start', array_merge([
            'kind' => $kind,
            'name' => $name,
            'pane_id' => $paneId,
        ], $options));

        $agent = Agent::fromResult($result, $this->connection);

        // agent_started carries the launched argv beside the agent; keep it.
        if (isset($result['argv'])) {
            $agent->fill(['argv' => $result['argv']]);
        }

        return $agent;
    }

    /**
     * Set a transient declarative Agent view (agent.view.set): a filtered and
     * sorted agent list that drives sidebar and navigation order.
     *
     * @param  array<string, mixed>  $options  e.g.
     *   ['label' => 'Blocked', 'filter' => ['op' => 'eq', 'field' => 'agent_status', 'value' => 'blocked'],
     *    'sort' => [['field' => 'workspace', 'order' => 'asc']]]
     */
    public function setView(string $source, array $options = []): Result
    {
        return Result::fromResult(
            $this->connection->request('agent.view.set', array_merge(['source' => $source], $options)),
            $this->connection,
        );
    }

    /**
     * Clear the Agent view (agent.view.clear), optionally only when owned by
     * the given source.
     */
    public function clearView(?string $source = null): Result
    {
        $params = $source === null ? [] : ['source' => $source];

        return Result::fromResult($this->connection->request('agent.view.clear', $params), $this->connection);
    }
}
