<?php

namespace MartinRo\Herdr\Data;

use Illuminate\Support\Collection;

/**
 * A live Herdr pane: both a snapshot of pane state and a handle for acting on
 * it. When created without an id it targets Herdr's currently focused pane.
 *
 * @property-read string|null $pane_id
 * @property-read string|null $workspace_id
 * @property-read string|null $tab_id
 * @property-read string|null $terminal_id
 * @property-read string|null $cwd
 * @property-read string|null $foreground_cwd
 * @property-read string|null $agent
 * @property-read string|null $agent_status
 * @property-read string|null $display_agent
 * @property-read string|null $label
 * @property-read string|null $title
 * @property-read string|null $terminal_title
 * @property-read string|null $terminal_title_stripped
 * @property-read bool|null   $focused
 * @property-read int|null    $revision
 * @property-read array|null  $scroll        {offset_from_bottom, max_offset_from_bottom, viewport_rows}
 * @property-read array|null  $state_labels
 * @property-read array|null  $tokens
 * @property-read array|null  $agent_session
 */
class Pane extends Data
{
    public const INNER_KEY = 'pane';

    public const LIST_KEYS = ['panes'];

    public function id(): ?string
    {
        return $this->get('pane_id');
    }

    public function terminalId(): ?string
    {
        return $this->get('terminal_id');
    }

    public function workspaceId(): ?string
    {
        return $this->get('workspace_id');
    }

    public function tabId(): ?string
    {
        return $this->get('tab_id');
    }

    public function cwd(): ?string
    {
        return $this->get('cwd');
    }

    public function foregroundCwd(): ?string
    {
        return $this->get('foreground_cwd');
    }

    /**
     * The kind of agent running in this pane (e.g. "claude"), if any.
     */
    public function agent(): ?string
    {
        return $this->get('agent');
    }

    public function agentStatus(): ?string
    {
        return $this->get('agent_status');
    }

    public function isFocused(): bool
    {
        return (bool) $this->get('focused', false);
    }

    public function label(): ?string
    {
        return $this->get('label');
    }

    public function title(): ?string
    {
        return $this->get('title');
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
     * Read terminal output (pane.read). The result payload nests the read under
     * a "read" key, which is unwrapped here so ->text()/->lines() work directly.
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

        $result = $this->call('pane.read', $params);

        return new Result($result['read'] ?? $result, $this->connection);
    }

    /**
     * Block until output containing a literal substring appears, or a timeout
     * elapses (pane.wait_for_output).
     *
     * @param  array<string, mixed>  $options  e.g. ['source' => 'visible', 'lines' => 100,
     *   'strip_ansi' => false, 'timeout_ms' => 10000]
     */
    public function waitForOutput(string $substring, array $options = []): Result
    {
        return $this->waitFor(['type' => 'substring', 'value' => $substring], $options);
    }

