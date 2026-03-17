{{--
/**
 * Component: App User Menu
 *
 * Authenticated user menu block for AuthKit's application shell.
 *
 * Purpose:
 * - Displays the current authenticated user identity in the topbar.
 * - Provides a dropdown surface for account actions.
 *
 * Notes:
 * - Uses native <details>/<summary> for simple progressive enhancement.
 * - Keeps logout inside the dropdown instead of inline in the topbar.
 */
--}}

@php
    $guard = (string) config('authkit.auth.guard', 'web');
    $user = auth($guard)->user();

    $apiNames = (array) config('authkit.route_names.api', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $logoutRouteName = (string) ($apiNames['logout'] ?? 'authkit.api.auth.logout');
    $settingsRouteName = (string) ($webNames['settings'] ?? 'authkit.web.settings');
    $securityRouteName = (string) ($webNames['security'] ?? 'authkit.web.settings.security');
    $sessionsRouteName = (string) ($webNames['sessions'] ?? 'authkit.web.settings.sessions');

    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttribute = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

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

    $displayMeta = null;

    if ($user !== null && is_string(data_get($user, 'email')) && trim((string) data_get($user, 'email')) !== '') {
        $email = trim((string) data_get($user, 'email'));
        $displayMeta = $email !== $displayName ? $email : null;
    }

    $initials = collect(preg_split('/\s+/', $displayName) ?: [])
        ->filter(fn ($part) => is_string($part) && trim($part) !== '')
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr(trim($part), 0, 1)))
        ->implode('');

    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr($displayName, 0, 1));
    }

    $canLogout = $logoutRouteName !== '' && \Illuminate\Support\Facades\Route::has($logoutRouteName);
    $hasSettings = $settingsRouteName !== '' && \Illuminate\Support\Facades\Route::has($settingsRouteName);
    $hasSecurity = $securityRouteName !== '' && \Illuminate\Support\Facades\Route::has($securityRouteName);
    $hasSessions = $sessionsRouteName !== '' && \Illuminate\Support\Facades\Route::has($sessionsRouteName);
@endphp

<details class="authkit-app-user-menu">
    <summary class="authkit-app-user-menu__trigger">
        <span class="authkit-app-user-menu__identity">
            <span class="authkit-app-user-menu__avatar" aria-hidden="true">
                {{ $initials }}
            </span>

            <span class="authkit-app-user-menu__copy">
                <span class="authkit-app-user-menu__name">
                    {{ $displayName }}
                </span>

                @if (is_string($displayMeta) && $displayMeta !== '')
                    <span class="authkit-app-user-menu__meta">
                        {{ $displayMeta }}
                    </span>
                @endif
            </span>
        </span>

        <span class="authkit-app-user-menu__chevron" aria-hidden="true"></span>
    </summary>

    <div class="authkit-app-user-menu__dropdown">
        <div class="authkit-app-user-menu__dropdown-header">
            <div class="authkit-app-user-menu__dropdown-name">{{ $displayName }}</div>

            @if (is_string($displayMeta) && $displayMeta !== '')
                <div class="authkit-app-user-menu__dropdown-meta">{{ $displayMeta }}</div>
            @endif
        </div>

        <div class="authkit-app-user-menu__dropdown-list">
            @if ($hasSettings)
                <a href="{{ route($settingsRouteName) }}" class="authkit-app-user-menu__item">
                    Account settings
                </a>
            @endif

            @if ($hasSecurity)
                <a href="{{ route($securityRouteName) }}" class="authkit-app-user-menu__item">
                    Security
                </a>
            @endif

            @if ($hasSessions)
                <a href="{{ route($sessionsRouteName) }}" class="authkit-app-user-menu__item">
                    Sessions
                </a>
            @endif
        </div>

        @if ($canLogout)
            <div class="authkit-app-user-menu__dropdown-footer">
                <form
                        method="POST"
                        action="{{ route($logoutRouteName) }}"
                        class="authkit-app-user-menu__form"
                @if($isAjax) {{ $ajaxAttribute }}="true" @endif
                >
                @csrf

                <x-authkit::button
                        type="submit"
                        variant="ghost"
                        class="authkit-app-user-menu__logout"
                >
                    Logout
                </x-authkit::button>
                </form>
            </div>
        @endif
    </div>
</details>