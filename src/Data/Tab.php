<?php

namespace Herdr\Data;

use Illuminate\Support\Collection;

/**
 * A live Herdr tab.
 *
 * @property-read string|null $tab_id
 * @property-read string|null $workspace_id
 * @property-read string|null $name
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

    public function name(): ?string
    {
        return $this->get('name');
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

    public function rename(string $name): static
    {
        $this->call('tab.rename', ['name' => $name]);

        return $this->fill(['name' => $name]);
    }

    public function close(): void
    {
        $this->call('tab.close');
    }

    /**
     * Panes belonging to this tab (pane.list scoped to it).
     *
     * @return Collection<int, Pane>
     */
    public function panes(): Collection
    {
        return Pane::collectionFromResult($this->call('pane.list'), $this->connection);
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
