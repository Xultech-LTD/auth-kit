{{--
/**
 * Component: App Navigation
 *
 * Wrapper component for authenticated AuthKit navigation collections.
 *
 * Responsibilities:
 * - Renders a semantic navigation container for authenticated app links.
 * - Iterates over normalized navigation items.
 * - Delegates item rendering to the configured nav-item component.
 * - Provides stable structural hooks for package themes and consumer overrides.
 *
 * Expected item shape:
 * - page   : internal page key
 * - label  : visible label
 * - route  : named route string
 * - icon   : optional icon key
 * - active : whether the item is currently active
 * - config : resolved page config
 *
 * Notes:
 * - This component is intentionally thin and presentation-oriented.
 * - Sidebar/topbar components may compose this component or render nav items directly.
 * - Consumers may replace this component to support grouped navigation,
 *   badges, nested menus, or custom layouts.
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
     * Navigation items to render.
     */
    'items' => [],

    /**
     * Accessible label for the navigation region.
     */
    'label' => 'Application navigation',
])

@php
    $components = (array) config('authkit.components', []);
    $navItemComponent = (string) data_get($components, 'app_nav_item', 'authkit::app.nav-item');

    $resolvedItems = collect(is_array($items) ? $items : [])
        ->filter(fn ($item) => is_array($item) && ((string) data_get($item, 'label', '')) !== '')
        ->values()
        ->all();
@endphp

<nav
        {{ $attributes->merge([
            'class' => 'authkit-app-nav',
            'data-authkit-app-nav' => '1',
            'aria-label' => $label,
        ]) }}
>
    @forelse ($resolvedItems as $item)
        <x-dynamic-component
                :component="$navItemComponent"
                :page-key="$pageKey"
                :page-config="$pageConfig"
                :item="$item"
        />
    @empty
        <div class="authkit-app-nav__empty">
            No navigation items available.
        </div>
    @endforelse
</nav>