{{--
/**
 * Component: Alert
 *
 * Generic alert container used for status, info, and error messages.
 *
 * Purpose:
 * - Renders a semantic alert wrapper for feedback messages.
 * - Provides stable theme hooks for success, info, warning, and error states.
 *
 * Styling:
 * - No inline styling is applied.
 * - Presentation is controlled entirely by the active theme stylesheet.
 * - Default base class: authkit-alert
 * - Optional variant class: authkit-alert--{variant}
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Slots:
 * - $slot
 *
 * Props:
 * - variant: default|success|info|warning|danger|error
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'variant' => 'default',
    'unstyled' => false,
])

@php
    $baseClass = 'authkit-alert';
    $variantClass = $variant !== '' ? "authkit-alert--{$variant}" : '';
    $class = $unstyled ? '' : trim($baseClass.' '.$variantClass);
@endphp

<div
        role="alert"
        {{ $attributes->merge(['class' => $class]) }}
>
    {{ $slot }}
</div>
