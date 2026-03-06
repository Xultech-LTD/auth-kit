{{--
/**
 * Page: Forgot Password
 *
 * Data:
 * - $email: prefilled email (optional).
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

    $sendReset = (string) ($apiNames['password_send_reset'] ?? 'authkit.api.password.reset.send');
    $loginPage = (string) ($webNames['login'] ?? 'authkit.web.login');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $driver = (string) ($driver ?? config('authkit.password_reset.driver', 'link'));
@endphp

{{-- Layout Wrapper --}}
<x-dynamic-component :component="data_get($c, 'layout')" title="Forgot password">

    {{-- Container --}}
    <x-dynamic-component :component="data_get($c, 'container')">

        {{-- Card --}}
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Auth Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Forgot password"
                    subtitle="Enter your email and we’ll send you instructions to reset your password."
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

            {{-- Forgot Password Form --}}
            <form method="post" action="{{ route($sendReset) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            {{-- Email Field --}}
            <x-dynamic-component :component="data_get($c, 'label')" for="email">
                Email
            </x-dynamic-component>

            <x-dynamic-component
                    :component="data_get($c, 'input')"
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                    autofocus
                    :value="old('email', $email ?? '')"
            />

            <x-dynamic-component :component="data_get($c, 'error')" name="email" />

            {{-- Submit Button --}}
            <div style="margin-top:14px;">
                <x-dynamic-component :component="data_get($c, 'button')">
                    Send reset {{ $driver === 'token' ? 'code' : 'link' }}
                </x-dynamic-component>
            </div>
            </form>

            {{-- Divider --}}
            <x-dynamic-component :component="data_get($c, 'divider')" />

            {{-- Footer --}}
            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                Remember your password?
                <x-dynamic-component :component="data_get($c, 'link')" :href="route($loginPage)">
                    Back to login
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>