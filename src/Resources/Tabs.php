<?php

namespace Herdr\Resources;

use Herdr\Connection;
use Herdr\Data\Tab;
use Herdr\Exceptions\RequestException;
use Illuminate\Support\Collection;

/**
 * Query and create tabs.
 */
class Tabs
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * All tabs (tab.list), optionally scoped, e.g. ['workspace_id' => 'w1'].
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Tab>
     */
    public function all(array $filters = []): Collection
    {
        return Tab::collectionFromResult($this->connection->request('tab.list', $filters), $this->connection);
    }

    public function get(string $id): Tab
    {
        return Tab::fromResult($this->connection->request('tab.get', ['tab_id' => $id]), $this->connection);
    }

    public function find(string $id): ?Tab
    {
        try {
            return $this->get($id);
        } catch (RequestException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Create a tab (tab.create).
     *
     * @param  array<string, mixed>  $params
     */
    public function create(array $params = []): Tab
    {
        return Tab::fromResult($this->connection->request('tab.create', $params), $this->connection);
    }
}
