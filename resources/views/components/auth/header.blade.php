{{--
/**
 * Component: Auth Header
 *
 * Standard header section for authentication pages.
 *
 * Purpose:
 * - Displays the primary page title.
 * - Optionally displays a supporting subtitle.
 *
 * Styling:
 * - No inline styling is applied.
 * - All spacing, typography, and opacity are controlled by the active theme.
 * - Base wrapper class: authkit-auth-header
 * - Title class: authkit-auth-header__title
 * - Subtitle class: authkit-auth-header__subtitle
 * - Consumers may pass additional classes.
 * - Consumers may disable package styling using "unstyled".
 *
 * Props:
 * - title: Primary heading text.
 * - subtitle: Optional secondary descriptive text.
 * - unstyled: When true, prevents default package classes from being applied.
 */
--}}

@props([
    'title' => '',
    'subtitle' => null,
    'unstyled' => false,
])

@php
    $wrapperClass = $unstyled ? '' : 'authkit-auth-header';
    $titleClass = $unstyled ? '' : 'authkit-auth-header__title';
    $subtitleClass = $unstyled ? '' : 'authkit-auth-header__subtitle';
@endphp

{{-- Header Wrapper --}}
<div {{ $attributes->merge(['class' => $wrapperClass]) }}>

    {{-- Title --}}
    <h1 class="{{ $titleClass }}">
        {{ $title }}
    </h1>

    {{-- Subtitle --}}
    @if (is_string($subtitle) && $subtitle !== '')
        <p class="{{ $subtitleClass }}">
            {{ $subtitle }}
        </p>
    @endif

</div>