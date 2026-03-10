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
 * - Optionally carry grouped field validation messages for frontend forms.
 *
 * Design notes:
 * - This DTO intentionally remains generic so that different actions
 *   can reuse one payload container while the package contract remains stable.
 * - Action-specific payload DTOs may later be introduced and nested inside
 *   this DTO if stronger typing becomes desirable.
 * - Validation field maps are exposed under the "fields" key when present.
 *
 * Field error map format:
 * - [
 *     'email' => ['The email field is required.'],
 *     'password' => ['The password field is required.'],
 *   ]
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
     * Create a public payload instance with grouped field validation messages.
     *
     * @param array<string, array<int, string>> $fields
     * @param array<string, mixed> $extra
     * @return self
     */
    public static function withFields(array $fields, array $extra = []): self
    {
        return new self([
            ...$extra,
            'fields' => $fields,
        ]);
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
     * Determine whether the payload contains grouped field validation messages.
     *
     * @return bool
     */
    public function hasFields(): bool
    {
        return isset($this->data['fields']) && is_array($this->data['fields']);
    }

    /**
     * Retrieve grouped field validation messages.
     *
     * @return array<string, array<int, string>>
     */
    public function fields(): array
    {
        $fields = $this->data['fields'] ?? [];

        return is_array($fields) ? $fields : [];
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