<?php

namespace Herdr\Data;

/**
 * A generic, permissive wrapper around any Herdr result payload. Returned by
 * low-level and read-style calls so callers keep full access to the raw fields
 * while enjoying a few convenience accessors.
 */
class Result extends Data
{
    /**
     * The result "type" tag, if present (e.g. "pane_info", "pong").
     */
    public function type(): ?string
    {
        return $this->get('type');
    }

    /**
     * Best-effort text extraction for read-style results, checking the field
     * names Herdr commonly uses. Joins line arrays when needed.
     */
    public function text(): string
    {
        foreach (['text', 'read.text', 'output', 'content', 'data'] as $key) {
            $value = $this->get($key);

            if (is_string($value)) {
                return $value;
            }

            if (is_array($value)) {
                return implode("\n", array_map('strval', $value));
            }
        }

        $lines = $this->lines();

        return $lines === [] ? '' : implode("\n", $lines);
    }

    /**
     * Best-effort line extraction for read-style results.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $value = $this->get('lines') ?? $this->get('read.lines') ?? $this->get('rows');

        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        $text = $this->get('text') ?? $this->get('read.text') ?? $this->get('output') ?? $this->get('content');

        if (is_string($text)) {
            return explode("\n", $text);
        }

        return [];
    }
}
