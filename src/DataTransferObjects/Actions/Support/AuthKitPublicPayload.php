<?php

namespace Xul\AuthKit\DataTransferObjects\Actions\Support;

/**
 * AuthKitPublicPayload
 *
 * Client-safe payload DTO for AuthKit action outcomes.
 *
 * Responsibilities:
 * - Carry metadata that is safe to expose to JSON consumers.
 * - Provide a stable payload location for frontend and UI integrations.
 * - Reduce the need for scattered custom top-level response keys.
 *
 * Design notes:
 * - This DTO intentionally remains generic so that different actions
 *   can reuse one payload container while the package contract remains stable.
 * - Action-specific payload DTOs may later be introduced and nested inside
 *   this DTO if stronger typing becomes desirable.
 */
final class AuthKitPublicPayload
{
    /**
     * Create a new public payload instance.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data = [],
    ) {}

    /**
     * Create a public payload instance from the given data.
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
     * Convert the public payload into an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}