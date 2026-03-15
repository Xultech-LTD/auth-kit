{{--
/**
 * Page: Forgot Password Sent
 *
 * Data:
 * - $email: Email for the pending reset flow.
 * - $driver: Password reset driver (link|token).
 *
 * Responsibilities:
 * - Renders confirmation UI after a password reset request.
 * - Resolves the resend-reset form schema with runtime email context.
 * - Delegates field rendering to the schema-driven field collection component.
 */
--}}

@php
    $c = (array) config('authkit.components', []);
    $apiNames = (array) config('authkit.route_names.api', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

    $email = (string) ($email ?? '');
    $driver = (string) ($driver ?? config('authkit.password_reset.driver', 'link'));

    $sendReset = (string) ($apiNames['password_send_reset'] ?? 'authkit.api.password.reset.send');
    $tokenPage = (string) ($webNames['password_reset_token_page'] ?? 'authkit.web.password.reset.token');
    $forgotPage = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)
        ->resolveWithRuntime('password_forgot_resend', [
            'email' => $email,
        ]);

    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');

    $resendLabel = $driver === 'token' ? 'Resend reset code' : 'Resend reset link';
    $pageComponent = (string) data_get($c, 'page', 'authkit::page');
    $pageKey = (string) data_get(config('authkit.javascript.pages', []), 'password_forgot_sent.page_key', 'password_forgot_sent');
@endphp

<x-dynamic-component :component="$pageComponent" title="Reset email sent" :page-key="$pageKey">
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Check your email"
                    subtitle="We sent a password reset {{ $driver === 'token' ? 'code' : 'link' }} to your email address."
            />

            @if ($status !== '')
                <x-dynamic-component :component="data_get($c, 'alert')" variant="warning">
                    {{ $status }}
                </x-dynamic-component>
            @endif

            @if ($error !== '')
                <x-dynamic-component :component="data_get($c, 'alert')" variant="error">
                    {{ $error }}
                </x-dynamic-component>
            @endif

            <x-dynamic-component :component="data_get($c, 'errors')" />

            @if ($email !== '')
                <div class="authkit-note authkit-note--muted">
                    Reset instructions will be sent to <strong>{{ $email }}</strong>
                </div>
            @endif

            @if ($driver === 'token')
                <div class="authkit-secondary-actions">
                    <x-dynamic-component
                            :component="data_get($c, 'link')"
                            :href="route($tokenPage, ['email' => $email])"
                    >
                        Enter reset code
                    </x-dynamic-component>
                </div>

                <x-dynamic-component :component="data_get($c, 'divider')" />
            @endif

            <form method="post" action="{{ route($sendReset) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            <x-dynamic-component
                    :component="$fieldsComponent"
                    :fields="$fields"
            />

            <div class="authkit-form-actions">
                <x-dynamic-component :component="data_get($c, 'button')">
                    {{ $resendLabel }}
                </x-dynamic-component>
            </div>
            </form>

            <x-dynamic-component :component="data_get($c, 'divider')" />

            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                Use a different email?
                <x-dynamic-component :component="data_get($c, 'link')" :href="route($forgotPage)">
                    Go back
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>