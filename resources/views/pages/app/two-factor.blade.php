{{--
/**
 * Page: Two-factor authentication
 *
 * Authenticated AuthKit two-factor management page.
 *
 * Responsibilities:
 * - Resolves the configured AuthKit app page metadata for the two-factor page.
 * - Renders inside the configured authenticated app layout.
 * - Shows setup details when two-factor is not yet enabled.
 * - Shows confirmation form using the schema resolver.
 * - Shows recovery-code regeneration action.
 * - Shows disable action when two-factor is already enabled.
 */
--}}
@php
    $c = (array) config('authkit.components', []);
    $appPages = (array) config('authkit.app.pages', []);
    $appLayouts = (array) config('authkit.app.layouts', []);
    $apiNames = (array) config('authkit.route_names.api', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $page = is_array($pageConfig ?? null)
        ? $pageConfig
        : (array) ($appPages['two_factor_settings'] ?? []);

    $pageKey = is_string($pageKey ?? null) && $pageKey !== ''
        ? $pageKey
        : 'two_factor_settings';

    $layoutKey = (string) data_get($page, 'layout', 'default');
    $layoutComponent = (string) data_get($appLayouts, $layoutKey, 'authkit::app.layout');

    $containerComponent = (string) data_get($c, 'container', 'authkit::container');
    $cardComponent = (string) data_get($c, 'card', 'authkit::card');
    $alertComponent = (string) data_get($c, 'alert', 'authkit::alert');
    $buttonComponent = (string) data_get($c, 'button', 'authkit::button');
    $linkComponent = (string) data_get($c, 'link', 'authkit::link');
    $errorsComponent = (string) data_get($c, 'errors', 'authkit::form.errors');
    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');
    $settingsSectionComponent = (string) data_get($c, 'settings_section', 'authkit::app.settings.section');

    $title = (string) data_get($page, 'title', 'Two-factor authentication');
    $heading = (string) data_get($page, 'heading', 'Manage two-factor authentication');

    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

    $twoFactor = is_array($twoFactor ?? null) ? $twoFactor : [];

    $enabled = (bool) data_get($twoFactor, 'enabled', false);
    $confirmed = (bool) data_get($twoFactor, 'confirmed', false);
    $supportsSecret = (bool) data_get($twoFactor, 'supports_secret', false);
    $secret = (string) data_get($twoFactor, 'secret', '');
    $recoveryCodesCount = (int) data_get($twoFactor, 'recovery_codes_count', 0);

    $otpUri = (string) ($twoFactorOtpUri ?? '');

    $securityRoute = (string) ($webNames['security'] ?? 'authkit.web.settings.security');
    $confirmRoute = (string) ($apiNames['two_factor_confirm'] ?? 'authkit.api.settings.two_factor.confirm');
    $disableRoute = (string) ($apiNames['two_factor_disable'] ?? 'authkit.api.settings.two_factor.disable');
    $regenerateRecoveryRoute = (string) ($apiNames['two_factor_recovery_regenerate'] ?? 'authkit.api.settings.two_factor.recovery.regenerate');

    $securityUrl = \Illuminate\Support\Facades\Route::has($securityRoute) ? route($securityRoute) : '#';
    $confirmAction = \Illuminate\Support\Facades\Route::has($confirmRoute) ? route($confirmRoute) : '#';
    $disableAction = \Illuminate\Support\Facades\Route::has($disableRoute) ? route($disableRoute) : '#';
    $regenerateRecoveryAction = \Illuminate\Support\Facades\Route::has($regenerateRecoveryRoute) ? route($regenerateRecoveryRoute) : '#';

    $confirmSchema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)->resolve('two_factor_confirm');
    $confirmFields = is_array($confirmSchema['fields'] ?? null) ? $confirmSchema['fields'] : [];
    $confirmSubmit = is_array($confirmSchema['submit'] ?? null) ? $confirmSchema['submit'] : [];
    $confirmSubmitLabel = (string) ($confirmSubmit['label'] ?? 'Confirm setup');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $qrCodeUrl = $otpUri !== ''
        ? 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($otpUri)
        : '';
@endphp

<x-dynamic-component
        :component="$layoutComponent"
        :page-key="$pageKey"
        :page-config="$page"
        :page-title="$title"
        :heading="$heading"
