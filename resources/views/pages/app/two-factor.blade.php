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
 * - Display newly generated recovery codes from either:
 *   - session flash (normal HTTP flow)
 *   - AJAX success payload (client-side enhancement)
 * - Respect the configured recovery-code flash/session key.
 * - Keep the recovery-code presentation section hidden until:
 *   - the current request contains flashed recovery codes, or
 *   - client-side JavaScript reveals the section after an AJAX success response.
 * - Provide a download action for recovery codes.
 * - Render separate two-factor disable forms for:
 *   - authenticator-code verification
 *   - recovery-code fallback verification
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

    $twoFactorConfig = (array) config('authkit.two_factor', []);
    $recoveryCodesConfig = (array) ($twoFactorConfig['recovery_codes'] ?? []);
    $recoveryFlashKey = (string) ($recoveryCodesConfig['flash_key'] ?? 'authkit.two_factor.recovery_codes');
    $hideRecoveryCodesWhenEmpty = (bool) ($recoveryCodesConfig['hide_when_empty'] ?? true);

    $confirmAction = (string) ($apiNames['two_factor_confirm'] ?? 'authkit.api.settings.two_factor.confirm');
    $disableAction = (string) ($apiNames['two_factor_disable'] ?? 'authkit.api.settings.two_factor.disable');
    $regenerateAction = (string) ($apiNames['two_factor_recovery_regenerate'] ?? 'authkit.api.settings.two_factor.recovery.regenerate');

    $confirmSchema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)->resolve('two_factor_confirm');
    $confirmFields = is_array($confirmSchema['fields'] ?? null) ? $confirmSchema['fields'] : [];
    $confirmSubmit = is_array($confirmSchema['submit'] ?? null) ? $confirmSchema['submit'] : [];
    $confirmSubmitLabel = (string) ($confirmSubmit['label'] ?? 'Confirm setup');

    $disableSchema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)->resolve('two_factor_disable');
    $disableFields = is_array($disableSchema['fields'] ?? null) ? $disableSchema['fields'] : [];
    $disableSubmit = is_array($disableSchema['submit'] ?? null) ? $disableSchema['submit'] : [];
    $disableSubmitLabel = (string) ($disableSubmit['label'] ?? 'Disable two-factor authentication');

    $disableRecoverySchema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)->resolve('two_factor_disable_recovery');
    $disableRecoveryFields = is_array($disableRecoverySchema['fields'] ?? null) ? $disableRecoverySchema['fields'] : [];
    $disableRecoverySubmit = is_array($disableRecoverySchema['submit'] ?? null) ? $disableRecoverySchema['submit'] : [];
    $disableRecoverySubmitLabel = (string) ($disableRecoverySubmit['label'] ?? 'Disable two-factor authentication');

    $regenerateSchema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)->resolve('two_factor_recovery_regenerate');
    $regenerateFields = is_array($regenerateSchema['fields'] ?? null) ? $regenerateSchema['fields'] : [];
    $regenerateSubmit = is_array($regenerateSchema['submit'] ?? null) ? $regenerateSchema['submit'] : [];
    $regenerateSubmitLabel = (string) ($regenerateSubmit['label'] ?? 'Regenerate recovery codes');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    /**
     * Newly generated recovery codes flashed by the action during normal
     * redirect-based web submissions.
     *
     * The flash key is configuration-driven so consumers may rename it without
     * editing this page template.
     *
     * @var array<int, string> $flashedRecoveryCodes
     */
    $flashedRecoveryCodes = array_values(array_filter(
        (array) session($recoveryFlashKey, []),
        static fn ($value): bool => is_string($value) && trim($value) !== ''
    ));

    $hasFlashedRecoveryCodes = $flashedRecoveryCodes !== [];
    $hideRecoveryCodesSection = $hideRecoveryCodesWhenEmpty && ! $hasFlashedRecoveryCodes;

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
    <div class="authkit-dashboard authkit-dashboard__stack" data-authkit-two-factor-settings>
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

        {{-- Newly generated recovery codes (SSR + AJAX target) --}}
            <div
                    data-authkit-two-factor-recovery-section
                    @if ($hideRecoveryCodesSection) hidden @endif
            >
                <x-dynamic-component
                        :component="$settingsSectionComponent"
                        title="New recovery codes"
                        description="These recovery codes are shown once. Save them somewhere secure before leaving this page."
                >
                    <div
                            class="authkit-two-factor-recovery-codes"
                            data-authkit-two-factor-recovery
                            data-authkit-two-factor-recovery-flash-key="{{ $recoveryFlashKey }}"
                            data-authkit-two-factor-recovery-response-key="{{ (string) (($recoveryCodesConfig['response_key'] ?? 'recovery_codes')) }}"
                    >
                        <div class="authkit-empty-state">
                            <div class="authkit-empty-state__title">Save these recovery codes now</div>

                            <p class="authkit-empty-state__text">
                                Each code can be used once to recover access to your account if you lose access to your authenticator app.
                            </p>
                        </div>

                        <div
                                class="authkit-app-session-card__agent"
                                data-authkit-two-factor-recovery-list
                        >@if ($hasFlashedRecoveryCodes){{ implode("\n", $flashedRecoveryCodes) }}@endif</div>

                        <div class="authkit-form-actions">
                            <x-dynamic-component
                                    :component="data_get($c, 'button')"
                                    type="button"
                                    variant="primary"
                                    data-authkit-two-factor-download
                            >
                                Download recovery codes
                            </x-dynamic-component>
                        </div>
                    </div>
                </x-dynamic-component>
            </div>

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

                <form
                        method="post"
                        action="{{ route($confirmAction) }}"
                @if($isAjax) {{ $ajaxAttr }}="1" @endif
                data-authkit-two-factor-confirm-form
                >
                @csrf

                <x-dynamic-component
                        :component="$fieldsComponent"
                        :fields="$confirmFields"
                />

                <div class="authkit-form-actions">
                    <x-dynamic-component :component="data_get($c, 'button')">
                        {{ $confirmSubmitLabel }}
                    </x-dynamic-component>
                </div>
                </form>
            </x-dynamic-component>
        @else
                <x-dynamic-component
                        :component="$settingsSectionComponent"
                        title="Recovery codes"
                        description="Regenerate recovery codes when needed. You must confirm with your authenticator code before a new set is issued."
                >
                    <div class="authkit-empty-state">
                        <div class="authkit-empty-state__title">Regenerate recovery codes</div>

                        <p class="authkit-empty-state__text">
                            Generating a new set will replace your existing recovery codes. Save the newly generated codes somewhere secure immediately after regeneration.
                        </p>
                    </div>

                    <form
                            method="post"
                            action="{{ route($regenerateAction) }}"
                    @if($isAjax) {{ $ajaxAttr }}="1" @endif
                    data-authkit-two-factor-regenerate-form
                    >
                    @csrf

                    @if ($regenerateFields !== [])
                        <x-dynamic-component
                                :component="$fieldsComponent"
                                :fields="$regenerateFields"
                        />
                    @endif

                    <div class="authkit-form-actions">
                        <x-dynamic-component :component="data_get($c, 'button')">
                            {{ $regenerateSubmitLabel }}
                        </x-dynamic-component>
                    </div>
                    </form>
                </x-dynamic-component>

            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Disable two-factor"
                    description="Turn off two-factor authentication for this account."
            >
                <div
                        class="authkit-empty-state"
                        data-authkit-two-factor-disable-note
                >
                    <div class="authkit-empty-state__title">Confirm before disabling</div>

                    <p class="authkit-empty-state__text">
                        Enter an authenticator code to disable two-factor authentication.
                    </p>

                    @if ($disableRecoveryFields !== [])
                        <p class="authkit-empty-state__text">
                            Can’t access your authenticator app?
                            <button
                                    type="button"
                                    class="authkit-link"
                                    data-authkit-two-factor-disable-toggle
                                    data-authkit-two-factor-disable-target="recovery"
                            >
                                Use a recovery code instead
                            </button>
                        </p>
                    @endif
                </div>

                <form
                        method="post"
                        action="{{ route($disableAction) }}"
                @if($isAjax) {{ $ajaxAttr }}="1" @endif
                data-authkit-two-factor-disable-form
                data-authkit-two-factor-disable-mode="code"
                >
                @csrf

                @if ($disableFields !== [])
                    <x-dynamic-component
                            :component="$fieldsComponent"
                            :fields="$disableFields"
                    />
                @endif

                <div class="authkit-form-actions">
                    <x-dynamic-component :component="data_get($c, 'button')">
                        {{ $disableSubmitLabel }}
                    </x-dynamic-component>
                </div>
                </form>

                @if ($disableRecoveryFields !== [])
                    <div
                            data-authkit-two-factor-disable-recovery
                            hidden
                    >
                        <div class="authkit-empty-state">
                            <div class="authkit-empty-state__title">Disable with recovery code</div>

                            <p class="authkit-empty-state__text">
                                Use one of your saved recovery codes if you no longer have access to your authenticator app.
                            </p>

                            <p class="authkit-empty-state__text">
                                <button
                                        type="button"
                                        class="authkit-link"
                                        data-authkit-two-factor-disable-toggle
                                        data-authkit-two-factor-disable-target="code"
                                >
                                    Back to authenticator code
                                </button>
                            </p>
                        </div>

                        <form
                                method="post"
                                action="{{ route($disableAction) }}"
                        @if($isAjax) {{ $ajaxAttr }}="1" @endif
                        data-authkit-two-factor-disable-form
                        data-authkit-two-factor-disable-mode="recovery"
                        >
                        @csrf

                        <x-dynamic-component
                                :component="$fieldsComponent"
                                :fields="$disableRecoveryFields"
                        />

                        <div class="authkit-form-actions">
                            <x-dynamic-component :component="data_get($c, 'button')">
                                {{ $disableRecoverySubmitLabel }}
                            </x-dynamic-component>
                        </div>
                        </form>
                    </div>
                @endif
            </x-dynamic-component>
        @endif
    </div>
</x-authkit::app.layout>