<?php

namespace Xul\AuthKit\Actions\App\Settings;

use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Support\TwoFactor\TwoFactorManager;

/**
 * RegenerateTwoFactorRecoveryCodesAction
 *
 * Regenerates two-factor recovery codes for an already-authenticated user from
 * the AuthKit security/settings area.
 *
 * Responsibilities:
 * - Validate that the provided authenticated user is usable.
 * - Resolve the active two-factor driver through TwoFactorManager so consumer
 *   driver overrides remain respected.
 * - Confirm that two-factor authentication is enabled for the current user.
 * - Verify the submitted authenticator code before allowing regeneration.
 * - Generate a fresh set of recovery codes through the active driver.
 * - Persist the new recovery codes through the user model's AuthKit trait API.
 * - Return a standardized AuthKitActionResult for both success and failure.
 *
 * Design notes:
 * - This action intentionally relies on the configured driver manager rather
 *   than assuming the packaged TOTP driver directly.
 * - Persistence of recovery codes is delegated to the user model's
 *   setTwoFactorRecoveryCodes() method when available, which aligns with the
 *   HasAuthKitTwoFactor trait contract and keeps hashing behavior centralized.
 * - Redirect intent is returned as part of the result contract so controllers
 *   remain thin and transport-focused.
 */
final class RegenerateTwoFactorRecoveryCodesAction
{
    /**
     * Create a new instance.
     *
     * @param  TwoFactorManager  $manager
     */
    public function __construct(
        protected TwoFactorManager $manager,
    ) {}

    /**
     * Execute the recovery-code regeneration operation.
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

        $code = trim((string) ($data['code'] ?? ''));

        try {
            $driver = $this->manager->driver();
        } catch (Throwable $e) {
            report($e);

            return AuthKitActionResult::failure(
                message: 'Unable to resolve the active two-factor driver.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('two_factor_driver_unavailable', 'Unable to resolve the active two-factor driver.'),
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

        if ($code === '' || ! $driver->verify($user, $code)) {
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

        try {
            $recoveryCodes = $driver->generateRecoveryCodes();

            if (method_exists($user, 'setTwoFactorRecoveryCodes')) {
                $user->setTwoFactorRecoveryCodes($recoveryCodes);
            } else {
                $column = (string) config('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
                data_set($user, $column, $recoveryCodes);
            }

            if (method_exists($user, 'save')) {
                $user->save();
            }

            session()->flash('authkit.two_factor.recovery_codes', $recoveryCodes);

        } catch (Throwable $e) {
            report($e);

            return AuthKitActionResult::failure(
                message: 'Unable to regenerate recovery codes at this time.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make(
                        'two_factor_recovery_regeneration_failed',
                        'Unable to regenerate recovery codes at this time.'
                    ),
                ],
                redirect: $this->twoFactorSettingsRedirect()
            );
        }

        $responseKey = (string) data_get(
            config('authkit.two_factor.recovery_codes', []),
            'response_key',
            'recovery_codes'
        );

        return AuthKitActionResult::success(
            message: 'Recovery codes regenerated successfully.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $this->twoFactorSettingsRedirect(),
            payload: AuthKitPublicPayload::make([
                $responseKey => $recoveryCodes,
                'regenerated' => true,
            ])
        );
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