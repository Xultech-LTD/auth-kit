{{--
/**
 * Page: Email Verification Notice
 *
 * Informs the user that email verification is required.
 *
 * Data:
 * - $email: Email address in verification context.
 * - $driver: Verification driver (link|token).
 *
 * Responsibilities:
 * - Renders notice/status UI for pending email verification.
 * - Resolves the resend-verification form schema with runtime email context.
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
    $driver = (string) ($driver ?? config('authkit.email_verification.driver', 'link'));

    $sendVerification = (string) ($apiNames['send_verification'] ?? 'authkit.api.email.verification.send');
    $tokenPage = (string) ($webNames['verify_token_page'] ?? 'authkit.web.email.verify.token');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)
        ->resolveWithRuntime('email_verification_send', [
            'email' => $email,
        ]);

    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
    $submitLabel = (string) ($submit['label'] ?? 'Didn’t receive it? Resend.');

    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');
@endphp

<x-dynamic-component :component="data_get($c, 'layout')" title="Verify your email">
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Verify your email"
                    subtitle="We need to confirm your email address before you can continue."
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
                    We sent a verification message to <strong>{{ $email }}</strong>
                </div>

                <form method="post" action="{{ route($sendVerification) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
                @csrf

                <x-dynamic-component
                        :component="$fieldsComponent"
                        :fields="$fields"
                />

                <x-dynamic-component :component="data_get($c, 'button')">
                    {{ $submitLabel }}
                </x-dynamic-component>
                </form>
            @else
                <x-dynamic-component :component="data_get($c, 'alert')">
                    Missing email verification context.
                </x-dynamic-component>
            @endif

            @if ($driver === 'token')
                <x-dynamic-component :component="data_get($c, 'divider')" />

                <x-dynamic-component :component="data_get($c, 'auth_footer')">
                    Prefer entering a code instead?
                    <x-dynamic-component
                            :component="data_get($c, 'link')"
                            :href="route($tokenPage, ['email' => $email])"
                    >
                        Enter verification code
                    </x-dynamic-component>
                </x-dynamic-component>
            @endif

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>