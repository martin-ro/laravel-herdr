<?php

namespace MartinRo\Herdr\Resources;

use Illuminate\Support\Collection;
use MartinRo\Herdr\Connection;
use MartinRo\Herdr\Data\Terminal;
use MartinRo\Herdr\Exceptions\RequestException;

/**
 * List, find and start herdr-core terminals.
 */
class Terminals
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Live terminals (terminal.list), optionally only those carrying all the
     * given labels. Pass $finished to include finished terminals too.
     *
     * @param  array<string, string>  $labels
     * @return Collection<int, Terminal>
     */
    public function all(array $labels = [], bool $finished = false): Collection
    {
        $params = [];

        if ($labels !== []) {
            $params['labels'] = $labels;
        }

        if ($finished) {
            $params['all'] = true;
        }

        return Terminal::collectionFromResult($this->connection->request('terminal.list', $params), $this->connection);
    }

    /**
     * One terminal, live or finished (terminal.get).
     */
    public function get(string $terminal): Terminal
    {
        return Terminal::fromResult($this->connection->request('terminal.get', ['terminal' => $terminal]), $this->connection);
    }

    public function find(string $terminal): ?Terminal
    {
        try {
            return $this->get($terminal);
        } catch (RequestException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Start a program in a new terminal (terminal.run). An empty argv starts
     * the machine's shell.
     *
     * The desktop app shows a terminal that carries the herdr.space label in
     * that space: in the tab named by herdr.tab, else in a new tab. A space id
     * the app does not know becomes a new space. Terminals without the labels
     * stay out of the app's window.
     *
     * @param  list<string>  $argv
     * @param  array<string, mixed>  $options  e.g. ['cwd' => '/srv/app', 'labels' => ['herdr.space' => 'sp1'],
     *                                         'keep' => true, 'no_parent' => true, 'name' => 'reviewer']
     */
    public function run(array $argv = [], array $options = []): Terminal
    {
        return Terminal::fromResult(
            $this->connection->request('terminal.run', array_merge(['argv' => $argv], $options)),
            $this->connection,
        );
    }
}
