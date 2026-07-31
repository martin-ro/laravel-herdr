<?php

namespace MartinRo\Herdr\Data;

/**
 * A pushed Herdr event. Since Herdr 0.7.5 the pushed envelope is
 * {"event":"pane_created","data":{"type":"pane_created","pane":{...}}}:
 * an underscore event name plus a data payload that embeds full info objects
 * (pane, workspace, tab, worktree) where applicable.
 *
 * @property-read string|null $event
 * @property-read array|null  $data
 */
class Event extends Data
{
    public const INNER_KEY = 'event';

    /**
     * Translate a dotted event name ("pane.created") to the underscore wire
     * form ("pane_created").
     */
    public static function normalizeName(string $name): string
    {
        return str_replace('.', '_', $name);
    }

    /**
     * The event name, e.g. "pane_agent_status_changed", "pane_exited".
     */
    public function name(): ?string
    {
        $event = $this->get('event');

        return is_string($event) ? $event : $this->get('data.type');
    }

    /**
     * Alias for {@see name()}.
     */
    public function type(): ?string
    {
        return $this->name();
    }

    /**
     * The event's data payload as a permissive {@see Result}.
     */
    public function data(): Result
    {
        return new Result((array) $this->get('data', []), $this->connection);
    }

    /**
     * The pane this event concerns, when applicable.
     */
    public function paneId(): ?string
    {
        return $this->referencedId('pane');
    }

    public function workspaceId(): ?string
    {
        return $this->referencedId('workspace');
    }

    public function tabId(): ?string
    {
        return $this->referencedId('tab');
    }

    /**
     * Whether this event's name matches the given name or "pane_*"-style glob.
     * Dotted forms ("pane.created", "pane.*") are accepted for convenience and
     * matched against the underscore name.
     */
    public function is(string $name): bool
    {
        $actual = (string) $this->name();

        $name = static::normalizeName($name);

        if ($name === $actual) {
            return true;
        }

        if (str_ends_with($name, '_*')) {
            return str_starts_with($actual, substr($name, 0, -1));
        }

        return false;
    }

    /**
     * Resolve the live pane referenced by this event, if any. Hydrates from
     * the embedded pane info when the event carries one.
     */
    public function pane(): ?Pane
    {
        return $this->referenced('pane', Pane::class);
    }

    /**
     * Resolve the live workspace referenced by this event, if any.
     */
    public function workspace(): ?Workspace
    {
        return $this->referenced('workspace', Workspace::class);
    }

    /**
     * Resolve the live tab referenced by this event, if any.
     */
    public function tab(): ?Tab
    {
        return $this->referenced('tab', Tab::class);
    }

    /**
     * The id of the referenced object: a scoped "<key>_id" field on the data
     * payload (or the top level), else the id inside an embedded info object.
     */
    private function referencedId(string $key): ?string
    {
        return $this->get("data.{$key}_id")
            ?? $this->get("data.{$key}.{$key}_id")
            ?? $this->get("{$key}_id");
    }

    /**
     * @template T of Data
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    private function referenced(string $key, string $class): ?Data
    {
        $info = $this->get("data.{$key}");

        if (is_array($info)) {
            return new $class($info, $this->connection);
        }

        $id = $this->referencedId($key);

        return $id === null ? null : new $class(["{$key}_id" => $id], $this->connection);
    }
}
