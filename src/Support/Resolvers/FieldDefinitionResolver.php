<?php

namespace Xul\AuthKit\Support\Resolvers;

final class FieldDefinitionResolver
{
    /**
     * Resolve and normalize a single field definition.
     *
     * @param  string  $name
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    public static function resolve(string $name, array $field): array
    {
        $type = self::normalizeType((string) ($field['type'] ?? 'text'));

        $wrapper = is_array($field['wrapper'] ?? null) ? $field['wrapper'] : [];
        $attributes = is_array($field['attributes'] ?? null) ? $field['attributes'] : [];
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];

        $label = $field['label'] ?? self::defaultLabel($name);

        return [
            'name' => $name,
            'label' => is_string($label) && $label !== '' ? $label : self::defaultLabel($name),
            'type' => $type,
            'required' => self::bool($field['required'] ?? false),
            'placeholder' => self::nullableString($field['placeholder'] ?? null),
            'help' => self::nullableString($field['help'] ?? null),
            'autocomplete' => self::nullableString($field['autocomplete'] ?? null),
            'inputmode' => self::nullableString($field['inputmode'] ?? null),
            'value' => $field['value'] ?? null,
            'value_resolver' => self::nullableString($field['value_resolver'] ?? null),
            'checked' => self::bool($field['checked'] ?? false),
            'multiple' => self::bool($field['multiple'] ?? false),
            'rows' => self::nullablePositiveInt($field['rows'] ?? null),
            'accept' => self::nullableString($field['accept'] ?? null),
            'options' => $options,
            'attributes' => $attributes,
            'wrapper' => [
                'class' => self::nullableString($wrapper['class'] ?? null),
                'style' => self::nullableString($wrapper['style'] ?? null),
            ],
            'component' => self::nullableString($field['component'] ?? null),
            'render' => array_key_exists('render', $field) ? self::bool($field['render']) : true,
        ];
    }

    /**
     * Normalize the field type to a supported canonical value.
     */
    protected static function normalizeType(string $type): string
    {
        $type = trim(mb_strtolower($type));

        $supported = [
            'text',
            'email',
            'password',
            'hidden',
            'number',
            'tel',
            'url',
            'search',
            'date',
            'datetime-local',
            'time',
            'month',
            'week',
            'color',
            'file',
            'textarea',
            'checkbox',
            'radio',
            'select',
            'multiselect',
            'radio_group',
            'checkbox_group',
            'otp',
            'custom',
        ];

        return in_array($type, $supported, true) ? $type : 'text';
    }

    /**
     * Build a default label from a field name.
     */
    protected static function defaultLabel(string $name): string
    {
        $label = str_replace(['_', '-'], ' ', $name);
        $label = preg_replace('/\s+/', ' ', $label) ?: $name;

        return ucwords(trim($label));
    }

    /**
     * Normalize a boolean-like value.
     */
    protected static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Normalize nullable strings.
     */
    protected static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * Normalize nullable positive integers.
     */
    protected static function nullablePositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }
}