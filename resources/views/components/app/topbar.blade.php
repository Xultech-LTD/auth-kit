{{--
/**
 * Component: App Topbar
 *
 * Authenticated application topbar for AuthKit pages.
 *
 * Responsibilities:
 * - Renders the upper action bar for authenticated pages.
 * - Displays current page context using resolved title metadata.
 * - Optionally renders the packaged theme toggle inside the app shell.
 * - Renders the configured user menu component.
 * - Provides stable structural hooks for authenticated shell styling.
 *
 * Notes:
 * - This component is intentionally lightweight and compositional.
 * - Consumers may replace it to introduce breadcrumbs, notifications,
 *   tenant switchers, search, or other project-specific actions.
 */
--}}

@props([
    /**
     * Current authenticated app page key.
     */
    'pageKey' => null,

    /**
     * Current resolved page config.
     */
    'pageConfig' => [],

    /**
     * Current page/browser title text.
     */
    'title' => null,

    /**
     * Whether the packaged theme toggle should be shown here.
     */
    'toggleEnabled' => false,

    /**
     * Theme toggle component alias.
     */
    'themeToggleComponent' => 'authkit::theme-toggle',
])

@php
    $components = (array) config('authkit.components', []);
    $userMenuComponent = (string) data_get($components, 'app_user_menu', 'authkit::app.user-menu');

    $resolvedTitle = is_string($title) && $title !== ''
        ? $title
        : (string) data_get((array) $pageConfig, 'title', 'Account');

    $resolvedPageKey = is_string($pageKey) && $pageKey !== ''
        ? $pageKey
        : '';
@endphp

<header
        {{ $attributes->merge([
            'class' => 'authkit-app-topbar',
            'data-authkit-app-topbar' => '1',
        ]) }}
>
    <div class="authkit-app-topbar__inner">
        <div class="authkit-app-topbar__context" data-authkit-app-topbar-context="1">
            <div class="authkit-app-topbar__eyebrow">
                Authenticated area
            </div>

            <div class="authkit-app-topbar__title" data-authkit-app-page="{{ $resolvedPageKey }}">
                {{ $resolvedTitle }}
            </div>
        </div>

        <div class="authkit-app-topbar__actions" data-authkit-app-topbar-actions="1">
            @if ($toggleEnabled)
                <div class="authkit-app-topbar__toggle" data-authkit-app-topbar-toggle="1">
                    <x-dynamic-component :component="$themeToggleComponent" />
                </div>
            @endif

            <x-dynamic-component
                    :component="$userMenuComponent"
                    :page-key="$resolvedPageKey"
                    :page-config="$pageConfig"
            />
        </div>
    </div>
</header>