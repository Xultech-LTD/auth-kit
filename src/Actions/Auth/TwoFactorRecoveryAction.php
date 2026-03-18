<?php

namespace Xul\AuthKit\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Session\Session;
use RuntimeException;
use Throwable;
use Xul\AuthKit\Concerns\Actions\InteractsWithMappedPayload;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Events\AuthKitTwoFactorRecovered;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\PendingLogin;
use Xul\AuthKit\Support\TwoFactor\TwoFactorManager;

/**
 * TwoFactorRecoveryAction
 *
 * Completes a pending login challenge using a recovery code.
 *
 * Session handling:
 * - On success: forgets AuthKitSessionKeys::TWO_FACTOR_CHALLENGE.
 * - On expired or invalid challenge: forgets AuthKitSessionKeys::TWO_FACTOR_CHALLENGE.
 * - On invalid recovery code: forgets AuthKitSessionKeys::TWO_FACTOR_CHALLENGE.
 *
 * Responsibilities:
 * - Consume normalized mapped recovery payload data.
 * - Resolve the pending login challenge payload.
 * - Resolve the intended user from the configured guard provider.
 * - Verify and consume the submitted recovery code.
 * - Persist mapper-approved attributes when the resolved user model supports it.
 * - Establish the authenticated session after successful recovery.
 * - Dispatch recovery and login events.
 * - Return a standardized AuthKitActionResult for all outcomes.
 *
 * Expected normalized payload shape:
 * - attributes.challenge
 * - attributes.recovery_code
 */
final class TwoFactorRecoveryAction
{
    use InteractsWithMappedPayload;

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
     * Handle the recovery request.
     *
     * @param array<string, mixed> $input
     * @return AuthKitActionResult
     * @throws Throwable
     */
    public function handle(array $input): AuthKitActionResult
    {
        $attributes = $this->payloadAttributes($input);

        $challenge = (string) ($attributes['challenge'] ?? '');
        $recoveryCode = (string) ($attributes['recovery_code'] ?? '');

        if (trim($challenge) === '' || trim($recoveryCode) === '') {
            return AuthKitActionResult::failure(
                message: 'Invalid recovery request.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::validation('challenge', 'The challenge field is required.', 'missing_challenge'),
                    AuthKitError::validation('recovery_code', 'The recovery code field is required.', 'missing_recovery_code'),
                ],
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
                    AuthKitError::make(
                        'invalid_or_expired_two_factor_challenge',
                        'Expired or invalid two-factor challenge.'
                    ),
                ],
                redirect: $this->loginRedirect()
            );
        }

        $guardName = (string) (($payload['guard'] ?? null) ?: config('authkit.auth.guard', 'web'));
        $remember = (bool) ($payload['remember'] ?? false);

        $user = $this->resolveUserFromPayload($guardName, (array) $payload);

        if (! $user) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return AuthKitActionResult::failure(
                message: 'User not found for this challenge.',
                status: 404,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make(
                        'two_factor_recovery_user_not_found',
                        'User not found for this challenge.'
                    ),
                ],
                redirect: $this->twoFactorRedirect($challenge)
            );
        }

        /**
         * Intentionally persistence-aware.
         *
         * Recovery fields are non-persistable by default, but this call ensures
         * the action remains forward-compatible if a consumer marks one or more
         * recovery attributes as persistable through a custom mapper.
         */
        $this->persistMappedAttributesIfSupported($user, 'two_factor_recovery', $input);

        $driver = $this->twoFactor->driver();

        if (! $driver->enabled($user)) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

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
                redirect: $this->twoFactorRedirect($challenge)
            );
        }

        if (! $driver->verifyRecoveryCode($user, $recoveryCode)) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return AuthKitActionResult::failure(
                message: 'Invalid recovery code.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_recovery_code', 'Invalid recovery code.'),
                ],
                redirect: $this->twoFactorRedirect($challenge),
                payload: AuthKitPublicPayload::make([
                    'challenge' => $challenge,
                ])
            );
        }

        if (! $driver->consumeRecoveryCode($user, $recoveryCode)) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return AuthKitActionResult::failure(
                message: 'Recovery code could not be consumed.',
                status: 409,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make(
                        'recovery_code_not_consumed',
                        'Recovery code could not be consumed.'
                    ),
                ],
                redirect: $this->twoFactorRedirect($challenge),
                payload: AuthKitPublicPayload::make([
                    'challenge' => $challenge,
                ])
            );
        }

        $this->consumePendingAndLogin($guardName, $challenge, $user, $remember);

        $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

        $driverKey = $driver->key();

        event(new AuthKitTwoFactorRecovered(
            user: $user,
            guard: $guardName,
            remember: $remember,
            driver: $driverKey
        ));

        event(new AuthKitLoggedIn(
            user: $user,
            guard: $guardName,
            remember: $remember
        ));

        return AuthKitActionResult::success(
            message: 'Recovered and logged in.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $this->successRedirect(),
            payload: AuthKitPublicPayload::make([
                'two_factor_recovered' => true,
                'user_id' => (string) $user->getAuthIdentifier(),
                'driver' => $driverKey,
                'remember' => $remember,
            ])
        );
    }

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

    protected function consumePendingAndLogin(
        string $guardName,
        string $challenge,
        Authenticatable $user,
        bool $remember
    ): void {
        $guard = $this->auth->guard($guardName);

        if (! $guard instanceof StatefulGuard) {
            throw new RuntimeException("AuthKit guard [{$guardName}] must be stateful.");
        }

        try {
            $this->pending->consume($challenge);
        } catch (Throwable) {
            if (method_exists($this->pending, 'forget')) {
                $this->pending->forget($challenge);
            }
        }

        $guard->login($user, $remember);
    }

    protected function successRedirect(): AuthKitRedirect
    {
        $redirectRoute = data_get(config('authkit.login', []), 'redirect_route');
        $dashboardRoute = (string) data_get(config('authkit.login', []), 'dashboard_route', 'dashboard');
        $loginRoute = (string) data_get(config('authkit.route_names.web', []), 'login', 'authkit.web.login');

        $target = is_string($redirectRoute) && $redirectRoute !== ''
            ? $redirectRoute
            : $dashboardRoute;

        if ($target === '') {
            $target = $loginRoute;
        }

        return AuthKitRedirect::route(
            routeName: $target,
            parameters: [],
            url: route($target)
        );
    }

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

    protected function twoFactorRedirect(string $challenge): AuthKitRedirect
    {
        $twoFactorRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'two_factor_challenge',
            'authkit.web.twofactor.challenge'
        );

        $parameters = $challenge !== '' ? ['c' => $challenge] : [];

        return AuthKitRedirect::route(
            routeName: $twoFactorRoute,
            parameters: $parameters,
            url: route($twoFactorRoute, $parameters)
        );
    }
}