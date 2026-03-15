{{--
/**
 * Page: Login
 *
 * Login page composed using AuthKit components.
 *
 * Responsibilities:
 * - Resolves the login form schema.
 * - Renders the AuthKit page shell.
 * - Renders page-level card content and actions.
 * - Delegates field rendering to the schema-driven field collection component.
 */
--}}

{{-- Configuration, Route Resolution & Schema --}}
@php
    $c = (array) config('authkit.components', []);
    $apiNames = (array) config('authkit.route_names.api', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

    $loginAction = (string) ($apiNames['login'] ?? 'authkit.api.auth.login');
    $registerPage = (string) ($webNames['register'] ?? 'authkit.web.register');
    $forgottenPage = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)->resolve('login');

    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
    $submitLabel = (string) ($submit['label'] ?? 'Continue');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $pageComponent = (string) data_get($c, 'page', 'authkit::page');
    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');
    $pageKey = (string) data_get(config('authkit.javascript.pages', []), 'login.page_key', 'login');
@endphp

<x-dynamic-component :component="$pageComponent" title="Login" variant="auth" :page-key="$pageKey">
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Welcome back"
                    subtitle="Login to continue."
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

            <form method="post" action="{{ route($loginAction) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            <x-dynamic-component
                    :component="$fieldsComponent"
                    :fields="$fields"
            />

            <div class="authkit-inline-actions authkit-inline-actions--end">
                <x-dynamic-component
                        :component="data_get($c, 'link')"
                        :href="route($forgottenPage)"
                >
                    Forgot your password?
                </x-dynamic-component>
            </div>

            <div class="authkit-form-actions">
                <x-dynamic-component :component="data_get($c, 'button')">
                    {{ $submitLabel }}
                </x-dynamic-component>
            </div>
            </form>

            <x-dynamic-component :component="data_get($c, 'divider')" />

            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                Don’t have an account?
                <x-dynamic-component :component="data_get($c, 'link')" :href="route($registerPage)">
                    Register
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>