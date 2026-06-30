<?php

namespace MartinRo\Herdr\Data;

/**
 * A live Herdr git worktree. Worktrees have no id; they are identified by their
 * path and/or branch, and the workspace they're open in is "open_workspace_id".
 *
 * @property-read string|null $path
 * @property-read string|null $branch
 * @property-read string|null $label
 * @property-read string|null $open_workspace_id
 * @property-read bool|null   $is_linked_worktree
 */
class Worktree extends Data
{
    public const INNER_KEY = 'worktree';

    public const LIST_KEYS = ['worktrees'];

    public function path(): ?string
    {
        return $this->get('path');
    }

    public function branch(): ?string
    {
        return $this->get('branch');
    }

    public function label(): ?string
    {
        return $this->get('label');
    }

    /**
     * The id of the workspace this worktree is currently open in, if any.
     */
    public function workspaceId(): ?string
    {
        return $this->get('open_workspace_id') ?? $this->get('workspace_id');
    }

    public function isLinked(): bool
    {
        return (bool) $this->get('is_linked_worktree', false);
    }

    /**
     * Open this worktree in a workspace (worktree.open).
     *
     * @param  array<string, mixed>  $params
     */
    public function open(array $params = []): Result
    {
        return Result::fromResult($this->call('worktree.open', $params), $this->connection);
    }

    /**
     * Remove this worktree (worktree.remove).
     *
     * @param  array<string, mixed>  $params  e.g. ['force' => true]
     */
    public function remove(array $params = []): void
    {
        $this->call('worktree.remove', $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params = []): array
    {
        $identity = array_filter([
            'workspace_id' => $this->workspaceId(),
            'branch' => $this->branch(),
            'path' => $this->path(),
        ], fn ($v) => $v !== null);

        return $this->connection()->request($method, array_merge($identity, $params));
    }
}
