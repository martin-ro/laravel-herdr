<?php

namespace Herdr\Data;

/**
 * A pushed Herdr event, e.g. {"type":"pane.agent_status_changed","pane_id":"w1:p1","agent_status":"blocked"}.
 *
 * @property-read string|null $type
 * @property-read string|null $pane_id
 */
class Event extends Data
{
    /**
     * The event type, e.g. "pane.agent_status_changed", "pane.exited".
     */
    public function type(): ?string
    {
        return $this->get('type');
    }

    /**
     * The pane this event concerns, when applicable.
     */
    public function paneId(): ?string
    {
        return $this->get('pane_id');
    }

    /**
     * Whether this event's type matches the given type or "namespace.*" glob.
     */
    public function is(string $type): bool
    {
        $actual = (string) $this->type();

        if ($type === $actual) {
            return true;
        }

        if (str_ends_with($type, '.*')) {
            return str_starts_with($actual, substr($type, 0, -1));
        }

        return false;
    }

    /**
     * Resolve the live pane referenced by this event, if any.
     */
    public function pane(): ?Pane
    {
        $id = $this->paneId();

        return $id === null ? null : new Pane(['pane_id' => $id], $this->connection);
    }
}
