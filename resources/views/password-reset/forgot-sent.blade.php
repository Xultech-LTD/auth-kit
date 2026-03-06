{{--
/**
 * Page: Forgot Password Sent
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

    $sendReset = (string) ($apiNames['password_send_reset'] ?? 'authkit.api.password.reset.send');
    $tokenPage = (string) ($webNames['password_reset_token_page'] ?? 'authkit.web.password.reset.token');
    $forgotPage = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $driver = (string) ($driver ?? config('authkit.password_reset.driver', 'link'));
@endphp

{{-- Layout Wrapper --}}
<x-dynamic-component :component="data_get($c, 'layout')" title="Reset email sent">

    {{-- Container --}}
    <x-dynamic-component :component="data_get($c, 'container')">

        {{-- Card --}}
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Auth Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Check your email"
                    subtitle="We sent a password reset {{ $driver === 'token' ? 'code' : 'link' }} to your email address."
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
                    Reset instructions will be sent to <strong>{{ $email }}</strong>
                </div>
            @endif

            {{-- Token Driver Primary Action --}}
            @if ($driver === 'token')
                <div style="margin-top:12px;">
                    <x-dynamic-component
                            :component="data_get($c, 'button')"
                            :href="route($tokenPage, ['email' => $email])"
                    >
                        Enter reset code
                    </x-dynamic-component>
                </div>

                <x-dynamic-component :component="data_get($c, 'divider')" />
            @endif

            {{-- Resend Reset Form --}}
            <form method="post" action="{{ route($sendReset) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            {{-- Email Field --}}
            <input type="hidden" name="email" value="{{ (string) $email }}">

            {{-- Submit Button --}}
            <x-dynamic-component :component="data_get($c, 'button')">
                Resend reset {{ $driver === 'token' ? 'code' : 'link' }}
            </x-dynamic-component>
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