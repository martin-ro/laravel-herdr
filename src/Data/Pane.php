<?php

namespace Herdr\Data;

use Illuminate\Support\Collection;

/**
 * A live Herdr pane: both a snapshot of pane state and a handle for acting on
 * it. When created without an id it targets Herdr's currently focused pane.
 *
 * @property-read string|null $pane_id
 * @property-read string|null $workspace_id
 * @property-read string|null $tab_id
 * @property-read string|null $terminal_id
 * @property-read string|null $agent_status
 * @property-read bool|null   $focused
 * @property-read int|null    $revision
 */
class Pane extends Data
{
    public const INNER_KEY = 'pane';

    public const LIST_KEYS = ['panes'];

    public function id(): ?string
    {
        return $this->get('pane_id');
    }

    public function workspaceId(): ?string
    {
        return $this->get('workspace_id');
    }

    public function tabId(): ?string
    {
        return $this->get('tab_id');
    }

    public function agentStatus(): ?string
    {
        return $this->get('agent_status');
    }

    public function isFocused(): bool
    {
        return (bool) $this->get('focused', false);
    }

    /* ----------------------------------------------------------------------
     | Reading state
     |---------------------------------------------------------------------- */

    /**
     * Reload this pane's state from Herdr (pane.get) and update in place.
     */
    public function refresh(): static
    {
        $result = $this->call('pane.get');

        return $this->fill(static::fromResult($result)->toArray());
    }

    /**
     * Read terminal output (pane.read).
     *
     * @param  'recent'|'screen'|'scrollback'|string  $source
     */
    public function read(?int $lines = null, string $source = 'recent'): Result
    {
        $params = ['source' => $source];

        if ($lines !== null) {
            $params['lines'] = $lines;
        }

        return Result::fromResult($this->call('pane.read', $params), $this->connection);
    }

    /**
     * Block until output matching a pattern appears, or a timeout elapses
     * (pane.wait_for_output).
     *
     * @param  array<string, mixed>  $options
     */
    public function waitForOutput(string $pattern, array $options = []): Result
    {
        return Result::fromResult(
            $this->call('pane.wait_for_output', array_merge(['pattern' => $pattern], $options)),
            $this->connection,
        );
    }

    public function processInfo(): Result
    {
        return Result::fromResult($this->call('pane.process_info'), $this->connection);
    }

    public function edges(): Result
    {
        return Result::fromResult($this->call('pane.edges'), $this->connection);
    }

    /* ----------------------------------------------------------------------
     | Sending input
     |---------------------------------------------------------------------- */

    /**
     * Type literal text into the pane (pane.send_text). Does not press Enter.
     */
    public function sendText(string $text): static
    {
        $this->call('pane.send_text', ['text' => $text]);

        return $this;
    }

    /**
     * Alias for {@see sendText()} that reads fluently.
     */
    public function type(string $text): static
    {
        return $this->sendText($text);
    }

    /**
     * Send key-combo strings (pane.send_keys), e.g. "enter", "esc", "ctrl+c",
     * "f1". Accepts a single key or a list of keys.
     *
     * @param  string|list<string>  $keys
     */
    public function sendKeys(string|array $keys): static
    {
        $keys = is_array($keys) ? array_values($keys) : [$keys];

        $this->call('pane.send_keys', ['keys' => $keys]);

        return $this;
    }

    /**
     * Lower-level structured input (pane.send_input).
     *
     * @param  array<string, mixed>  $input
     */
    public function sendInput(array $input): static
    {
        $this->call('pane.send_input', $input);

        return $this;
    }

    /**
     * Press Enter.
     */
    public function enter(): static
    {
        return $this->sendKeys('enter');
    }

    /**
     * Type a command and press Enter — the common case.
     */
    public function run(string $command): static
    {
        return $this->sendText($command)->enter();
    }

    /* ----------------------------------------------------------------------
     | Layout & focus
     |---------------------------------------------------------------------- */

