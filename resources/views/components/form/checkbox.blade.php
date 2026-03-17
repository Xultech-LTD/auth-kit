{{--
/**
 * Component: Form Checkbox
 *
 * Checkbox input component used within AuthKit forms.
 *
 * Purpose:
 * - Renders a checkbox with associated label text.
 * - Automatically restores checked state using old() fallback.
 *
 * Styling:
 * - No inline styling is applied.
 * - All layout, spacing, and sizing are controlled by the active theme.
 * - Wrapper class: authkit-checkbox
 * - Input class: authkit-checkbox__input
 * - Label text class: authkit-checkbox__label
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Slots:
 * - $slot: Label text displayed next to the checkbox.
 *
 * Props:
 * - name: Input name (required).
 * - id: Optional id (defaults to name).
 * - checked: Default checked state (bool).
 * - value: Submitted checkbox value.
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'name',
    'id' => null,
    'checked' => false,
    'value' => '1',
    'unstyled' => false,
    'extraAttributes' => [],
])

@php
    $checkboxId = is_string($id) && $id !== '' ? $id : $name;
    $isChecked = (bool) old($name, $checked);

    $wrapperClass = $unstyled ? '' : 'authkit-checkbox';
    $inputClass = $unstyled ? '' : 'authkit-checkbox__input';
    $labelClass = $unstyled ? '' : 'authkit-checkbox__label';

    $extraAttributeBag = new \Illuminate\View\ComponentAttributeBag(
        is_array($extraAttributes) ? $extraAttributes : []
    );
@endphp

<label class="{{ $wrapperClass }}">
    <input
            id="{{ $checkboxId }}"
            name="{{ $name }}"
            type="checkbox"
            value="{{ $value }}"
            @if($isChecked) checked @endif
            {{ $extraAttributeBag->merge($attributes->getAttributes())->merge(['class' => $inputClass]) }}
    >

    <span class="{{ $labelClass }}">
        {{ $slot }}
    </span>
</label>