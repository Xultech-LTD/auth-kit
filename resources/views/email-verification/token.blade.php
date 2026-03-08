{{--
/**
 * Page: Email Verification Token
 *
 * Allows the user to enter a verification code/token received via email.
 *
 * Data:
 * - $email: Email address in verification context.
 *
 * Responsibilities:
 * - Renders token-verification page UI for pending email verification.
 * - Resolves the token-verification form schema with runtime email context.
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

    $verifyToken = (string) ($apiNames['verify_token'] ?? 'authkit.api.email.verification.verify.token');
    $noticePage = (string) ($webNames['verify_notice'] ?? 'authkit.web.email.verify.notice');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)
        ->resolveWithRuntime('email_verification_token', [
            'email' => $email,
        ]);

    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
    $submitLabel = (string) ($submit['label'] ?? 'Verify email');

    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');
@endphp

<x-dynamic-component :component="data_get($c, 'layout')" title="Verify your email">
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Enter verification code"
                    subtitle="Enter the code sent to your email to verify your account."
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
                    Verifying <strong>{{ $email }}</strong>
                </div>

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
            @else
                <x-dynamic-component :component="data_get($c, 'alert')">
                    Missing email verification context.
                </x-dynamic-component>
            @endif

            <x-dynamic-component :component="data_get($c, 'divider')" />

            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                Back to notice page?
                <x-dynamic-component
                        :component="data_get($c, 'link')"
                        :href="route($noticePage, ['email' => $email])"
                >
                    View verification notice
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>