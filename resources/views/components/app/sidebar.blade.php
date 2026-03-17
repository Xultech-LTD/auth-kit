{{--
/**
 * Component: App Sidebar
 *
 * Authenticated application sidebar for AuthKit.
 *
 * Purpose:
 * - Renders the primary authenticated navigation region.
 * - Displays product branding for the authenticated area.
 * - Resolves and renders configured sidebar navigation items.
 * - Renders a contextual footer card pinned to the lower area of the sidebar.
 *
 * Props:
 * - currentPage: Current authenticated page key.
 */
--}}

@props([
    'currentPage' => null,
])

@php
    $app = (array) config('authkit.app', []);
    $pages = (array) data_get($app, 'pages', []);
    $sidebarItems = (array) data_get($app, 'navigation.sidebar', []);
    $components = (array) config('authkit.components', []);

    $navComponent = (string) ($components['app_nav'] ?? 'authkit::app.nav');

    $brand = (array) data_get($app, 'brand', []);

    $brandTitle = (string) ($brand['title'] ?? config('app.name', 'AuthKit'));
    $brandSubtitle = (string) ($brand['subtitle'] ?? 'Application Console');
    $brandType = (string) ($brand['type'] ?? 'letter');

    $brandLetter = (string) ($brand['letter'] ?? 'AK');
    $brandImage = (string) ($brand['image'] ?? '');
    $brandImageAlt = (string) ($brand['image_alt'] ?? $brandTitle);

    $showBrandSubtitle = (bool) ($brand['show_subtitle'] ?? true);

    $brandType = in_array($brandType, ['letter', 'image'], true) ? $brandType : 'letter';
    $shouldUseImage = $brandType === 'image' && $brandImage !== '';

    $currentPageConfig = is_string($currentPage) && $currentPage !== ''
        ? (array) ($pages[$currentPage] ?? [])
        : [];

    $currentPageTitle = (string) ($currentPageConfig['title'] ?? '');
    $currentPageHeading = (string) ($currentPageConfig['heading'] ?? '');

    $footerLabel = 'Workspace overview';
    $footerTitle = $currentPageTitle !== '' ? $currentPageTitle : 'Account workspace';
    $footerText = $currentPageHeading !== ''
        ? $currentPageHeading
        : 'Manage your account tools, workspace settings, and security controls from here.';
@endphp

<div class="authkit-app-sidebar" id="authkit-app-sidebar">
    <div class="authkit-app-sidebar__inner">
        <div class="authkit-app-sidebar__top">
            <div class="authkit-app-sidebar__brand">
                <a href="{{ url('/') }}" class="authkit-app-sidebar__brand-link" aria-label="{{ $brandTitle }}">
                    <div class="authkit-app-sidebar__brand-mark" aria-hidden="true">
                        @if ($shouldUseImage)
                            <img
                                    src="{{ asset($brandImage) }}"
                                    alt="{{ $brandImageAlt }}"
                                    class="authkit-app-sidebar__brand-image"
                            >
                        @else
                            <span class="authkit-app-sidebar__brand-letter">
                                {{ $brandLetter }}
                            </span>
                        @endif
                    </div>

                    <div class="authkit-app-sidebar__brand-copy">
                        <div class="authkit-app-sidebar__brand-title">
                            {{ $brandTitle }}
                        </div>

                        @if ($showBrandSubtitle && $brandSubtitle !== '')
                            <div class="authkit-app-sidebar__brand-subtitle">
                                {{ $brandSubtitle }}
                            </div>
                        @endif
                    </div>
                </a>
            </div>
        </div>

        <div class="authkit-app-sidebar__middle">
            <div class="authkit-app-sidebar__nav-scroll">
                <x-dynamic-component
                        :component="$navComponent"
                        :items="$sidebarItems"
                        :pages="$pages"
                        :current-page="$currentPage"
                />
            </div>
        </div>

        <div class="authkit-app-sidebar__bottom">
            <div class="authkit-app-sidebar__context">
                <div class="authkit-app-sidebar__context-label">
                    {{ $footerLabel }}
                </div>

                <div class="authkit-app-sidebar__context-title">
                    {{ $footerTitle }}
                </div>

                <div class="authkit-app-sidebar__context-text">
                    {{ $footerText }}
                </div>
            </div>
        </div>
    </div>
</div>