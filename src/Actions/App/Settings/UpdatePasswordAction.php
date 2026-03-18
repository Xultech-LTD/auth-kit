<?php

namespace Xul\AuthKit\Actions\App\Settings;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Hash;
use Xul\AuthKit\Contracts\PasswordReset\PasswordUpdaterContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;

/**
 * UpdatePasswordAction
 *
 * Updates the password of an already-authenticated user from the AuthKit
 * security/settings area.
 *
 * Responsibilities:
 * - Validate that the provided authenticated user is usable.
 * - Verify the submitted current password against the authenticated user.
 * - Persist the new password through the configured PasswordUpdaterContract.
 * - Optionally log out other devices when requested by the user.
 * - Return a standardized AuthKitActionResult for both success and failure.
 *
 * Design notes:
 * - This action intentionally does not resolve users from identity values,
 *   because password update is an authenticated-user operation.
 * - Password persistence is delegated to PasswordUpdaterContract so consuming
 *   applications may customize hashing, auditing, password history, or token
 *   invalidation behavior through existing AuthKit extension points.
 * - Redirect intent is returned as part of the result contract so controllers
 *   remain thin and transport-focused.
 */
final class UpdatePasswordAction
{
    /**
     * Create a new instance.
     *
     * @param  PasswordUpdaterContract  $updater
     * @param  AuthFactory  $auth
     */
    public function __construct(
        protected PasswordUpdaterContract $updater,
        protected AuthFactory $auth,
    ) {}

    /**
     * Execute the password update operation.
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
                redirect: $this->securityRedirect()
            );
        }

        $currentPassword = (string) ($data['current_password'] ?? '');
        $newPassword = (string) ($data['password'] ?? '');
        $logoutOtherDevices = (bool) ($data['logout_other_devices'] ?? false);

        $existingHash = (string) $user->getAuthPassword();

        if ($currentPassword === '' || ! Hash::check($currentPassword, $existingHash)) {
            return AuthKitActionResult::validationFailure(
                message: 'The current password you entered is incorrect.',
                errors: [
                    AuthKitError::validation(
                        'current_password',
                        'The current password you entered is incorrect.',
                        'invalid_current_password'
                    ),
                ],
                fields: [
                    'current_password' => ['The current password you entered is incorrect.'],
                ],
                status: 422,
                flow: AuthKitFlowStep::failed(),
                redirect: $this->securityRedirect()
            );
        }

        if ($logoutOtherDevices) {
            $this->logoutOtherDevices($currentPassword);
        }

        $refreshRememberToken = (bool) data_get(
            config('authkit.password_reset.password_updater', []),
            'refresh_remember_token',
            true
        );

        $this->updater->update($user, $newPassword, $refreshRememberToken);

        return AuthKitActionResult::success(
            message: $logoutOtherDevices
                ? 'Password updated successfully. Other devices have been logged out.'
                : 'Password updated successfully.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $this->securityRedirect(),
            payload: AuthKitPublicPayload::make([
                'user_id' => (string) $user->getAuthIdentifier(),
                'password_updated' => true,
                'logout_other_devices' => $logoutOtherDevices,
            ])
        );
    }

    /**
     * Log out other active devices for the current guard when supported.
     *
     * Notes:
     * - Laravel session guards expose logoutOtherDevices().
     * - This method is treated as best-effort behavior and is skipped when the
     *   current guard does not support it.
     *
     * @param  string  $currentPassword
     * @return void
     */
    protected function logoutOtherDevices(string $currentPassword): void
    {
        $guardName = (string) config('authkit.auth.guard', 'web');
        $guard = $this->auth->guard($guardName);

        if (! $guard instanceof StatefulGuard) {
            return;
        }

        if (! method_exists($guard, 'logoutOtherDevices')) {
            return;
        }

        $guard->logoutOtherDevices($currentPassword);
    }

    /**
     * Resolve the standard security-page redirect.
     *
     * @return AuthKitRedirect
     */
    protected function securityRedirect(): AuthKitRedirect
    {
        $routeName = (string) data_get(
            config('authkit.route_names.web', []),
            'security',
            'authkit.web.settings.security'
        );

        return AuthKitRedirect::route(
            routeName: $routeName,
            parameters: [],
            url: route($routeName)
        );
    }
}