{{--
/**
 * Page: Two Factor Challenge
 *
 * Two-factor challenge page composed using AuthKit components.
 *
 * Data:
 * - $challenge: Pending login challenge reference.
 * - $methods: Allowed methods for completing 2FA (e.g. totp, email, magic_link).
 */
--}}

{{-- Configuration & Route Resolution --}}
@php
    $c = (array) config('authkit.components', []);
    $apiNames = (array) config('authkit.route_names.api', []);

    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

    $methods = isset($methods) ? (array) $methods : ['totp'];

    $twoFactorAction = (string) ($apiNames['two_factor_challenge'] ?? 'authkit.api.twofactor.challenge');
    $twoFactorRecoveryRoute = (string) ($webNames['two_factor_recovery'] ?? 'authkit.web.twofactor.recovery');
@endphp

{{-- Layout Wrapper --}}
<x-dynamic-component :component="data_get($c, 'layout')" title="Two-factor challenge">

    {{-- Container --}}
    <x-dynamic-component :component="data_get($c, 'container')">

        {{-- Card --}}
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Auth Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Two-factor verification"
                    subtitle="Enter your authentication code to continue."
            />

            {{-- Global Validation Errors --}}
            <x-dynamic-component :component="data_get($c, 'errors')" />

            {{-- Two-Factor Form --}}
            <form method="post" action="{{ route($twoFactorAction) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            {{-- Authentication Code Field --}}
            <div style="margin-bottom:12px;">
                <x-dynamic-component :component="data_get($c, 'label')" for="code">
                    Authentication code
                </x-dynamic-component>

                <x-dynamic-component
                        :component="data_get($c, 'input')"
                        name="code"
                        id="code"
                        autocomplete="one-time-code"
                />

                <x-dynamic-component :component="data_get($c, 'error')" name="code" />
            </div>

            {{-- Submit Button --}}
            <x-dynamic-component :component="data_get($c, 'button')">
                Verify
            </x-dynamic-component>

            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                <x-dynamic-component
                        :component="data_get($c, 'link')"
                        href="{{ route($twoFactorRecoveryRoute) }}"
                >
                    Use a recovery code
                </x-dynamic-component>
            </x-dynamic-component>
            </form>

            {{-- Alternative Methods Section --}}
            @if (in_array('email', $methods, true) || in_array('magic_link', $methods, true))
                <x-dynamic-component :component="data_get($c, 'divider')" />

                {{-- Footer / Alternative Methods Info --}}
                <x-dynamic-component :component="data_get($c, 'auth_footer')">
                    Other verification options will appear here based on your account setup.
                </x-dynamic-component>
            @endif

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>