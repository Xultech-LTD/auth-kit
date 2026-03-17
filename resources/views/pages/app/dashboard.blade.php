{{--
/**
 * Page: App Dashboard
 *
 * Authenticated AuthKit dashboard page.
 *
 * Responsibilities:
 * - Render the authenticated application layout.
 * - Pass page metadata into the shared app shell.
 * - Display a clean account overview surface for the signed-in user.
 * - Show lightweight summary cards and quick navigation actions.
 *
 * Expected data:
 * - $title: Visible/browser page title.
 * - $heading: Supporting page heading text.
 * - $pageKey: Current runtime page key.
 * - $currentPage: Current authenticated navigation key.
 * - $page: Optional resolved page configuration.
 *
 * Notes:
 * - This page intentionally stays presentation-focused.
 * - Business logic should remain in controllers/services.
 * - Additional dashboard widgets can be appended later without
 *   restructuring the entire page.
 */
--}}
@php
    $guard = (string) config('authkit.auth.guard', 'web');
    $user = auth($guard)->user();

    $displayName = 'Authenticated User';

    if ($user !== null) {
        if (is_string(data_get($user, 'name')) && trim((string) data_get($user, 'name')) !== '') {
            $displayName = trim((string) data_get($user, 'name'));
        } elseif (is_string(data_get($user, 'username')) && trim((string) data_get($user, 'username')) !== '') {
            $displayName = trim((string) data_get($user, 'username'));
        } elseif (is_string(data_get($user, 'email')) && trim((string) data_get($user, 'email')) !== '') {
            $displayName = trim((string) data_get($user, 'email'));
        }
    }

    $email = is_string(data_get($user, 'email')) ? trim((string) data_get($user, 'email')) : '';

    $webRouteNames = (array) config('authkit.route_names.web', []);
    $apiRouteNames = (array) config('authkit.route_names.api', []);

    $settingsRoute = (string) ($webRouteNames['settings'] ?? 'authkit.web.settings');
    $securityRoute = (string) ($webRouteNames['security'] ?? 'authkit.web.settings.security');
    $sessionsRoute = (string) ($webRouteNames['sessions'] ?? 'authkit.web.settings.sessions');
    $logoutRoute = (string) ($apiRouteNames['logout'] ?? 'authkit.api.auth.logout');

    $hasSettingsRoute = $settingsRoute !== '' && \Illuminate\Support\Facades\Route::has($settingsRoute);
    $hasSecurityRoute = $securityRoute !== '' && \Illuminate\Support\Facades\Route::has($securityRoute);
    $hasSessionsRoute = $sessionsRoute !== '' && \Illuminate\Support\Facades\Route::has($sessionsRoute);
    $hasLogoutRoute = $logoutRoute !== '' && \Illuminate\Support\Facades\Route::has($logoutRoute);

    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttribute = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

    $twoFactorEnabled = false;

    if ($user !== null) {
        $twoFactorEnabled = filled(data_get($user, 'two_factor_confirmed_at'))
            || filled(data_get($user, 'two_factor_enabled_at'))
            || (bool) data_get($user, 'two_factor_enabled', false);
    }

    $emailVerified = false;

    if ($user !== null && method_exists($user, 'hasVerifiedEmail')) {
        $emailVerified = (bool) $user->hasVerifiedEmail();
    } elseif ($user !== null) {
        $emailVerified = filled(data_get($user, 'email_verified_at'));
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
            <div class="authkit-dashboard__hero-copy">
                <div class="authkit-dashboard__eyebrow">
                    Welcome back
                </div>

                <h2 class="authkit-dashboard__hero-title">
                    {{ $displayName }}
                </h2>

                <p class="authkit-dashboard__hero-text">
                    This is your account workspace. Manage your profile, security settings,
                    sessions, and authentication preferences from one place.
                </p>
            </div>

            <div class="authkit-dashboard__hero-meta">
                <div class="authkit-dashboard__hero-chip">
                    <span class="authkit-dashboard__hero-chip-label">Email</span>
                    <span class="authkit-dashboard__hero-chip-value">
                        {{ $email !== '' ? $email : 'No email available' }}
                    </span>
                </div>
            </div>
        </section>

        <section class="authkit-dashboard__grid">
            <article class="authkit-dashboard-card">
                <div class="authkit-dashboard-card__header">
                    <div>
                        <h3 class="authkit-dashboard-card__title">Account status</h3>
                        <p class="authkit-dashboard-card__text">
                            A quick summary of the current account setup.
                        </p>
                    </div>
                </div>

                <div class="authkit-dashboard-card__stack">
                    <div class="authkit-dashboard-stat">
                        <div class="authkit-dashboard-stat__label">Email verification</div>
                        <div class="authkit-dashboard-stat__value">
                            {{ $emailVerified ? 'Verified' : 'Pending' }}
                        </div>
                    </div>

                    <div class="authkit-dashboard-stat">
                        <div class="authkit-dashboard-stat__label">Two-factor authentication</div>
                        <div class="authkit-dashboard-stat__value">
                            {{ $twoFactorEnabled ? 'Enabled' : 'Not enabled' }}
                        </div>
                    </div>

                    <div class="authkit-dashboard-stat">
                        <div class="authkit-dashboard-stat__label">Signed-in identity</div>
                        <div class="authkit-dashboard-stat__value">
                            {{ $displayName }}
                        </div>
                    </div>
                </div>
            </article>

            <article class="authkit-dashboard-card">
                <div class="authkit-dashboard-card__header">
                    <div>
                        <h3 class="authkit-dashboard-card__title">Security</h3>
                        <p class="authkit-dashboard-card__text">
                            Review the core security controls available for your account.
                        </p>
                    </div>
                </div>

                <div class="authkit-dashboard-card__stack">
                    <div class="authkit-dashboard-feature">
                        <div class="authkit-dashboard-feature__title">Password and credentials</div>
                        <div class="authkit-dashboard-feature__text">
                            Keep your account protected with strong credentials and updated access settings.
                        </div>
                    </div>

                    <div class="authkit-dashboard-feature">
                        <div class="authkit-dashboard-feature__title">Two-factor protection</div>
                        <div class="authkit-dashboard-feature__text">
                            Add an extra layer of account security for sign-in verification.
                        </div>
                    </div>

                    <div class="authkit-dashboard-feature">
                        <div class="authkit-dashboard-feature__title">Session management</div>
                        <div class="authkit-dashboard-feature__text">
                            Review and manage active authenticated sessions across devices.
                        </div>
                    </div>
                </div>
            </article>

            <article class="authkit-dashboard-card authkit-dashboard-card--wide">
                <div class="authkit-dashboard-card__header">
                    <div>
                        <h3 class="authkit-dashboard-card__title">Quick actions</h3>
                        <p class="authkit-dashboard-card__text">
                            Jump directly into the most common account tasks.
                        </p>
                    </div>
                </div>

                <div class="authkit-dashboard-actions">
                    @if ($hasSettingsRoute)
                        <a href="{{ route($settingsRoute) }}" class="authkit-dashboard-action">
                            <span class="authkit-dashboard-action__title">Open settings</span>
                            <span class="authkit-dashboard-action__text">
                                Update your account preferences and profile-related options.
                            </span>
                        </a>
                    @endif

                    @if ($hasSecurityRoute)
                        <a href="{{ route($securityRoute) }}" class="authkit-dashboard-action">
                            <span class="authkit-dashboard-action__title">Review security</span>
                            <span class="authkit-dashboard-action__text">
                                Manage password, two-factor authentication, and security settings.
                            </span>
                        </a>
                    @endif

                    @if ($hasSessionsRoute)
                        <a href="{{ route($sessionsRoute) }}" class="authkit-dashboard-action">
                            <span class="authkit-dashboard-action__title">Manage sessions</span>
                            <span class="authkit-dashboard-action__text">
                                Inspect active devices and sign out old or unused sessions.
                            </span>
                        </a>
                    @endif
                </div>
            </article>

            @if ($hasLogoutRoute)
                <article class="authkit-dashboard-card">
                    <div class="authkit-dashboard-card__header">
                        <div>
                            <h3 class="authkit-dashboard-card__title">Sign out</h3>
                            <p class="authkit-dashboard-card__text">
                                End the current authenticated session from this browser.
                            </p>
                        </div>
                    </div>

                    <form
                            method="POST"
                            action="{{ route($logoutRoute) }}"
                            class="authkit-dashboard__logout-form"
                    @if($isAjax) {{ $ajaxAttribute }}="true" @endif
                    >
                    @csrf

                    <x-authkit::button type="submit" variant="ghost">
                        Logout
                    </x-authkit::button>
                    </form>
                </article>
            @endif
        </section>
    </div>
</x-authkit::app.layout>