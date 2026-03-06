{{--
/**
 * Page: Two Factor Recovery
 *
 * Recovery code page composed using AuthKit components.
 *
 * Data:
 * - $challenge: Pending login challenge reference.
 * - $methods: Allowed methods available for the pending login.
 */
--}}

@php
    $c = (array) config('authkit.components', []);
    $apiNames = (array) config('authkit.route_names.api', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

    $challenge = (string) ($challenge ?? '');

    $recoveryAction = (string) ($apiNames['two_factor_recovery'] ?? 'authkit.api.twofactor.recovery');
    $challengeRoute = (string) ($webNames['two_factor_challenge'] ?? 'authkit.web.twofactor.challenge');
@endphp

<x-dynamic-component :component="data_get($c, 'layout')" title="Recovery code">
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Use a recovery code"
                    subtitle="Enter one of your saved recovery codes to continue."
            />

            <x-dynamic-component :component="data_get($c, 'errors')" />

            <form method="post" action="{{ route($recoveryAction) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            <div style="margin-bottom:12px;">
                <x-dynamic-component :component="data_get($c, 'label')" for="recovery_code">
                    Recovery code
                </x-dynamic-component>

                <x-dynamic-component
                        :component="data_get($c, 'input')"
                        name="recovery_code"
                        id="recovery_code"
                        autocomplete="one-time-code"
                />

                <x-dynamic-component :component="data_get($c, 'error')" name="recovery_code" />
            </div>

            <x-dynamic-component :component="data_get($c, 'button')">
                Continue
            </x-dynamic-component>
            </form>

            <x-dynamic-component :component="data_get($c, 'divider')" />

            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                <x-dynamic-component
                        :component="data_get($c, 'link')"
                        href="{{ route($challengeRoute, ['c' => $challenge]) }}"
                >
                    Use authentication code instead
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>