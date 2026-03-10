<?php

namespace Xul\AuthKit\DataTransferObjects\Actions\Support;

/**
 * AuthKitError
 *
 * Structured error DTO for AuthKit action outcomes.
 *
 * Responsibilities:
 * - Represent a machine-readable error code.
 * - Provide a human-friendly error message.
 * - Optionally associate an error with a specific field.
 * - Optionally carry metadata for additional error context.
 *
 * Design notes:
 * - Error codes should be stable and suitable for programmatic branching.
 * - Field is optional and is most useful for validation-style failures.
 * - Metadata may contain non-sensitive contextual details to help
 *   responders, tests, or frontend clients.
 */
final class AuthKitError
{
    /**
     * Create a new error instance.
     *
     * @param string $code
     * @param string $message
     * @param string|null $field
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly ?string $field = null,
        public readonly array $meta = [],
    ) {}

    /**
     * Create a generic error instance.
     *
     * @param string $code
     * @param string $message
     * @param string|null $field
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function make(
        string $code,
        string $message,
        ?string $field = null,
        array $meta = [],
    ): self {
        return new self($code, $message, $field, $meta);
    }

    /**
     * Create a validation-style error instance.
     *
     * @param string $field
     * @param string $message
     * @param string $code
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function validation(
        string $field,
        string $message,
        string $code = 'validation_error',
        array $meta = [],
    ): self {
        return new self($code, $message, $field, $meta);
    }

    /**
     * Convert the error into an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'field' => $this->field,
            'meta' => $this->meta,
        ];
    }
}