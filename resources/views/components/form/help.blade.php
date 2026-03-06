{{--
/**
 * Component: Form Help
 *
 * Displays contextual helper text below an input field.
 *
 * Purpose:
 * - Provides guidance, hints, or additional information for form inputs.
 *
 * Styling:
 * - No inline styling is applied.
 * - All spacing, typography, and opacity are controlled by the active theme.
 * - Default base class: authkit-form-help
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Slots:
 * - $slot: Helper text content.
 *
 * Props:
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'unstyled' => false,
])

@php
    $baseClass = $unstyled ? '' : 'authkit-form-help';
@endphp

{{-- Help Text Wrapper --}}
<div {{ $attributes->merge(['class' => $baseClass]) }}>
    {{ $slot }}
</div>