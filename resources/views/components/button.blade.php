{{--
/**
 * Component: Button
 *
 * Reusable button component for AuthKit forms and actions.
 *
 * Purpose:
 * - Renders a semantic button element with stable internal hooks for loading UI.
 * - Exposes a label wrapper for the normal button content.
 * - Exposes a loader wrapper that can be populated by the AuthKit runtime.
 * - Supports future loading-state customization without requiring page templates
 *   to change their markup.
 *
 * Styling:
 * - No inline styling is applied.
 * - Default styling uses package classes so the active theme can style buttons.
 * - Consumers may add additional classes via the standard "class" attribute.
 * - Consumers may disable package styling entirely using the "unstyled" prop.
 *
 * Internal structure:
 * - .authkit-btn__content
 * - .authkit-btn__loader
 * - .authkit-btn__label
 *
 * Notes:
 * - The loader wrapper is rendered empty by default and is intended to be
 *   populated or toggled by the AuthKit browser runtime during submission.
 * - The label wrapper preserves the original slot content so the runtime can
 *   swap or restore text safely.
 *
 * Slots:
 * - $slot
 *
 * Props:
 * - type: Button type (submit|button|reset).
 * - variant: Optional style variant key (default|primary|secondary|danger|ghost).
 * - unstyled: When true, package button classes are not applied.
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

    $contentClass = $unstyled ? '' : 'authkit-btn__content';
    $loaderClass = $unstyled ? '' : 'authkit-btn__loader';
    $labelClass = $unstyled ? '' : 'authkit-btn__label';
@endphp

<button
        type="{{ $type }}"
        {{ $attributes->merge(['class' => $class]) }}
>
    <span class="{{ $contentClass }}">
        <span
                class="{{ $loaderClass }}"
                data-authkit-button-loader="1"
                aria-hidden="true"
        ></span>

        <span
                class="{{ $labelClass }}"
                data-authkit-button-label="1"
        >
            {{ $slot }}
        </span>
    </span>
</button>