>
    <x-dynamic-component :component="$containerComponent" size="lg">
        <div class="authkit-page-stack">

            @if ($status !== '')
                <x-dynamic-component :component="$alertComponent" variant="success">
                    {{ $status }}
                </x-dynamic-component>
            @endif

            @if ($error !== '')
                <x-dynamic-component :component="$alertComponent" variant="error">
                    {{ $error }}
                </x-dynamic-component>
            @endif

            <x-dynamic-component :component="$errorsComponent" />

            @if (! $enabled)
                <x-dynamic-component
                        :component="$settingsSectionComponent"
                        title="Set up two-factor authentication"
                        description="Scan the QR code with your authenticator app or copy the setup key manually, then enter the generated code below to confirm setup."
                >
                    <x-dynamic-component :component="$cardComponent">
                        <div class="authkit-page-stack">
                            @if ($supportsSecret && $qrCodeUrl !== '')
                                <div class="authkit-two-factor-setup__qr">
                                    <img
                                            src="{{ $qrCodeUrl }}"
                                            alt="Two-factor authenticator QR code"
                                            class="authkit-two-factor-setup__qr-image"
                                    >
                                </div>
                            @endif

                            @if ($supportsSecret && $secret !== '')
                                <div class="authkit-two-factor-setup__secret">
                                    <div class="authkit-two-factor-setup__label">
                                        Setup key
                                    </div>

                                    <div class="authkit-two-factor-setup__value">
                                        {{ $secret }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </x-dynamic-component>
                </x-dynamic-component>

                <x-dynamic-component
                        :component="$settingsSectionComponent"
                        title="Confirm setup"
                        description="Enter the authentication code from your authenticator app to finish enabling two-factor authentication."
                >
                    <x-dynamic-component :component="$cardComponent">
                        <form method="post" action="{{ $confirmAction }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
                        @csrf

                        <x-dynamic-component
                                :component="$fieldsComponent"
                                :fields="$confirmFields"
                        />

                        <div class="authkit-form-actions">
                            <x-dynamic-component :component="$buttonComponent" type="submit">
                                {{ $confirmSubmitLabel }}
                            </x-dynamic-component>
                        </div>
                        </form>
                    </x-dynamic-component>
                </x-dynamic-component>

                <x-dynamic-component
                        :component="$settingsSectionComponent"
                        title="Recovery codes"
                        description="Generate recovery codes you can use if you lose access to your authenticator device."
                >
                    <x-dynamic-component :component="$cardComponent">
                        <div class="authkit-page-stack">
                            <p class="authkit-app-text">
                                Recovery codes available: {{ $recoveryCodesCount }}
                            </p>

                            <form method="post" action="{{ $regenerateRecoveryAction }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
                            @csrf

                            <div class="authkit-form-actions">
                                <x-dynamic-component :component="$buttonComponent" type="submit">
                                    Generate recovery codes
                                </x-dynamic-component>
                            </div>
                            </form>
                        </div>
                    </x-dynamic-component>
                </x-dynamic-component>
            @else
                <x-dynamic-component
                        :component="$settingsSectionComponent"
                        title="Disable two-factor authentication"
                        description="Two-factor authentication is currently active on your account. You can disable it here if needed."
                >
                    <x-dynamic-component :component="$cardComponent">
                        <div class="authkit-page-stack">
                            <p class="authkit-app-text">
                                Status: {{ $confirmed ? 'Enabled and confirmed' : 'Enabled' }}
                            </p>

                            <p class="authkit-app-text">
                                Recovery codes available: {{ $recoveryCodesCount }}
                            </p>

                            <form method="post" action="{{ $disableAction }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
                            @csrf

                            <div class="authkit-form-actions">
                                <x-dynamic-component :component="$buttonComponent" type="submit">
                                    Disable two-factor authentication
                                </x-dynamic-component>
                            </div>
                            </form>
                        </div>
                    </x-dynamic-component>
                </x-dynamic-component>
            @endif

            <div class="authkit-inline-actions">
                <x-dynamic-component
                        :component="$linkComponent"
                        :href="$securityUrl"
                >
                    Back to security
                </x-dynamic-component>
            </div>
        </div>
    </x-dynamic-component>
</x-dynamic-component>