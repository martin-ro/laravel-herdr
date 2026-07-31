<?php

namespace MartinRo\Herdr\Resources;

use MartinRo\Herdr\Connection;
use MartinRo\Herdr\Data\Result;

/**
 * Install and uninstall Herdr's built-in agent integrations. Valid targets:
 * pi, omp, claude, codex, copilot, devin, droid, kimi, opencode, kilo,
 * hermes, qodercli, cursor, mastracode.
 */
class Integrations
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function install(string $target): Result
    {
        return Result::fromResult(
            $this->connection->request('integration.install', ['target' => $target]),
            $this->connection,
        );
    }

    public function uninstall(string $target): Result
    {
        return Result::fromResult(
            $this->connection->request('integration.uninstall', ['target' => $target]),
            $this->connection,
        );
    }
}
