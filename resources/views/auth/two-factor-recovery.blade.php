{{--
/**
 * Page: Two Factor Recovery
 *
 * Recovery code page composed using AuthKit components.
 *
 * Data:
 * - $challenge: Pending login challenge reference.
 * - $methods: Allowed methods available for the pending login.
 *
 * Responsibilities:
 * - Resolves the two-factor recovery form schema.
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

    $recoveryAction = (string) ($apiNames['two_factor_recovery'] ?? 'authkit.api.twofactor.recovery');
    $challengeRoute = (string) ($webNames['two_factor_challenge'] ?? 'authkit.web.twofactor.challenge');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)
        ->resolveWithRuntime('two_factor_recovery', [
            'challenge' => $challenge,
        ]);

    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
    $submitLabel = (string) ($submit['label'] ?? 'Continue');

    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');
@endphp

<x-dynamic-component :component="data_get($c, 'layout')" title="Recovery code">
    <x-dynamic-component :component="data_get($c, 'container')">
        <x-dynamic-component :component="data_get($c, 'card')">

            <x-dynamic-component
                    :component="data_get($c, 'auth_header')"
                    title="Use a recovery code"
                    subtitle="Enter one of your saved recovery codes to continue."
            />

            <x-dynamic-component :component="data_get($c, 'errors')" />

            <form method="post" action="{{ route($recoveryAction) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
            @csrf

            <x-dynamic-component
                    :component="$fieldsComponent"
                    :fields="$fields"
            />

            <div style="margin-top:16px;">
                <x-dynamic-component :component="data_get($c, 'button')">
                    {{ $submitLabel }}
                </x-dynamic-component>
            </div>
            </form>

            <x-dynamic-component :component="data_get($c, 'divider')" />

            <x-dynamic-component :component="data_get($c, 'auth_footer')">
                <x-dynamic-component
                        :component="data_get($c, 'link')"
                        :href="route($challengeRoute, ['c' => $challenge])"
                >
                    Use authentication code instead
                </x-dynamic-component>
            </x-dynamic-component>

        </x-dynamic-component>
    </x-dynamic-component>
</x-dynamic-component>