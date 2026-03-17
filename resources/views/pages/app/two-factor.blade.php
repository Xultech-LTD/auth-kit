{{--
/**
 * Page: App Two-Factor Authentication
 *
 * Authenticated AuthKit two-factor management page.
 *
 * Responsibilities:
 * - Render the authenticated app layout.
 * - Display current two-factor status.
 * - Show setup instructions and confirmation form when two-factor is not enabled.
 * - Show management actions when two-factor is enabled.
 *
 * Expected data:
 * - $title
 * - $heading
 * - $pageKey
 * - $currentPage
 * - $twoFactorEnabled
 * - $twoFactorMethods
 * - $hasRecoveryCodes
 * - $manualSecret
 * - $otpUri
 * - $setupAvailable
 */
--}}

@php
    $c = (array) config('authkit.components', []);
    $apiNames = (array) config('authkit.route_names.api', []);
    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

    $confirmAction = (string) ($apiNames['two_factor_confirm'] ?? 'authkit.api.settings.two_factor.confirm');
    $disableAction = (string) ($apiNames['two_factor_disable'] ?? 'authkit.api.settings.two_factor.disable');
    $regenerateAction = (string) ($apiNames['two_factor_recovery_regenerate'] ?? 'authkit.api.settings.two_factor.recovery.regenerate');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)->resolve('two_factor_confirm');
    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
    $submitLabel = (string) ($submit['label'] ?? 'Confirm setup');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $settingsSectionComponent = (string) data_get($c, 'settings_section', 'authkit::app.settings.section');
    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');
@endphp

<x-authkit::app.layout
        :title="$title ?? 'Two-factor authentication'"
        :page-key="$pageKey ?? 'two_factor_settings'"
        :current-page="$currentPage ?? 'two_factor_settings'"
        :page-title="$title ?? 'Two-factor authentication'"
        :page-heading="$heading ?? 'Manage two-factor authentication'"
>
    <div class="authkit-dashboard authkit-dashboard__stack">
        @if ($status !== '')
            <x-dynamic-component :component="data_get($c, 'alert')" variant="success">
                {{ $status }}
            </x-dynamic-component>
        @endif

        @if ($error !== '')
            <x-dynamic-component :component="data_get($c, 'alert')" variant="error">
                {{ $error }}
            </x-dynamic-component>
        @endif

        <x-dynamic-component
                :component="$settingsSectionComponent"
                title="Two-factor status"
                description="Protect your account with an authenticator app and recovery codes."
        >
            <div class="authkit-empty-state">
                <div class="authkit-empty-state__title">
                    {{ $twoFactorEnabled ? 'Two-factor authentication is enabled' : 'Two-factor authentication is not enabled' }}
                </div>

                <p class="authkit-empty-state__text">
                    @if ($twoFactorEnabled)
                        Your account requires an additional verification code during sign-in.
                        {{ !empty($twoFactorMethods ?? []) ? 'Active methods: ' . implode(', ', $twoFactorMethods) . '.' : '' }}
                    @else
                        Enable two-factor authentication to add an extra layer of protection to your account.
                    @endif
                </p>

                @if ($twoFactorEnabled)
                    <p class="authkit-empty-state__text">
                        {{ ($hasRecoveryCodes ?? false) ? 'Recovery codes are available for emergency access.' : 'No recovery codes are currently stored.' }}
                    </p>
                @endif
            </div>
        </x-dynamic-component>

        @if (!($twoFactorEnabled ?? false))
            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Set up authenticator app"
                    description="Scan the setup QR with your authenticator app or enter the secret manually, then confirm with a generated code."
            >
                @if (($setupAvailable ?? false) && ($otpUri ?? '') !== '')
                    <div class="authkit-empty-state">
                        <div class="authkit-empty-state__title">Scan with your authenticator app</div>

                        <p class="authkit-empty-state__text">
                            Open your authenticator app and scan this QR code to add your account.
                        </p>

                        <div class="authkit-two-factor-setup">
                            <div class="authkit-two-factor-setup__qr">
                                {!! app(\Xul\AuthKit\Support\TwoFactor\TwoFactorQrCodeRenderer::class)->render($otpUri) !!}
                            </div>
                        </div>
                    </div>
                @endif

                    @if (($manualSecret ?? '') !== '')
                        <div class="authkit-empty-state">
                            <div class="authkit-empty-state__title">Manual setup key</div>

                            <p class="authkit-empty-state__text">
                                If you cannot scan the QR code, enter this key manually in your authenticator app.
                            </p>

                            <div class="authkit-app-session-card__agent">
                                {{ $manualSecret }}
                            </div>
                        </div>
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
        @else
            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Recovery codes"
                    description="Regenerate recovery codes when needed. Newly generated codes should be stored in a safe location."
            >
                <form method="post" action="{{ route($regenerateAction) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
                @csrf

                <div class="authkit-form-actions">
                    <x-dynamic-component :component="data_get($c, 'button')">
                        Regenerate recovery codes
                    </x-dynamic-component>
                </div>
                </form>
            </x-dynamic-component>

            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Disable two-factor"
                    description="Turn off two-factor authentication for this account."
            >
                <form method="post" action="{{ route($disableAction) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
                @csrf

                <div class="authkit-form-actions">
                    <x-dynamic-component :component="data_get($c, 'button')">
                        Disable two-factor authentication
                    </x-dynamic-component>
                </div>
                </form>
            </x-dynamic-component>
        @endif
    </div>
</x-authkit::app.layout>