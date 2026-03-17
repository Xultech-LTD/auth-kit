{{--
/**
 * Component: App Nav Item
 *
 * Single authenticated application navigation item for AuthKit.
 *
 * Supports:
 * - main nav link
 * - optional child links
 * - independent route override
 * - independent child-menu toggle control
 */
--}}

@props([
    'pageKey',
    'page' => [],
    'route' => '',
    'icon' => '',
    'label' => '',
    'active' => false,
    'children' => [],
    'expanded' => false,
    'currentPage' => null,
])

@php
    $resolvedPageKey = is_string($pageKey) ? $pageKey : '';
    $resolvedPage = is_array($page) ? $page : [];
    $resolvedRoute = is_string($route) ? trim($route) : '';
    $resolvedIcon = is_string($icon) ? trim($icon) : '';
    $resolvedLabel = is_string($label) ? trim($label) : '';
    $resolvedChildren = is_array($children) ? $children : [];

    $hasChildren = !empty($resolvedChildren);

    $finalLabel = $resolvedLabel !== ''
        ? $resolvedLabel
        : (string) (
            $resolvedPage['nav_label']
            ?? $resolvedPage['title']
            ?? ucfirst(str_replace('_', ' ', $resolvedPageKey))
        );

    $pageRouteName = (string) ($resolvedPage['route'] ?? '');
    $routeName = $resolvedRoute !== '' ? $resolvedRoute : $pageRouteName;

    $href = '#';

    if ($routeName === '#') {
        $href = '#';
    } elseif ($routeName !== '' && \Illuminate\Support\Facades\Route::has($routeName)) {
        $href = route($routeName);
    }

    $iconMap = [
        'home' => '⌂',
        'settings' => '⚙',
        'shield' => '🛡',
        'devices' => '◫',
        'key' => '🔑',
        'user' => '◉',
        'lock' => '🔒',
        'help' => '?',
        'book' => '≣',
        'mail' => '✉',
    ];

    $iconGlyph = $iconMap[$resolvedIcon] ?? '•';

    $rootClass = trim('authkit-app-nav-item' . ($active ? ' authkit-app-nav-item--active' : ''));
    $childrenId = $resolvedPageKey !== '' ? 'authkit-app-nav-children-' . $resolvedPageKey : null;
@endphp

<div
        class="{{ $rootClass }}"
        data-authkit-app-nav-item="{{ $resolvedPageKey }}"
        @if($hasChildren) data-authkit-app-nav-has-children="true" @endif
        @if($hasChildren) data-authkit-app-nav-expanded="{{ $expanded ? 'true' : 'false' }}" @endif
>
    <div class="authkit-app-nav-item__row">
        <a
                href="{{ $href }}"
                class="authkit-app-nav-item__link"
                @if($active) aria-current="page" @endif
        >
            <span class="authkit-app-nav-item__icon" aria-hidden="true">
                {{ $iconGlyph }}
            </span>

            <span class="authkit-app-nav-item__label">
                {{ $finalLabel }}
            </span>
        </a>

        @if ($hasChildren)
            <button
                    type="button"
                    class="authkit-app-nav-item__toggle"
                    data-authkit-app-nav-toggle
                    aria-label="Toggle {{ $finalLabel }} submenu"
                    aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                    @if($childrenId) aria-controls="{{ $childrenId }}" @endif
            >
                <span class="authkit-app-nav-item__caret" aria-hidden="true">›</span>
            </button>
        @endif
    </div>

    @if ($hasChildren)
        <ul
                class="authkit-app-nav-item__children"
                @if($childrenId) id="{{ $childrenId }}" @endif
        >
            @foreach ($resolvedChildren as $child)
                @php
                    $childPageKey = (string) ($child['page_key'] ?? '');
                    $childPage = (array) ($child['page'] ?? []);
                    $childRoute = (string) ($child['route'] ?? '');
                    $childIcon = (string) ($child['icon'] ?? '');
                    $childLabelOverride = (string) ($child['label'] ?? '');

                    $childLabel = $childLabelOverride !== ''
                        ? $childLabelOverride
                        : (string) (
                            $childPage['nav_label']
                            ?? $childPage['title']
                            ?? ucfirst(str_replace('_', ' ', $childPageKey))
                        );

                    $childPageRouteName = (string) ($childPage['route'] ?? '');
                    $childRouteName = $childRoute !== '' ? $childRoute : $childPageRouteName;

                    $childHref = '#';

                    if ($childRouteName === '#') {
                        $childHref = '#';
                    } elseif ($childRouteName !== '' && \Illuminate\Support\Facades\Route::has($childRouteName)) {
                        $childHref = route($childRouteName);
                    }

                    $childActive = is_string($currentPage) && $currentPage === $childPageKey;
                    $childIconGlyph = $iconMap[$childIcon] ?? '•';
                @endphp

                <li class="authkit-app-nav-item__children-item">
                    <a
                            href="{{ $childHref }}"
                            class="authkit-app-nav-item__child-link{{ $childActive ? ' authkit-app-nav-item__child-link--active' : '' }}"
                            @if($childActive) aria-current="page" @endif
                    >
                        <span class="authkit-app-nav-item__child-icon" aria-hidden="true">
                            {{ $childIconGlyph }}
                        </span>

                        <span class="authkit-app-nav-item__child-label">
                            {{ $childLabel }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>