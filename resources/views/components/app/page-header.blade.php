{{--
/**
 * Component: App Page Header
 *
 * Standard page header for AuthKit authenticated application pages.
 *
 * Responsibilities:
 * - Renders the page heading and optional supporting copy.
 * - Exposes stable structural hooks for title, description, and actions.
 * - Provides a reusable page-header surface across dashboard, settings,
 *   security, sessions, and confirmation pages.
 *
 * Props:
 * - title: Primary page heading text.
 * - description: Optional supporting description text.
 * - pageKey: Optional current authenticated page key.
 * - pageConfig: Optional resolved page configuration.
 *
 * Slots:
 * - $slot: Optional actions/content rendered on the right side of the header.
 *
 * Notes:
 * - This component is presentation-only and does not resolve page metadata.
 * - Controllers/pages should pass already-resolved title/description values.
 * - Consumers may override this component to support breadcrumbs, badges,
 *   tabs, or richer page-level controls.
 */
--}}

@props([
    'title' => null,
    'description' => null,
    'pageKey' => null,
    'pageConfig' => [],
])

@php
    $resolvedTitle = is_string($title) && trim($title) !== ''
        ? trim($title)
        : (string) data_get($pageConfig, 'heading', data_get($pageConfig, 'title', 'Page'));

    $resolvedDescription = is_string($description) && trim($description) !== ''
        ? trim($description)
        : null;

    $hasActions = trim((string) $slot) !== '';
@endphp

<header
        {{ $attributes->merge([
            'class' => 'authkit-app-page-header',
            'data-authkit-app-page-header' => is_string($pageKey) && $pageKey !== '' ? $pageKey : 'page',
        ]) }}
>
    <div class="authkit-app-page-header__content">
        <div class="authkit-app-page-header__copy">
            <h1 class="authkit-app-page-header__title">
                {{ $resolvedTitle }}
            </h1>

            @if ($resolvedDescription !== null)
                <p class="authkit-app-page-header__description">
                    {{ $resolvedDescription }}
                </p>
            @endif
        </div>

        @if ($hasActions)
            <div class="authkit-app-page-header__actions">
                {{ $slot }}
            </div>
        @endif
    </div>
</header>