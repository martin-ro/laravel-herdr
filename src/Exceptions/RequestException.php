<?php

namespace MartinRo\Herdr\Exceptions;

/**
 * Thrown when Herdr responds to a request with an `error` payload, e.g.
 * {"id":"req_1","error":{"code":"not_found","message":"pane not found"}}.
 */
class RequestException extends HerdrException
{
    /**
     * @param  array<string, mixed>  $error  The raw error object from Herdr.
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly string $method,
        public readonly array $error = [],
    ) {
        parent::__construct(sprintf('[%s] %s (method: %s)', $errorCode, $message, $method));
    }

    /**
     * @param  array<string, mixed>  $error
     */
    public static function fromError(array $error, string $method): self
    {
        return new self(
            (string) ($error['code'] ?? 'error'),
            (string) ($error['message'] ?? 'Unknown Herdr error.'),
            $method,
            $error,
        );
    }

    /**
     * The Herdr error code, e.g. "pane_not_found", "invalid_request".
     */
    public function code(): string
    {
        return $this->errorCode;
    }

    /**
     * Whether this represents a "not found" error. Herdr namespaces these per
     * resource, e.g. "pane_not_found", "workspace_not_found", as well as a
     * bare "not_found".
     */
    public function isNotFound(): bool
    {
        return $this->errorCode === 'not_found' || str_ends_with($this->errorCode, '_not_found');
    }
}
