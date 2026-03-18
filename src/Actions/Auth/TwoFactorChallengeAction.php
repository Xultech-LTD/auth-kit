<?php

namespace Xul\AuthKit\Actions\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Session\Session;
use Throwable;
use Xul\AuthKit\Concerns\Actions\InteractsWithMappedPayload;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Events\AuthKitTwoFactorLoggedIn;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\PendingLogin;
use Xul\AuthKit\Support\TwoFactor\TwoFactorManager;

/**
 * TwoFactorChallengeAction
 *
 * Completes a pending login challenge by verifying a submitted two-factor code
 * and establishing the authenticated session for the configured guard.
 *
 * Responsibilities:
 * - Resolve the configured auth guard and provider.
 * - Consume normalized mapped challenge payload data.
 * - Resolve the pending login challenge using the configured challenge strategy.
 * - Verify the submitted authentication code using the active two-factor driver.
 * - Persist mapper-approved attributes when the resolved user model supports it.
 * - Log the user in after successful verification.
 * - Clear pending two-factor challenge session state when appropriate.
 * - Dispatch standardized AuthKit login-related events.
 * - Return a standardized AuthKitActionResult for all outcomes.
 *
 * Expected normalized payload shape:
 * - attributes.challenge
 * - attributes.code
 *
 * Notes:
 * - This action is for login-time two-factor completion.
 * - This is distinct from authenticated step-up confirmation flows.
 */
final class TwoFactorChallengeAction
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
     * Complete the two-factor challenge.
     *
     * @param array<string, mixed> $data
     * @return AuthKitActionResult
     * @throws Throwable
     */
    public function handle(array $data): AuthKitActionResult
    {
        $guardName = (string) config('authkit.auth.guard', 'web');
        $guard = $this->auth->guard($guardName);

        if (! $guard instanceof StatefulGuard) {
            return AuthKitActionResult::failure(
                message: 'Auth guard is not stateful.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('guard_not_stateful', 'Auth guard is not stateful.'),
                ],
            );
        }

        $provider = $guard->getProvider();

        if (! $provider instanceof UserProvider) {
            return AuthKitActionResult::failure(
                message: 'Invalid auth provider.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_auth_provider', 'Invalid auth provider.'),
                ],
            );
        }

        $attributes = $this->payloadAttributes($data);

        $challenge = (string) ($attributes['challenge'] ?? '');
        $code = (string) ($attributes['code'] ?? '');

        if (trim($challenge) === '' || trim($code) === '') {
            return AuthKitActionResult::failure(
                message: 'Missing two-factor challenge data.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::validation('challenge', 'The challenge field is required.', 'missing_challenge'),
                    AuthKitError::validation('code', 'The code field is required.', 'missing_code'),
                ],
            );
        }

        $strategy = (string) config('authkit.two_factor.challenge_strategy', 'peek');

        $payload = $strategy === 'consume'
            ? $this->pending->consume($challenge)
            : $this->pending->peek($challenge);

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
            );
        }

        $userId = (string) data_get($payload, 'user_id', '');

        if ($userId === '') {
            if ($strategy !== 'consume') {
                $this->pending->forget($challenge);
            }

            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return AuthKitActionResult::failure(
                message: 'Two-factor challenge payload is invalid.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make(
                        'invalid_two_factor_challenge_payload',
                        'Two-factor challenge payload is invalid.'
                    ),
                ],
            );
        }

        $user = $provider->retrieveById($userId);

        if (! $user) {
            if ($strategy !== 'consume') {
                $this->pending->forget($challenge);
            }

            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return AuthKitActionResult::failure(
                message: 'User not found for two-factor challenge.',
                status: 404,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('two_factor_user_not_found', 'User not found for two-factor challenge.'),
                ],
            );
        }

        $driverName = (string) config('authkit.two_factor.driver', 'totp');
        $driver = $this->twoFactor->driver($driverName);

        if (! $driver->enabled($user)) {
            if ($strategy !== 'consume') {
                $this->pending->forget($challenge);
            }

            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return AuthKitActionResult::failure(
                message: 'Two-factor authentication is not enabled for this user.',
                status: 403,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('two_factor_not_enabled', 'Two-factor authentication is not enabled for this user.'),
                ],
            );
        }

        $verified = $driver->verify($user, $code);

        if (! $verified) {
            if ($strategy === 'consume') {
                $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

                return AuthKitActionResult::failure(
                    message: 'Invalid authentication code.',
                    status: 401,
                    flow: AuthKitFlowStep::failed(),
                    errors: [
                        AuthKitError::make('invalid_two_factor_code', 'Invalid authentication code.'),
                    ],
                );
            }

            $twoFactorRoute = (string) data_get(
                config('authkit.route_names.web', []),
                'two_factor_challenge',
                'authkit.web.twofactor.challenge'
            );

            return AuthKitActionResult::failure(
                message: 'Invalid authentication code.',
                status: 401,
                flow: AuthKitFlowStep::twoFactorRequired(),
                errors: [
                    AuthKitError::make('invalid_two_factor_code', 'Invalid authentication code.'),
                ],
                redirect: AuthKitRedirect::route(
                    routeName: $twoFactorRoute,
                    parameters: ['c' => $challenge],
                    url: route($twoFactorRoute, ['c' => $challenge])
                ),
                payload: AuthKitPublicPayload::make([
                    'challenge' => $challenge,
                    'strategy' => $strategy,
                ]),
            );
        }

        $remember = (bool) data_get($payload, 'remember', false);

        $methods = array_values(array_filter(
            (array) data_get($payload, 'methods', []),
            static fn ($value): bool => is_string($value) && $value !== ''
        ));

        if ($strategy !== 'consume') {
            $this->pending->forget($challenge);
        }

        /**
         * Intentionally persistence-aware.
         *
         * Two-factor challenge fields are non-persistable by default, but this call
         * ensures the action automatically respects consumer mapper extensions that
         * mark one or more challenge attributes as persistable.
         */
        $this->persistMappedAttributesIfSupported($user, 'two_factor_challenge', $data);

        $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

        $guard->login($user, $remember);

        event(new AuthKitTwoFactorLoggedIn(
            user: $user,
            guard: $guardName,
            challenge: $challenge,
            methods: $methods,
            remember: $remember
        ));

        event(new AuthKitLoggedIn(
            user: $user,
            guard: $guardName,
            remember: $remember
        ));

        return AuthKitActionResult::success(
            message: 'Two-factor verified.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $this->resolveSuccessRedirect(),
            payload: AuthKitPublicPayload::make([
                'user_id' => (string) $user->getAuthIdentifier(),
                'remember' => $remember,
                'methods' => $methods,
            ]),
        );
    }

    /**
     * Resolve the post-login redirect target after successful two-factor completion.
     *
     * @return AuthKitRedirect
     */
    protected function resolveSuccessRedirect(): AuthKitRedirect
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
}