{{--
/**
 * Component: Layout
 *
 * Root document layout for AuthKit pages.
 *
 * Responsibilities:
 * - Defines the HTML document shell (doctype, head, body).
 * - Resolves the active theme stylesheet.
 * - Loads AuthKit base JS (if present) for optional client behaviors.
 *
 * Styling:
 * - No inline styling is applied.
 * - Themes are loaded as CSS files from the published assets directory.
 * - The theme file is expected at: public/{assets.base_path}/themes/{theme}.css
 *
 * Slots:
 * - $slot: Page body content.
 * - $head: Optional additional head content (meta tags, extra CSS, etc.).
 *
 * Props:
 * - title: Page title.
 * - theme: Optional theme key to override the configured default theme.
 */
--}}

@props([
    'title' => 'AuthKit',
    'theme' => null,
])

{{-- Theme & Asset Resolution --}}
@php
    $base = (string) config('authkit.assets.base_path', 'vendor/authkit');
    $defaultTheme = (string) config('authkit.themes.default', 'forest-theme');
    $activeTheme = is_string($theme) && $theme !== '' ? $theme : $defaultTheme;
@endphp

        <!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

{{-- Document Head --}}
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>

    {{-- Theme Stylesheet --}}
    <link rel="stylesheet" href="{{ asset($base.'/themes/'.$activeTheme.'.css') }}">

    {{-- AuthKit Base Script --}}
    <script src="{{ asset($base.'/js/authkit.js') }}" defer></script>

    {{-- Optional Head Slot --}}
    {{ $head ?? '' }}
</head>

{{-- Document Body --}}
<body>
{{-- Page Content Slot --}}
{{ $slot }}
</body>
</html>