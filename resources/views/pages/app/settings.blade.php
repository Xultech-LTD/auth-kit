{{--
/**
 * Page: App Settings
 *
 * Authenticated AuthKit settings overview page.
 *
 * Responsibilities:
 * - Render the authenticated app layout.
 * - Present a settings overview for common account-management areas.
 * - Reuse the packaged settings section component.
 * - Link users to available account/security/session destinations.
 *
 * Expected data:
 * - $title
 * - $heading
 * - $pageKey
 * - $currentPage
 * - $pages
 */
--}}

@php
    $pages = is_array($pages ?? null) ? $pages : [];

    $securityPage = (array) ($pages['security'] ?? []);
    $sessionsPage = (array) ($pages['sessions'] ?? []);
    $twoFactorPage = (array) ($pages['two_factor_settings'] ?? []);

    $securityEnabled = (bool) ($securityPage['enabled'] ?? false);
    $sessionsEnabled = (bool) ($sessionsPage['enabled'] ?? false);
    $twoFactorEnabled = (bool) ($twoFactorPage['enabled'] ?? false);

    $securityRoute = (string) ($securityPage['route'] ?? '');
    $sessionsRoute = (string) ($sessionsPage['route'] ?? '');
    $twoFactorRoute = (string) ($twoFactorPage['route'] ?? '');

    $securityHref = ($securityRoute !== '' && \Illuminate\Support\Facades\Route::has($securityRoute))
        ? route($securityRoute)
        : '#';

    $sessionsHref = ($sessionsRoute !== '' && \Illuminate\Support\Facades\Route::has($sessionsRoute))
        ? route($sessionsRoute)
        : '#';

    $twoFactorHref = ($twoFactorRoute !== '' && \Illuminate\Support\Facades\Route::has($twoFactorRoute))
        ? route($twoFactorRoute)
        : '#';
@endphp

<x-authkit::app.layout
        :title="$title ?? 'Settings'"
        :page-key="$pageKey ?? 'settings'"
        :current-page="$currentPage ?? 'settings'"
        :page-title="$title ?? 'Settings'"
        :page-heading="$heading ?? 'Account settings'"
>
    <div class="authkit-dashboard">
        <section class="authkit-dashboard__hero">
            <div class="authkit-dashboard__hero-copy">
                <div class="authkit-dashboard__eyebrow">
                    Account center
                </div>

                <h2 class="authkit-dashboard__hero-title">
                    Settings overview
                </h2>

                <p class="authkit-dashboard__hero-text">
                    Manage your account preferences, security tools, and active session controls
                    from one place.
                </p>
            </div>

            <div class="authkit-dashboard__hero-meta">
                <div class="authkit-dashboard__hero-chip">
                    <span class="authkit-dashboard__hero-chip-label">Available areas</span>
                    <span class="authkit-dashboard__hero-chip-value">
                        {{ collect([
                            $securityEnabled,
                            $sessionsEnabled,
                            $twoFactorEnabled,
                        ])->filter()->count() }}
                    </span>
                </div>
            </div>
        </section>

        <div class="authkit-dashboard__stack">
            <x-authkit::app.settings.section
                    title="Account management"
                    description="Choose a settings area to manage your account, review sign-in activity, and strengthen account protection."
                    eyebrow="Overview"
            >
                @if ($securityEnabled || $sessionsEnabled || $twoFactorEnabled)
                    <div class="authkit-settings-links">
                        @if ($securityEnabled)
                            <a href="{{ $securityHref }}" class="authkit-settings-links__item">
                                <div class="authkit-settings-links__title">
                                    {{ (string) ($securityPage['title'] ?? 'Security') }}
                                </div>

                                <div class="authkit-settings-links__text">
                                    Update your password, review protection settings, and manage security-related actions.
                                </div>
                            </a>
                        @endif

                        @if ($sessionsEnabled)
                            <a href="{{ $sessionsHref }}" class="authkit-settings-links__item">
                                <div class="authkit-settings-links__title">
                                    {{ (string) ($sessionsPage['title'] ?? 'Sessions') }}
                                </div>

                                <div class="authkit-settings-links__text">
                                    Review active sessions across browsers and devices connected to your account.
                                </div>
                            </a>
                        @endif

                        @if ($twoFactorEnabled)
                            <a href="{{ $twoFactorHref }}" class="authkit-settings-links__item">
                                <div class="authkit-settings-links__title">
                                    {{ (string) ($twoFactorPage['title'] ?? 'Two-factor authentication') }}
                                </div>

                                <div class="authkit-settings-links__text">
                                    Enable, confirm, or manage your two-factor authentication experience.
                                </div>
                            </a>
                        @endif
                    </div>
                @else
                    <div class="authkit-empty-state">
                        <div class="authkit-empty-state__title">
                            No settings areas are currently enabled
                        </div>

                        <p class="authkit-empty-state__text">
                            Enable one or more authenticated app pages in your AuthKit configuration
                            to display settings destinations here.
                        </p>
                    </div>
                @endif
            </x-authkit::app.settings.section>

            <x-authkit::app.settings.section
                    title="What you can manage here"
                    description="AuthKit keeps authenticated account-management pages organized into focused destinations."
                    eyebrow="Guide"
            >
                <div class="authkit-settings-links">
                    <div class="authkit-settings-links__item">
                        <div class="authkit-settings-links__title">
                            Security controls
                        </div>

                        <div class="authkit-settings-links__text">
                            Password management, two-factor authentication, and other protection-related account tools.
                        </div>
                    </div>

                    <div class="authkit-settings-links__item">
                        <div class="authkit-settings-links__title">
                            Session visibility
                        </div>

                        <div class="authkit-settings-links__text">
                            Inspect where your account is signed in and review tracked device activity.
                        </div>
                    </div>
                </div>
            </x-authkit::app.settings.section>
        </div>
    </div>
</x-authkit::app.layout>