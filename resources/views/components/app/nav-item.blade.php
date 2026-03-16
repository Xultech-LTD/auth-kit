{{--
/**
 * Component: App Navigation Item
 *
 * Single authenticated navigation link renderer for AuthKit.
 *
 * Responsibilities:
 * - Renders one normalized navigation item.
 * - Resolves the final destination URL from the configured route name.
 * - Determines active state from the normalized item or current page key.
 * - Emits stable semantic hooks for icon, label, and active state styling.
 *
 * Expected item shape:
 * - page   : internal page key
 * - label  : visible label
 * - route  : named route string
 * - icon   : optional icon key
 * - active : optional explicit active flag
 * - config : optional resolved page config
 *
 * Notes:
 * - Icon rendering is intentionally text/attribute based for now so the package
 *   can remain framework-agnostic.
 * - Consumers may override this component to swap icon systems, add badges,
 *   support nested items, or change active-state presentation.
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
     * Normalized navigation item.
     */
    'item' => [],
])

@php
    $item = is_array($item) ? $item : [];

    $itemPage = (string) data_get($item, 'page', '');
    $itemLabel = (string) data_get($item, 'label', '');
    $itemRoute = (string) data_get($item, 'route', '');
    $itemIcon = (string) data_get($item, 'icon', '');
    $itemConfig = (array) data_get($item, 'config', []);

    $isActive = (bool) data_get($item, 'active', false);

    if (! $isActive && $itemPage !== '' && is_string($pageKey) && $pageKey !== '') {
        $isActive = $itemPage === $pageKey;
    }

    $href = '#';

    if ($itemRoute !== '' && \Illuminate\Support\Facades\Route::has($itemRoute)) {
        $href = route($itemRoute);
    }

    $classes = trim(implode(' ', array_filter([
        'authkit-app-nav-item',
        $isActive ? 'authkit-app-nav-item--active' : '',
    ])));

    $ariaCurrent = $isActive ? 'page' : null;

    $label = $itemLabel !== ''
        ? $itemLabel
        : (string) data_get($itemConfig, 'nav_label', data_get($itemConfig, 'title', 'Navigation item'));
@endphp

<a
        href="{{ $href }}"
        {{ $attributes->merge([
            'class' => $classes,
            'data-authkit-app-nav-item' => $itemPage !== '' ? $itemPage : 'item',
            'aria-current' => $ariaCurrent,
        ]) }}
>
    <span
            class="authkit-app-nav-item__icon"
            data-authkit-app-nav-icon="{{ $itemIcon !== '' ? $itemIcon : 'default' }}"
            aria-hidden="true"
    >
        {{ $itemIcon !== '' ? strtoupper(substr($itemIcon, 0, 1)) : '•' }}
    </span>

    <span class="authkit-app-nav-item__label">
        {{ $label }}
    </span>
</a>