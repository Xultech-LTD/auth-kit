<?php

namespace Xul\AuthKit\Actions\App\Settings;

use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;
use Xul\AuthKit\Concerns\Actions\InteractsWithMappedPayload;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Support\TwoFactor\TwoFactorManager;

/**
 * ConfirmTwoFactorSetupAction
 *
 * Finalizes authenticated two-factor setup after the user submits a valid code
 * from their authenticator application.
 *
 * Responsibilities:
 * - Resolve the active two-factor driver through TwoFactorManager.
 * - Read normalized setup-confirmation input from the mapped attributes bucket.
 * - Verify the submitted code against the active driver.
 * - Generate a fresh set of recovery codes after successful confirmation.
 * - Persist enabled state, confirmed timestamp, configured methods, and
 *   recovery codes onto the user model.
 * - Persist mapper-approved attributes when the user model supports
 *   AuthKit mapped persistence.
 * - Flash recovery codes for standard web flows so the next page can display
 *   them for download or safe storage.
 * - Return a standardized AuthKitActionResult for both success and failure.
 *
 * Notes:
 * - Persistence is intentionally handled inside the action so consumers may
 *   replace the packaged controller without losing critical behavior.
 * - Recovery codes are returned in the public payload for JSON consumers and
 *   flashed to session for redirect-based web flows.
 * - This action assumes the setup flow has already provisioned any required
 *   secret for the active driver.
 */
final class ConfirmTwoFactorSetupAction
{
    use InteractsWithMappedPayload;

    /**
     * Create a new instance.
     *
     * @param  TwoFactorManager  $twoFactor
     */
    public function __construct(
        protected TwoFactorManager $twoFactor
    ) {}

    /**
     * Confirm two-factor setup for the authenticated user.
     *
     * @param  mixed  $user
     * @param  array<string, mixed>  $data
     * @return AuthKitActionResult
     */
    public function handle(mixed $user, array $data): AuthKitActionResult
    {
        if (! is_object($user)) {
            return AuthKitActionResult::failure(
                message: 'Authenticated user could not be resolved.',
                status: 401,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('unauthenticated', 'Authenticated user could not be resolved.'),
                ],
                redirect: $this->settingsRedirect()
            );
        }

        $attributes = $this->payloadAttributes($data);
        $code = trim((string) ($attributes['code'] ?? ''));

        if ($code === '') {
            return AuthKitActionResult::validationFailure(
                message: 'The given data was invalid.',
                errors: [
                    AuthKitError::validation('code', 'The authentication code field is required.'),
                ],
                fields: [
                    'code' => ['The authentication code field is required.'],
                ],
                redirect: $this->settingsRedirect()
            );
        }

