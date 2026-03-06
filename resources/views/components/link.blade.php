{{--
/**
 * Component: Link
 *
 * Generic link component used across AuthKit pages.
 *
 * Styling:
 * - No inline styling is applied.
 * - All visual appearance is controlled by the active theme.
 * - Default base class: authkit-link
 * - Optional variant class: authkit-link--{variant}
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Slots:
 * - $slot
 *
 * Props:
 * - href: link target URL.
 * - variant: optional style variant key (default|muted|primary|danger, etc.)
 * - unstyled: when true, prevents default package classes from being applied
 */
--}}

@props([
    'href' => '#',
    'variant' => 'default',
    'unstyled' => false,
])

@php
    $baseClass = 'authkit-link';
    $variantClass = $variant !== '' ? "authkit-link--{$variant}" : '';
    $class = $unstyled ? '' : trim($baseClass . ' ' . $variantClass);
@endphp

{{-- Anchor Element --}}
<a
        href="{{ $href }}"
        {{ $attributes->merge(['class' => $class]) }}
>
    {{ $slot }}
</a>