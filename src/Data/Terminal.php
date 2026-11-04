<?php

namespace MartinRo\Herdr\Data;

use MartinRo\Herdr\Exceptions\HerdrException;

/**
 * A herdr-core terminal: a program that core keeps running. It is addressed
 * by its address (`t7`), its name, or `machine/t7`. Agents are terminals whose
 * program reports a status through OSC 7501: idle, working, done, blocked or
 * error.
 *
 * @property-read string|null $address
 * @property-read string|null $name
 * @property-read string|null $state
 * @property-read string|null $cwd
 * @property-read array<string, string>|null $labels
 * @property-read array<string, mixed>|null $status
 * @property-read array<string, mixed>|null $foreground
 * @property-read int|null $turn
 * @property-read string|null $parent
 * @property-read string|null $title
 */
class Terminal extends Data
{
    public const LIST_KEYS = ['terminals'];

    public function address(): ?string
    {
        return $this->get('address');
    }

    public function id(): ?string
    {
        return $this->address();
    }

    public function name(): ?string
    {
        return $this->get('name');
    }

    /**
     * "live" or "finished".
     */
    public function state(): ?string
    {
        return $this->get('state');
    }

    public function isLive(): bool
    {
        return $this->state() === 'live';
    }

    /**
     * The program's own status word (idle, working, done, blocked, error), or
     * null when it never reported one or has exited.
     */
    public function status(): ?string
    {
        $state = $this->get('status.state');

        return is_string($state) ? $state : null;
    }

    /**
     * The program that reported the status, e.g. "claude-code".
     */
    public function app(): ?string
    {
        $app = $this->get('status.app');

        return is_string($app) ? $app : null;
    }

    /**
     * Goes up each time the status leaves `working`.
     */
    public function turn(): int
    {
        return (int) $this->get('turn', 0);
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return (array) $this->get('labels', []);
    }

    public function label(string $key): ?string
    {
        $value = $this->labels()[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Where its foreground program runs now, else the folder it started in.
     */
    public function cwd(): ?string
    {
        $cwd = $this->get('foreground.cwd') ?? $this->get('cwd');

        return is_string($cwd) ? $cwd : null;
    }

    /**
     * The terminal that started it, if any.
     */
    public function parent(): ?string
    {
        return $this->get('parent');
    }

    /* ----------------------------------------------------------------------
     | Actions
     |---------------------------------------------------------------------- */

    /**
     * The screen plus $lines of history as plain text (terminal.read). To read
     * the history of a full-screen app, core scrolls it with the mouse wheel
     * and back, so the default of 0 (the screen only) never touches the app.
     */
    public function read(int $lines = 0): Result
    {
        return Result::fromResult(
            $this->connection()->request('terminal.read', ['terminal' => $this->requireAddress(), 'lines' => $lines]),
            $this->connection(),
        );
    }

    /**
     * Paste text and press Enter (terminal.send). Core refuses a blocked
     * program unless $force, since the text may answer its dialog.
     */
    public function send(string $text, bool $force = false): Result
    {
        $params = ['terminal' => $this->requireAddress(), 'text' => $text];

        if ($force) {
            $params['force'] = true;
        }

        return Result::fromResult($this->connection()->request('terminal.send', $params), $this->connection());
    }

    /**
     * Press named keys (terminal.keys), e.g. "esc", "shift+tab", "ctrl+c".
     * Core encodes them for the program's current keyboard modes.
     *
     * @param  string|list<string>  $keys
     */
    public function keys(string|array $keys): Result
    {
        return Result::fromResult(
            $this->connection()->request('terminal.keys', ['terminal' => $this->requireAddress(), 'keys' => (array) $keys]),
            $this->connection(),
        );
    }

    /**
     * End the program: hangup, then terminate, then kill (terminal.kill).
     */
    public function kill(): static
    {
        return $this->fill($this->connection()->request('terminal.kill', ['terminal' => $this->requireAddress()]));
    }

    private function requireAddress(): string
    {
        $address = $this->address();

        if ($address === null || $address === '') {
            throw new HerdrException('This terminal has no address; obtain it through Herdr::core().');
        }

        return $address;
    }
}
