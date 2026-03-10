<?php

namespace Xul\AuthKit\DataTransferObjects\Actions\Support;

/**
 * AuthKitInternalPayload
 *
 * Internal-only payload DTO for AuthKit action outcomes.
 *
 * Responsibilities:
 * - Carry transport or orchestration data intended for server-side use only.
 * - Provide a dedicated home for sensitive or non-public action metadata.
 * - Prevent internal state from being mixed casually with client-safe payloads.
 *
 * Typical examples:
 * - Pending login challenge tokens.
 * - Internal verification context.
 * - Session-persistence values.
 * - Other non-public flow coordination details.
 *
 * Design notes:
 * - This DTO should not be serialized directly to public JSON responses
 *   unless explicitly intended by the responder layer.
 * - Controllers and responders may consume this DTO to apply session or
 *   redirect-related side effects.
 */
final class AuthKitInternalPayload
{
    /**
     * Create a new internal payload instance.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data = [],
    ) {}

    /**
     * Create an internal payload instance from the given data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function make(array $data = []): self
    {
        return new self($data);
    }

    /**
     * Determine whether the payload contains any data.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->data === [];
    }

    /**
     * Retrieve a value from the payload by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Determine whether the payload contains the given key.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Convert the internal payload into an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}