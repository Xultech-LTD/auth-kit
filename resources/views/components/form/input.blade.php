{{--
/**
 * Component: Form Input
 *
 * Generic input field component used across AuthKit forms.
 *
 * Purpose:
 * - Renders a standard <input> element.
 * - Automatically restores previous input using old() fallback.
 *
 * Styling:
 * - No inline styling is applied.
 * - All layout, spacing, borders, and background are controlled by the active theme.
 * - Default base class: authkit-input
 * - Variant class (optional): authkit-input--{variant}
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Props:
 * - name: Input name (required).
 * - type: Input type (text|email|password|etc).
 * - value: Default value (fallback if old() not present).
 * - id: Optional id (defaults to name).
 * - autocomplete: Optional autocomplete attribute.
 * - variant: Optional visual variant (default|error|success|etc).
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'name',
    'type' => 'text',
    'value' => null,
    'id' => null,
    'autocomplete' => null,
    'variant' => 'default',
    'unstyled' => false,
])

@php
    $inputId = is_string($id) && $id !== '' ? $id : $name;

    $baseClass = 'authkit-input';
    $variantClass = $variant !== '' ? "authkit-input--{$variant}" : '';
    $class = $unstyled ? '' : trim($baseClass . ' ' . $variantClass);
@endphp

{{-- Input Element --}}
<input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        {{ $attributes->merge(['class' => $class]) }}
>