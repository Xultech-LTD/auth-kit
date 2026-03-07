<?php

namespace Xul\AuthKit\Support\Resolvers;

use Illuminate\Support\Arr;
use Xul\AuthKit\Contracts\Forms\FieldValueProviderContract;

final class FieldValueResolver
{
    /**
     * Resolve the effective value for a field.
     *
     * Precedence:
     * 1. old input
     * 2. runtime values
     * 3. configured value_resolver
     * 4. static schema value
     * 5. null
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $runtime
     * @return mixed
     */
    public static function resolve(array $field, array $runtime = []): mixed
    {
        $name = (string) ($field['name'] ?? '');

        if ($name === '') {
            return null;
        }

        $old = old($name);

        if ($old !== null) {
            return $old;
        }

        if (Arr::has($runtime, $name)) {
            return Arr::get($runtime, $name);
        }

        $resolverClass = $field['value_resolver'] ?? null;

        if (is_string($resolverClass) && $resolverClass !== '' && class_exists($resolverClass)) {
            $resolver = app($resolverClass);

            if ($resolver instanceof FieldValueProviderContract) {
                return $resolver->resolve($field, $runtime);
            }
        }

        return $field['value'] ?? null;
    }

    /**
     * Resolve the effective checked state for checkbox-like fields.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $runtime
     */
    public static function resolveChecked(array $field, array $runtime = []): bool
    {
        $name = (string) ($field['name'] ?? '');

        if ($name === '') {
            return false;
        }

        $oldInput = request()->old();

        if (is_array($oldInput) && array_key_exists($name, $oldInput)) {
            return self::bool($oldInput[$name]);
        }

        if (array_key_exists($name, $runtime)) {
            return self::bool($runtime[$name]);
        }

        return self::bool($field['checked'] ?? false);
    }

    protected static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}