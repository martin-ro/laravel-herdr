<?php

namespace MartinRo\Herdr;

use MartinRo\Herdr\Transport\SocketTransport;
use MartinRo\Herdr\Transport\Transport;

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
     *
     * Because Herdr serves one request per socket, the connection is given a
     * factory that opens a fresh {@see SocketTransport} for every request.
     */
    public function make(?string $session = null): Connection
    {
        return $this->connectTo($this->resolvePath($session));
    }

    /**
     * Create a connection to the herdr-core socket. Core keeps a connection
     * open for many calls, but a fresh one per request works the same way.
     */
    public function makeCore(): Connection
    {
        return $this->connectTo($this->resolveCorePath());
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

    /**
     * Resolve the herdr-core socket the way core does. Precedence (highest first):
     *   1. a configured core_socket_path (HC_SOCKET)
     *   2. "$XDG_RUNTIME_DIR/herdr-core/<session>.sock"
     *   3. "/run/user/<uid>/herdr-core/<session>.sock", when that runtime directory exists
     *   4. "<XDG_STATE_HOME or ~/.local/state>/herdr-core/<session>/<session>.sock"
     *
     * The session is core_session (HC_SESSION), "default" when unset.
     */
    public function resolveCorePath(): string
    {
        $socketPath = $this->config['core_socket_path'] ?? null;

        if (is_string($socketPath) && $socketPath !== '') {
            return $socketPath;
        }

        $session = $this->config['core_session'] ?? null;
        $session = is_string($session) && $session !== '' ? $session : 'default';

        $runtime = getenv('XDG_RUNTIME_DIR') ?: $this->userRuntimeDirectory();

        if (is_string($runtime) && $runtime !== '') {
            return rtrim($runtime, '/').'/herdr-core/'.$session.'.sock';
        }

        $stateHome = getenv('XDG_STATE_HOME')
            ?: rtrim((string) (getenv('HOME') ?: ''), '/').'/.local/state';

        return rtrim($stateHome, '/').'/herdr-core/'.$session.'/'.$session.'.sock';
    }

    private function connectTo(string $path): Connection
    {
        $connectTimeout = (float) ($this->config['connect_timeout'] ?? 5.0);
        $readTimeout = $this->config['read_timeout'] ?? null;

        return new Connection(
            fn () => new SocketTransport($path, $connectTimeout, $readTimeout),
            (string) ($this->config['id_prefix'] ?? 'req'),
        );
    }

    private function userRuntimeDirectory(): ?string
    {
        if (! function_exists('posix_getuid')) {
            return null;
        }

        $directory = '/run/user/'.posix_getuid();

        return is_dir($directory) ? $directory : null;
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
