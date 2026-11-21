<?php

namespace App\Jurisdictions\Contracts;

/**
 * Result of validating a jurisdiction's configured data.
 */
final readonly class VerificationResult
{
    /**
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public bool $ok,
        public array $messages = [],
    ) {}

    public static function success(array $messages = []): self
    {
        return new self(true, array_values($messages));
    }

    public static function failure(array $messages): self
    {
        return new self(false, array_values($messages));
    }

    /**
     * @return array{ok: bool, messages: array<int, string>}
     */
    public function toArray(): array
    {
        return ['ok' => $this->ok, 'messages' => $this->messages];
    }
}
