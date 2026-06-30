<?php

namespace Herdr\Data;

use Illuminate\Support\Collection;

/**
 * A live Herdr workspace.
 *
 * @property-read string|null $workspace_id
 * @property-read string|null $label
 * @property-read int|null    $number
 * @property-read int|null    $pane_count
 * @property-read int|null    $tab_count
 * @property-read string|null $active_tab_id
 * @property-read string|null $agent_status
 * @property-read bool|null   $focused
 */
class Workspace extends Data
{
    public const INNER_KEY = 'workspace';

    public const LIST_KEYS = ['workspaces'];

    public function id(): ?string
    {
        return $this->get('workspace_id');
    }

    /**
     * The workspace's display label (Herdr's field is "label").
     */
    public function label(): ?string
    {
        return $this->get('label');
    }

    /**
     * Alias for {@see label()}.
     */
    public function name(): ?string
    {
        return $this->label();
    }

    public function agentStatus(): ?string
    {
        return $this->get('agent_status');
    }

    public function isFocused(): bool
    {
        return (bool) $this->get('focused', false);
    }

    public function refresh(): static
    {
        return $this->fill(static::fromResult($this->call('workspace.get'))->toArray());
    }

    public function focus(): static
    {
        $this->call('workspace.focus');

        return $this;
    }

    public function rename(string $label): static
    {
        return $this->fill(static::fromResult($this->call('workspace.rename', ['label' => $label]))->toArray());
    }

    public function close(): void
    {
        $this->call('workspace.close');
    }

    /**
     * Tabs belonging to this workspace (tab.list scoped to it).
     *
     * @return Collection<int, Tab>
     */
    public function tabs(): Collection
    {
        return Tab::collectionFromResult($this->call('tab.list'), $this->connection);
    }

    /**
     * Panes belonging to this workspace (pane.list scoped to it).
     *
     * @return Collection<int, Pane>
     */
    public function panes(): Collection
    {
        return Pane::collectionFromResult($this->call('pane.list'), $this->connection);
    }

    /**
     * Create a tab in this workspace (tab.create).
     *
     * @param  array<string, mixed>  $params
     */
    public function createTab(array $params = []): Tab
    {
        return Tab::fromResult($this->call('tab.create', $params), $this->connection);
    }

    /**
     * Worktrees for this workspace (worktree.list).
     *
     * @return Collection<int, Worktree>
     */
    public function worktrees(): Collection
    {
        return Worktree::collectionFromResult($this->call('worktree.list'), $this->connection);
    }

    /**
     * Create a git worktree in this workspace (worktree.create).
     *
     * @param  array<string, mixed>  $options
     */
    public function createWorktree(string $branch, array $options = []): Worktree
    {
        return Worktree::fromResult(
            $this->call('worktree.create', array_merge(['branch' => $branch], $options)),
            $this->connection,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params = []): array
    {
        if (($id = $this->id()) !== null) {
            $params = array_merge(['workspace_id' => $id], $params);
        }

        return $this->connection()->request($method, $params);
    }
}
