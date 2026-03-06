{{--
/**
 * Component: Container
 *
 * Centers auth content and applies consistent spacing constraints.
 *
 * Styling:
 * - No inline styling is applied.
 * - All layout and spacing are controlled by the active theme.
 * - Default base class: authkit-container
 * - Size modifier class: authkit-container--{size}
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Slots:
 * - $slot
 *
 * Props:
 * - size: sm|md|lg (controls max-width via theme)
 * - unstyled: when true, prevents default package classes from being applied
 */
--}}

@props([
    'size' => 'md',
    'unstyled' => false,
])

@php
    $baseClass = 'authkit-container';
    $sizeClass = "authkit-container--{$size}";
    $class = $unstyled ? '' : trim($baseClass . ' ' . $sizeClass);
@endphp

{{-- Container Wrapper --}}
<div {{ $attributes->merge(['class' => $class]) }}>
    {{ $slot }}
</div>