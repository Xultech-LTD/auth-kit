<?php

namespace Xul\AuthKit\Actions\App\Settings;

use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;
use Xul\AuthKit\Concerns\Actions\InteractsWithMappedPayload;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Support\TwoFactor\TwoFactorManager;

/**
 * DisableTwoFactorAction
 *
 * Disables two-factor authentication for an already-authenticated user from the
 * AuthKit security/settings area.
 *
 * Responsibilities:
 * - Validate that the provided authenticated user is usable.
 * - Resolve the active two-factor driver through TwoFactorManager so consumer
 *   driver overrides remain respected.
 * - Confirm that two-factor authentication is enabled for the current user.
 * - Read disable credentials from the normalized mapped payload.
 * - Accept either an authenticator code or recovery code as the confirmation
 *   credential for disabling two-factor.
 * - Verify the submitted credential through the active driver.
 * - Consume the submitted recovery code when that path is used.
 * - Persist mapper-approved attributes when the user model supports mapped persistence.
 * - Disable two-factor and clear related user state.
 * - Return a standardized AuthKitActionResult for both success and failure.
 *
 * Design notes:
 * - This action intentionally relies on the configured driver manager rather
 *   than assuming the packaged TOTP driver directly.
 * - Persistence is delegated to the user model's AuthKit trait API where
 *   available so consuming applications remain compatible with config-driven
 *   column mappings and storage behavior.
 * - Redirect intent is returned as part of the result contract so controllers
 *   remain thin and transport-focused.
 */
final class DisableTwoFactorAction
{
    use InteractsWithMappedPayload;

    /**
     * Create a new instance.
     *
     * @param  TwoFactorManager  $manager
     */
    public function __construct(
        protected TwoFactorManager $manager,
    ) {}

    /**
     * Execute the two-factor disable operation.
     *
     * @param  mixed  $user
     * @param  array<string, mixed>  $data
     * @return AuthKitActionResult
     */
    public function handle(mixed $user, array $data): AuthKitActionResult
    {
        if (! $user instanceof Authenticatable) {
            return AuthKitActionResult::failure(
                message: 'Unable to resolve the authenticated user.',
                status: 401,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('unauthenticated_user', 'Unable to resolve the authenticated user.'),
                ],
                redirect: $this->twoFactorSettingsRedirect()
            );
        }

        $attributes = $this->payloadAttributes($data);

        $code = trim((string) ($attributes['code'] ?? ''));
        $recoveryCode = trim((string) ($attributes['recovery_code'] ?? ''));

