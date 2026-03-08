{{--
/**
 * Page: Password Reset Token Entry
 *
 * Data:
 * - $email: Email for the pending reset flow.
 * - $driver: Password reset driver (link|token).
 *
 * Responsibilities:
 * - Resolves the password-reset-token form schema with runtime email context.
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
    $driver = (string) ($driver ?? config('authkit.password_reset.driver', 'link'));

    $forgotPage = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');
    $verifyToken = (string) ($apiNames['password_verify_token'] ?? 'authkit.api.password.reset.verify.token');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)
        ->resolveWithRuntime('password_reset_token', [
            'email' => $email,
        ]);

    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
    $submitLabel = (string) ($submit['label'] ?? 'Reset password');

    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');
@endphp

<x-dynamic-component :component="data_get($c, 'layout')" title="Enter reset code">
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Reset your password"
                    subtitle="Enter the code we sent and choose a new password."
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

            @if ($email !== '')
                <div style="margin:12px 0;opacity:.85;">
                    Reset code was sent to <strong>{{ $email }}</strong>
                </div>
            @endif

            <form method="post" action="{{ route($verifyToken) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
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
                Use a different email?
                <x-dynamic-component :component="data_get($c, 'link')" :href="route($forgotPage)">
                    Go back
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>