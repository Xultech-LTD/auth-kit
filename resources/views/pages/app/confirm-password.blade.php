{{--
/**
 * Page: Confirm Password
 *
 * Authenticated AuthKit password confirmation page (auth-style).
 *
 * Responsibilities:
 * - Resolve the confirm-password form schema.
 * - Render AuthKit page shell (auth variant).
 * - Render feedback messages and validation errors.
 * - Render schema-driven password confirmation form.
 * - Support both HTTP and AJAX submission modes.
 *
 * Expected data:
 * - $title
 * - $heading
 * - $pageKey
 * - $currentPage
 */
--}}

@php
    $c = (array) config('authkit.components', []);
    $apiNames = (array) config('authkit.route_names.api', []);

    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

    $confirmAction = (string) ($apiNames['confirm_password'] ?? 'authkit.api.confirm.password');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)->resolve('confirm_password');

    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
    $submitLabel = (string) ($submit['label'] ?? 'Confirm password');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $pageComponent = (string) data_get($c, 'page', 'authkit::page');
    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');

    $pageKey = $pageKey ?? (string) data_get(
        config('authkit.javascript.pages', []),
        'confirm_password.page_key',
        'confirm_password'
    );
@endphp

<x-dynamic-component :component="$pageComponent" :title="$title ?? 'Confirm password'" variant="auth" :page-key="$pageKey">
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            {{-- Header --}}
            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    :title="$heading ?? 'Confirm your password'"
                    subtitle="For your security, please confirm your current password before continuing."
            />

            {{-- Status --}}
            @if ($status !== '')
                <x-dynamic-component :component="data_get($c, 'alert')" variant="warning">
                    {{ $status }}
                </x-dynamic-component>
            @endif

            {{-- Error --}}
            @if ($error !== '')
                <x-dynamic-component :component="data_get($c, 'alert')" variant="error">
                    {{ $error }}
                </x-dynamic-component>
            @endif

            {{-- Validation errors --}}
            <x-dynamic-component :component="data_get($c, 'errors')" />

            {{-- Form --}}
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