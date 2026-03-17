{{--
/**
 * Component: Form Select
 *
 * Select dropdown component used across AuthKit forms.
 *
 * Purpose:
 * - Renders a standard <select> element.
 * - Supports single and multiple selection modes.
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
 * - $slot: <option> and/or <optgroup> elements.
 *
 * Props:
 * - name: Select name (required).
 * - id: Optional id (defaults to name).
 * - value: Default selected value (reserved for compatibility; option selection is typically handled by rendered options).
 * - multiple: Whether the select allows multiple values.
 * - required: Whether the select is required.
 * - variant: Optional visual variant (default|error|success|etc).
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'name',
    'id' => null,
    'value' => null,
    'multiple' => false,
    'required' => false,
    'variant' => 'default',
    'unstyled' => false,
    'extraAttributes' => [],
])

@php
    $selectId = is_string($id) && $id !== '' ? $id : $name;
    $selectName = $multiple ? $name.'[]' : $name;

    $baseClass = 'authkit-select';
    $variantClass = $variant !== '' ? "authkit-select--{$variant}" : '';
    $class = $unstyled ? '' : trim($baseClass . ' ' . $variantClass);

    $extraAttributeBag = new \Illuminate\View\ComponentAttributeBag(
        is_array($extraAttributes) ? $extraAttributes : []
    );
@endphp

<select
        id="{{ $selectId }}"
        name="{{ $selectName }}"
        @if($multiple) multiple @endif
        @if($required) required @endif
        {{ $extraAttributeBag->merge($attributes->getAttributes())->merge(['class' => $class]) }}
>
    {{ $slot }}
</select>