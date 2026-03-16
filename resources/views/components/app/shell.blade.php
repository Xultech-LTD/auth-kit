{{--
/**
 * Component: App Shell
 *
 * Shared authenticated shell for AuthKit application pages.
 *
 * Responsibilities:
 * - Renders the authenticated application chrome around page content.
 * - Resolves sidebar, topbar, and page-header support components from configuration.
 * - Resolves the current page definition and navigation context.
 * - Renders the packaged theme toggle within the authenticated shell when enabled.
 * - Provides stable structural hooks for authenticated page styling.
 *
 * Notes:
 * - This component is intentionally layout-agnostic and expects the root document
 *   shell to be handled by the authenticated app layout.
 * - Authorization remains the responsibility of route middleware.
 * - Navigation visibility is driven by authkit.app.pages and authkit.app.navigation.
 */
--}}

@props([
    /**
     * Current authenticated app page key.
     */
    'pageKey' => null,

    /**
     * Resolved current page config.
     *
     * Expected shape:
     * - title
     * - heading
     * - nav_label
     * - show_in_sidebar
     * - route
     * - view
     */
    'pageConfig' => [],

    /**
     * Resolved page heading text.
     */
    'heading' => null,

    /**
     * Resolved browser/page title text.
     */
    'title' => null,

    /**
     * Whether the packaged theme toggle should be rendered in the shell.
     */
    'toggleEnabled' => false,

    /**
     * Theme toggle component alias.
     */
    'themeToggleComponent' => 'authkit::theme-toggle',
])

@php
    $appConfig = (array) config('authkit.app', []);
    $components = (array) config('authkit.components', []);
    $navigation = (array) data_get($appConfig, 'navigation.sidebar', []);
    $pages = (array) data_get($appConfig, 'pages', []);

    $sidebarComponent = (string) data_get($components, 'app_sidebar', 'authkit::app.sidebar');
    $topbarComponent = (string) data_get($components, 'app_topbar', 'authkit::app.topbar');
    $pageHeaderComponent = (string) data_get($components, 'app_page_header', 'authkit::app.page-header');

    $resolvedPageKey = is_string($pageKey) && $pageKey !== ''
        ? $pageKey
        : null;

    $resolvedPageConfig = is_array($pageConfig) ? $pageConfig : [];

    $resolvedHeading = is_string($heading) && $heading !== ''
        ? $heading
        : (string) data_get($resolvedPageConfig, 'heading', '');

    $resolvedTitle = is_string($title) && $title !== ''
        ? $title
        : (string) data_get($resolvedPageConfig, 'title', '');

    /**
     * Normalize sidebar items against configured app pages.
     *
     * Rules:
     * - Ignore invalid page references.
     * - Ignore disabled pages.
     * - Ignore pages hidden from sidebar.
     * - Preserve configured item ordering.
     */
    $sidebarItems = collect($navigation)
        ->map(function ($item) use ($pages, $resolvedPageKey) {
            $item = is_array($item) ? $item : [];
            $page = (string) data_get($item, 'page', '');

            if ($page === '') {
                return null;
            }

            $pageConfig = (array) data_get($pages, $page, []);

            if ($pageConfig === []) {
                return null;
            }

            if (! (bool) data_get($pageConfig, 'enabled', true)) {
                return null;
            }

            if (! (bool) data_get($pageConfig, 'show_in_sidebar', false)) {
                return null;
            }

            return [
                'page' => $page,
                'icon' => (string) data_get($item, 'icon', ''),
                'label' => (string) data_get($pageConfig, 'nav_label', data_get($pageConfig, 'title', $page)),
                'route' => (string) data_get($pageConfig, 'route', ''),
                'active' => $page === $resolvedPageKey,
                'config' => $pageConfig,
            ];
        })
        ->filter()
        ->values()
        ->all();
@endphp

<div
        {{ $attributes->merge([
            'class' => 'authkit-app-shell',
            'data-authkit-app-shell' => '1',
        ]) }}
>
    <x-dynamic-component
            :component="$sidebarComponent"
            :page-key="$resolvedPageKey"
            :page-config="$resolvedPageConfig"
            :items="$sidebarItems"
    />

    <div class="authkit-app-shell__main" data-authkit-app-main="1">
        <x-dynamic-component
                :component="$topbarComponent"
                :page-key="$resolvedPageKey"
                :page-config="$resolvedPageConfig"
                :title="$resolvedTitle"
                :toggle-enabled="$toggleEnabled"
                :theme-toggle-component="$themeToggleComponent"
        />

        <main class="authkit-app-shell__content" data-authkit-app-content="1">
            <x-dynamic-component
                    :component="$pageHeaderComponent"
                    :page-key="$resolvedPageKey"
                    :page-config="$resolvedPageConfig"
                    :title="$resolvedTitle"
                    :heading="$resolvedHeading"
            />

            <div class="authkit-app-shell__body" data-authkit-app-body="1">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>