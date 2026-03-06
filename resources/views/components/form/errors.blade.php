{{--
/**
 * Component: Form Errors
 *
 * Displays a top-level validation error summary.
 *
 * Purpose:
 * - Renders a list of all validation errors when present.
 * - Intended for placement near the top of forms.
 *
 * Styling:
 * - No inline styling is applied.
 * - All spacing, borders, background, and typography are controlled by the active theme.
 * - Wrapper class: authkit-form-errors
 * - Title class: authkit-form-errors__title
 * - List class: authkit-form-errors__list
 * - Item class: authkit-form-errors__item
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Props:
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'unstyled' => false,
])

@php
    $wrapperClass = $unstyled ? '' : 'authkit-form-errors';
    $titleClass = $unstyled ? '' : 'authkit-form-errors__title';
    $listClass = $unstyled ? '' : 'authkit-form-errors__list';
    $itemClass = $unstyled ? '' : 'authkit-form-errors__item';
@endphp

{{-- Error Summary --}}
@if ($errors->any())
    <div {{ $attributes->merge(['class' => $wrapperClass]) }}>

        {{-- Error Title --}}
        <div class="{{ $titleClass }}">
            Please fix the errors below:
        </div>

        {{-- Error List --}}
        <ul class="{{ $listClass }}">
            @foreach ($errors->all() as $error)
                <li class="{{ $itemClass }}">
                    {{ $error }}
                </li>
            @endforeach
        </ul>

    </div>
@endif