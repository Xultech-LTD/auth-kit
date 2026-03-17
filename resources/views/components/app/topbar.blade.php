{{--
/**
 * Component: App Topbar
 *
 * Authenticated application topbar for AuthKit.
 *
 * Purpose:
 * - Renders the top region of authenticated AuthKit pages.
 * - Displays sidebar toggle controls for desktop/mobile shell behavior.
 * - Displays the page header block for the current page.
 * - Displays a compact theme selector beside the authenticated user menu.
 */
--}}
@props([
    'currentPage' => null,
    'pageTitle' => null,
    'pageHeading' => null,
    'showThemeToggle' => true,
    'showUserMenu' => true,
])

@php
    $components = (array) config('authkit.components', []);
    $app = (array) config('authkit.app', []);
    $pages = (array) data_get($app, 'pages', []);

    $pageHeaderComponent = (string) ($components['app_page_header'] ?? 'authkit::app.page-header');
    $userMenuComponent = (string) ($components['app_user_menu'] ?? 'authkit::app.user-menu');
    $themeToggleComponent = (string) ($components['theme_toggle'] ?? 'authkit::theme-toggle');

    $resolvedPage = is_string($currentPage) && $currentPage !== ''
        ? (array) ($pages[$currentPage] ?? [])
        : [];

    $resolvedTitle = is_string($pageTitle) && trim($pageTitle) !== ''
        ? trim($pageTitle)
        : (string) ($resolvedPage['title'] ?? 'Dashboard');

    $resolvedHeading = is_string($pageHeading) && trim($pageHeading) !== ''
        ? trim($pageHeading)
        : (string) ($resolvedPage['heading'] ?? '');

    $allowCollapse = (bool) data_get($app, 'shell.sidebar.allow_collapse', true);
    $allowMobileDrawer = (bool) data_get($app, 'shell.sidebar.mobile_drawer', true);
@endphp

<div class="authkit-app-topbar">
    <div class="authkit-app-topbar__left">
        @if ($allowMobileDrawer || $allowCollapse)
            <div class="authkit-app-topbar__toggles">
                @if ($allowMobileDrawer)
                    <button
                            type="button"
                            class="authkit-app-topbar__toggle authkit-app-topbar__toggle--mobile"
                            data-authkit-sidebar-open-trigger
                            aria-label="Open sidebar"
                            aria-controls="authkit-app-sidebar"
                    >
                        <span class="authkit-app-topbar__toggle-bars" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </span>
                    </button>
                @endif

                @if ($allowCollapse)
                    <button
                            type="button"
                            class="authkit-app-topbar__toggle authkit-app-topbar__toggle--desktop"
                            data-authkit-sidebar-collapse-trigger
                            aria-label="Toggle sidebar"
                            aria-controls="authkit-app-sidebar"
                    >
                        <span class="authkit-app-topbar__collapse-icon" aria-hidden="true">
                            <span></span>
                            <span></span>
                        </span>
                    </button>
                @endif
            </div>
        @endif

        <div class="authkit-app-topbar__main">
            <x-dynamic-component
                    :component="$pageHeaderComponent"
                    :title="$resolvedTitle"
                    :subtitle="$resolvedHeading"
            />
        </div>
    </div>

    @if ($showThemeToggle || $showUserMenu)
        <div class="authkit-app-topbar__right">
            @if ($showThemeToggle)
                <div class="authkit-app-topbar__theme-toggle">
                    <x-dynamic-component
                            :component="$themeToggleComponent"
                            variant="dropdown"
                            :show-labels="true"
                    />
                </div>
            @endif

            @if ($showUserMenu)
                <div class="authkit-app-topbar__user-menu">
                    <x-dynamic-component :component="$userMenuComponent" />
                </div>
            @endif
        </div>
    @endif
</div>