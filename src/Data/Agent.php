<?php

namespace MartinRo\Herdr\Data;

/**
 * A live Herdr agent. Agents are addressed by a "target": the agent's unique
 * live name, or the id of the pane hosting it. The "agent" field holds the
 * agent kind (e.g. "claude"); "name" holds the unique live name, if set.
 *
 * @property-read string|null $name
 * @property-read string|null $terminal_id
 * @property-read string|null $pane_id
 * @property-read string|null $workspace_id
 * @property-read string|null $tab_id
 * @property-read string|null $agent
 * @property-read string|null $agent_status
 * @property-read string|null $display_agent
 * @property-read string|null $title
 * @property-read bool|null   $focused
 * @property-read bool|null   $interactive_ready
 * @property-read bool|null   $launch_pending
 * @property-read int|null    $revision
 */
class Agent extends Data
{
    public const INNER_KEY = 'agent';

    public const LIST_KEYS = ['agents'];

    /**
     * The value used to address this agent over the API: its unique live name,
     * or failing that the id of the pane hosting it. Set explicitly when
     * constructed via Herdr::agent('<target>').
     */
    public function target(): ?string
    {
        return $this->get('target')
            ?? $this->get('name')
            ?? $this->get('pane_id');
    }

    /**
     * Alias for {@see target()}.
     */
    public function id(): ?string
    {
        return $this->target();
    }

    /**
     * The agent's unique live name, if one was assigned at start or rename.
     */
    public function name(): ?string
    {
        return $this->get('name');
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

    /**
     * @return 'idle'|'working'|'blocked'|'done'|'unknown'|string|null
     */
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
     * @param  'visible'|'recent'|'recent_unwrapped'|'detection'|string  $source
     * @param  'text'|'ansi'|string  $format
     * @param  bool|null  $stripAnsi  omit to use the server default for the format
     */
    public function read(?int $lines = null, string $source = 'recent', string $format = 'text', ?bool $stripAnsi = null): Result
    {
        $params = ['source' => $source, 'format' => $format];

        if ($stripAnsi !== null) {
            $params['strip_ansi'] = $stripAnsi;
        }

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
     * Submit a prompt to the agent (agent.prompt). Herdr types the text and
     * presses Enter atomically. Optionally wait server-side for the agent to
     * settle into one of the given statuses afterwards.
     *
     * @param  array<int, string>|string|null  $until  e.g. 'idle' or ['idle', 'blocked']
     */
    public function prompt(string $text, array|string|null $until = null, ?int $timeoutMs = null): static
    {
        $params = ['text' => $text];

        $wait = $this->waitOptions($until, $timeoutMs);

        if ($wait !== []) {
            $params['wait'] = $wait;
        }

        $result = $this->call('agent.prompt', $params);

        return $this->fill(static::fromResult($result)->toArray());
    }

    /**
     * Send key-combo strings to the agent (agent.send_keys), e.g. "enter",
     * "esc", "ctrl+c". Accepts a single key or a list of keys.
     *
     * @param  string|list<string>  $keys
     */
    public function sendKeys(string|array $keys): static
    {
        $keys = is_array($keys) ? array_values($keys) : [$keys];

        $this->call('agent.send_keys', ['keys' => $keys]);

        return $this;
    }

    /**
     * Block server-side until the agent reaches one of the given statuses
     * (agent.wait), then refresh this instance from the result.
     *
     * @param  array<int, string>|string  $until  e.g. 'idle' or ['idle', 'blocked']
     */
    public function wait(array|string $until = [], ?int $timeoutMs = null): static
    {
        $result = $this->call('agent.wait', $this->waitOptions($until, $timeoutMs));

        return $this->fill(static::fromResult($result)->toArray());
    }

    /**
     * Build the shared until/timeout wait parameters. An empty until list is
     * omitted so the server default applies.
     *
     * @param  array<int, string>|string|null  $until
     * @return array<string, mixed>
     */
    private function waitOptions(array|string|null $until, ?int $timeoutMs): array
    {
        $params = [];

        $until = match (true) {
            $until === null => [],
            is_array($until) => array_values($until),
            default => [$until],
        };

        if ($until !== []) {
            $params['until'] = $until;
        }

        if ($timeoutMs !== null) {
            $params['timeout_ms'] = $timeoutMs;
        }

        return $params;
    }

    public function focus(): static
    {
        $this->call('agent.focus');

        return $this;
    }

    /**
     * Set or clear the agent's unique live name (agent.rename).
     */
    public function rename(?string $name): static
    {
        // Clearing the name invalidates a name-based target; learn the pane id
        // first so this handle stays addressable afterwards.
        if ($name === null && $this->get('pane_id') === null) {
            $this->refresh();
        }

        $this->call('agent.rename', ['name' => $name]);

        // An explicitly set target may hold the old name; let target() resolve
        // again from the updated name (or the pane id when the name cleared).
        unset($this->attributes['target']);

        return $this->fill(['name' => $name]);
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
