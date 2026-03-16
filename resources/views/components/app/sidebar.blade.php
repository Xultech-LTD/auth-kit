{{--
/**
 * Component: App Sidebar
 *
 * Authenticated application sidebar for AuthKit pages.
 *
 * Responsibilities:
 * - Renders the authenticated navigation area.
 * - Displays a configurable product/application brand area.
 * - Delegates navigation item rendering to the configured nav item component.
 * - Highlights the active page based on the resolved current page key.
 * - Provides stable structural hooks for authenticated shell styling.
 *
 * Expected item shape:
 * - page   : internal page key
 * - label  : visible navigation label
 * - route  : named route string
 * - icon   : optional icon key
 * - active : whether the item is the current page
 * - config : resolved page config
 *
 * Notes:
 * - This component intentionally stays presentation-oriented.
 * - Route protection remains the responsibility of route middleware.
 * - Consumers may override this component to fully customize sidebar markup.
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
     * Normalized sidebar navigation items.
     */
    'items' => [],
])

@php
    $components = (array) config('authkit.components', []);
    $appConfig = (array) config('authkit.app', []);
    $navItemComponent = (string) data_get($components, 'app_nav_item', 'authkit::app.nav-item');

    $resolvedItems = collect(is_array($items) ? $items : [])
        ->filter(fn ($item) => is_array($item) && ((string) data_get($item, 'label', '')) !== '')
        ->values()
        ->all();

    $appTitle = (string) data_get($appConfig, 'brand.name', 'AuthKit');
    $appSubtitle = (string) data_get($appConfig, 'brand.subtitle', 'Account');
@endphp

<aside
        {{ $attributes->merge([
            'class' => 'authkit-app-sidebar',
            'data-authkit-app-sidebar' => '1',
        ]) }}
>
    <div class="authkit-app-sidebar__inner">
        <div class="authkit-app-sidebar__brand" data-authkit-app-sidebar-brand="1">
            <div class="authkit-app-sidebar__brand-mark" aria-hidden="true">
                {{ strtoupper(substr($appTitle, 0, 1)) }}
            </div>

            <div class="authkit-app-sidebar__brand-copy">
                <div class="authkit-app-sidebar__brand-title">
                    {{ $appTitle }}
                </div>

                <div class="authkit-app-sidebar__brand-subtitle">
                    {{ $appSubtitle }}
                </div>
            </div>
        </div>

        <nav
                class="authkit-app-sidebar__nav"
                data-authkit-app-sidebar-nav="1"
                aria-label="Application navigation"
        >
            @forelse ($resolvedItems as $item)
                <x-dynamic-component
                        :component="$navItemComponent"
                        :page-key="$pageKey"
                        :page-config="$pageConfig"
                        :item="$item"
                />
            @empty
                <div class="authkit-app-sidebar__empty">
                    No navigation items available.
                </div>
            @endforelse
        </nav>
    </div>
</aside>