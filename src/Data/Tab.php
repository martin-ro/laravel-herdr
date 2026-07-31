<?php

namespace MartinRo\Herdr\Data;

use Illuminate\Support\Collection;

/**
 * A live Herdr tab.
 *
 * @property-read string|null $tab_id
 * @property-read string|null $workspace_id
 * @property-read string|null $label
 * @property-read int|null    $number
 * @property-read int|null    $pane_count
 * @property-read string|null $agent_status
 * @property-read bool|null   $focused
 */
class Tab extends Data
{
    public const INNER_KEY = 'tab';

    public const LIST_KEYS = ['tabs'];

    public function id(): ?string
    {
        return $this->get('tab_id');
    }

    /**
     * The tab's display label (Herdr's field is "label").
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

    public function isFocused(): bool
    {
        return (bool) $this->get('focused', false);
    }

    public function refresh(): static
    {
        return $this->fill(static::fromResult($this->call('tab.get'))->toArray());
    }

    public function focus(): static
    {
        $this->call('tab.focus');

        return $this;
    }

    public function rename(string $label): static
    {
        return $this->fill(static::fromResult($this->call('tab.rename', ['label' => $label]))->toArray());
    }

    public function close(): void
    {
        $this->call('tab.close');
    }

    /**
     * Move this tab to a new position within its workspace (tab.move).
     */
    public function move(int $insertIndex): static
    {
        $this->call('tab.move', ['insert_index' => $insertIndex]);

        return $this;
    }

    /**
     * Panes belonging to this tab. pane.list only filters by workspace, so
     * this lists the workspace and narrows to the tab locally.
     *
     * @return Collection<int, Pane>
     */
    public function panes(): Collection
    {
        if ($this->id() !== null && $this->get('workspace_id') === null) {
            $this->refresh();
        }

        $panes = Pane::collectionFromResult(
            $this->connection()->request('pane.list', array_filter(
                ['workspace_id' => $this->get('workspace_id')],
                fn ($v) => $v !== null,
            )),
            $this->connection,
        );

        $tabId = $this->id();

        return $tabId === null
            ? $panes
            : $panes->filter(fn (Pane $pane) => $pane->tabId() === $tabId)->values();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params = []): array
    {
        if (($id = $this->id()) !== null) {
            $params = array_merge(['tab_id' => $id], $params);
        }

        return $this->connection()->request($method, $params);
    }
}
