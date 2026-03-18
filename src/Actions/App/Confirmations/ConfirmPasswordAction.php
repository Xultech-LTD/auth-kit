<?php

namespace Xul\AuthKit\Actions\App\Confirmations;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Hash;
use Throwable;
use Xul\AuthKit\Concerns\Actions\InteractsWithMappedPayload;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;

/**
 * ConfirmPasswordAction
 *
 * Verifies the current authenticated user's password for AuthKit's
 * password-based step-up confirmation flow.
 *
 * Responsibilities:
 * - Ensure a valid authenticated user object is present.
 * - Read normalized confirm-password data from the mapped attributes bucket.
 * - Ensure the user exposes a retrievable password hash.
 * - Verify the submitted current password against the stored hash.
 * - Persist mapper-approved attributes when the user model supports
 *   AuthKit mapped persistence.
 * - Persist a fresh password-confirmation timestamp into session on success.
 * - Clear transient confirmation navigation metadata after success.
 * - Resolve the appropriate post-confirmation redirect target.
 * - Return a standardized AuthKitActionResult for success and failure paths.
 *
 * Notes:
 * - Session persistence is handled inside this action so the action remains
 *   self-contained and reusable even when consumers swap controllers.
 * - Redirect intent is resolved from the stored intended URL when present,
 *   otherwise the configured fallback route is used.
 * - Password confirmation does not persist fields by default because the
 *   packaged mapper marks them as non-persistable. This action remains
 *   persistence-aware so consumer-defined mappers can opt in to persistence.
 */
final class ConfirmPasswordAction
{
    use InteractsWithMappedPayload;

    /**
     * Handle the password confirmation attempt.
     *
     * @param  mixed  $user
     * @param  array<string, mixed>  $data
     * @param  Session  $session
     * @return AuthKitActionResult
     * @throws Throwable
     */
    public function handle(mixed $user, array $data, Session $session): AuthKitActionResult
    {
        if (! is_object($user)) {
            return AuthKitActionResult::failure(
                message: 'Unable to confirm password for the current user.',
                status: 401,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('unauthenticated', 'Unable to confirm password for the current user.'),
                ],
                redirect: $this->confirmPasswordRedirect()
            );
        }

        $attributes = $this->payloadAttributes($data);

        $password = trim((string) ($attributes['password'] ?? ''));

        if ($password === '') {
            return AuthKitActionResult::validationFailure(
                message: 'The given data was invalid.',
                errors: [
                    AuthKitError::validation('password', 'The password field is required.'),
                ],
                fields: [
                    'password' => ['The password field is required.'],
                ],
                status: 422,
                flow: AuthKitFlowStep::failed(),
                redirect: $this->confirmPasswordRedirect()
            );
        }

        if (! method_exists($user, 'getAuthPassword')) {
            return AuthKitActionResult::failure(
                message: 'This user model does not support password confirmation.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make(
                        'password_confirmation_not_supported',
                        'This user model does not support password confirmation.'
                    ),
                ],
                redirect: $this->confirmPasswordRedirect()
            );
        }

        $storedHash = (string) $user->getAuthPassword();

        if ($storedHash === '' || ! Hash::check($password, $storedHash)) {
            return AuthKitActionResult::failure(
                message: 'The provided password is incorrect.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::validation(
                        'password',
                        'The provided password is incorrect.',
                        'invalid_password'
                    ),
                ],
                redirect: $this->confirmPasswordRedirect()
            );
        }

        /**
         * Intentionally persistence-aware.
         *
         * Confirm-password does not persist fields by default because the packaged
         * mapper marks all fields as non-persistable. This call remains here so the
         * action continues to work correctly if a consumer extends the mapper and
         * marks additional attributes as persistable.
         */
        $this->persistMappedAttributesIfSupported($user, 'confirm_password', $data);

        $redirect = $this->successRedirect($session);

        $this->persistSuccessfulConfirmation($session);

        return AuthKitActionResult::success(
            message: 'Password confirmed successfully.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $redirect,
            payload: AuthKitPublicPayload::make([
                'confirmed' => true,
                'type' => 'password',
            ])
        );
    }

    /**
     * Persist a fresh password-confirmation marker and clear transient
     * confirmation navigation metadata.
     *
     * @param  Session  $session
     * @return void
     */
    protected function persistSuccessfulConfirmation(Session $session): void
    {
        $sessionConfig = (array) data_get(config('authkit.confirmations', []), 'session', []);

        $passwordKey = (string) ($sessionConfig['password_key'] ?? 'authkit.confirmed.password_at');
        $intendedKey = (string) ($sessionConfig['intended_key'] ?? 'authkit.confirmation.intended');
        $typeKey = (string) ($sessionConfig['type_key'] ?? 'authkit.confirmation.type');

        $session->put($passwordKey, now()->timestamp);
        $session->forget([$intendedKey, $typeKey]);
    }

    /**
     * Resolve the redirect used when confirmation fails.
     *
     * @return AuthKitRedirect
     */
    protected function confirmPasswordRedirect(): AuthKitRedirect
    {
        $routeName = (string) data_get(
            config('authkit.route_names.web', []),
            'confirm_password',
            'authkit.web.confirm.password'
        );

        return AuthKitRedirect::route(
            routeName: $routeName,
            parameters: [],
            url: route($routeName)
        );
    }

    /**
     * Resolve the redirect used after successful confirmation.
     *
     * Resolution order:
     * - Stored intended URL from session
     * - Configured confirmation fallback route
     *
     * @param  Session  $session
     * @return AuthKitRedirect
     */
    protected function successRedirect(Session $session): AuthKitRedirect
    {
        $sessionConfig = (array) data_get(config('authkit.confirmations', []), 'session', []);
        $intendedKey = (string) ($sessionConfig['intended_key'] ?? 'authkit.confirmation.intended');

        $intendedUrl = $session->get($intendedKey);

        if (is_string($intendedUrl) && trim($intendedUrl) !== '') {
            return AuthKitRedirect::url(trim($intendedUrl));
        }

        $fallbackRoute = (string) data_get(
            config('authkit.confirmations.routes', []),
            'fallback',
            'authkit.web.dashboard'
        );

        return AuthKitRedirect::route(
            routeName: $fallbackRoute,
            parameters: [],
            url: route($fallbackRoute)
        );
    }
}