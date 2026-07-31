<?php

namespace MartinRo\Herdr\Resources;

use MartinRo\Herdr\Connection;
use MartinRo\Herdr\Data\Result;

/**
 * Export and apply declarative tab layouts.
 */
class Layouts
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Export a tab's layout as a declarative description (layout.export).
     * Defaults to the focused tab; scope with a tab id or any pane id in the
     * tab.
     */
    public function export(?string $tabId = null, ?string $paneId = null): Result
    {
        $params = array_filter([
            'tab_id' => $tabId,
            'pane_id' => $paneId,
        ], fn ($v) => $v !== null);

        return Result::fromResult($this->connection->request('layout.export', $params), $this->connection);
    }

    /**
     * Apply a declarative layout (layout.apply). The root node is either
     * ['type' => 'pane', ...] or ['type' => 'split', 'direction' => ...,
     * 'ratio' => ..., 'first' => <node>, 'second' => <node>].
     *
     * @param  array<string, mixed>  $root
     * @param  array<string, mixed>  $options  e.g.
     *   ['workspace_id' => 'w1', 'tab_id' => 'w1:t1', 'tab_label' => 'dev', 'focus' => true]
     */
    public function apply(array $root, array $options = []): Result
    {
        return Result::fromResult(
            $this->connection->request('layout.apply', array_merge(['root' => $root], $options)),
            $this->connection,
        );
    }

    /**
     * Set the ratio of one split in a tab's layout tree
     * (layout.set_split_ratio). The path walks from the root: false = first
     * child, true = second child.
     *
     * @param  list<bool>  $path
     * @param  array<string, mixed>  $options  e.g. ['tab_id' => 'w1:t1'] or ['pane_id' => 'w1:p1']
     */
    public function setSplitRatio(array $path, float $ratio, array $options = []): Result
    {
        return Result::fromResult(
            $this->connection->request('layout.set_split_ratio', array_merge([
                'path' => array_values($path),
                'ratio' => $ratio,
            ], $options)),
            $this->connection,
        );
    }
}
