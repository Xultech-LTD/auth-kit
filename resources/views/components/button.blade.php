{{--
/**
 * Component: Button
 *
 * Button component for forms and actions.
 *
 * Styling:
 * - No inline styling is applied.
 * - Default styling uses package classes so the active theme can style buttons.
 * - Consumers may add additional classes via the standard "class" attribute.
 * - Consumers may disable package styling entirely using the "unstyled" prop.
 *
 * Slots:
 * - $slot
 *
 * Props:
 * - type: button type (submit|button|reset)
 * - variant: optional style variant key (default|primary|secondary|danger|ghost)
 * - unstyled: when true, does not apply package classes
 */
--}}

@props([
    'type' => 'submit',
    'variant' => 'default',
    'unstyled' => false,
])

@php
    $baseClass = 'authkit-btn';
    $variantClass = $variant !== '' ? "authkit-btn--{$variant}" : '';
    $class = $unstyled ? '' : trim($baseClass . ' ' . $variantClass);
@endphp

<button
        type="{{ $type }}"
        {{ $attributes->merge(['class' => $class]) }}
>
    {{ $slot }}
</button>