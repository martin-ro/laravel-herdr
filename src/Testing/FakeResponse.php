<?php

namespace MartinRo\Herdr\Testing;

/**
 * A scripted reply used with {@see \MartinRo\Herdr\HerdrManager::fake()}. Lets a stub
 * return either a successful result or a Herdr-style error.
 */
class FakeResponse
{
    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>|null  $error
     */
    private function __construct(
        public readonly array $result = [],
        public readonly ?array $error = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function ok(array $result = []): self
    {
        return new self(result: $result);
    }

    public static function error(string $code, string $message): self
    {
        return new self(error: ['code' => $code, 'message' => $message]);
    }

    /**
     * Build the full response envelope for a given request id.
     *
     * @return array<string, mixed>
     */
    public function envelope(string $id): array
    {
        if ($this->error !== null) {
            return ['id' => $id, 'error' => $this->error];
        }

        return ['id' => $id, 'result' => $this->result];
    }
}
