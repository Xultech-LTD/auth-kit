{{--
/**
 * Page: Confirm Two-Factor Authentication
 *
 * AuthKit confirmation page rendered with the auth-style layout shell.
 *
 * Responsibilities:
 * - Resolve the confirm-two-factor form schema.
 * - Render feedback messages and validation errors.
 * - Render a single schema-driven OTP confirmation form.
 * - Support both normal HTTP and AuthKit AJAX submission modes.
 *
 * Expected data:
 * - $title
 * - $heading
 * - $pageKey
 */
--}}

@php
    $c = (array) config('authkit.components', []);
    $apiNames = (array) config('authkit.route_names.api', []);

    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

    $confirmAction = (string) ($apiNames['confirm_two_factor'] ?? 'authkit.api.confirm.two_factor');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)->resolve('confirm_two_factor');
    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
    $submitLabel = (string) ($submit['label'] ?? 'Confirm');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $pageComponent = (string) data_get($c, 'page', 'authkit::page');
    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');

    $pageKey = (string) data_get(
        config('authkit.javascript.pages', []),
        'confirm_two_factor.page_key',
        'confirm_two_factor'
    );
@endphp

<x-dynamic-component
        :component="$pageComponent"
        :title="$title ?? 'Confirm two-factor authentication'"
        variant="auth"
        :page-key="$pageKey"
>
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    :title="$title ?? 'Confirm two-factor authentication'"
                    :subtitle="$heading ?? 'Enter your authentication code to continue.'"
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

            <form method="post" action="{{ route($confirmAction) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            <x-dynamic-component
                    :component="$fieldsComponent"
                    :fields="$fields"
            />

            <div class="authkit-form-actions">
                <x-dynamic-component :component="data_get($c, 'button')">
                    {{ $submitLabel }}
                </x-dynamic-component>
            </div>
            </form>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>