        try {
            $driver = $this->twoFactor->driver();
        } catch (Throwable) {
            return AuthKitActionResult::failure(
                message: 'Two-factor driver could not be resolved.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('two_factor_driver_unavailable', 'Two-factor driver could not be resolved.'),
                ],
                redirect: $this->settingsRedirect()
            );
        }

        if (! $driver->verify($user, $code)) {
            return AuthKitActionResult::failure(
                message: 'The provided authentication code is invalid.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::validation(
                        'code',
                        'The provided authentication code is invalid.',
                        'invalid_two_factor_code'
                    ),
                ],
                redirect: $this->settingsRedirect(),
                payload: AuthKitPublicPayload::withFields([
                    'code' => ['The provided authentication code is invalid.'],
                ])
            );
        }

        $methods = $this->resolveDriverMethods($driver, $user);
        $recoveryCodes = $this->generateRecoveryCodes($driver);

        $this->persistConfirmedSetup(
            user: $user,
            methods: $methods,
            recoveryCodes: $recoveryCodes
        );

        /**
         * Intentionally persistence-aware.
         *
         * Confirm-two-factor-setup does not persist mapped fields by default
         * because the packaged mapper marks them as non-persistable. This call
         * remains here so consumer-supplied mapper extensions can persist extra
         * attributes without replacing the action.
         */
        $this->persistMappedAttributesIfSupported($user, 'two_factor_confirm', $data);

        $flashKey = (string) data_get(
            config('authkit.two_factor.recovery_codes', []),
            'flash_key',
            'authkit.two_factor.recovery_codes'
        );

        session()->flash($flashKey, $recoveryCodes);

        $responseKey = (string) data_get(
            config('authkit.two_factor.recovery_codes', []),
            'response_key',
            'recovery_codes'
        );

        return AuthKitActionResult::success(
            message: 'Two-factor authentication has been enabled. Save your recovery codes in a secure location.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $this->settingsRedirect(),
            payload: AuthKitPublicPayload::make([
                'confirmed' => true,
                'methods' => $methods,
                $responseKey => $recoveryCodes,
            ])
        );
    }

    /**
     * Resolve the configured redirect back to the two-factor settings page.
     *
     * @return AuthKitRedirect
     */
    protected function settingsRedirect(): AuthKitRedirect
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

    /**
     * Resolve the effective methods reported by the active driver.
     *
     * Falls back to configured methods when the driver returns an empty list.
     *
     * @param  object  $driver
     * @param  object  $user
     * @return array<int, string>
     */
    protected function resolveDriverMethods(object $driver, object $user): array
    {
        $methods = [];

        try {
            $methods = (array) $driver->methods($user);
        } catch (Throwable) {
            $methods = [];
        }

        $methods = array_values(array_filter(
            $methods,
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));

        if ($methods === []) {
            $methods = array_values(array_filter(
                (array) config('authkit.two_factor.methods', ['totp']),
                static fn ($value): bool => is_string($value) && trim($value) !== ''
            ));
        }

        return array_values(array_unique($methods));
    }

    /**
     * Generate a fresh set of recovery codes from the active driver.
     *
     * @param  object  $driver
     * @return array<int, string>
     */
    protected function generateRecoveryCodes(object $driver): array
    {
        try {
            $codes = (array) $driver->generateRecoveryCodes();
        } catch (Throwable $e) {
            throw new RuntimeException('AuthKit failed to generate two-factor recovery codes.', previous: $e);
        }

        $codes = array_values(array_filter(
            $codes,
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));

        if ($codes === []) {
            throw new RuntimeException('AuthKit failed to generate two-factor recovery codes.');
        }

        return $codes;
    }

    /**
     * Persist the newly confirmed two-factor setup onto the user model.
     *
     * Persistence includes:
     * - enabling two-factor
     * - saving active methods
     * - saving recovery codes
     * - setting confirmed_at timestamp when configured
     *
     * @param  object  $user
     * @param  array<int, string>  $methods
     * @param  array<int, string>  $recoveryCodes
     * @return void
     */
    protected function persistConfirmedSetup(object $user, array $methods, array $recoveryCodes): void
    {
        $enabledColumn = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');
        $methodsColumn = (string) config('authkit.two_factor.columns.methods', 'two_factor_methods');
        $recoveryColumn = (string) config('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
        $confirmedAtColumn = (string) config('authkit.two_factor.columns.confirmed_at', 'two_factor_confirmed_at');

        if (method_exists($user, 'enableTwoFactor')) {
            $user->enableTwoFactor();
        } elseif (method_exists($user, 'setAttribute')) {
            $user->setAttribute($enabledColumn, true);
        } else {
            data_set($user, $enabledColumn, true);
        }

        if (method_exists($user, 'setTwoFactorMethods')) {
            $user->setTwoFactorMethods($methods);
        } elseif (method_exists($user, 'setAttribute')) {
            $user->setAttribute($methodsColumn, $methods);
        } else {
            data_set($user, $methodsColumn, $methods);
        }

        if (method_exists($user, 'setTwoFactorRecoveryCodes')) {
            $user->setTwoFactorRecoveryCodes($recoveryCodes);
        } elseif (method_exists($user, 'setAttribute')) {
            $user->setAttribute($recoveryColumn, $recoveryCodes);
        } else {
            data_set($user, $recoveryColumn, $recoveryCodes);
        }

        if ($confirmedAtColumn !== '') {
            $value = Carbon::now();

            if (method_exists($user, 'setAttribute')) {
                $user->setAttribute($confirmedAtColumn, $value);
            } else {
                data_set($user, $confirmedAtColumn, $value);
            }
        }

        if (method_exists($user, 'save')) {
            $user->save();
        }
    }
}