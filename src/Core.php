<?php

namespace MartinRo\Herdr;

use MartinRo\Herdr\Data\Result;
use MartinRo\Herdr\Data\Terminal;
use MartinRo\Herdr\Resources\Terminals;

/**
 * The herdr-core API: the backend of the Herdr desktop app, spoken as JSON-RPC
 * over the core socket. Core runs terminals and keeps them alive; spaces and
 * tabs belong to the app, which keeps them in the "herdr-app" document and
 * marks its terminals with the herdr.space and herdr.tab labels.
 */
class Core
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * Call any core method and get the raw result array.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = []): array
    {
        return $this->connection->request($method, $params);
    }

    /**
     * Like {@see call()} but wraps the result in a permissive {@see Result}.
     *
     * @param  array<string, mixed>  $params
     */
    public function result(string $method, array $params = []): Result
    {
        return Result::fromResult($this->call($method, $params), $this->connection);
    }

    /**
     * The core's identity, version, pid, uptime and terminal counts (server.status).
     */
    public function status(): Result
    {
        return $this->result('server.status');
    }

    public function terminals(): Terminals
    {
        return new Terminals($this->connection);
    }

    /**
     * A handle to a terminal by address (`t7`), name, or `machine/t7`.
     */
    public function terminal(string $terminal): Terminal
    {
        return new Terminal(['address' => $terminal], $this->connection);
    }

    /**
     * An opaque JSON document and its version (doc.get): `value` is null and
     * `version` is 0 for a name never written. The desktop app keeps its spaces
     * and tabs in the "herdr-app" document.
     */
    public function doc(string $name): Result
    {
        return $this->result('doc.get', ['name' => $name]);
    }
}
