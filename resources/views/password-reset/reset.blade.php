{{--
/**
 * Page: Reset Password
 *
 * Data:
 * - $email: Email for the pending reset flow.
 * - $token: Reset token from the reset link.
 * - $driver: Password reset driver (link|token).
 *
 * Responsibilities:
 * - Resolves the reset-password form schema with runtime reset context.
 * - Renders page-level shell and actions.
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
    $token = (string) ($token ?? '');

    $loginPage = (string) ($webNames['login'] ?? 'authkit.web.login');
    $resetPassword = (string) ($apiNames['password_reset'] ?? 'authkit.api.password.reset');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)
        ->resolveWithRuntime('password_reset', [
            'email' => $email,
            'token' => $token,
        ]);

    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
    $submitLabel = (string) ($submit['label'] ?? 'Reset password');

    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');
@endphp

<x-dynamic-component :component="data_get($c, 'layout')" title="Reset password">
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Reset your password"
                    subtitle="Choose a new password to regain access to your account."
            />

            @if ($status !== '')
                <x-dynamic-component :component="data_get($c, 'alert')">
                    {{ $status }}
                </x-dynamic-component>
            @endif

            @if ($error !== '')
                <x-dynamic-component :component="data_get($c, 'alert')">
                    {{ $error }}
                </x-dynamic-component>
            @endif

            <form method="post" action="{{ route($resetPassword) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            <x-dynamic-component
                    :component="$fieldsComponent"
                    :fields="$fields"
            />

            <div style="margin-top:14px;">
                <x-dynamic-component :component="data_get($c, 'button')">
                    {{ $submitLabel }}
                </x-dynamic-component>
            </div>
            </form>

            <x-dynamic-component :component="data_get($c, 'divider')" />

            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                Back to
                <x-dynamic-component :component="data_get($c, 'link')" :href="route($loginPage)">
                    login
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>