    /**
     * Block until output matching a regex appears, or a timeout elapses
     * (pane.wait_for_output).
     *
     * @param  array<string, mixed>  $options
     */
    public function waitForPattern(string $regex, array $options = []): Result
    {
        return $this->waitFor(['type' => 'regex', 'value' => $regex], $options);
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $options
     */
    private function waitFor(array $match, array $options): Result
    {
        $params = array_merge(['source' => 'recent'], $options, ['match' => $match]);

        return Result::fromResult($this->call('pane.wait_for_output', $params), $this->connection);
    }

    public function processInfo(): Result
    {
        return Result::fromResult($this->call('pane.process_info'), $this->connection);
    }

    public function edges(): Result
    {
        return Result::fromResult($this->call('pane.edges'), $this->connection);
    }

    /**
     * The layout snapshot of the tab containing this pane (pane.layout).
     */
    public function layout(): Result
    {
        return Result::fromResult($this->call('pane.layout'), $this->connection);
    }

    /* ----------------------------------------------------------------------
     | Graphics
     |---------------------------------------------------------------------- */

    /**
     * Show an image in the pane (pane.graphics.set).
     *
     * @param  array<string, mixed>  $params  e.g.
     *   ['format' => 'png', 'image_width' => 640, 'image_height' => 480,
     *    'data_base64' => '...', 'placement' => ['grid_cols' => 40]]
     */
    public function setGraphics(array $params): static
    {
        $this->call('pane.graphics.set', $params);

        return $this;
    }

    /**
     * Clear pane graphics (pane.graphics.clear).
     */
    public function clearGraphics(): static
    {
        $this->call('pane.graphics.clear');

        return $this;
    }

    /**
     * Cell pixel metrics for graphics placement (pane.graphics.info).
     */
    public function graphicsInfo(): Result
    {
        return Result::fromResult($this->call('pane.graphics.info'), $this->connection);
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
     * Split this pane and return the new pane (pane.split). Targets the
     * focused pane when this handle has no id.
     *
     * @param  'right'|'down'|string  $direction
     * @param  array<string, mixed>  $options  e.g. ['env' => [...], 'cwd' => '...', 'focus' => false]
     */
    public function split(string $direction = 'right', ?float $ratio = null, array $options = []): static
    {
        $params = array_merge(['direction' => $direction], $options);

        if ($ratio !== null) {
            $params['ratio'] = $ratio;
        }

        return static::fromResult($this->call('pane.split', $params, 'target_pane_id'), $this->connection);
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

    /**
     * Zoom the pane (pane.zoom). Toggles by default; pass 'on' or 'off' to
     * set the zoom state explicitly.
     *
     * @param  'toggle'|'on'|'off'|string  $mode
     */
    public function zoom(string $mode = 'toggle'): static
    {
        $this->call('pane.zoom', ['mode' => $mode]);

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

    /**
     * Swap this pane with its neighbour in a direction (pane.swap).
     *
     * @param  'left'|'right'|'up'|'down'|string  $direction
     */
    public function swap(string $direction): static
    {
        $this->call('pane.swap', ['direction' => $direction]);

        return $this;
    }

    /**
     * Swap two panes explicitly (pane.swap with source/target).
     */
    public function swapWith(string $targetPaneId): static
    {
        $this->connection()->request('pane.swap', [
            'source_pane_id' => $this->id(),
            'target_pane_id' => $targetPaneId,
        ]);

        return $this;
    }

    /**
     * Move this pane to a destination (pane.move). The destination envelope is
     * one of:
     *   ['type' => 'tab', 'tab_id' => ..., 'split' => 'right'|'down', 'target_pane_id' => ?, 'ratio' => ?]
     *   ['type' => 'new_tab', 'workspace_id' => ?, 'label' => ?]
     *   ['type' => 'new_workspace', 'label' => ?, 'tab_label' => ?]
     *
     * @param  array<string, mixed>  $destination
     * @param  array<string, mixed>  $options  e.g. ['focus' => false]
     */
    public function move(array $destination, array $options = []): static
    {
        $this->call('pane.move', array_merge($options, ['destination' => $destination]));

        return $this;
    }

    /**
     * Move this pane into an existing tab (pane.move, destination "tab").
     *
     * @param  'right'|'down'|string  $split
     * @param  array<string, mixed>  $options  destination extras, e.g. ['target_pane_id' => ..., 'ratio' => 0.5]
     */
    public function moveToTab(string $tabId, string $split = 'right', array $options = [], ?bool $focus = null): static
    {
        return $this->moveTo(
            array_merge(['type' => 'tab', 'tab_id' => $tabId, 'split' => $split], $options),
            $focus,
        );
    }

    /**
     * Move this pane into a new tab (pane.move, destination "new_tab").
     */
    public function moveToNewTab(?string $workspaceId = null, ?string $label = null, ?bool $focus = null): static
    {
        return $this->moveTo([
            'type' => 'new_tab',
            'workspace_id' => $workspaceId,
            'label' => $label,
        ], $focus);
    }

    /**
     * Move this pane into a new workspace (pane.move, destination "new_workspace").
     */
    public function moveToNewWorkspace(?string $label = null, ?string $tabLabel = null, ?bool $focus = null): static
    {
        return $this->moveTo([
            'type' => 'new_workspace',
            'label' => $label,
            'tab_label' => $tabLabel,
        ], $focus);
    }

    /**
     * @param  array<string, mixed>  $destination
     */
    private function moveTo(array $destination, ?bool $focus): static
    {
        return $this->move(
            array_filter($destination, fn ($v) => $v !== null),
            $focus === null ? [] : ['focus' => $focus],
        );
    }

    public function rename(string $label): static
    {
        $this->call('pane.rename', ['label' => $label]);

        return $this->fill(['label' => $label]);
    }

    /* ----------------------------------------------------------------------
     | Agent authority / reporting
     |---------------------------------------------------------------------- */

    /**
     * Report agent state for this pane (pane.report_agent).
     *
     * @param  array<string, mixed>  $attributes  e.g.
     *   ['source' => 'custom:docs', 'agent' => 'docs-bot', 'state' => 'working',
     *    'message' => 'building docs']
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
     * no id is set the call targets Herdr's focused pane. Methods that address
     * the pane under a different key (e.g. pane.split's target_pane_id) pass
     * it as $idKey.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params = [], string $idKey = 'pane_id'): array
    {
        if (($id = $this->id()) !== null) {
            $params = array_merge([$idKey => $id], $params);
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
        // An id-only handle knows neither its workspace nor its tab; hydrate
        // first so the listing can actually be scoped.
        if ($this->id() !== null && ($this->workspaceId() === null || $this->tabId() === null)) {
            $this->refresh();
        }

        $panes = static::collectionFromResult(
            $this->connection()->request('pane.list', array_filter(
                ['workspace_id' => $this->workspaceId()],
                fn ($v) => $v !== null,
            )),
            $this->connection,
        );

        // pane.list only filters by workspace; narrow to this tab locally.
        $tabId = $this->tabId();

        return $tabId === null
            ? $panes
            : $panes->filter(fn (Pane $pane) => $pane->tabId() === $tabId)->values();
    }
}
