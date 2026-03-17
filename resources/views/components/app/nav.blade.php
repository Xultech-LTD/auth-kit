{{--
/**
 * Component: App Nav
 *
 * Authenticated application navigation for AuthKit.
 *
 * Supports:
 * - top-level nav items
 * - nested child nav items
 * - independent route values
 */
--}}

@props([
    'items' => [],
    'pages' => [],
    'currentPage' => null,
])

@php
    $components = (array) config('authkit.components', []);
    $navItemComponent = (string) ($components['app_nav_item'] ?? 'authkit::app.nav-item');

    $resolvedItems = is_array($items) ? $items : [];
    $resolvedPages = is_array($pages) ? $pages : [];

    $resolveItems = function (array $items) use ($resolvedPages): array {
        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) use ($resolvedPages): ?array {
                $pageKey = (string) ($item['page'] ?? '');

                if ($pageKey === '') {
                    return null;
                }

                $page = (array) ($resolvedPages[$pageKey] ?? []);

                if ($page === []) {
                    return null;
                }

                if (! (bool) ($page['enabled'] ?? false)) {
                    return null;
                }

                return [
                    'page_key' => $pageKey,
                    'page' => $page,
                    'route' => (string) ($item['route'] ?? ''),
                    'icon' => (string) ($item['icon'] ?? ''),
                    'label' => (string) ($item['label'] ?? ''),
                    'children' => is_array($item['children'] ?? null) ? $item['children'] : [],
                ];
            })
            ->filter()
            ->values()
            ->all();
    };

    $navigationItems = $resolveItems($resolvedItems);
@endphp

<nav class="authkit-app-nav" aria-label="Sidebar navigation">
    <div class="authkit-app-nav__section">
        <div class="authkit-app-nav__section-label">
            Navigation
        </div>

        <ul class="authkit-app-nav__list">
            @foreach ($navigationItems as $item)
                @php
                    $children = $resolveItems((array) ($item['children'] ?? []));
                    $pageKey = (string) ($item['page_key'] ?? '');
                    $isActive = is_string($currentPage) && $currentPage === $pageKey;

                    $hasActiveChild = collect($children)->contains(
                        fn ($child) => is_string($currentPage) && $currentPage === (string) ($child['page_key'] ?? '')
                    );
                @endphp

                <li class="authkit-app-nav__item">
                    <x-dynamic-component
                            :component="$navItemComponent"
                            :page-key="$pageKey"
                            :page="$item['page']"
                            :route="$item['route']"
                            :icon="$item['icon']"
                            :label="$item['label']"
                            :active="$isActive"
                            :children="$children"
                            :expanded="$isActive || $hasActiveChild"
                            :current-page="$currentPage"
                    />
                </li>
            @endforeach
        </ul>
    </div>
</nav>