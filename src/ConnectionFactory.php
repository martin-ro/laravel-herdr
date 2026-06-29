<?php

namespace Herdr;

use Herdr\Transport\SocketTransport;
use Herdr\Transport\Transport;

/**
 * Builds {@see Connection} instances, resolving which socket to talk to from
 * configuration and the environment, following Herdr's own resolution order.
 */
class ConnectionFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /**
     * Create a connection, optionally bound to a named session.
     */
    public function make(?string $session = null): Connection
    {
        $transport = new SocketTransport(
            path: $this->resolvePath($session),
            connectTimeout: (float) ($this->config['connect_timeout'] ?? 5.0),
            readTimeout: $this->config['read_timeout'] ?? null,
        );

        return $this->fromTransport($transport);
    }

    /**
     * Wrap an arbitrary transport (used for fakes and custom transports).
     */
    public function fromTransport(Transport $transport): Connection
    {
        return new Connection($transport, (string) ($this->config['id_prefix'] ?? 'req'));
    }

    /**
     * Resolve the socket path. Precedence (highest first):
     *   1. an explicit session argument (akin to `herdr --session <name>`)
     *   2. a configured socket_path (HERDR_SOCKET_PATH)
     *   3. a configured session name (HERDR_SESSION)
     *   4. the default session socket
     */
    public function resolvePath(?string $session = null): string
    {
        if ($session !== null && $session !== '') {
            return $this->sessionSocket($session);
        }

        $socketPath = $this->config['socket_path'] ?? null;

        if (is_string($socketPath) && $socketPath !== '') {
            return $socketPath;
        }

        $configuredSession = $this->config['session'] ?? null;

        if (is_string($configuredSession) && $configuredSession !== '') {
            return $this->sessionSocket($configuredSession);
        }

        return $this->basePath().'/herdr.sock';
    }

    private function sessionSocket(string $session): string
    {
        return $this->basePath().'/sessions/'.$session.'/herdr.sock';
    }

    private function basePath(): string
    {
        $base = $this->config['base_path'] ?? null;

        if (is_string($base) && $base !== '') {
            return rtrim($base, '/');
        }

        $configHome = getenv('XDG_CONFIG_HOME')
            ?: rtrim((string) (getenv('HOME') ?: ''), '/').'/.config';

        return rtrim($configHome, '/').'/herdr';
    }
}
