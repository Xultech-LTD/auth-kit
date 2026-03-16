{{--
/**
 * Page: Settings
 *
 * Authenticated AuthKit settings page.
 *
 * Responsibilities:
 * - Resolves the configured AuthKit app page metadata for the settings page.
 * - Renders inside the configured authenticated app layout.
 * - Displays packaged account settings entry points using shared components.
 *
 * Notes:
 * - This is the packaged default settings page.
 * - Consumers may replace this page entirely by changing authkit.app.pages.settings.view.
 */
--}}

@php
    $c = (array) config('authkit.components', []);
    $appPages = (array) config('authkit.app.pages', []);
    $appLayouts = (array) config('authkit.app.layouts', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $page = is_array($pageConfig ?? null)
        ? $pageConfig
        : (array) ($appPages['settings'] ?? []);

    $pageKey = is_string($pageKey ?? null) && $pageKey !== ''
        ? $pageKey
        : 'settings';

    $layoutKey = (string) data_get($page, 'layout', 'default');
    $layoutComponent = (string) data_get($appLayouts, $layoutKey, 'authkit::app.layout');

    $containerComponent = (string) data_get($c, 'container', 'authkit::container');
    $cardComponent = (string) data_get($c, 'card', 'authkit::card');
    $linkComponent = (string) data_get($c, 'link', 'authkit::link');
    $alertComponent = (string) data_get($c, 'alert', 'authkit::alert');
    $settingsSectionComponent = (string) data_get($c, 'settings_section', 'authkit::app.settings.section');
    $dividerComponent = (string) data_get($c, 'divider', 'authkit::divider');

    $title = (string) data_get($page, 'title', 'Settings');
    $heading = (string) data_get($page, 'heading', 'Account settings');

    $securityRouteName = (string) ($webNames['security'] ?? 'authkit.web.settings.security');
    $sessionsRouteName = (string) ($webNames['sessions'] ?? 'authkit.web.settings.sessions');
    $twoFactorRouteName = (string) ($webNames['two_factor_settings'] ?? 'authkit.web.settings.two_factor');

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

            <x-dynamic-component :component="$alertComponent" variant="info">
                Manage your account preferences and review the available account areas.
            </x-dynamic-component>

            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Profile"
                    description="Basic account identity and general account information."
            >
                <x-dynamic-component :component="$cardComponent" class="authkit-settings-card">
                    <div class="authkit-settings-card__content">
                        <div class="authkit-settings-card__copy">
                            <h3 class="authkit-settings-card__title">Account profile</h3>
                            <p class="authkit-settings-card__text">
                                Signed in as <strong>{{ $userName }}</strong>@if($userEmail !== '') ({{ $userEmail }})@endif.
                            </p>
                        </div>
                    </div>
                </x-dynamic-component>
            </x-dynamic-component>

            <x-dynamic-component :component="$dividerComponent" />

            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Manage account"
                    description="Open the main areas related to your account."
            >
                <div class="authkit-dashboard-grid">
                    <x-dynamic-component :component="$cardComponent" class="authkit-dashboard-action-card">
                        <h3 class="authkit-dashboard-action-card__title">Security</h3>
                        <p class="authkit-dashboard-action-card__text">
                            Review password protection and security-related account settings.
                        </p>

                        <div class="authkit-dashboard-action-card__actions">
                            <x-dynamic-component
                                    :component="$linkComponent"
                                    :href="$securityUrl"
                                    variant="primary"
                            >
                                Open security
                            </x-dynamic-component>
                        </div>
                    </x-dynamic-component>

                    <x-dynamic-component :component="$cardComponent" class="authkit-dashboard-action-card">
                        <h3 class="authkit-dashboard-action-card__title">Two-factor authentication</h3>
                        <p class="authkit-dashboard-action-card__text">
                            Manage your two-factor authentication setup and related access controls.
                        </p>

                        <div class="authkit-dashboard-action-card__actions">
                            <x-dynamic-component
                                    :component="$linkComponent"
                                    :href="$twoFactorUrl"
                                    variant="primary"
                            >
                                Manage two-factor
                            </x-dynamic-component>
                        </div>
                    </x-dynamic-component>

                    <x-dynamic-component :component="$cardComponent" class="authkit-dashboard-action-card">
                        <h3 class="authkit-dashboard-action-card__title">Sessions</h3>
                        <p class="authkit-dashboard-action-card__text">
                            Review active devices and session activity for your account.
                        </p>

                        <div class="authkit-dashboard-action-card__actions">
                            <x-dynamic-component
                                    :component="$linkComponent"
                                    :href="$sessionsUrl"
                                    variant="primary"
                            >
                                Review sessions
                            </x-dynamic-component>
                        </div>
                    </x-dynamic-component>
                </div>
            </x-dynamic-component>

        </div>
    </x-dynamic-component>
</x-dynamic-component>