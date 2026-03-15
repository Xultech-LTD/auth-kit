{{--
/**
 * Component: Layout
 *
 * Root document layout for AuthKit pages.
 *
 * Responsibilities:
 * - Defines the HTML document shell (doctype, head, body).
 * - Resolves the active UI engine, theme, and appearance mode.
 * - Loads published AuthKit built assets.
 * - Loads optional extension CSS/JS declared in configuration.
 * - Exposes stable root hooks for package CSS, JavaScript, and consumer overrides.
 * - Exposes AuthKit browser runtime configuration on window.AuthKit.config.
 * - Renders the packaged theme toggle by default when enabled.
 */
--}}

@props([
    'title' => 'AuthKit',
    'theme' => null,
    'engine' => null,
    'mode' => null,
])

@php
    $basePath = trim((string) config('authkit.assets.base_path', 'vendor/authkit'), '/');

    $ui = (array) config('authkit.ui', []);
    $themes = (array) config('authkit.themes', []);
    $components = (array) config('authkit.components', []);
    $javascript = (array) config('authkit.javascript', []);
    $forms = (array) config('authkit.forms', []);

    $resolvedEngine = is_string($engine) && $engine !== ''
        ? $engine
        : (string) data_get($ui, 'engine', 'tailwind');

    $resolvedTheme = is_string($theme) && $theme !== ''
        ? $theme
        : (string) data_get($ui, 'theme', 'forest');

    $resolvedMode = is_string($mode) && $mode !== ''
        ? $mode
        : (string) data_get($ui, 'mode', 'system');

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

    /**
     * Default built asset fallbacks.
     *
     * These are the compiled assets expected to exist after package build + publish.
     */
    if ($loadScript && $javascriptEnabled && empty($baseJs)) {
        $baseJs = ['js/authkit.js'];
    }

    $htmlAttributes = new \Illuminate\View\ComponentAttributeBag([
        'lang' => str_replace('_', '-', app()->getLocale()),
    ]);

    if ($enableRootHooks) {
        $htmlAttributes = $htmlAttributes->merge([
            'class' => 'authkit',
        ]);
    }

    if ($useDataAttributes) {
        $htmlAttributes = $htmlAttributes->merge([
            'data-authkit-engine' => $resolvedEngine,
            'data-authkit-theme' => $resolvedTheme,
            'data-authkit-mode' => $resolvedMode,
        ]);
    }

    $storageEnabled = (bool) data_get($ui, 'persistence.enabled', true);
    $storageKey = (string) data_get($ui, 'persistence.storage_key', 'authkit.ui.mode');

    /**
     * Browser config payload normalized to the shape expected by AuthKit JS.
     */
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
    ];
@endphp

        <!doctype html>
<html {{ $htmlAttributes }}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }}</title>

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
<body class="authkit-body">
@if ($toggleEnabled)
    <div class="authkit-layout-toggle" data-authkit-layout-toggle="1">
        <x-dynamic-component
                :component="$themeToggleComponent"
        />
    </div>
@endif

{{ $slot }}
</body>
</html>