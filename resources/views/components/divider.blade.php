{{--
/**
 * Component: Divider
 *
 * Horizontal divider element used to visually separate sections.
 *
 * Styling:
 * - No inline styling is applied.
 * - All spacing, color, and opacity are controlled by the active theme.
 * - Default base class: authkit-divider
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Props:
 * - unstyled: when true, prevents default package classes from being applied
 */
--}}

@props([
    'unstyled' => false,
])

@php
    $baseClass = 'authkit-divider';
    $class = $unstyled ? '' : $baseClass;
@endphp

{{-- Divider Element --}}
<hr {{ $attributes->merge(['class' => $class]) }}>