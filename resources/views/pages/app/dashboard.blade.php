{{--
/**
 * Page: Dashboard
 *
 * Authenticated AuthKit dashboard page.
 *
 * Responsibilities:
 * - Render the authenticated application layout.
 * - Present a minimal but polished account overview entry page.
 * - Offer clear next-step links into settings, security, sessions,
 *   and two-factor management.
 *
 * Expected data:
 * - $title: Browser/page title.
 * - $heading: Page heading text.
 * - $pageKey: Runtime page key.
 * - $currentPage: Current authenticated page key.
 */
--}}

@php
    $webNames = (array) config('authkit.route_names.web', []);

    $settingsRoute = (string) ($webNames['settings'] ?? 'authkit.web.settings');
    $securityRoute = (string) ($webNames['security'] ?? 'authkit.web.settings.security');
    $sessionsRoute = (string) ($webNames['sessions'] ?? 'authkit.web.settings.sessions');
    $twoFactorRoute = (string) ($webNames['two_factor_settings'] ?? 'authkit.web.settings.two_factor');

    $guard = (string) config('authkit.auth.guard', 'web');
    $user = auth($guard)->user();

    $displayName = 'there';

    if ($user !== null) {
        if (is_string(data_get($user, 'name')) && trim((string) data_get($user, 'name')) !== '') {
            $displayName = trim((string) data_get($user, 'name'));
        } elseif (is_string(data_get($user, 'email')) && trim((string) data_get($user, 'email')) !== '') {
            $displayName = trim((string) data_get($user, 'email'));
        }
    }
@endphp

<x-authkit::app.layout
        :title="$title ?? 'Dashboard'"
        :page-key="$pageKey ?? 'dashboard_web'"
        :current-page="$currentPage ?? 'dashboard_web'"
        :page-title="$title ?? 'Dashboard'"
        :page-heading="$heading ?? 'Account overview'"
>
    <div class="authkit-dashboard">
        <section class="authkit-dashboard__hero">
            <x-authkit::card>
                <div class="authkit-dashboard__hero-inner">
                    <div class="authkit-dashboard__eyebrow">
                        AuthKit Dashboard
                    </div>

                    <h2 class="authkit-dashboard__title">
                        Welcome, {{ $displayName }}
                    </h2>

                    <p class="authkit-dashboard__text">
                        Your account area is ready. From here, you can review your settings,
                        strengthen security, manage active sessions, and prepare two-factor
                        authentication for your application.
                    </p>

                    <div class="authkit-dashboard__actions">
                        @if (\Illuminate\Support\Facades\Route::has($securityRoute))
                            <x-authkit::link :href="route($securityRoute)">
                                Go to security
                            </x-authkit::link>
                        @endif

                        @if (\Illuminate\Support\Facades\Route::has($settingsRoute))
                            <x-authkit::link :href="route($settingsRoute)" variant="muted">
                                Open settings
                            </x-authkit::link>
                        @endif
                    </div>
                </div>
            </x-authkit::card>
        </section>

        <section class="authkit-dashboard__grid">
            @if (\Illuminate\Support\Facades\Route::has($settingsRoute))
                <x-authkit::card class="authkit-dashboard__panel">
                    <div class="authkit-dashboard__panel-title">Settings</div>
                    <p class="authkit-dashboard__panel-text">
                        Update general account preferences and manage profile-level options.
                    </p>

                    <div class="authkit-dashboard__panel-action">
                        <x-authkit::link :href="route($settingsRoute)">
                            Open settings
                        </x-authkit::link>
                    </div>
                </x-authkit::card>
            @endif

            @if (\Illuminate\Support\Facades\Route::has($securityRoute))
                <x-authkit::card class="authkit-dashboard__panel">
                    <div class="authkit-dashboard__panel-title">Security</div>
                    <p class="authkit-dashboard__panel-text">
                        Review password, security actions, and account-protection options.
                    </p>

                    <div class="authkit-dashboard__panel-action">
                        <x-authkit::link :href="route($securityRoute)">
                            Review security
                        </x-authkit::link>
                    </div>
                </x-authkit::card>
            @endif

            @if (\Illuminate\Support\Facades\Route::has($sessionsRoute))
                <x-authkit::card class="authkit-dashboard__panel">
                    <div class="authkit-dashboard__panel-title">Sessions</div>
                    <p class="authkit-dashboard__panel-text">
                        Inspect your active sessions and sign out from other devices when needed.
                    </p>

                    <div class="authkit-dashboard__panel-action">
                        <x-authkit::link :href="route($sessionsRoute)">
                            View sessions
                        </x-authkit::link>
                    </div>
                </x-authkit::card>
            @endif

            @if (\Illuminate\Support\Facades\Route::has($twoFactorRoute))
                <x-authkit::card class="authkit-dashboard__panel">
                    <div class="authkit-dashboard__panel-title">Two-factor authentication</div>
                    <p class="authkit-dashboard__panel-text">
                        Access the two-factor page when you are ready to manage extra account protection.
                    </p>

                    <div class="authkit-dashboard__panel-action">
                        <x-authkit::link :href="route($twoFactorRoute)">
                            Open two-factor
                        </x-authkit::link>
                    </div>
                </x-authkit::card>
            @endif
        </section>
    </div>
</x-authkit::app.layout>