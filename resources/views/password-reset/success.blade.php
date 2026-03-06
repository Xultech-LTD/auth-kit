{{--
/**
 * Page: Reset Password Success
 *
 * Confirmation page shown after a successful password reset.
 */
--}}

{{-- Configuration & Route Resolution --}}
@php
    $c = (array) config('authkit.components', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $loginPage = (string) ($webNames['login'] ?? 'authkit.web.login');

    $status = (string) session('status', session('message', 'Password reset successful.'));
@endphp

{{-- Layout Wrapper --}}
<x-dynamic-component :component="data_get($c, 'layout')" title="Password reset">

    {{-- Container --}}
    <x-dynamic-component :component="data_get($c, 'container')">

        {{-- Card --}}
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Auth Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Password reset successful"
                    subtitle="You can now sign in with your new password."
            />

            {{-- Success Alert --}}
            <x-dynamic-component :component="data_get($c, 'alert')">
                {{ $status }}
            </x-dynamic-component>

            {{-- Primary Action --}}
            <div style="margin-top:14px;">
                <x-dynamic-component
                        :component="data_get($c, 'button')"
                        :href="route($loginPage)"
                >
                    Continue to login
                </x-dynamic-component>
            </div>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>