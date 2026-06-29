<?php

namespace Herdr\Data;

/**
 * A live Herdr agent.
 *
 * @property-read string|null $agent_id
 * @property-read string|null $pane_id
 * @property-read string|null $name
 * @property-read string|null $state
 */
class Agent extends Data
{
    public const INNER_KEY = 'agent';

    public const LIST_KEYS = ['agents'];

    public function id(): ?string
    {
        return $this->get('agent_id') ?? $this->get('id');
    }

    public function name(): ?string
    {
        return $this->get('name');
    }

    public function state(): ?string
    {
        return $this->get('state') ?? $this->get('agent_status');
    }

    public function refresh(): static
    {
        return $this->fill(static::fromResult($this->call('agent.get'))->toArray());
    }

    /**
     * Read the agent's recent output/transcript (agent.read).
     */
    public function read(): Result
    {
        return Result::fromResult($this->call('agent.read'), $this->connection);
    }

    /**
     * Ask Herdr to explain the agent's current state (agent.explain).
     */
    public function explain(): Result
    {
        return Result::fromResult($this->call('agent.explain'), $this->connection);
    }

    /**
     * Send a message/prompt to the agent (agent.send).
     */
    public function send(string $text): static
    {
        $this->call('agent.send', ['text' => $text]);

        return $this;
    }

    public function focus(): static
    {
        $this->call('agent.focus');

        return $this;
    }

    public function rename(string $name): static
    {
        $this->call('agent.rename', ['name' => $name]);

        return $this->fill(['name' => $name]);
    }

    /**
     * Start an agent (agent.start).
     *
     * @param  array<string, mixed>  $params
     */
    public function start(array $params = []): static
    {
        return $this->fill(static::fromResult($this->call('agent.start', $params))->toArray());
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params = []): array
    {
        if (($id = $this->id()) !== null) {
            $params = array_merge(['agent_id' => $id], $params);
        }

        return $this->connection()->request($method, $params);
    }
}
