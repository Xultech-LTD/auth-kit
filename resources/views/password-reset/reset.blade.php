{{--
/**
 * Page: Reset Password
 *
 * Data:
 * - $email: email for the pending reset flow.
 * - $token: reset token from the reset link.
 * - $driver: link|token
 */
--}}

{{-- Configuration & Route Resolution --}}
@php
    $c = (array) config('authkit.components', []);
    $apiNames = (array) config('authkit.route_names.api', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

    $loginPage = (string) ($webNames['login'] ?? 'authkit.web.login');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $resetPassword = (string) ($apiNames['password_reset'] ?? 'authkit.api.password.reset');
@endphp

{{-- Layout Wrapper --}}
<x-dynamic-component :component="data_get($c, 'layout')" title="Reset password">

    {{-- Container --}}
    <x-dynamic-component :component="data_get($c, 'container')">

        {{-- Card --}}
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Auth Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Reset your password"
                    subtitle="Choose a new password to regain access to your account."
            />

            {{-- Status Alert --}}
            @if ($status !== '')
                <x-dynamic-component :component="data_get($c, 'alert')">
                    {{ $status }}
                </x-dynamic-component>
            @endif

            {{-- Error Alert --}}
            @if ($error !== '')
                <x-dynamic-component :component="data_get($c, 'alert')">
                    {{ $error }}
                </x-dynamic-component>
            @endif

            {{-- Reset Password Form --}}
            <form method="post" action="{{ route($resetPassword) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            {{-- Reset Context Fields --}}
            <input type="hidden" name="email" value="{{ (string) $email }}">
            <input type="hidden" name="token" value="{{ (string) $token }}">

            {{-- New Password Field --}}
            <x-dynamic-component :component="data_get($c, 'label')" for="password">
                New password
            </x-dynamic-component>

            <x-dynamic-component
                    :component="data_get($c, 'input')"
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    required
            />

            <x-dynamic-component :component="data_get($c, 'error')" name="password" />

            {{-- Password Confirmation Field --}}
            <div style="margin-top:12px;">
                <x-dynamic-component :component="data_get($c, 'label')" for="password_confirmation">
                    Confirm password
                </x-dynamic-component>

                <x-dynamic-component
                        :component="data_get($c, 'input')"
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                />

                <x-dynamic-component :component="data_get($c, 'error')" name="password_confirmation" />
            </div>

            {{-- Submit Button --}}
            <div style="margin-top:14px;">
                <x-dynamic-component :component="data_get($c, 'button')">
                    Reset password
                </x-dynamic-component>
            </div>
            </form>

            {{-- Divider --}}
            <x-dynamic-component :component="data_get($c, 'divider')" />

            {{-- Footer --}}
            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                Back to
                <x-dynamic-component :component="data_get($c, 'link')" :href="route($loginPage)">
                    login
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>