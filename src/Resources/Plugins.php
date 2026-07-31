<?php

namespace MartinRo\Herdr\Resources;

use MartinRo\Herdr\Connection;
use MartinRo\Herdr\Data\Result;
use Illuminate\Support\Collection;

/**
 * Manage plugins, invoke plugin actions, and control plugin panes.
 */
class Plugins
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * All linked plugins (plugin.list).
     *
     * @return Collection<int, Result>
     */
    public function all(): Collection
    {
        return Result::collectionFromResult($this->connection->request('plugin.list'), $this->connection);
    }

    /**
     * Link a plugin directory (plugin.link).
     *
     * @param  array<string, mixed>  $options  e.g. ['enabled' => false]
     */
    public function link(string $path, array $options = []): Result
    {
        return Result::fromResult(
            $this->connection->request('plugin.link', array_merge(['path' => $path], $options)),
            $this->connection,
        );
    }

    public function unlink(string $pluginId): Result
    {
        return Result::fromResult($this->connection->request('plugin.unlink', ['plugin_id' => $pluginId]), $this->connection);
    }

    public function enable(string $pluginId): Result
    {
        return Result::fromResult($this->connection->request('plugin.enable', ['plugin_id' => $pluginId]), $this->connection);
    }

    public function disable(string $pluginId): Result
    {
        return Result::fromResult($this->connection->request('plugin.disable', ['plugin_id' => $pluginId]), $this->connection);
    }

    /**
     * List declared plugin actions (plugin.action.list), for one plugin or all.
     *
     * @return Collection<int, Result>
     */
    public function actions(?string $pluginId = null): Collection
    {
        $params = $pluginId === null ? [] : ['plugin_id' => $pluginId];

        return Result::collectionFromResult(
            $this->connection->request('plugin.action.list', $params),
            $this->connection,
        );
    }

    /**
     * Invoke a plugin action (plugin.action.invoke).
     *
     * @param  array<string, mixed>  $context  invocation context, e.g.
     *   ['workspace_id' => 'w1', 'focused_pane_id' => 'w1:p1']
     */
    public function invoke(string $actionId, ?string $pluginId = null, array $context = []): Result
    {
        $params = ['action_id' => $actionId];

        if ($pluginId !== null) {
            $params['plugin_id'] = $pluginId;
        }

        if ($context !== []) {
            $params['context'] = $context;
        }

        return Result::fromResult(
            $this->connection->request('plugin.action.invoke', $params),
            $this->connection,
        );
    }

    /**
     * Recent plugin command logs (plugin.log.list).
     *
     * @return Collection<int, Result>
     */
    public function logs(?string $pluginId = null, ?int $limit = null): Collection
    {
        $params = array_filter([
            'plugin_id' => $pluginId,
            'limit' => $limit,
        ], fn ($v) => $v !== null);

        return Result::collectionFromResult(
            $this->connection->request('plugin.log.list', $params),
            $this->connection,
        );
    }

    /**
     * Open a plugin-owned pane (plugin.pane.open).
     *
     * @param  array<string, mixed>  $options  e.g. ['workspace_id' => 'w1',
     *   'direction' => 'right', 'placement' => 'popup', 'focus' => false]
     */
    public function openPane(string $pluginId, string $entrypoint, array $options = []): Result
    {
        return Result::fromResult(
            $this->connection->request('plugin.pane.open', array_merge([
                'plugin_id' => $pluginId,
                'entrypoint' => $entrypoint,
            ], $options)),
            $this->connection,
        );
    }

    public function focusPane(string $paneId): Result
    {
        return Result::fromResult($this->connection->request('plugin.pane.focus', ['pane_id' => $paneId]), $this->connection);
    }

    public function closePane(string $paneId): Result
    {
        return Result::fromResult($this->connection->request('plugin.pane.close', ['pane_id' => $paneId]), $this->connection);
    }
}
