{{--
/**
 * Component: Form Label
 *
 * Label element used for form inputs.
 *
 * Purpose:
 * - Associates descriptive text with a form control.
 * - Supports optional "for" attribute binding.
 *
 * Styling:
 * - No inline styling is applied.
 * - All typography, spacing, and visual styling are controlled by the active theme.
 * - Default base class: authkit-label
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Slots:
 * - $slot: Label text content.
 *
 * Props:
 * - for: Optional id of the associated input element.
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'for' => null,
    'unstyled' => false,
])

@php
    $baseClass = $unstyled ? '' : 'authkit-label';
@endphp

{{-- Label Element --}}
<label
        @if($for) for="{{ $for }}" @endif
        {{ $attributes->merge(['class' => $baseClass]) }}
>
    {{ $slot }}
</label>