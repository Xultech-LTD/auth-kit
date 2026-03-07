<?php

namespace Xul\AuthKit\Support\Resolvers;

use Xul\AuthKit\Contracts\Forms\FieldComponentResolverContract;

final class FieldComponentResolver implements FieldComponentResolverContract
{
    /**
     * Resolve the component used to render a field.
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

            default => (string) ($components['input'] ?? 'authkit::form.input'),
        };
    }
}