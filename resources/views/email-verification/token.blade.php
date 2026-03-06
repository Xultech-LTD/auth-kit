{{--
/**
 * Page: Email Verification Token
 *
 * Allows the user to enter a verification code/token received via email.
 *
 * Data:
 * - $email: Email address in verification context.
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

    $verifyToken = (string) ($apiNames['verify_token'] ?? 'authkit.api.email.verification.verify.token');
    $noticePage = (string) ($webNames['verify_notice'] ?? 'authkit.web.email.verify.notice');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');
@endphp

{{-- Layout Wrapper --}}
<x-dynamic-component :component="data_get($c, 'layout')" title="Verify your email">

    {{-- Container --}}
    <x-dynamic-component :component="data_get($c, 'container')">

        {{-- Card --}}
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Auth Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Enter verification code"
                    subtitle="Enter the code sent to your email to verify your account."
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

            {{-- Verification Context --}}
            @if (is_string($email) && $email !== '')
                <div style="margin:12px 0;opacity:.85;">
                    Verifying <strong>{{ $email }}</strong>
                </div>
            @endif

            @if (is_string($email) && $email !== '')
                {{-- Token Verification Form --}}
                <form method="post" action="{{ route($verifyToken) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
                @csrf

                {{-- Email Field --}}
                <input type="hidden" name="email" value="{{ (string) $email }}"/>

                {{-- Token Field --}}
                <div style="margin-top:12px;">
                    <x-dynamic-component :component="data_get($c, 'label')" for="token">
                        Verification code
                    </x-dynamic-component>

                    <x-dynamic-component
                            :component="data_get($c, 'input')"
                            id="token"
                            name="token"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            required
                    />
                </div>

                {{-- Submit Button --}}
                <div style="margin-top:14px;">
                    <x-dynamic-component :component="data_get($c, 'button')">
                        Verify email
                    </x-dynamic-component>
                </div>
                </form>
            @else
                <x-dynamic-component :component="data_get($c, 'alert')">
                    Missing email verification context.
                </x-dynamic-component>
            @endif

            {{-- Divider --}}
            <x-dynamic-component :component="data_get($c, 'divider')" />

            {{-- Footer --}}
            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                Back to notice page?
                <x-dynamic-component
                        :component="data_get($c, 'link')"
                        :href="route($noticePage, ['email' => (string) $email])"
                >
                    View verification notice
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>