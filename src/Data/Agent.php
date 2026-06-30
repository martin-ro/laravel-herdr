<?php

namespace MartinRo\Herdr\Data;

/**
 * A live Herdr agent. Agents have no id of their own; they are addressed by a
 * "target" — a terminal id, a unique agent name, or a pane id. The "agent"
 * field holds the agent kind (e.g. "claude").
 *
 * @property-read string|null $terminal_id
 * @property-read string|null $pane_id
 * @property-read string|null $workspace_id
 * @property-read string|null $tab_id
 * @property-read string|null $agent
 * @property-read string|null $agent_status
 */
class Agent extends Data
{
    public const INNER_KEY = 'agent';

    public const LIST_KEYS = ['agents'];

    /**
     * The value used to address this agent over the API: its terminal id, or
     * failing that its pane id or kind. Set explicitly when constructed via
     * Herdr::agent('<target>').
     */
    public function target(): ?string
    {
        return $this->get('target')
            ?? $this->get('terminal_id')
            ?? $this->get('pane_id')
            ?? $this->get('agent');
    }

    /**
     * Alias for {@see target()}.
     */
    public function id(): ?string
    {
        return $this->target();
    }

    public function terminalId(): ?string
    {
        return $this->get('terminal_id');
    }

    public function paneId(): ?string
    {
        return $this->get('pane_id');
    }

    /**
     * The agent kind, e.g. "claude".
     */
    public function kind(): ?string
    {
        return $this->get('agent');
    }

    public function status(): ?string
    {
        return $this->get('agent_status');
    }

    public function refresh(): static
    {
        return $this->fill(static::fromResult($this->call('agent.get'))->toArray());
    }

    /**
     * Read the agent's recent output (agent.read).
     *
     * @param  'visible'|'recent'|'recent-unwrapped'|string  $source
     * @param  'text'|'ansi'|string  $format
     */
    public function read(?int $lines = null, string $source = 'recent', string $format = 'text'): Result
    {
        $params = ['source' => $source, 'format' => $format];

        if ($lines !== null) {
            $params['lines'] = $lines;
        }

        $result = $this->call('agent.read', $params);

        return new Result($result['read'] ?? $result, $this->connection);
    }

    /**
     * Ask Herdr to explain the agent's current state (agent.explain).
     */
    public function explain(): Result
    {
        return Result::fromResult($this->call('agent.explain'), $this->connection);
    }

    /**
     * Send literal text to the agent (agent.send). Unlike a pane "run", this
     * does not append Enter.
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

        return $this->fill(['agent' => $name]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params = []): array
    {
        if (($target = $this->target()) !== null) {
            $params = array_merge(['target' => $target], $params);
        }

        return $this->connection()->request($method, $params);
    }
}
