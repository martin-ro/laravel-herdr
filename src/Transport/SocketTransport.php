<?php

namespace Herdr\Transport;

use Herdr\Exceptions\ConnectionException;

/**
 * Talks to a real Herdr control socket: a Unix domain socket on Linux/macOS,
 * or a named pipe on Windows. Connection is lazy — established on first use.
 */
class SocketTransport implements Transport
{
    /** @var resource|null */
    private $stream = null;

    /**
     * Holds bytes read from the socket that have not yet been split into a
     * complete line. Newline-delimited JSON means we may read past one message.
     */
    private string $buffer = '';

    public function __construct(
        private readonly string $path,
        private readonly float $connectTimeout = 5.0,
        private readonly ?float $readTimeout = null,
        private readonly int $chunkSize = 8192,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isConnected(): bool
    {
        return is_resource($this->stream) && ! feof($this->stream);
    }

    public function write(string $line): void
    {
        $this->connect();

        $payload = $line."\n";
        $length = strlen($payload);
        $written = 0;

        while ($written < $length) {
            $bytes = @fwrite($this->stream, substr($payload, $written));

            if ($bytes === false || $bytes === 0) {
                throw ConnectionException::writeFailed($this->path);
            }

            $written += $bytes;
        }
    }

    public function readLine(): ?string
    {
        $this->connect();

        while (($newline = strpos($this->buffer, "\n")) === false) {
            $chunk = @fread($this->stream, $this->chunkSize);

            if ($chunk === false || $chunk === '') {
                // Distinguish a clean EOF from a read timeout.
                $meta = stream_get_meta_data($this->stream);

                if ($meta['timed_out'] ?? false) {
                    throw ConnectionException::timedOut($this->path);
                }

                if (feof($this->stream)) {
                    if ($this->buffer === '') {
                        return null;
                    }

                    // Flush a trailing, newline-less final line.
                    $line = $this->buffer;
                    $this->buffer = '';

                    return $line;
                }

                // Non-blocking would-block: yield briefly and retry.
                usleep(1000);

                continue;
            }

            $this->buffer .= $chunk;
        }

        $line = substr($this->buffer, 0, $newline);
        $this->buffer = substr($this->buffer, $newline + 1);

        return $line;
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
        $this->buffer = '';
    }

    private function connect(): void
    {
        if (is_resource($this->stream)) {
            return;
        }

        if ($this->isNamedPipe()) {
            $stream = @fopen($this->path, 'r+b');

            if ($stream === false) {
                $error = error_get_last()['message'] ?? '';
                throw ConnectionException::cannotConnect($this->path, $error, 0);
            }
        } else {
            $stream = @stream_socket_client(
                'unix://'.$this->path,
                $errno,
                $errstr,
                $this->connectTimeout,
            );

            if ($stream === false) {
                throw ConnectionException::cannotConnect($this->path, (string) $errstr, (int) $errno);
            }
        }

        if ($this->readTimeout !== null) {
            $seconds = (int) floor($this->readTimeout);
            $micros = (int) round(($this->readTimeout - $seconds) * 1_000_000);
            stream_set_timeout($stream, $seconds, $micros);
        }

        $this->stream = $stream;
    }

    private function isNamedPipe(): bool
    {
        return str_starts_with($this->path, '\\\\')
            || str_starts_with($this->path, '//./pipe/');
    }

    public function __destruct()
    {
        $this->close();
    }
}
