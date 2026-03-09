{{--
/**
 * Component: Layout
 *
 * Root document layout for AuthKit pages.
 *
 * Responsibilities:
 * - Defines the HTML document shell (doctype, head, body).
 * - Resolves the active UI engine, theme, and appearance mode.
 * - Loads packaged AuthKit theme assets when enabled.
 * - Loads optional extension CSS/JS declared in configuration.
 * - Exposes stable root hooks for package CSS, JavaScript, and consumer overrides.
 * - Renders the packaged theme toggle by default when enabled.
 *
 * Styling model:
 * - AuthKit uses semantic package classes in Blade components.
 * - Visual appearance is selected through:
 *   - ui.engine : styling family (for example: tailwind or bootstrap)
 *   - ui.theme  : color/brand skin within that family
 *   - ui.mode   : light, dark, or system
 * - Theme stylesheets are resolved using the configured flat filename pattern:
 *   {engine}-{theme}.css
 *
 * Data attributes:
 * - When enabled, the layout emits:
 *   - data-authkit-engine
 *   - data-authkit-theme
 *   - data-authkit-mode
 *
 * Notes:
 * - When ui.mode is "system", JavaScript may replace the initial fallback mode
 *   at runtime using prefers-color-scheme and persisted user preference.
 * - Consumers may disable packaged asset loading and provide their own styles/scripts.
 * - Consumers may publish this view to reposition or remove the packaged toggle.
 *
 * Slots:
 * - $slot : Page body content.
 * - $head : Optional additional head content (meta tags, extra links, inline styles, etc.).
 *
 * Props:
 * - title  : Document title.
 * - theme  : Optional theme override.
 * - engine : Optional UI engine override.
 * - mode   : Optional appearance mode override.
 */
--}}

@props([
    'title' => 'AuthKit',
    'theme' => null,
    'engine' => null,
    'mode' => null,
])

@php
    /**
     * Asset base path.
     */
    $basePath = (string) config('authkit.assets.base_path', 'vendor/authkit');

    /**
     * UI configuration.
     */
    $ui = (array) config('authkit.ui', []);
    $themes = (array) config('authkit.themes', []);
    $components = (array) config('authkit.components', []);

    /**
     * Resolve UI state with prop overrides taking precedence over config.
     */
    $resolvedEngine = is_string($engine) && $engine !== ''
        ? $engine
        : (string) data_get($ui, 'engine', 'tailwind');

    $resolvedTheme = is_string($theme) && $theme !== ''
        ? $theme
        : (string) data_get($ui, 'theme', 'forest');

    $resolvedMode = is_string($mode) && $mode !== ''
        ? $mode
        : (string) data_get($ui, 'mode', 'system');

    /**
     * Theme filename resolution.
     *
     * Flat naming convention example:
     * - tailwind-forest.css
     * - bootstrap-red-beige.css
     */
    $themeFilePattern = (string) data_get($themes, 'file_pattern', '{engine}-{theme}.css');
    $themeFile = strtr($themeFilePattern, [
        '{engine}' => $resolvedEngine,
        '{theme}' => $resolvedTheme,
    ]);

    /**
     * Asset loading toggles.
     */
    $loadStylesheet = (bool) data_get($ui, 'load_stylesheet', true);
    $loadScript = (bool) data_get($ui, 'load_script', true);

    /**
     * Data-attribute root hooks.
     */
    $useDataAttributes = (bool) data_get($ui, 'use_data_attributes', true);
    $enableRootHooks = (bool) data_get($ui, 'extensions.enable_root_hooks', true);

    /**
     * Theme toggle configuration.
     */
    $toggleEnabled = (bool) data_get($ui, 'toggle.enabled', true);
    $themeToggleComponent = (string) data_get($components, 'theme_toggle', 'authkit::theme-toggle');

    /**
     * Extension assets loaded after packaged AuthKit assets.
     */
    $extraCss = array_values(array_filter((array) data_get($ui, 'extensions.extra_css', []), fn ($path) => is_string($path) && $path !== ''));
    $extraJs = array_values(array_filter((array) data_get($ui, 'extensions.extra_js', []), fn ($path) => is_string($path) && $path !== ''));

    /**
     * Base assets.
     *
     * These are optional generic AuthKit assets declared under authkit.assets.base.
     * They remain separate from packaged theme CSS resolution.
     */
    $baseAssets = (array) config('authkit.assets.base', []);
    $baseCss = array_values(array_filter((array) data_get($baseAssets, 'css', []), fn ($path) => is_string($path) && $path !== ''));
    $baseJs = array_values(array_filter((array) data_get($baseAssets, 'js', []), fn ($path) => is_string($path) && $path !== ''));

    /**
     * Backward-compatibility fallback for base JS.
     *
     * If no base JS is configured and packaged script loading is enabled,
     * AuthKit falls back to the default base client script.
     */
    if ($loadScript && empty($baseJs)) {
        $baseJs = ['js/authkit.js'];
    }

    /**
     * Root attributes for the <html> element.
     *
     * When mode="system", emit "system" as the declared preference.
     * JavaScript may later resolve the active runtime mode.
     */
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

    /**
     * UI persistence settings exposed to JavaScript.
     */
    $storageEnabled = (bool) data_get($ui, 'persistence.enabled', true);
    $storageKey = (string) data_get($ui, 'persistence.storage_key', 'authkit.ui.mode');
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
                    // Fail silently to preserve page rendering.
                }
            })();
        </script>
    @endif

    @foreach ($baseCss as $path)
        <link rel="stylesheet" href="{{ asset($basePath.'/'.ltrim($path, '/')) }}">
    @endforeach

    @if ($loadStylesheet)
        <link rel="stylesheet" href="{{ asset($basePath.'/themes/'.$themeFile) }}">
    @endif

    @foreach ($extraCss as $path)
        <link rel="stylesheet" href="{{ asset($basePath.'/'.ltrim($path, '/')) }}">
    @endforeach

    {{ $head ?? '' }}

    @foreach ($baseJs as $path)
        <script src="{{ asset($basePath.'/'.ltrim($path, '/')) }}" defer></script>
    @endforeach

    @foreach ($extraJs as $path)
        <script src="{{ asset($basePath.'/'.ltrim($path, '/')) }}" defer></script>
    @endforeach
</head>
<body class="authkit-body">
@if ($toggleEnabled)
    <div class="authkit-layout-toggle" data-authkit-layout-toggle="1">
        <x-dynamic-component
                :component="$themeToggleComponent"
                variant="icon"
        />
    </div>
@endif

{{ $slot }}
</body>
</html>