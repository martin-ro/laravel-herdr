<?php

namespace Herdr\Data;

/**
 * A live Herdr git worktree.
 *
 * @property-read string|null $worktree_id
 * @property-read string|null $workspace_id
 * @property-read string|null $branch
 * @property-read string|null $path
 */
class Worktree extends Data
{
    public const INNER_KEY = 'worktree';

    public const LIST_KEYS = ['worktrees'];

    public function id(): ?string
    {
        return $this->get('worktree_id') ?? $this->get('id');
    }

    public function branch(): ?string
    {
        return $this->get('branch');
    }

    public function path(): ?string
    {
        return $this->get('path');
    }

    /**
     * Open this worktree (worktree.open).
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
     * @param  array<string, mixed>  $params
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
            'worktree_id' => $this->id(),
            'workspace_id' => $this->get('workspace_id'),
            'branch' => $this->branch(),
            'path' => $this->path(),
        ], fn ($v) => $v !== null);

        return $this->connection()->request($method, array_merge($identity, $params));
    }
}
