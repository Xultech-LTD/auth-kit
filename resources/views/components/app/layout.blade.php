{{--
/**
 * Component: App Layout
 *
 * Root authenticated document layout for AuthKit application pages.
 *
 * Responsibilities:
 * - Defines the HTML document shell for authenticated AuthKit pages.
 * - Resolves the active UI engine, theme, and appearance mode.
 * - Loads published AuthKit built assets and optional extension assets.
 * - Exposes stable root hooks for authenticated page styling and behavior.
 * - Exposes AuthKit browser runtime configuration on window.AuthKit.config.
 * - Resolves authenticated application configuration for page rendering.
 * - Renders the packaged authenticated shell component around page content.
 *
 * Notes:
 * - This layout is intended for authenticated "app" pages such as dashboard,
 *   settings, security, sessions, and confirmation pages.
 * - This layout does not itself decide page authorization; route middleware
 *   remains responsible for access control.
 * - Presentation remains theme-driven. No inline visual styling is applied.
 */
--}}

@props([
    'title' => 'AuthKit',
    'theme' => null,
    'engine' => null,
    'mode' => null,

    /**
     * Current authenticated app page key.
     *
     * Expected examples:
     * - dashboard_web
     * - settings
     * - security
     * - sessions
     * - two_factor_settings
     * - confirm_password
     * - confirm_two_factor
     */
    'pageKey' => null,

    /**
     * Optional page heading override.
     *
     * When null, the heading is resolved from authkit.app.pages.{pageKey}.heading.
     */
    'heading' => null,

    /**
     * Optional page title override.
     *
     * When null, the browser title is resolved from the explicit "title" prop first,
     * then from authkit.app.pages.{pageKey}.title.
     */
    'pageTitle' => null,
])

