{{--
/**
 * Component: Auth Footer
 *
 * Footer container for authentication pages.
 *
 * Purpose:
 * - Displays secondary navigation or contextual links
 *   (e.g. "Register", "Back to login", etc.).
 *
 * Styling:
 * - No inline styling is applied.
 * - All spacing, typography, and opacity are controlled by the active theme.
 * - Default base class: authkit-auth-footer
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Slots:
 * - $slot
 *
 * Props:
 * - unstyled: when true, prevents default package classes from being applied
 */
--}}

@props([
    'unstyled' => false,
])

@php
    $baseClass = 'authkit-auth-footer';
    $class = $unstyled ? '' : $baseClass;
@endphp

{{-- Footer Wrapper --}}
<div {{ $attributes->merge(['class' => $class]) }}>
    {{ $slot }}
</div>