<?php

namespace Xul\AuthKit\Actions\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Session\Session;
use Throwable;
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
 * Strategy:
 * - When authkit.two_factor.challenge_strategy = 'peek':
 *   - Challenge is checked without consuming, and invalidated only after success.
 * - When authkit.two_factor.challenge_strategy = 'consume':
 *   - Challenge is consumed immediately, and invalid codes require a new login attempt.
 * Session handling:
 *  - On success: forgets AuthKitSessionKeys::TWO_FACTOR_CHALLENGE.
 *  - On invalid code:
 *    - consume strategy: forgets AuthKitSessionKeys::TWO_FACTOR_CHALLENGE (challenge is consumed / restart required).
 *    - peek strategy: keeps AuthKitSessionKeys::TWO_FACTOR_CHALLENGE (challenge remains valid).
 *  - On expired/invalid challenge: forgets AuthKitSessionKeys::TWO_FACTOR_CHALLENGE.
 * Events:
 * - AuthKitTwoFactorLoggedIn is dispatched on successful two-factor completion.
 * - AuthKitLoggedIn is dispatched after the session is established.
 */
final class TwoFactorChallengeAction
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
     * Complete the two-factor challenge.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws Throwable
     */
    public function handle(array $data): array
    {
        $guardName = (string) config('authkit.auth.guard', 'web');
        $guard = $this->auth->guard($guardName);

        if (!$guard instanceof StatefulGuard) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Auth guard is not stateful.',
            ];
        }

        $provider = $guard->getProvider();

        if (!$provider instanceof UserProvider) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Invalid auth provider.',
            ];
        }

        $challenge = (string) ($data['challenge'] ?? '');
        $code = (string) ($data['code'] ?? '');

        if (trim($challenge) === '' || trim($code) === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Missing two-factor challenge data.',
            ];
        }

        $strategy = (string) config('authkit.two_factor.challenge_strategy', 'peek');

        $payload = $strategy === 'consume'
            ? $this->pending->consume($challenge)
            : $this->pending->peek($challenge);

        if (!$payload) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return [
                'ok' => false,
                'status' => 410,
                'message' => 'Expired or invalid two-factor challenge.',
            ];
        }

        $userId = (string) data_get($payload, 'user_id', '');

        if ($userId === '') {
            if ($strategy !== 'consume') {
                $this->pending->forget($challenge);
            }

            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Two-factor challenge payload is invalid.',
            ];
        }

        $user = $provider->retrieveById($userId);

        if (!$user) {
            if ($strategy !== 'consume') {
                $this->pending->forget($challenge);
            }

            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return [
                'ok' => false,
                'status' => 404,
                'message' => 'User not found for two-factor challenge.',
            ];
        }

        $driverName = (string) config('authkit.two_factor.driver', 'totp');
        $driver = $this->twoFactor->driver($driverName);

        if (!$driver->enabled($user)) {
            if ($strategy !== 'consume') {
                $this->pending->forget($challenge);
            }

            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Two-factor authentication is not enabled for this user.',
            ];
        }

        $ok = $driver->verify($user, $code);

        $twoFactorRoute = (string) data_get(config('authkit.route_names.web', []), 'two_factor_challenge', 'authkit.web.twofactor.challenge');

        if (!$ok) {
            if ($strategy === 'consume') {
                $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

                return [
                    'ok' => false,
                    'status' => 401,
                    'message' => 'Invalid authentication code.',
                ];
            }

            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Invalid authentication code.',
                'two_factor_required' => true,
                'challenge' => $challenge,
            ];
        }

        $remember = (bool) data_get($payload, 'remember', false);
        $methods = (array) data_get($payload, 'methods', []);

        if ($strategy !== 'consume') {
            $this->pending->forget($challenge);
        }

        $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

        $guard->login($user, $remember);

        event(new AuthKitTwoFactorLoggedIn(
            user: $user,
            guard: $guardName,
            challenge: $challenge,
            methods: array_values(array_filter($methods, static fn ($v) => is_string($v) && $v !== '')),
            remember: $remember
        ));

        event(new AuthKitLoggedIn(
            user: $user,
            guard: $guardName,
            remember: $remember
        ));

        $redirectRoute = data_get(config('authkit.login', []), 'redirect_route');
        $dashboardRoute = (string) data_get(config('authkit.login', []), 'dashboard_route', 'dashboard');

        $target = is_string($redirectRoute) && $redirectRoute !== ''
            ? $redirectRoute
            : $dashboardRoute;

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Two-factor verified.',
            'two_factor_required' => false,
            'user_id' => $user->getAuthIdentifier(),
            'redirect_url' => route($target)
        ];
    }
}