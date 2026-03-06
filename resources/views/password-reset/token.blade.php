{{--
/**
 * Page: Password Reset Token Entry
 *
 * Data:
 * - $email: email for the pending reset flow.
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

    $forgotPage = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $driver = (string) ($driver ?? config('authkit.password_reset.driver', 'link'));

    $verifyToken = (string) ($apiNames['password_verify_token'] ?? 'authkit.api.password.reset.verify.token');
@endphp

{{-- Layout Wrapper --}}
<x-dynamic-component :component="data_get($c, 'layout')" title="Enter reset code">

    {{-- Container --}}
    <x-dynamic-component :component="data_get($c, 'container')">

        {{-- Card --}}
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Auth Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Reset your password"
                    subtitle="Enter the code we sent and choose a new password."
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

            {{-- Reset Context --}}
            @if (is_string($email) && $email !== '')
                <div style="margin:12px 0;opacity:.85;">
                    Reset code was sent to <strong>{{ $email }}</strong>
                </div>
            @endif

            {{-- Token Verification & Reset Form --}}
            <form method="post" action="{{ route($verifyToken) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            {{-- Email Field --}}
            <input type="hidden" name="email" value="{{ (string) $email }}">

            {{-- Reset Code Field --}}
            <x-dynamic-component :component="data_get($c, 'label')" for="token">
                Reset code
            </x-dynamic-component>

            <x-dynamic-component
                    :component="data_get($c, 'input')"
                    id="token"
                    name="token"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    required
                    autofocus
                    :value="old('token', '')"
            />

            <x-dynamic-component :component="data_get($c, 'error')" name="token" />

            {{-- New Password Field --}}
            <div style="margin-top:12px;">
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
            </div>

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
                Use a different email?
                <x-dynamic-component :component="data_get($c, 'link')" :href="route($forgotPage)">
                    Go back
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>