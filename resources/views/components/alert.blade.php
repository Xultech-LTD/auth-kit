{{--
/**
 * Component: Alert
 *
 * Generic alert container used for status, info, and error messages.
 *
 * Styling:
 * - No inline styling is applied.
 * - Presentation is controlled entirely by the active theme stylesheet.
 *
 * Slots:
 * - $slot
 */
--}}

@props([])

<div {{ $attributes->merge(['class' => 'ak-alert']) }}>
    {{ $slot }}
</div>