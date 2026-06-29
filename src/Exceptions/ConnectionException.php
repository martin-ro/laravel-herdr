<?php

namespace Herdr\Exceptions;

/**
 * Thrown for transport-level failures: the socket could not be opened, the
 * peer closed the connection, a write failed, or a read timed out.
 */
class ConnectionException extends HerdrException
{
    public static function cannotConnect(string $path, string $error, int $errno): self
    {
        return new self(sprintf(
            'Unable to connect to the Herdr socket at "%s": %s (%d). Is Herdr running?',
            $path,
            $error !== '' ? $error : 'unknown error',
            $errno,
        ));
    }

    public static function closed(): self
    {
        return new self('The Herdr socket connection was closed by the peer.');
    }

    public static function writeFailed(string $path): self
    {
        return new self(sprintf('Failed to write to the Herdr socket at "%s".', $path));
    }

    public static function timedOut(string $path): self
    {
        return new self(sprintf('Timed out reading from the Herdr socket at "%s".', $path));
    }

    public static function invalidMessage(string $line): self
    {
        return new self('Received a malformed (non-JSON) message from Herdr: '.$line);
    }
}
