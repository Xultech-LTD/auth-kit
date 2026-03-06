{{--
/**
 * Page: Register
 *
 * Registration page composed using AuthKit components.
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

    $registerRoute = (string) ($apiNames['register'] ?? 'authkit.api.auth.register');
    $loginRoute = (string) ($webNames['login'] ?? 'authkit.web.login');
@endphp

{{-- Layout Wrapper --}}
<x-dynamic-component :component="data_get($c, 'layout')" title="Register">

    {{-- Container --}}
    <x-dynamic-component :component="data_get($c, 'container')">

        {{-- Card --}}
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Auth Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Create account"
                    subtitle="Register to continue."
            />

            {{-- Global Validation Errors --}}
            <x-dynamic-component :component="data_get($c, 'errors')" />

            {{-- Registration Form --}}
            <form method="post" action="{{ route($registerRoute) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            {{-- Name Field --}}
            <div style="margin-bottom:12px;">
                <x-dynamic-component :component="data_get($c, 'label')" for="name">
                    Name
                </x-dynamic-component>

                <x-dynamic-component
                        :component="data_get($c, 'input')"
                        name="name"
                        id="name"
                        autocomplete="name"
                />

                <x-dynamic-component :component="data_get($c, 'error')" name="name" />
            </div>

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
                        autocomplete="new-password"
                />

                <x-dynamic-component :component="data_get($c, 'error')" name="password" />
            </div>

            {{-- Password Confirmation Field --}}
            <div style="margin-bottom:16px;">
                <x-dynamic-component :component="data_get($c, 'label')" for="password_confirmation">
                    Confirm password
                </x-dynamic-component>

                <x-dynamic-component
                        :component="data_get($c, 'input')"
                        name="password_confirmation"
                        id="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                />
            </div>

            {{-- Submit Button --}}
            <x-dynamic-component :component="data_get($c, 'button')">
                Create account
            </x-dynamic-component>
            </form>

            {{-- Divider --}}
            <x-dynamic-component :component="data_get($c, 'divider')" />

            {{-- Footer --}}
            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                Already have an account?
                <x-dynamic-component :component="data_get($c, 'link')" :href="route($loginRoute)">
                    Login
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>