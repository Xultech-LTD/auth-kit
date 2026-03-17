{{--
/**
 * Page: App Security
 *
 * Authenticated AuthKit security page.
 *
 * Responsibilities:
 * - Render the authenticated app layout.
 * - Display security overview content.
 * - Show two-factor status with navigation to dedicated management page.
 * - Show sessions shortcut with navigation to the sessions page.
 * - Resolve and render the password-update schema as the only packaged form on the page.
 *
 * Expected data:
 * - $title
 * - $heading
 * - $pageKey
 * - $currentPage
 * - $sections
 * - $twoFactorEnabled
 * - $twoFactorMethods
 * - $hasRecoveryCodes
 */
--}}

@php
    $c = (array) config('authkit.components', []);
    $apiNames = (array) config('authkit.route_names.api', []);
    $webNames = (array) config('authkit.route_names.web', []);

    $formsMode = (string) config('authkit.forms.mode', 'http');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');
    $isAjax = $formsMode === 'ajax';

    $passwordUpdateAction = (string) ($apiNames['password_update'] ?? 'authkit.api.settings.password.update');
    $twoFactorPage = (string) ($webNames['two_factor_settings'] ?? 'authkit.web.settings.two_factor');
    $sessionsPage = (string) ($webNames['sessions'] ?? 'authkit.web.settings.sessions');

    $schema = app(\Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract::class)->resolve('password_update');

    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
    $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
    $submitLabel = (string) ($submit['label'] ?? 'Update password');

    $status = (string) session('status', session('message', ''));
    $error = (string) session('error', '');

    $sections = is_array($sections ?? null) ? $sections : [];
    $showPasswordUpdate = (bool) ($sections['password_update'] ?? true);

    $twoFactorEnabled = (bool) ($twoFactorEnabled ?? false);
    $twoFactorMethods = array_values(array_filter(
        (array) ($twoFactorMethods ?? []),
        static fn ($method) => is_string($method) && trim($method) !== ''
    ));
    $hasRecoveryCodes = (bool) ($hasRecoveryCodes ?? false);

    $settingsSectionComponent = (string) data_get($c, 'settings_section', 'authkit::app.settings.section');
    $fieldsComponent = (string) data_get($c, 'fields', 'authkit::form.fields');
@endphp

<x-authkit::app.layout
        :title="$title ?? 'Security'"
        :page-key="$pageKey ?? 'security'"
        :current-page="$currentPage ?? 'security'"
        :page-title="$title ?? 'Security'"
        :page-heading="$heading ?? 'Manage your account security'"
>
    <div class="authkit-dashboard authkit-dashboard__stack">
        <section class="authkit-dashboard__hero">
            <div class="authkit-dashboard__hero-copy">
                <div class="authkit-dashboard__eyebrow">
                    Account protection
                </div>

                <h2 class="authkit-dashboard__hero-title">
                    Security controls
                </h2>

                <p class="authkit-dashboard__hero-text">
                    Review how your account is protected, manage your authentication
                    settings, and keep your password up to date.
                </p>
            </div>

            <div class="authkit-dashboard__hero-meta">
                <div class="authkit-dashboard__hero-chip">
                    <span class="authkit-dashboard__hero-chip-label">Two-factor</span>
                    <span class="authkit-dashboard__hero-chip-value">
                        {{ $twoFactorEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>

                <div class="authkit-dashboard__hero-chip">
                    <span class="authkit-dashboard__hero-chip-label">Recovery codes</span>
                    <span class="authkit-dashboard__hero-chip-value">
                        {{ $hasRecoveryCodes ? 'Available' : 'Not generated' }}
                    </span>
                </div>
            </div>
        </section>

        <section class="authkit-dashboard__grid">
            <article class="authkit-dashboard-card">
                <div class="authkit-dashboard-card__header">
                    <div>
                        <h3 class="authkit-dashboard-card__title">Two-factor authentication</h3>
                        <p class="authkit-dashboard-card__text">
                            Add an extra verification step to help protect your account
                            from unauthorized access.
                        </p>
                    </div>
                </div>

                <div class="authkit-settings-links">
                    <a href="{{ route($twoFactorPage) }}" class="authkit-settings-links__item">
                        <span class="authkit-settings-links__title">
                            {{ $twoFactorEnabled ? 'Manage two-factor' : 'Set up two-factor' }}
                        </span>

                        <span class="authkit-settings-links__text">
                            Status:
                            {{ $twoFactorEnabled ? 'enabled' : 'disabled' }}.
                            @if (!empty($twoFactorMethods))
                                Active method{{ count($twoFactorMethods) > 1 ? 's' : '' }}:
                                {{ implode(', ', $twoFactorMethods) }}.
                            @else
                                Configure your authenticator settings and recovery options.
                            @endif
                        </span>
                    </a>
                </div>
            </article>

            <article class="authkit-dashboard-card">
                <div class="authkit-dashboard-card__header">
                    <div>
                        <h3 class="authkit-dashboard-card__title">Sessions</h3>
                        <p class="authkit-dashboard-card__text">
                            Review where your account is signed in and monitor active devices.
                        </p>
                    </div>
                </div>

                <div class="authkit-settings-links">
                    <a href="{{ route($sessionsPage) }}" class="authkit-settings-links__item">
                        <span class="authkit-settings-links__title">Review active sessions</span>
                        <span class="authkit-settings-links__text">
                            Open the sessions page to inspect recent activity across browsers and devices.
                        </span>
                    </a>
                </div>
            </article>
        </section>

        @if ($showPasswordUpdate)
            <x-dynamic-component
                    :component="$settingsSectionComponent"
                    title="Update password"
                    description="Change your current password to keep your account secure."
            >
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

                <x-dynamic-component :component="data_get($c, 'errors')" />

                <form method="post" action="{{ route($passwordUpdateAction) }}" @if($isAjax) {{ $ajaxAttr }}="1" @endif>
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
        @endif
    </div>
</x-authkit::app.layout>