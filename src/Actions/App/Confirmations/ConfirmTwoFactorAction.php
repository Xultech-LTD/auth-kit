<?php

namespace Xul\AuthKit\Actions\App\Confirmations;

use Illuminate\Contracts\Session\Session;
use Throwable;
use Xul\AuthKit\Concerns\Actions\InteractsWithMappedPayload;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Support\TwoFactor\TwoFactorManager;

/**
 * ConfirmTwoFactorAction
 *
 * Verifies a one-time authentication code for AuthKit's authenticated
 * step-up two-factor confirmation flow.
 *
 * Responsibilities:
 * - Ensure a valid authenticated user object is present.
 * - Ensure the authenticated user has two-factor authentication enabled.
 * - Read the submitted confirmation code from the normalized mapped payload.
 * - Verify the submitted code using the active two-factor driver.
 * - Persist mapper-approved attributes when the user model supports
 *   AuthKit mapped persistence.
 * - Persist a fresh two-factor confirmation timestamp into session on success.
 * - Clear transient confirmation navigation metadata after success.
 * - Resolve the appropriate post-confirmation redirect target.
 * - Return a standardized AuthKitActionResult for success and failure paths.
 *
 * Notes:
 * - This action is distinct from the login-time two-factor challenge flow.
 * - This action is used only for already-authenticated users performing
 *   step-up confirmation before sensitive actions or pages.
 * - Session persistence is handled inside this action so the same action
 *   remains reusable across different transport layers or controllers.
 */
final class ConfirmTwoFactorAction
{
    use InteractsWithMappedPayload;

    /**
     * Create a new instance.
     *
     * @param  TwoFactorManager  $twoFactor
     */
    public function __construct(
        protected TwoFactorManager $twoFactor,
    ) {}

    /**
     * Handle the two-factor confirmation attempt.
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
                message: 'Unable to confirm two-factor authentication for the current user.',
                status: 401,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make(
                        'unauthenticated',
                        'Unable to confirm two-factor authentication for the current user.'
                    ),
                ],
                redirect: $this->confirmTwoFactorRedirect()
            );
        }

        if (! $this->userHasTwoFactorEnabled($user)) {
            return AuthKitActionResult::failure(
                message: 'Two-factor authentication is not enabled for this account.',
                status: 409,
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
                status: 422,
                flow: AuthKitFlowStep::failed(),
                redirect: $this->confirmTwoFactorRedirect()
            );
        }

        $driverName = (string) config('authkit.two_factor.driver', 'totp');
        $driver = $this->twoFactor->driver($driverName);

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
                redirect: $this->confirmTwoFactorRedirect()
            );
        }

        /**
         * Intentionally persistence-aware.
         *
         * Confirm-two-factor does not persist fields by default because the packaged
         * mapper marks all fields as non-persistable. This call remains here so the
         * action continues to work correctly if a consumer extends the mapper and
         * marks additional attributes as persistable.
         */
        $this->persistMappedAttributesIfSupported($user, 'confirm_two_factor', $data);

        $redirect = $this->successRedirect($session);

        $this->persistSuccessfulConfirmation($session);

        return AuthKitActionResult::success(
            message: 'Two-factor authentication confirmed successfully.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $redirect,
            payload: AuthKitPublicPayload::make([
                'confirmed' => true,
                'type' => 'two_factor',
                'driver' => $driverName,
            ])
        );
    }

    /**
     * Determine whether the given user currently has two-factor enabled.
     *
     * Resolution order:
     * - hasTwoFactorEnabled() model method
     * - configured enabled column from authkit.two_factor.columns.enabled
     *
     * @param  mixed  $user
     * @return bool
     */
    protected function userHasTwoFactorEnabled(mixed $user): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if (method_exists($user, 'hasTwoFactorEnabled')) {
            return (bool) $user->hasTwoFactorEnabled();
        }

        $enabledColumn = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');

        return (bool) data_get($user, $enabledColumn, false);
    }

    /**
     * Persist a fresh two-factor confirmation marker and clear transient
     * confirmation navigation metadata.
     *
     * @param  Session  $session
     * @return void
     */
    protected function persistSuccessfulConfirmation(Session $session): void
    {
        $sessionConfig = (array) data_get(config('authkit.confirmations', []), 'session', []);

        $twoFactorKey = (string) ($sessionConfig['two_factor_key'] ?? 'authkit.confirmed.two_factor_at');
        $intendedKey = (string) ($sessionConfig['intended_key'] ?? 'authkit.confirmation.intended');
        $typeKey = (string) ($sessionConfig['type_key'] ?? 'authkit.confirmation.type');

        $session->put($twoFactorKey, now()->timestamp);
        $session->forget([$intendedKey, $typeKey]);
    }

    /**
     * Resolve the redirect used when confirmation fails.
     *
     * @return AuthKitRedirect
     */
    protected function confirmTwoFactorRedirect(): AuthKitRedirect
    {
        $routeName = (string) data_get(
            config('authkit.route_names.web', []),
            'confirm_two_factor',
            'authkit.web.confirm.two_factor'
        );

        return AuthKitRedirect::route(
            routeName: $routeName,
            parameters: [],
            url: route($routeName)
        );
    }

    /**
     * Resolve the redirect to the authenticated two-factor settings page.
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