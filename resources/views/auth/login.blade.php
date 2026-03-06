{{-- 
/**
 * Page: Login
 *
 * Login page composed using AuthKit components.
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

    $loginAction = (string) ($apiNames['login'] ?? 'authkit.api.auth.login');
    $registerPage = (string) ($webNames['register'] ?? 'authkit.web.register');
    $forgottenPage = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');
@endphp

{{-- Layout Wrapper --}}
<x-dynamic-component :component="data_get($c, 'layout')" title="Login">

    {{-- Container --}}
    <x-dynamic-component :component="data_get($c, 'container')">

        {{-- Card --}}
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Auth Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Welcome back"
                    subtitle="Login to continue."
            />

            {{-- Global Validation Errors --}}
            <x-dynamic-component :component="data_get($c, 'errors')" />

            {{-- Login Form --}}
            <form method="post" action="{{ route($loginAction) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            {{-- Email Field --}}
            <div style="margin-bottom:12px;">
                <x-dynamic-component :component="data_get($c, 'label')" for="email">
                    Email
                </x-dynamic-component>

                <x-dynamic-component
                        :component="data_get($c, 'input')"
                        name="email"
                        id="email"
                        type="email"
                        autocomplete="email"
                />

                <x-dynamic-component :component="data_get($c, 'error')" name="email" />
            </div>

            {{-- Password Field --}}
            <div style="margin-bottom:12px;">
                <x-dynamic-component :component="data_get($c, 'label')" for="password">
                    Password
                </x-dynamic-component>

                <x-dynamic-component
                        :component="data_get($c, 'input')"
                        name="password"
                        id="password"
                        type="password"
                        autocomplete="current-password"
                />

                <x-dynamic-component :component="data_get($c, 'error')" name="password" />

                {{-- Forgot Password Link --}}
                <div style="margin-top:6px; text-align:right;">
                    <x-dynamic-component
                            :component="data_get($c, 'link')"
                            :href="route($forgottenPage)"
                    >
                        Forgot your password?
                    </x-dynamic-component>
                </div>
            </div>

            {{-- Remember Me --}}
            <div style="margin-bottom:16px;">
                <x-dynamic-component :component="data_get($c, 'checkbox')" name="remember" :checked="true">
                    Remember me
                </x-dynamic-component>
            </div>

            {{-- Submit Button --}}
            <x-dynamic-component :component="data_get($c, 'button')">
                Continue
            </x-dynamic-component>
            </form>

            {{-- Divider --}}
            <x-dynamic-component :component="data_get($c, 'divider')" />

            {{-- Footer --}}
            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                Don’t have an account?
                <x-dynamic-component :component="data_get($c, 'link')" :href="route($registerPage)">
                    Register
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>