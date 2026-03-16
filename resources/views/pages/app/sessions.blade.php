{{--
/**
 * Page: Sessions
 *
 * Authenticated AuthKit sessions page.
 */
--}}

@php
    $c = (array) config('authkit.components', []);
    $appPages = (array) config('authkit.app.pages', []);
    $appLayouts = (array) config('authkit.app.layouts', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $page = is_array($pageConfig ?? null)
        ? $pageConfig
        : (array) ($appPages['sessions'] ?? []);

    $pageKey = is_string($pageKey ?? null) && $pageKey !== ''
        ? $pageKey
        : 'sessions';

    $layoutKey = (string) data_get($page, 'layout', 'default');
    $layoutComponent = (string) data_get($appLayouts, $layoutKey, 'authkit::app.layout');

    $containerComponent = (string) data_get($c, 'container', 'authkit::container');
    $cardComponent = (string) data_get($c, 'card', 'authkit::card');
    $linkComponent = (string) data_get($c, 'link', 'authkit::link');
    $alertComponent = (string) data_get($c, 'alert', 'authkit::alert');
    $settingsSectionComponent = (string) data_get($c, 'settings_section', 'authkit::app.settings.section');
    $sessionListComponent = (string) data_get($c, 'session_list', 'authkit::app.sessions.list');

    $title = (string) data_get($page, 'title', 'Sessions');
    $heading = (string) data_get($page, 'heading', 'Active sessions');

    $settingsRouteName = (string) ($webNames['settings'] ?? 'authkit.web.settings');
    $securityRouteName = (string) ($webNames['security'] ?? 'authkit.web.settings.security');

    $settingsUrl = \Illuminate\Support\Facades\Route::has($settingsRouteName) ? route($settingsRouteName) : '#';
    $securityUrl = \Illuminate\Support\Facades\Route::has($securityRouteName) ? route($securityRouteName) : '#';

    $guard = (string) config('authkit.auth.guard', 'web');
    $user = auth($guard)->user();

    $userName = is_object($user)
        ? (string) (data_get($user, 'name') ?: data_get($user, 'email', 'User'))
        : 'User';

    $resolvedSessions = is_iterable($sessions ?? null)
        ? collect($sessions)->values()->all()
        : [];

    $sessionCount = count($resolvedSessions);
    $currentSession = collect($resolvedSessions)->first(fn ($session) => (bool) data_get($session, 'is_current', false));
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
                Review devices and browser sessions associated with your account.
            </x-dynamic-component>

            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Session overview"
                    description="Monitor where your account is active and keep access under control."
            >
                <x-dynamic-component :component="$cardComponent" class="authkit-settings-card">
                    <div class="authkit-settings-card__content">
                        <div class="authkit-settings-card__copy">
                            <h3 class="authkit-settings-card__title">Active access</h3>
                            <p class="authkit-settings-card__text">
                                {{ $userName }}, you currently have {{ $sessionCount }} visible session{{ $sessionCount === 1 ? '' : 's' }} on record.
                            </p>

                            @if (is_array($currentSession))
                                <p class="authkit-settings-card__text">
                                    Current session:
                                    {{ (string) data_get($currentSession, 'location', 'This device') }}
                                    @if ((string) data_get($currentSession, 'browser', '') !== '')
                                        · {{ (string) data_get($currentSession, 'browser') }}
                                    @endif
                                    @if ((string) data_get($currentSession, 'platform', '') !== '')
                                        · {{ (string) data_get($currentSession, 'platform') }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                </x-dynamic-component>
            </x-dynamic-component>

            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Current and recent sessions"
                    description="Real session activity associated with your account."
            >
                <x-dynamic-component
                        :component="$sessionListComponent"
                        :sessions="$resolvedSessions"
                />
            </x-dynamic-component>

            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Related account areas"
                    description="Open other account sections related to access and account protection."
            >
                <div class="authkit-dashboard-grid">
                    <x-dynamic-component :component="$cardComponent" class="authkit-dashboard-action-card">
                        <h3 class="authkit-dashboard-action-card__title">Settings</h3>
                        <p class="authkit-dashboard-action-card__text">
                            Return to your main account settings overview.
                        </p>

                        <div class="authkit-dashboard-action-card__actions">
                            <x-dynamic-component
                                    :component="$linkComponent"
                                    :href="$settingsUrl"
                                    variant="primary"
                            >
                                Open settings
                            </x-dynamic-component>
                        </div>
                    </x-dynamic-component>

                    <x-dynamic-component :component="$cardComponent" class="authkit-dashboard-action-card">
                        <h3 class="authkit-dashboard-action-card__title">Security</h3>
                        <p class="authkit-dashboard-action-card__text">
                            Review password protection and two-factor security controls.
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
                </div>
            </x-dynamic-component>

        </div>
    </x-dynamic-component>
</x-dynamic-component>