        try {
            $driver = $this->manager->driver();
        } catch (Throwable $e) {
            report($e);

            return AuthKitActionResult::failure(
                message: 'Unable to resolve the active two-factor driver.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make(
                        'two_factor_driver_unavailable',
                        'Unable to resolve the active two-factor driver.'
                    ),
                ],
                redirect: $this->twoFactorSettingsRedirect()
            );
        }

        if (! $driver->enabled($user)) {
            return AuthKitActionResult::failure(
                message: 'Two-factor authentication is not enabled for this account.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make(
                        'two_factor_not_enabled',
                        'Two-factor authentication is not enabled for this account.'
                    ),
                ],
                redirect: $this->twoFactorSettingsRedirect()
            );
        }

        if ($code === '' && $recoveryCode === '') {
            return AuthKitActionResult::validationFailure(
                message: 'Provide either an authentication code or a recovery code.',
                errors: [
                    AuthKitError::validation(
                        'code',
                        'Provide either an authentication code or a recovery code.',
                        'two_factor_disable_credential_required'
                    ),
                ],
                fields: [
                    'code' => ['Provide either an authentication code or a recovery code.'],
                ],
                status: 422,
                flow: AuthKitFlowStep::failed(),
                redirect: $this->twoFactorSettingsRedirect()
            );
        }

        if ($code !== '') {
            if (! $driver->verify($user, $code)) {
                return AuthKitActionResult::validationFailure(
                    message: 'The authentication code you entered is invalid.',
                    errors: [
                        AuthKitError::validation(
                            'code',
                            'The authentication code you entered is invalid.',
                            'invalid_two_factor_code'
                        ),
                    ],
                    fields: [
                        'code' => ['The authentication code you entered is invalid.'],
                    ],
                    status: 422,
                    flow: AuthKitFlowStep::failed(),
                    redirect: $this->twoFactorSettingsRedirect()
                );
            }
        } else {
            if (
                ! method_exists($driver, 'consumeRecoveryCode') ||
                ! $driver->consumeRecoveryCode($user, $recoveryCode)
            ) {
                return AuthKitActionResult::validationFailure(
                    message: 'The recovery code you entered is invalid.',
                    errors: [
                        AuthKitError::validation(
                            'recovery_code',
                            'The recovery code you entered is invalid.',
                            'invalid_two_factor_recovery_code'
                        ),
                    ],
                    fields: [
                        'recovery_code' => ['The recovery code you entered is invalid.'],
                    ],
                    status: 422,
                    flow: AuthKitFlowStep::failed(),
                    redirect: $this->twoFactorSettingsRedirect()
                );
            }
        }

        try {
            /**
             * Intentionally persistence-aware.
             *
             * The packaged disable-two-factor mapper does not persist fields by default.
             * This hook remains so consumer-defined mappers may mark one or more
             * disable-flow attributes as persistable for audit or analytics purposes.
             */
            $this->persistMappedAttributesIfSupported($user, 'two_factor_disable', $data);

            $this->disableTwoFactorState($user);
        } catch (Throwable $e) {
            report($e);

            return AuthKitActionResult::failure(
                message: 'Unable to disable two-factor authentication at this time.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make(
                        'two_factor_disable_failed',
                        'Unable to disable two-factor authentication at this time.'
                    ),
                ],
                redirect: $this->twoFactorSettingsRedirect()
            );
        }

        return AuthKitActionResult::success(
            message: 'Two-factor authentication has been disabled successfully.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $this->twoFactorSettingsRedirect(),
            payload: AuthKitPublicPayload::make([
                'two_factor_disabled' => true,
                'used_recovery_code' => $recoveryCode !== '',
            ])
        );
    }

    /**
     * Disable two-factor and clear related persisted state.
     *
     * @param  object  $user
     * @return void
     */
    protected function disableTwoFactorState(object $user): void
    {
        $enabledColumn = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');
        $secretColumn = (string) config('authkit.two_factor.columns.secret', 'two_factor_secret');
        $recoveryCodesColumn = (string) config('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
        $methodsColumn = (string) config('authkit.two_factor.columns.methods', 'two_factor_methods');
        $confirmedAtColumn = (string) config('authkit.two_factor.columns.confirmed_at', 'two_factor_confirmed_at');

        if (method_exists($user, 'disableTwoFactor')) {
            $user->disableTwoFactor();
        } else {
            data_set($user, $enabledColumn, false);
        }

        if (method_exists($user, 'setTwoFactorSecret')) {
            $user->setTwoFactorSecret('');
        } else {
            data_set($user, $secretColumn, null);
        }

        if (method_exists($user, 'setTwoFactorRecoveryCodes')) {
            $user->setTwoFactorRecoveryCodes([]);
        } else {
            data_set($user, $recoveryCodesColumn, null);
        }

        if (method_exists($user, 'setTwoFactorMethods')) {
            $user->setTwoFactorMethods([]);
        } else {
            data_set($user, $methodsColumn, []);
        }

        data_set($user, $confirmedAtColumn, null);

        if (method_exists($user, 'save')) {
            $user->save();
        }
    }

    /**
     * Resolve the standard two-factor-settings-page redirect.
     *
     * @return AuthKitRedirect
     */
    protected function twoFactorSettingsRedirect(): AuthKitRedirect
    {
        $routeName = (string) data_get(
            config('authkit.route_names.web', []),
            'two_factor_settings',
            'authkit.web.settings.two_factor'
        );

        return AuthKitRedirect::route(
            routeName: $routeName,
            parameters: [],
            url: route($routeName)
        );
    }
}