@php
    $basePath = trim((string) config('authkit.assets.base_path', 'vendor/authkit'), '/');

    $ui = (array) config('authkit.ui', []);
    $themes = (array) config('authkit.themes', []);
    $components = (array) config('authkit.components', []);
    $javascript = (array) config('authkit.javascript', []);
    $forms = (array) config('authkit.forms', []);
    $appConfig = (array) config('authkit.app', []);

    $resolvedEngine = is_string($engine) && $engine !== ''
        ? $engine
        : (string) data_get($ui, 'engine', 'tailwind');

    $resolvedTheme = is_string($theme) && $theme !== ''
        ? $theme
        : (string) data_get($ui, 'theme', 'forest');

    $resolvedMode = is_string($mode) && $mode !== ''
        ? $mode
        : (string) data_get($ui, 'mode', 'system');

    $resolvedPageKey = is_string($pageKey) && $pageKey !== ''
        ? $pageKey
        : null;

    $pageConfig = $resolvedPageKey !== null
        ? (array) data_get($appConfig, "pages.{$resolvedPageKey}", [])
        : [];

    $resolvedPageTitle = is_string($pageTitle) && $pageTitle !== ''
        ? $pageTitle
        : ((string) data_get($pageConfig, 'title', $title));

    $resolvedHeading = is_string($heading) && $heading !== ''
        ? $heading
        : (string) data_get($pageConfig, 'heading', '');

    $themeFilePattern = (string) data_get($themes, 'file_pattern', '{engine}-{theme}.css');
    $themeFile = strtr($themeFilePattern, [
        '{engine}' => $resolvedEngine,
        '{theme}' => $resolvedTheme,
    ]);

    $loadStylesheet = (bool) data_get($ui, 'load_stylesheet', true);
    $loadScript = (bool) data_get($ui, 'load_script', true);
    $javascriptEnabled = (bool) data_get($javascript, 'enabled', true);

    $useDataAttributes = (bool) data_get($ui, 'use_data_attributes', true);
    $enableRootHooks = (bool) data_get($ui, 'extensions.enable_root_hooks', true);

    $toggleEnabled = (bool) data_get($ui, 'toggle.enabled', true);
    $themeToggleComponent = (string) data_get($components, 'theme_toggle', 'authkit::theme-toggle');

    $appShellComponent = (string) data_get($components, 'app_shell', 'authkit::app.shell');

    $extraCss = array_values(array_filter(
        (array) data_get($ui, 'extensions.extra_css', []),
        fn ($path) => is_string($path) && $path !== ''
    ));

    $extraJs = array_values(array_filter(
        (array) data_get($ui, 'extensions.extra_js', []),
        fn ($path) => is_string($path) && $path !== ''
    ));

    $baseAssets = (array) config('authkit.assets.base', []);
    $baseCss = array_values(array_filter(
        (array) data_get($baseAssets, 'css', []),
        fn ($path) => is_string($path) && $path !== ''
    ));

    $baseJs = array_values(array_filter(
        (array) data_get($baseAssets, 'js', []),
        fn ($path) => is_string($path) && $path !== ''
    ));

    if ($loadScript && $javascriptEnabled && empty($baseJs)) {
        $baseJs = ['js/authkit.js'];
    }

    $htmlAttributes = new \Illuminate\View\ComponentAttributeBag([
        'lang' => str_replace('_', '-', app()->getLocale()),
    ]);

    if ($enableRootHooks) {
        $htmlAttributes = $htmlAttributes->merge([
            'class' => 'authkit authkit-app',
        ]);
    }

    if ($useDataAttributes) {
        $htmlAttributes = $htmlAttributes->merge([
            'data-authkit-engine' => $resolvedEngine,
            'data-authkit-theme' => $resolvedTheme,
            'data-authkit-mode' => $resolvedMode,
            'data-authkit-surface' => 'app',
        ]);

        if ($resolvedPageKey !== null) {
            $htmlAttributes = $htmlAttributes->merge([
                'data-authkit-page' => $resolvedPageKey,
            ]);
        }
    }

    $storageEnabled = (bool) data_get($ui, 'persistence.enabled', true);
    $storageKey = (string) data_get($ui, 'persistence.storage_key', 'authkit.ui.mode');

    $browserPages = collect((array) data_get($javascript, 'pages', []))
        ->mapWithKeys(function ($page, $key) {
            return [
                $key => [
                    'enabled' => (bool) data_get($page, 'enabled', true),
                    'pageKey' => (string) data_get($page, 'page_key', $key),
                ],
            ];
        })
        ->all();

    $browserConfig = [
        'runtime' => [
            'windowKey' => (string) data_get($javascript, 'runtime.window_key', 'AuthKit'),
            'dispatchEvents' => (bool) data_get($javascript, 'runtime.dispatch_events', true),
            'eventTarget' => (string) data_get($javascript, 'runtime.event_target', 'document'),
        ],
        'ui' => [
            'mode' => $resolvedMode,
            'persistence' => [
                'enabled' => $storageEnabled,
                'storageKey' => $storageKey,
            ],
            'toggle' => [
                'attribute' => (string) data_get($ui, 'toggle.attribute', 'data-authkit-theme-toggle'),
                'allowSystem' => (bool) data_get($ui, 'toggle.allow_system', true),
            ],
        ],
        'events' => [
            'ready' => (string) data_get($javascript, 'events.ready', 'authkit:ready'),
            'theme_ready' => (string) data_get($javascript, 'events.theme_ready', 'authkit:theme:ready'),
            'theme_changed' => (string) data_get($javascript, 'events.theme_changed', 'authkit:theme:changed'),
            'form_before_submit' => (string) data_get($javascript, 'events.form_before_submit', 'authkit:form:before-submit'),
            'form_success' => (string) data_get($javascript, 'events.form_success', 'authkit:form:success'),
            'form_error' => (string) data_get($javascript, 'events.form_error', 'authkit:form:error'),
            'page_ready' => (string) data_get($javascript, 'events.page_ready', 'authkit:page:ready'),
        ],
        'modules' => [
            'theme' => [
                'enabled' => (bool) data_get($javascript, 'modules.theme.enabled', true),
            ],
            'forms' => [
                'enabled' => (bool) data_get($javascript, 'modules.forms.enabled', true),
            ],
        ],
        'pages' => $browserPages,
        'forms' => [
            'mode' => (string) data_get($forms, 'mode', 'http'),
            'ajax' => [
                'attribute' => (string) data_get($forms, 'ajax.attribute', 'data-authkit-ajax'),
                'submitJson' => (bool) data_get($forms, 'ajax.submit_json', true),
                'successBehavior' => (string) data_get($forms, 'ajax.success_behavior', 'redirect'),
                'fallbackRedirect' => data_get($forms, 'ajax.fallback_redirect'),
            ],
            'loading' => [
                'enabled' => (bool) data_get($forms, 'loading.enabled', true),
                'preventDoubleSubmit' => (bool) data_get($forms, 'loading.prevent_double_submit', true),
                'disableSubmit' => (bool) data_get($forms, 'loading.disable_submit', true),
                'setAriaBusy' => (bool) data_get($forms, 'loading.set_aria_busy', true),
                'type' => (string) data_get($forms, 'loading.type', 'spinner_text'),
                'text' => (string) data_get($forms, 'loading.text', 'Processing...'),
                'showText' => (bool) data_get($forms, 'loading.show_text', true),
                'html' => data_get($forms, 'loading.html'),
                'className' => (string) data_get($forms, 'loading.class', 'authkit-btn--loading'),
            ],
        ],
        'app' => [
            'enabled' => (bool) data_get($appConfig, 'enabled', true),
            'pageKey' => $resolvedPageKey,
            'page' => [
                'title' => $resolvedPageTitle,
                'heading' => $resolvedHeading,
                'showInSidebar' => (bool) data_get($pageConfig, 'show_in_sidebar', false),
            ],
        ],
    ];
