{{--
/**
 * Component: Form Select
 *
 * Select dropdown component used across AuthKit forms.
 *
 * Purpose:
 * - Renders a standard <select> element.
 * - Works with old() fallback for preserving submitted values.
 *
 * Styling:
 * - No inline styling is applied.
 * - All layout, spacing, borders, and background are controlled by the active theme.
 * - Default base class: authkit-select
 * - Variant class (optional): authkit-select--{variant}
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Slots:
 * - $slot: <option> elements.
 *
 * Props:
 * - name: Select name (required).
 * - id: Optional id (defaults to name).
 * - value: Default selected value (fallback if old() not present).
 * - variant: Optional visual variant (default|error|success|etc).
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'name',
    'id' => null,
    'value' => null,
    'variant' => 'default',
    'unstyled' => false,
])

@php
    $selectId = is_string($id) && $id !== '' ? $id : $name;
    $selected = old($name, $value);

    $baseClass = 'authkit-select';
    $variantClass = $variant !== '' ? "authkit-select--{$variant}" : '';
    $class = $unstyled ? '' : trim($baseClass . ' ' . $variantClass);
@endphp

{{-- Select Element --}}
<select
        id="{{ $selectId }}"
        name="{{ $name }}"
        {{ $attributes->merge(['class' => $class]) }}
>
    {{ $slot }}
</select>