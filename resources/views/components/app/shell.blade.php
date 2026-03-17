{{--
/**
 * Component: App Shell
 *
 * Structural authenticated shell wrapper for AuthKit application pages.
 *
 * Purpose:
 * - Provides the shared authenticated application structure used by the
 *   authenticated root app layout.
 * - Separates overall app-shell markup from the root document layout so the
 *   shell can evolve independently.
 * - Supports default sidebar/topbar rendering as well as consumer overrides
 *   through named slots.
 * - Provides first-class shell hooks for:
 *   - desktop sidebar collapse
 *   - mobile sidebar drawer
 *   - overlay/backdrop handling
 *   - topbar-driven shell toggles
 *
 * Responsibilities:
 * - Render the authenticated application frame.
 * - Render the sidebar column when enabled.
 * - Render the main application column.
 * - Render the topbar row when enabled.
 * - Render the main page content region.
 * - Expose stateful shell attributes for CSS and JavaScript.
 *
 * Shell state attributes:
 * - data-authkit-shell
 * - data-authkit-sidebar-collapsed="true|false"
 * - data-authkit-sidebar-open="true|false"
 * - data-authkit-sidebar-enabled="true|false"
 *
 * Structure:
 * - .authkit-app-shell
 *   - .authkit-app-shell__backdrop
 *   - .authkit-app-shell__layout
 *     - .authkit-app-shell__sidebar
 *       - .authkit-app-shell__sidebar-inner
 *     - .authkit-app-shell__panel
 *       - .authkit-app-shell__topbar
 *       - .authkit-app-shell__body
 *         - .authkit-app-shell__body-inner
 *
 * Notes:
 * - This component is intentionally structural first.
 * - Sidebar visual rules belong in app-sidebar.css.
 * - Topbar visual rules belong in app-topbar.css.
 * - The shell owns shell-level interaction hooks even when actual interaction
 *   logic is implemented in JavaScript.
 *
 * Props:
 * - currentPage: Current authenticated page key.
 * - pageTitle: Visible page title rendered in the topbar/page header.
 * - pageHeading: Optional supporting text rendered in the topbar/page header.
 * - showSidebar: Whether to render the sidebar region.
 * - showTopbar: Whether to render the topbar region.
 * - showThemeToggle: Whether the topbar should render the theme toggle.
 * - showUserMenu: Whether the topbar should render the user menu.
 *
 * Slots:
 * - sidebar: Optional custom sidebar content.
 * - topbar: Optional custom topbar content.
 * - default slot: Main page content.
 --}}
@props([
    'currentPage' => null,
    'pageTitle' => null,
    'pageHeading' => null,
    'showSidebar' => true,
    'showTopbar' => true,
    'showThemeToggle' => true,
    'showUserMenu' => true,
])

@php
    $components = (array) config('authkit.components', []);
    $app = (array) config('authkit.app', []);

    $sidebarComponent = (string) ($components['app_sidebar'] ?? 'authkit::app.sidebar');
    $topbarComponent = (string) ($components['app_topbar'] ?? 'authkit::app.topbar');

    $hasSidebarSlot = isset($sidebar) && trim((string) $sidebar) !== '';
    $hasTopbarSlot = isset($topbar) && trim((string) $topbar) !== '';

    $allowCollapse = (bool) data_get($app, 'shell.sidebar.allow_collapse', true);
    $allowMobileDrawer = (bool) data_get($app, 'shell.sidebar.mobile_drawer', true);
    $defaultCollapsed = (bool) data_get($app, 'shell.sidebar.collapsed', false);

    $shellAttributes = new \Illuminate\View\ComponentAttributeBag([
        'class' => 'authkit-app-shell',
        'data-authkit-shell' => '1',
        'data-authkit-sidebar-enabled' => $showSidebar ? 'true' : 'false',
        'data-authkit-sidebar-collapsed' => $defaultCollapsed ? 'true' : 'false',
        'data-authkit-sidebar-open' => 'false',
        'data-authkit-sidebar-collapsible' => $allowCollapse ? 'true' : 'false',
        'data-authkit-sidebar-mobile-drawer' => $allowMobileDrawer ? 'true' : 'false',
    ]);
@endphp

<div {{ $shellAttributes }}>
    @if ($showSidebar && $allowMobileDrawer)
        <button
                type="button"
                class="authkit-app-shell__backdrop"
                data-authkit-sidebar-backdrop
                aria-label="Close sidebar"
                aria-hidden="true"
                tabindex="-1"
        ></button>
    @endif

    <div class="authkit-app-shell__layout">
        @if ($showSidebar)
            <aside
                    class="authkit-app-shell__sidebar"
                    aria-label="Application sidebar"
                    data-authkit-sidebar
            >
                <div class="authkit-app-shell__sidebar-inner">
                    @if ($hasSidebarSlot)
                        {{ $sidebar }}
                    @else
                        <x-dynamic-component
                                :component="$sidebarComponent"
                                :current-page="$currentPage"
                        />
                    @endif
                </div>
            </aside>
        @endif

        <div class="authkit-app-shell__panel">
            @if ($showTopbar)
                <header class="authkit-app-shell__topbar" data-authkit-topbar>
                    @if ($hasTopbarSlot)
                        {{ $topbar }}
                    @else
                        <x-dynamic-component
                                :component="$topbarComponent"
                                :current-page="$currentPage"
                                :page-title="$pageTitle"
                                :page-heading="$pageHeading"
                                :show-theme-toggle="$showThemeToggle"
                                :show-user-menu="$showUserMenu"
                        />
                    @endif
                </header>
            @endif

            <main class="authkit-app-shell__body">
                <div class="authkit-app-shell__body-inner">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</div>