<?php

namespace MartinRo\Herdr\Transport;

/**
 * A bidirectional, newline-delimited message transport to a Herdr socket.
 *
 * Implementations deal purely in whole JSON lines (without the trailing "\n");
 * framing, encoding and request/response correlation live in {@see \MartinRo\Herdr\Connection}.
 */
interface Transport
{
    /**
     * Write a single message line. The transport appends the "\n" delimiter.
     */
    public function write(string $line): void;

    /**
     * Read the next complete message line, or null when the peer has closed
     * the connection and no buffered data remains.
     */
    public function readLine(): ?string;

    /**
     * Whether the underlying connection is currently open.
     */
    public function isConnected(): bool;

    /**
     * Close the connection and release any resources.
     */
    public function close(): void;
}