@endphp

        <!doctype html>
<html {{ $htmlAttributes }}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $resolvedPageTitle }}</title>

    @if ($useDataAttributes)
        <script>
            (function () {
                try {
                    var root = document.documentElement;
                    var configuredMode = @json($resolvedMode);
                    var storageEnabled = @json($storageEnabled);
                    var storageKey = @json($storageKey);

                    var savedMode = null;

                    if (storageEnabled) {
                        try {
                            savedMode = window.localStorage.getItem(storageKey);
                        } catch (e) {
                            savedMode = null;
                        }
                    }

                    var preferredMode = savedMode || configuredMode;
                    var finalMode = preferredMode;

                    if (preferredMode === 'system') {
                        finalMode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    }

                    root.setAttribute('data-authkit-mode-preference', preferredMode);
                    root.setAttribute('data-authkit-mode-resolved', finalMode);
                    root.setAttribute('data-authkit-mode', finalMode);
                } catch (e) {
                    // Preserve rendering if mode preboot fails.
                }
            })();
        </script>
    @endif

    <script>
        window.AuthKit = window.AuthKit || {};
        window.AuthKit.config = @json($browserConfig);
    </script>

    @foreach ($baseCss as $path)
        <link rel="stylesheet" href="{{ asset($basePath . '/' . ltrim($path, '/')) }}">
    @endforeach

    @if ($loadStylesheet)
        <link rel="stylesheet" href="{{ asset($basePath . '/css/themes/' . $themeFile) }}">
    @endif

    @foreach ($extraCss as $path)
        <link rel="stylesheet" href="{{ asset($basePath . '/' . ltrim($path, '/')) }}">
    @endforeach

    {{ $head ?? '' }}

    @foreach ($baseJs as $path)
        <script src="{{ asset($basePath . '/' . ltrim($path, '/')) }}" defer></script>
    @endforeach

    @foreach ($extraJs as $path)
        <script src="{{ asset($basePath . '/' . ltrim($path, '/')) }}" defer></script>
    @endforeach
</head>
<body class="authkit-body authkit-app-body">
<x-dynamic-component
        :component="$appShellComponent"
        :page-key="$resolvedPageKey"
        :page-config="$pageConfig"
        :heading="$resolvedHeading"
        :title="$resolvedPageTitle"
        :toggle-enabled="$toggleEnabled"
        :theme-toggle-component="$themeToggleComponent"
>
    {{ $slot }}
</x-dynamic-component>
</body>
</html>