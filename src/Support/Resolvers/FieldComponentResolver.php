<?php

namespace Xul\AuthKit\Support\Resolvers;

use Xul\AuthKit\Contracts\Forms\FieldComponentResolverContract;

final class FieldComponentResolver implements FieldComponentResolverContract
{
    /**
     * Resolve the Blade component used to render a normalized field.
     *
     * Resolution order:
     * 1. Use the field's explicit component override when provided.
     * 2. Fall back to the configured component mapping based on field type.
     *
     * Supported default mappings:
     * - textarea   => authkit.components.textarea
     * - select     => authkit.components.select
     * - multiselect=> authkit.components.select
     * - checkbox   => authkit.components.checkbox
     * - otp        => authkit.components.otp
     * - default    => authkit.components.input
     *
     * Notes:
     * - This resolver intentionally centralizes field-type-to-component mapping
     *   so page templates and higher-level field renderers do not need to know
     *   which primitive component should render each field.
     * - Consumers may override any component alias in config or set a field-level
     *   "component" value directly in a schema definition.
     *
     * @param  array<string, mixed>  $field
     */
    public function resolve(array $field): string
    {
        $explicit = $field['component'] ?? null;

        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        $type = (string) ($field['type'] ?? 'text');
        $components = (array) config('authkit.components', []);

        return match ($type) {
            'textarea' => (string) ($components['textarea'] ?? 'authkit::form.textarea'),
            'select', 'multiselect' => (string) ($components['select'] ?? 'authkit::form.select'),
            'checkbox' => (string) ($components['checkbox'] ?? 'authkit::form.checkbox'),
            'otp' => (string) ($components['otp'] ?? 'authkit::form.otp'),

            default => (string) ($components['input'] ?? 'authkit::form.input'),
        };
    }
}