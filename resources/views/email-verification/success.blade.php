{{--
/**
 * Page: Email Verified
 *
 * Confirmation page shown after a successful email verification flow.
 *
 * Data:
 * - $status: Success message to display.
 */
--}}

{{-- Configuration & Route Resolution --}}
@php
    $c = (array) config('authkit.components', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $login = (string) ($webNames['login'] ?? 'authkit.web.login');
@endphp

{{-- Layout Wrapper --}}
<x-dynamic-component :component="data_get($c, 'layout')" title="Email verified">

    {{-- Container --}}
    <x-dynamic-component :component="data_get($c, 'container')">

        {{-- Card --}}
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Auth Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Email verified"
                    subtitle="You can now continue."
            />

            {{-- Success Alert --}}
            <x-dynamic-component :component="data_get($c, 'alert')">
                {{ $status }}
            </x-dynamic-component>

            {{-- Primary Action --}}
            <div style="margin-top:14px;">
                <x-dynamic-component :component="data_get($c, 'button')" :href="route($login)">
                    Continue to login
                </x-dynamic-component>
            </div>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>