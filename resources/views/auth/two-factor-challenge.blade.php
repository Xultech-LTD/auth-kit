{{--
/**
 * Page: Two Factor Challenge
 *
 * Two-factor challenge page composed using AuthKit components.
 *
 * Data:
 * - $challenge: Pending login challenge reference.
 * - $methods: Allowed methods for completing 2FA (e.g. totp, email, magic_link).
 *
 * Responsibilities:
 * - Resolves the two-factor challenge form schema.
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

    $challenge = (string) ($challenge ?? '');
    $methods = isset($methods) ? (array) $methods : ['totp'];

    $twoFactorAction = (string) ($apiNames['two_factor_challenge'] ?? 'authkit.api.twofactor.challenge');
    $twoFactorRecoveryRoute = (string) ($webNames['two_factor_recovery'] ?? 'authkit.web.twofactor.recovery');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)
        ->resolveWithRuntime('two_factor_challenge', [
            'challenge' => $challenge,
        ]);

    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
    $submitLabel = (string) ($submit['label'] ?? 'Verify');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');
    $pageComponent = (string) data_get($c, 'page', 'authkit::page');
    $pageKey = (string) data_get(config('authkit.javascript.pages', []), 'two_factor_challenge.page_key', 'two_factor_challenge');
@endphp

<x-dynamic-component :component="$pageComponent" title="Two-factor challenge" :page-key="$pageKey">
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Two-factor verification"
                    subtitle="Enter your authentication code to continue."
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

            <form method="post" action="{{ route($twoFactorAction) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
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

            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                <x-dynamic-component
                        :component="data_get($c, 'link')"
                        :href="route($twoFactorRecoveryRoute)"
                >
                    Use a recovery code
                </x-dynamic-component>
            </x-dynamic-component>
            </form>

            @if (in_array('email', $methods, true) || in_array('magic_link', $methods, true))
                <x-dynamic-component :component="data_get($c, 'divider')" />

                <x-dynamic-component :component="data_get($c, 'auth_footer')">
                    Other verification options will appear here based on your account setup.
                </x-dynamic-component>
            @endif

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>