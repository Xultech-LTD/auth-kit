{{--
/**
 * Page: Forgot Password
 *
 * Data:
 * - $email: Prefilled email value (optional).
 * - $driver: Password reset driver (link|token).
 *
 * Responsibilities:
 * - Resolves the forgot-password form schema.
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

    $sendReset = (string) ($apiNames['password_send_reset'] ?? 'authkit.api.password.reset.send');
    $loginPage = (string) ($webNames['login'] ?? 'authkit.web.login');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)
        ->resolveWithRuntime('password_forgot', [
            'email' => $email,
        ]);

    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];

    $defaultSubmitLabel = $driver === 'token' ? 'Send reset code' : 'Send reset link';
    $submitLabel = (string) ($submit['label'] ?? $defaultSubmitLabel);

    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');
    $pageComponent = (string) data_get($c, 'page', 'authkit::page');
    $pageKey = (string) data_get(config('authkit.javascript.pages', []),'password_forgot.page_key','password_forgot');
@endphp

<x-dynamic-component :component="$pageComponent" title="Forgot password" :page-key="$pageKey">
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Forgot password"
                    subtitle="Enter your email and we’ll send you instructions to reset your password."
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

            <form method="post" action="{{ route($sendReset) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
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
                Remember your password?
                <x-dynamic-component :component="data_get($c, 'link')" :href="route($loginPage)">
                    Back to login
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>