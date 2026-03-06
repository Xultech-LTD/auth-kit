{{--
/**
 * Component: Form Textarea
 *
 * Textarea component used across AuthKit forms.
 *
 * Purpose:
 * - Renders a standard <textarea> element.
 * - Automatically restores previous input using old() fallback.
 *
 * Styling:
 * - No inline styling is applied.
 * - All layout, spacing, borders, and background are controlled by the active theme.
 * - Default base class: authkit-textarea
 * - Variant class (optional): authkit-textarea--{variant}
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Props:
 * - name: Textarea name (required).
 * - id: Optional id (defaults to name).
 * - value: Default value (fallback if old() not present).
 * - rows: Number of rows (default: 4).
 * - variant: Optional visual variant (default|error|success|etc).
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'name',
    'id' => null,
    'value' => null,
    'rows' => 4,
    'variant' => 'default',
    'unstyled' => false,
])

@php
    $textareaId = is_string($id) && $id !== '' ? $id : $name;

    $baseClass = 'authkit-textarea';
    $variantClass = $variant !== '' ? "authkit-textarea--{$variant}" : '';
    $class = $unstyled ? '' : trim($baseClass . ' ' . $variantClass);
@endphp

{{-- Textarea Element --}}
<textarea
        id="{{ $textareaId }}"
        name="{{ $name }}"
        rows="{{ (int) $rows }}"
    {{ $attributes->merge(['class' => $class]) }}
>{{ old($name, $value) }}</textarea>