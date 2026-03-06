{{--
/**
 * Page: Email Verification Notice
 *
 * Informs the user that email verification is required.
 *
 * Data:
 * - $email: Email address in verification context.
 * - $driver: Verification driver (link|token).
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

    $sendVerification = (string) ($apiNames['send_verification'] ?? 'authkit.api.email.verification.send');
    $tokenPage = (string) ($webNames['verify_token_page'] ?? 'authkit.web.email.verify.token');

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
                    title="Verify your email"
                    subtitle="We need to confirm your email address before you can continue."
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
                    We sent a verification message to <strong>{{ $email }}</strong>
                </div>
            @endif

            @if (is_string($email) && $email !== '')
                {{-- Resend Verification Form --}}
                <form method="post" action="{{ route($sendVerification) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
                    @csrf

                    {{-- Email Field --}}
                    <input type="hidden" name="email" value="{{ (string) $email }}"/>

                    {{-- Submit Button --}}
                    <x-dynamic-component :component="data_get($c, 'button')">
                        Didn’t receive it? Resend.
                    </x-dynamic-component>
                </form>
            @else
                <x-dynamic-component :component="data_get($c, 'alert')">
                    Missing email verification context.
                </x-dynamic-component>
            @endif

            {{-- Token Driver Secondary Action --}}
            @if (($driver ?? 'link') === 'token')
                <x-dynamic-component :component="data_get($c, 'divider')" />

                {{-- Footer / Enter Code Link --}}
                <x-dynamic-component :component="data_get($c, 'auth_footer')">
                    Prefer entering a code instead?
                    <x-dynamic-component
                            :component="data_get($c, 'link')"
                            :href="route($tokenPage, ['email' => (string) $email])"
                    >
                        Enter verification code
                    </x-dynamic-component>
                </x-dynamic-component>
            @endif

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>