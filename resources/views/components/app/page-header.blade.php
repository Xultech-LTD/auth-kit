{{--
/**
 * Component: App Page Header
 *
 * Standard page-header block for authenticated AuthKit pages.
 *
 * Purpose:
 * - Renders the primary visible title for the current authenticated page.
 * - Optionally renders a supporting subtitle/description.
 *
 * Responsibilities:
 * - Render a stable heading structure for app pages.
 * - Keep page-title typography and supporting text centralized.
 *
 * Props:
 * - title: Primary page title.
 * - subtitle: Optional supporting copy.
 *
 * Notes:
 * - This component is intentionally simple and reusable.
 * - It is used by the app topbar, but may also be reused directly inside
 *   pages or sections if needed.
 */
--}}

@props([
    'title' => '',
    'subtitle' => null,
])

@php
    $resolvedTitle = is_string($title) ? trim($title) : '';
    $resolvedSubtitle = is_string($subtitle) ? trim($subtitle) : '';
@endphp

<div class="authkit-app-page-header">
    @if ($resolvedTitle !== '')
        <h1 class="authkit-app-page-header__title">
            {{ $resolvedTitle }}
        </h1>
    @endif

    @if ($resolvedSubtitle !== '')
        <p class="authkit-app-page-header__subtitle">
            {{ $resolvedSubtitle }}
        </p>
    @endif
</div>