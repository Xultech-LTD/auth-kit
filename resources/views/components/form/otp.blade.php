{{--
/**
 * Component: Form OTP
 *
 * One-time passcode / verification code input component used across AuthKit flows.
 *
 * Purpose:
 * - Renders a dedicated input for OTP-like values such as verification codes,
 *   reset codes, and two-factor authentication codes.
 * - Keeps OTP rendering separate from the generic input component so consumers
 *   can later replace it with a richer multi-slot or JavaScript-enhanced OTP UI
 *   without changing page templates or form schemas.
 *
 * Behavior:
 * - Automatically restores previous input using old() fallback.
 * - Defaults to a text input so leading zeros are preserved.
 * - Common OTP-related attributes such as autocomplete and inputmode may be passed in.
 *
 * Styling:
 * - No inline styling is applied.
 * - All layout, spacing, borders, and appearance are controlled by the active theme.
 * - Default base class: authkit-otp
 * - Variant class (optional): authkit-otp--{variant}
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Props:
 * - name: Input name (required).
 * - id: Optional id (defaults to name).
 * - value: Default value (fallback if old() not present).
 * - autocomplete: Optional autocomplete attribute.
 * - inputmode: Optional inputmode attribute (commonly "numeric").
 * - placeholder: Optional placeholder text.
 * - required: Whether the input is required.
 * - variant: Optional visual variant (default|error|success|etc).
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'name',
    'id' => null,
    'value' => null,
    'autocomplete' => 'one-time-code',
    'inputmode' => 'numeric',
    'placeholder' => null,
    'required' => false,
    'variant' => 'default',
    'unstyled' => false,
])

@php
    $inputId = is_string($id) && $id !== '' ? $id : $name;

    $baseClass = 'authkit-otp';
    $variantClass = $variant !== '' ? "authkit-otp--{$variant}" : '';
    $class = $unstyled ? '' : trim($baseClass . ' ' . $variantClass);
@endphp

{{-- OTP Input Element --}}
<input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="text"
        value="{{ old($name, $value) }}"
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if($inputmode) inputmode="{{ $inputmode }}" @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($required) required @endif
        {{ $attributes->merge(['class' => $class]) }}
>