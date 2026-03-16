{{--
/**
 * Page: Dashboard
 *
 * Authenticated dashboard page rendered inside the AuthKit application shell.
 *
 * Responsibilities:
 * - Resolves dashboard page metadata from configuration.
 * - Renders inside the configured authenticated app layout.
 * - Displays a cleaner packaged dashboard experience using shared components.
 * - Acts as the default packaged dashboard view, while allowing consumers
 *   to replace the page entirely through configuration.
 */
--}}

@php
    $c = (array) config('authkit.components', []);
    $appPages = (array) config('authkit.app.pages', []);
    $webNames = (array) config('authkit.route_names.web', []);
    $appLayouts = (array) config('authkit.app.layouts', []);

    $page = is_array($pageConfig ?? null)
        ? $pageConfig
        : (array) ($appPages['dashboard_web'] ?? []);

    $pageKey = is_string($pageKey ?? null) && $pageKey !== ''
        ? $pageKey
        : 'dashboard_web';

    $layoutKey = (string) data_get($page, 'layout', 'default');
    $layoutComponent = (string) data_get($appLayouts, $layoutKey, 'authkit::app.layout');

    $containerComponent = (string) data_get($c, 'container', 'authkit::container');
    $cardComponent = (string) data_get($c, 'card', 'authkit::card');
    $alertComponent = (string) data_get($c, 'alert', 'authkit::alert');
    $linkComponent = (string) data_get($c, 'link', 'authkit::link');
    $settingsSectionComponent = (string) data_get($c, 'settings_section', 'authkit::app.settings.section');
    $dividerComponent = (string) data_get($c, 'divider', 'authkit::divider');

    $title = (string) data_get($page, 'title', 'Dashboard');
    $heading = (string) data_get($page, 'heading', 'Account overview');

    $settingsRouteName = (string) ($webNames['settings'] ?? 'authkit.web.settings');
    $securityRouteName = (string) ($webNames['security'] ?? 'authkit.web.settings.security');
    $sessionsRouteName = (string) ($webNames['sessions'] ?? 'authkit.web.settings.sessions');
    $twoFactorRouteName = (string) ($webNames['two_factor_settings'] ?? 'authkit.web.settings.two_factor');

    $settingsUrl = \Illuminate\Support\Facades\Route::has($settingsRouteName) ? route($settingsRouteName) : '#';
    $securityUrl = \Illuminate\Support\Facades\Route::has($securityRouteName) ? route($securityRouteName) : '#';
    $sessionsUrl = \Illuminate\Support\Facades\Route::has($sessionsRouteName) ? route($sessionsRouteName) : '#';
    $twoFactorUrl = \Illuminate\Support\Facades\Route::has($twoFactorRouteName) ? route($twoFactorRouteName) : '#';

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
        ? ($twoFactorConfirmedAt ? 'Enabled and confirmed' : 'Enabled but pending confirmation')
        : 'Not enabled';
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

            <x-dynamic-component :component="$alertComponent" variant="success">
                You are signed in successfully.
                @if ($userEmail !== '')
                    Your account email is <strong>{{ $userEmail }}</strong>.
                @endif
            </x-dynamic-component>

            <x-dynamic-component :component="$cardComponent" class="authkit-dashboard-hero">
                <div class="authkit-dashboard-hero__content">
                    <div class="authkit-dashboard-hero__copy">
                        <h2 class="authkit-dashboard-hero__title">
                            Welcome back, {{ $userName }}
                        </h2>

                        <p class="authkit-dashboard-hero__text">
                            This is your AuthKit account overview. From here you can manage your
                            account settings, review security controls, and inspect active sessions.
                        </p>
                    </div>

                    <div class="authkit-dashboard-hero__actions">
                        <x-dynamic-component
                                :component="$linkComponent"
                                :href="$settingsUrl"
                                variant="primary"
                                class="authkit-dashboard-hero__link"
                        >
                            Open settings
                        </x-dynamic-component>

                        <x-dynamic-component
                                :component="$linkComponent"
                                :href="$securityUrl"
                                variant="default"
                                class="authkit-dashboard-hero__link"
                        >
                            Review security
                        </x-dynamic-component>
                    </div>
                </div>
            </x-dynamic-component>

            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Overview"
                    description="A quick summary of the packaged authenticated account area."
            >
                <div class="authkit-dashboard-grid">
                    <x-dynamic-component :component="$cardComponent" class="authkit-dashboard-summary-card">
                        <div class="authkit-dashboard-summary-card__eyebrow">Account</div>
                        <h3 class="authkit-dashboard-summary-card__title">Profile and preferences</h3>
                        <p class="authkit-dashboard-summary-card__text">
                            Manage your basic account settings and keep your details up to date.
                        </p>

                        <div class="authkit-dashboard-summary-card__actions">
                            <x-dynamic-component
                                    :component="$linkComponent"
                                    :href="$settingsUrl"
                                    variant="primary"
                            >
                                Settings
                            </x-dynamic-component>
                        </div>
                    </x-dynamic-component>

                    <x-dynamic-component :component="$cardComponent" class="authkit-dashboard-summary-card">
                        <div class="authkit-dashboard-summary-card__eyebrow">Security</div>
                        <h3 class="authkit-dashboard-summary-card__title">Protection status</h3>
                        <p class="authkit-dashboard-summary-card__text">
                            Two-factor authentication status: {{ $twoFactorStatus }}.
                        </p>

                        <div class="authkit-dashboard-summary-card__actions">
                            <x-dynamic-component
                                    :component="$linkComponent"
                                    :href="$securityUrl"
                                    variant="primary"
                            >
                                Security
                            </x-dynamic-component>
                        </div>
                    </x-dynamic-component>

                    <x-dynamic-component :component="$cardComponent" class="authkit-dashboard-summary-card">
                        <div class="authkit-dashboard-summary-card__eyebrow">Sessions</div>
                        <h3 class="authkit-dashboard-summary-card__title">Active devices</h3>
                        <p class="authkit-dashboard-summary-card__text">
                            Review the sessions currently signed in to your account and revoke access when needed.
                        </p>

                        <div class="authkit-dashboard-summary-card__actions">
                            <x-dynamic-component
                                    :component="$linkComponent"
                                    :href="$sessionsUrl"
                                    variant="primary"
                            >
                                Sessions
                            </x-dynamic-component>
                        </div>
                    </x-dynamic-component>
                </div>
            </x-dynamic-component>

        </div>
    </x-dynamic-component>
</x-dynamic-component>