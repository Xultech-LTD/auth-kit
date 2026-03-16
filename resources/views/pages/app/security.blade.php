{{--
/**
 * Page: Security
 *
 * Authenticated AuthKit security page.
 *
 * Responsibilities:
 * - Resolves the configured AuthKit app page metadata for the security page.
 * - Renders inside the configured authenticated app layout.
 * - Displays a more action-oriented packaged security overview.
 * - Respects config-driven section visibility for packaged security blocks.
 *
 * Notes:
 * - This page is intentionally config-driven.
 * - Consumers may replace this page entirely by changing authkit.app.pages.security.view.
 */
--}}
@php
    $c = (array) config('authkit.components', []);
    $appPages = (array) config('authkit.app.pages', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $page = is_array($pageConfig ?? null) ? $pageConfig : (array) ($appPages['security'] ?? []);

    $pageKey = is_string($pageKey ?? null) && $pageKey !== ''
        ? $pageKey
        : 'security';

    $layoutComponent = (string) data_get(config('authkit.app.layouts', []), data_get($page, 'layout', 'default'), 'authkit::app.layout');

    $containerComponent = (string) data_get($c, 'container', 'authkit::container');
    $cardComponent = (string) data_get($c, 'card', 'authkit::card');
    $alertComponent = (string) data_get($c, 'alert', 'authkit::alert');
    $buttonComponent = (string) data_get($c, 'button', 'authkit::button');
    $linkComponent = (string) data_get($c, 'link', 'authkit::link');
    $dividerComponent = (string) data_get($c, 'divider', 'authkit::divider');
    $settingsSectionComponent = (string) data_get($c, 'settings_section', 'authkit::app.settings.section');

    $title = (string) data_get($page, 'title', 'Security');
    $heading = (string) data_get($page, 'heading', 'Security settings');

    $sections = (array) data_get($page, 'sections', []);
    $showPasswordUpdate = (bool) data_get($sections, 'password_update', true);
    $showTwoFactor = (bool) data_get($sections, 'two_factor', true);
    $showRecoveryCodes = (bool) data_get($sections, 'recovery_codes', true);

    $twoFactorRouteName = (string) ($webNames['two_factor_settings'] ?? 'authkit.web.settings.two_factor');
    $sessionsRouteName = (string) ($webNames['sessions'] ?? 'authkit.web.settings.sessions');
    $settingsRouteName = (string) ($webNames['settings'] ?? 'authkit.web.settings');
    $confirmPasswordRouteName = (string) ($webNames['confirm_password'] ?? 'authkit.web.confirm.password');

    $twoFactorUrl = \Illuminate\Support\Facades\Route::has($twoFactorRouteName) ? route($twoFactorRouteName) : '#';
    $sessionsUrl = \Illuminate\Support\Facades\Route::has($sessionsRouteName) ? route($sessionsRouteName) : '#';
    $settingsUrl = \Illuminate\Support\Facades\Route::has($settingsRouteName) ? route($settingsRouteName) : '#';
    $confirmPasswordUrl = \Illuminate\Support\Facades\Route::has($confirmPasswordRouteName) ? route($confirmPasswordRouteName) : '#';

    $guard = (string) config('authkit.auth.guard', 'web');
    $user = auth($guard)->user();

    $userName = is_object($user)
        ? (string) (data_get($user, 'name') ?: data_get($user, 'email', 'there'))
        : 'there';

    $userEmail = is_object($user)
        ? (string) data_get($user, 'email', '')
        : '';

    $twoFactorEnabledColumn = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');
    $twoFactorConfirmedAtColumn = (string) config('authkit.two_factor.columns.confirmed_at', 'two_factor_confirmed_at');

    $twoFactorEnabled = is_object($user) ? (bool) data_get($user, $twoFactorEnabledColumn, false) : false;
    $twoFactorConfirmedAt = is_object($user) ? data_get($user, $twoFactorConfirmedAtColumn) : null;

    $twoFactorStatus = $twoFactorEnabled
        ? ($twoFactorConfirmedAt ? 'Enabled and confirmed' : 'Enabled but not yet confirmed')
        : 'Not enabled';

    $hasSecurityActions = $showPasswordUpdate || $showTwoFactor || $showRecoveryCodes;
@endphp

<x-dynamic-component
        :component="$layoutComponent"
        :page-key="$pageKey"
        :page-config="$page"
        :page-title="$title"
        :heading="$heading"
>
    <x-dynamic-component :component="$containerComponent" size="lg">
        <div class="authkit-page-stack">

            <x-dynamic-component
                    :component="$alertComponent"
                    variant="info"
            >
                Signed in as <strong>{{ $userName }}</strong>@if($userEmail !== '') ({{ $userEmail }}) @endif.
                Review your account protection below and use the quick actions to manage important security settings.
            </x-dynamic-component>

            <x-dynamic-component
                    :component="$cardComponent"
                    class="authkit-security-hero"
            >
                <div class="authkit-security-hero__content">
                    <div class="authkit-security-hero__copy">
                        <h2 class="authkit-security-hero__title">
                            Protect your account
                        </h2>

                        <p class="authkit-security-hero__text">
                            Welcome, {{ $userName }}. Keep your account safe by reviewing your password,
                            enabling two-factor authentication, and checking your active sessions.
                        </p>
                    </div>

                    <div class="authkit-security-hero__actions">
                        <x-dynamic-component
                                :component="$linkComponent"
                                :href="$twoFactorUrl"
                                variant="primary"
                                class="authkit-security-hero__link"
                        >
                            Manage two-factor authentication
                        </x-dynamic-component>

                        <x-dynamic-component
                                :component="$linkComponent"
                                :href="$sessionsUrl"
                                variant="default"
                                class="authkit-security-hero__link"
                        >
                            Review active sessions
                        </x-dynamic-component>
                    </div>
                </div>
            </x-dynamic-component>

            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Security overview"
                    description="A quick summary of your current account protection state."
            >
                <div class="authkit-security-grid">
                    <x-dynamic-component :component="$cardComponent" class="authkit-security-summary-card">
                        <div class="authkit-security-summary-card__eyebrow">Password</div>
                        <h3 class="authkit-security-summary-card__title">Password protection</h3>
                        <p class="authkit-security-summary-card__text">
                            Your password is the first layer protecting access to your account and sensitive settings.
                        </p>

                        <div class="authkit-security-summary-card__actions">
                            <x-dynamic-component
                                    :component="$linkComponent"
                                    :href="$settingsUrl"
                                    variant="primary"
                            >
                                Go to settings
                            </x-dynamic-component>
                        </div>
                    </x-dynamic-component>

                    <x-dynamic-component :component="$cardComponent" class="authkit-security-summary-card">
                        <div class="authkit-security-summary-card__eyebrow">Two-factor</div>
                        <h3 class="authkit-security-summary-card__title">Two-factor authentication</h3>
                        <p class="authkit-security-summary-card__text">
                            Current status: {{ $twoFactorStatus }}.
                        </p>

                        <div class="authkit-security-summary-card__actions">
                            <x-dynamic-component
                                    :component="$linkComponent"
                                    :href="$twoFactorUrl"
                                    variant="primary"
                            >
                                Manage 2FA
                            </x-dynamic-component>
                        </div>
                    </x-dynamic-component>

                    <x-dynamic-component :component="$cardComponent" class="authkit-security-summary-card">
                        <div class="authkit-security-summary-card__eyebrow">Sessions</div>
                        <h3 class="authkit-security-summary-card__title">Session activity</h3>
                        <p class="authkit-security-summary-card__text">
                            Review active sessions and remove access you do not recognize.
                        </p>

                        <div class="authkit-security-summary-card__actions">
                            <x-dynamic-component
                                    :component="$linkComponent"
                                    :href="$sessionsUrl"
                                    variant="primary"
                            >
                                View sessions
                            </x-dynamic-component>
                        </div>
                    </x-dynamic-component>
                </div>
            </x-dynamic-component>

        </div>
    </x-dynamic-component>
</x-dynamic-component>