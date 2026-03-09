{{--
/**
 * Component: Page
 *
 * High-level page shell used by AuthKit pages.
 *
 * Responsibilities:
 * - Wraps content with the root AuthKit layout component.
 * - Provides stable page-level CSS hooks.
 * - Allows page templates to remain focused on actual page content.
 *
 * Styling hooks:
 * - authkit-page
 * - authkit-page-container
 * - authkit-auth-page
 *
 * Props:
 * - title: Document/page title.
 * - theme: Optional UI theme override.
 * - engine: Optional UI engine override.
 * - mode: Optional appearance mode override.
 * - variant: Page variant key used for modifier classes.
 *
 * Supported variants (initial):
 * - auth
 * - default
 */
--}}

@props([
    'title' => 'AuthKit',
    'theme' => null,
    'engine' => null,
    'mode' => null,
    'variant' => 'auth',
    'pageKey' => null,
])

@php
    $pageClass = 'authkit-page';
    $containerClass = 'authkit-page-container';
    $variantClass = $variant !== '' ? 'authkit-'.$variant.'-page' : '';
@endphp

<x-authkit::layout
        :title="$title"
        :theme="$theme"
        :engine="$engine"
        :mode="$mode"
>
    <main class="{{ trim($pageClass.' '.$variantClass) }}"
          @if(is_string($pageKey) && $pageKey !== '') data-authkit-page="{{ $pageKey }}" @endif
    >
        <div class="{{ $containerClass }}">
            {{ $slot }}
        </div>
    </main>
</x-authkit::layout>