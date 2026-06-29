<?php

namespace Herdr\Resources;

use Herdr\Connection;
use Herdr\Data\Worktree;
use Illuminate\Support\Collection;

/**
 * Query and create git worktrees.
 */
class Worktrees
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * All worktrees (worktree.list), optionally scoped to a workspace.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Worktree>
     */
    public function all(array $filters = []): Collection
    {
        return Worktree::collectionFromResult($this->connection->request('worktree.list', $filters), $this->connection);
    }

    /**
     * Create a worktree (worktree.create).
     *
     * @param  array<string, mixed>  $options
     */
    public function create(string $branch, array $options = []): Worktree
    {
        return Worktree::fromResult(
            $this->connection->request('worktree.create', array_merge(['branch' => $branch], $options)),
            $this->connection,
        );
    }
}
