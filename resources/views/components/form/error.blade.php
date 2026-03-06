{{--
/**
 * Component: Form Error
 *
 * Displays a validation error message for a given field.
 *
 * Purpose:
 * - Renders the first validation error associated with the provided field name.
 * - Integrates with Laravel's @error directive.
 *
 * Styling:
 * - No inline styling is applied.
 * - All spacing, typography, and color are controlled by the active theme.
 * - Default base class: authkit-form-error
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Props:
 * - name: Field name used for validation error lookup.
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'name',
    'unstyled' => false,
])

@php
    $baseClass = $unstyled ? '' : 'authkit-form-error';
@endphp

{{-- Validation Error Output --}}
@error($name)
<div {{ $attributes->merge(['class' => $baseClass]) }}>
    {{ $message }}
</div>
@enderror