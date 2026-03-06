<?php

namespace Xul\AuthKit\Support\Resolvers;

final class FormSchemaResolver
{
    /**
     * Resolve a form schema by context key.
     *
     * Schemas define the fields AuthKit expects for each form context, and basic UI metadata.
     * Consumers may override schemas in config without publishing package files.
     *
     * @param  string  $context  Schema context key (e.g. "login", "register").
     * @return array<string, mixed>
     */
    public static function resolve(string $context): array
    {
        $schema = config("authkit.schemas.{$context}");

        if (!is_array($schema)) {
            $schema = [];
        }

        $fields = data_get($schema, 'fields', []);

        if (!is_array($fields)) {
            $fields = [];
        }

        $labels = data_get($schema, 'labels', []);

        if (!is_array($labels)) {
            $labels = [];
        }

        $inputs = data_get($schema, 'inputs', []);

        if (!is_array($inputs)) {
            $inputs = [];
        }

        return [
            'fields' => array_values(array_filter($fields, static fn ($v) => is_string($v) && $v !== '')),
            'labels' => $labels,
            'inputs' => $inputs,
        ];
    }
}