    /**
     * Split this pane and return the new pane (pane.split).
     *
     * @param  'left'|'right'|'up'|'down'|string  $direction
     * @param  array<string, mixed>  $options  e.g. ['env' => [...], 'command' => '...']
     */
    public function split(string $direction = 'right', ?float $ratio = null, array $options = []): static
    {
        $params = array_merge(['direction' => $direction], $options);

        if ($ratio !== null) {
            $params['ratio'] = $ratio;
        }

        return static::fromResult($this->call('pane.split', $params), $this->connection);
    }

    public function focus(): static
    {
        $this->call('pane.focus');

        return $this;
    }

    /**
     * @param  'left'|'right'|'up'|'down'|string  $direction
     */
    public function focusDirection(string $direction): Result
    {
        return Result::fromResult($this->call('pane.focus_direction', ['direction' => $direction]), $this->connection);
    }

    /**
     * @param  'left'|'right'|'up'|'down'|string  $direction
     */
    public function neighbor(string $direction): Result
    {
        return Result::fromResult($this->call('pane.neighbor', ['direction' => $direction]), $this->connection);
    }

    public function zoom(bool $zoom = true): static
    {
        $this->call('pane.zoom', ['zoom' => $zoom]);

        return $this;
    }

    /**
     * Resize the pane (pane.resize).
     *
     * @param  array<string, mixed>  $params  e.g. ['direction' => 'right', 'amount' => 5]
     */
    public function resize(array $params): static
    {
        $this->call('pane.resize', $params);

        return $this;
    }

    public function swap(string $targetPaneId): static
    {
        $this->call('pane.swap', ['target' => $targetPaneId]);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function move(array $params): static
    {
        $this->call('pane.move', $params);

        return $this;
    }

    public function rename(string $title): static
    {
        $this->call('pane.rename', ['title' => $title]);

        return $this->fill(['title' => $title]);
    }

    /* ----------------------------------------------------------------------
     | Agent authority / reporting
     |---------------------------------------------------------------------- */

    /**
     * Report agent state for this pane (pane.report_agent).
     *
     * @param  array<string, mixed>  $attributes  e.g.
     *   ['source' => 'custom:docs', 'agent' => 'docs-bot', 'state' => 'working',
     *    'message' => 'building docs', 'custom_status' => 'indexing']
     */
    public function reportAgent(array $attributes): static
    {
        $this->call('pane.report_agent', $attributes);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function reportAgentSession(array $attributes): static
    {
        $this->call('pane.report_agent_session', $attributes);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function reportMetadata(array $metadata): static
    {
        $this->call('pane.report_metadata', $metadata);

        return $this;
    }

    public function clearAgentAuthority(): static
    {
        $this->call('pane.clear_agent_authority');

        return $this;
    }

    public function releaseAgent(): static
    {
        $this->call('pane.release_agent');

        return $this;
    }

    /* ----------------------------------------------------------------------
     | Lifecycle
     |---------------------------------------------------------------------- */

    public function close(): void
    {
        $this->call('pane.close');
    }

    /* ----------------------------------------------------------------------
     | Internals
     |---------------------------------------------------------------------- */

    /**
     * Invoke a pane method, scoping it to this pane when an id is known. When
     * no id is set the call targets Herdr's focused pane.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params = []): array
    {
        if (($id = $this->id()) !== null) {
            $params = array_merge(['pane_id' => $id], $params);
        }

        return $this->connection()->request($method, $params);
    }

    /**
     * Convenience: the workspace this pane belongs to.
     */
    public function workspace(): ?Workspace
    {
        $id = $this->workspaceId();

        return $id === null ? null : new Workspace(['workspace_id' => $id], $this->connection);
    }

    /**
     * Drains nothing — present so collections of panes read fluently.
     *
     * @return Collection<int, static>
     */
    public function siblings(): Collection
    {
        return static::collectionFromResult(
            $this->connection()->request('pane.list', array_filter([
                'workspace_id' => $this->workspaceId(),
                'tab_id' => $this->tabId(),
            ])),
            $this->connection,
        );
    }
}
