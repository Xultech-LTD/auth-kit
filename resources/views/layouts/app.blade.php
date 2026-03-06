<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'AuthKit' }}</title>

    @php
        $theme = $theme ?? config('authkit.themes.default', 'forest-theme');

        $basePath = (string) config('authkit.assets.base_path', 'vendor/authkit');
        $baseAssets = (array) config('authkit.assets.base', []);

        $baseCss = (array) data_get($baseAssets, 'css', []);
        $baseJs = (array) data_get($baseAssets, 'js', []);

        // Backward compatibility:
        // If no base JS defined, fallback to default authkit.js
        if (empty($baseJs)) {
            $baseJs = ['js/authkit.js'];
        }
    @endphp

    {{-- Base CSS (optional) --}}
    @foreach ($baseCss as $path)
        <link rel="stylesheet" href="{{ asset($basePath.'/'.ltrim($path, '/')) }}">
    @endforeach

    {{-- Theme CSS --}}
    <link rel="stylesheet" href="{{ asset($basePath.'/themes/'.$theme.'.css') }}">

    {{-- Base JS --}}
    @foreach ($baseJs as $path)
        <script src="{{ asset($basePath.'/'.ltrim($path, '/')) }}" defer></script>
    @endforeach
</head>
<body>
<main style="max-width:520px;margin:48px auto;padding:24px;">
    @yield('content')
</main>
</body>
</html>