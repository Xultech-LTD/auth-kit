<?php

namespace Xul\AuthKit\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Event;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;
use Xul\AuthKit\Contracts\TwoFactorResendableContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Events\AuthKitTwoFactorResent;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\PendingLogin;
use Xul\AuthKit\Support\TwoFactor\TwoFactorManager;

/**
 * TwoFactorResendAction
 *
 * Resends a two-factor challenge code for a pending login challenge,
 * when the active driver supports resending.
 *
 * Responsibilities:
 * - Validate resend input context.
 * - Resolve the current pending challenge from session.
 * - Resolve the pending user from the configured guard provider.
 * - Validate that the provided identity matches the pending login user.
 * - Delegate resend work to drivers that support TwoFactorResendableContract.
 * - Dispatch AuthKitTwoFactorResent on successful resend.
 * - Return a standardized AuthKitActionResult for all outcomes.
 */
final class TwoFactorResendAction
{
    /**
     * Create a new instance.
     *
     * @param AuthFactory $auth
     * @param PendingLogin $pending
     * @param TwoFactorManager $twoFactor
     * @param Session $session
     */
    public function __construct(
        protected AuthFactory $auth,
        protected PendingLogin $pending,
        protected TwoFactorManager $twoFactor,
        protected Session $session
    ) {}

    /**
     * Handle the resend request.
     *
     * @param array<string, mixed> $input
     * @return AuthKitActionResult
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     */
    public function handle(array $input): AuthKitActionResult
    {
        $email = (string) ($input['email'] ?? '');

        if (trim($email) === '') {
            return AuthKitActionResult::failure(
                message: 'Invalid resend request.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::validation('email', 'The email field is required.', 'missing_email'),
                ],
            );
        }

        $challenge = (string) $this->session->get(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, '');

        if ($challenge === '') {
            return AuthKitActionResult::failure(
                message: 'Missing two-factor challenge.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('missing_two_factor_challenge', 'Missing two-factor challenge.'),
                ],
                redirect: $this->loginRedirect()
            );
        }

        $payload = $this->pending->peek($challenge);

        if (! $payload) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return AuthKitActionResult::failure(
                message: 'Expired or invalid two-factor challenge.',
                status: 410,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_or_expired_two_factor_challenge', 'Expired or invalid two-factor challenge.'),
                ],
                redirect: $this->loginRedirect()
            );
        }

        $guardName = (string) (($payload['guard'] ?? null) ?: config('authkit.auth.guard', 'web'));
        $user = $this->resolveUserFromPayload($guardName, (array) $payload);

        if (! $user) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return AuthKitActionResult::failure(
                message: 'User not found for this challenge.',
                status: 404,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('two_factor_resend_user_not_found', 'User not found for this challenge.'),
                ],
                redirect: $this->loginRedirect()
            );
        }

        $identityField = (string) data_get(config('authkit.identity.login', []), 'field', 'email');
        $userEmail = (string) data_get($user, $identityField, '');

        if ($userEmail === '' || strcasecmp($userEmail, $email) !== 0) {
            return AuthKitActionResult::failure(
                message: 'Invalid resend context.',
                status: 403,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_resend_context', 'Invalid resend context.'),
                ],
                redirect: $this->twoFactorRedirect(),
                payload: AuthKitPublicPayload::make([
                    'driver' => (string) config('authkit.two_factor.driver', 'totp'),
                ])
            );
        }

        $driver = $this->twoFactor->driver();

        if (! $driver->enabled($user)) {
            return AuthKitActionResult::failure(
                message: 'Two-factor authentication is not enabled for this account.',
                status: 409,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('two_factor_not_enabled', 'Two-factor authentication is not enabled for this account.'),
                ],
                redirect: $this->twoFactorRedirect(),
                payload: AuthKitPublicPayload::make([
                    'driver' => $driver->key(),
                ])
            );
        }

        if (! $driver instanceof TwoFactorResendableContract) {
            return AuthKitActionResult::failure(
                message: 'Two-factor resend is not supported for the active driver.',
                status: 409,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('two_factor_resend_not_supported', 'Two-factor resend is not supported for the active driver.'),
                ],
                redirect: $this->twoFactorRedirect(),
                payload: AuthKitPublicPayload::make([
                    'driver' => $driver->key(),
                ])
            );
        }

        $result = $driver->resend($user, [
            'challenge' => $challenge,
            'payload' => (array) $payload,
        ]);

        $ok = (bool) ($result['ok'] ?? true);

        if (! $ok) {
            $status = (int) ($result['status'] ?? 422);

            return AuthKitActionResult::failure(
                message: (string) ($result['message'] ?? 'Resend failed.'),
                status: $status,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('two_factor_resend_failed', (string) ($result['message'] ?? 'Resend failed.')),
                ],
                redirect: $this->twoFactorRedirect(),
                payload: AuthKitPublicPayload::make([
                    'driver' => $driver->key(),
                ])
            );
        }

        Event::dispatch(new AuthKitTwoFactorResent($user, $guardName, $driver->key()));

        return AuthKitActionResult::success(
            message: (string) ($result['message'] ?? 'Challenge resent.'),
            status: 200,
            flow: AuthKitFlowStep::twoFactorRequired(),
            redirect: $this->twoFactorRedirect(),
            payload: AuthKitPublicPayload::make([
                'driver' => $driver->key(),
            ])
        );
    }

    /**
     * Resolve an Authenticatable user from the pending payload using the configured guard provider.
     *
     * @param string $guardName
     * @param array<string, mixed> $payload
     * @return Authenticatable|null
     */
    protected function resolveUserFromPayload(string $guardName, array $payload): ?Authenticatable
    {
        $userId = (string) ($payload['user_id'] ?? '');

        if ($userId === '') {
            return null;
        }

        $guard = $this->auth->guard($guardName);

        if (! $guard instanceof StatefulGuard) {
            throw new RuntimeException("AuthKit guard [{$guardName}] must be stateful.");
        }

        $provider = method_exists($guard, 'getProvider') ? $guard->getProvider() : null;

        if (! $provider instanceof UserProvider) {
            throw new RuntimeException("AuthKit guard [{$guardName}] must have a user provider.");
        }

        $user = $provider->retrieveById($userId);

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * Resolve the login redirect.
     *
     * @return AuthKitRedirect
     */
    protected function loginRedirect(): AuthKitRedirect
    {
        $loginRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'login',
            'authkit.web.login'
        );

        return AuthKitRedirect::route(
            routeName: $loginRoute,
            parameters: [],
            url: route($loginRoute)
        );
    }

    /**
     * Resolve the two-factor challenge redirect.
     *
     * @return AuthKitRedirect
     */
    protected function twoFactorRedirect(): AuthKitRedirect
    {
        $twoFactorRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'two_factor_challenge',
            'authkit.web.twofactor.challenge'
        );

        return AuthKitRedirect::route(
            routeName: $twoFactorRoute,
            parameters: [],
            url: route($twoFactorRoute)
        );
    }
}