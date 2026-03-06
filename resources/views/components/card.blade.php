{{--
/**
 * Component: Card
 *
 * Simple card wrapper for auth forms and content blocks.
 *
 * Styling:
 * - No inline styling is applied.
 * - All visual appearance is controlled by the active theme.
 * - Default base class: authkit-card
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
    $baseClass = 'authkit-card';
    $class = $unstyled ? '' : $baseClass;
@endphp

{{-- Card Wrapper --}}
<div {{ $attributes->merge(['class' => $class]) }}>
    {{ $slot }}
</div>