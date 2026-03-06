<?php

namespace Xul\AuthKit\Actions\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;
use RuntimeException;
use Throwable;
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
 *  - On success: forgets AuthKitSessionKeys::TWO_FACTOR_CHALLENGE.
 *  - On expired/invalid challenge: forgets AuthKitSessionKeys::TWO_FACTOR_CHALLENGE.
 *  - On invalid recovery code: keeps AuthKitSessionKeys::TWO_FACTOR_CHALLENGE.
 *
 * @final
 */
final class TwoFactorRecoveryAction
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
     * Handle the recovery request.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     * @throws Throwable
     */
    public function handle(array $input): array
    {
        $challenge = (string) ($input['challenge'] ?? '');
        $recoveryCode = (string) ($input['recovery_code'] ?? '');

        if ($challenge === '' || $recoveryCode === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Invalid recovery request.',
            ];
        }

        $payload = $this->pending->peek($challenge);

        if (!$payload) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return [
                'ok' => false,
                'status' => 410,
                'message' => 'Expired or invalid two-factor challenge.',
            ];
        }

        $guardName = (string) (($payload['guard'] ?? null) ?: config('authkit.auth.guard', 'web'));
        $remember = (bool) ($payload['remember'] ?? false);

        $user = $this->resolveUserFromPayload($guardName, (array) $payload);

        if (!$user) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return [
                'ok' => false,
                'status' => 404,
                'message' => 'User not found for this challenge.',
            ];
        }

        $driver = $this->twoFactor->driver();

        if (!$driver->enabled($user)) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return [
                'ok' => false,
                'status' => 409,
                'message' => 'Two-factor authentication is not enabled for this account.',
            ];
        }

        if (!$driver->verifyRecoveryCode($user, $recoveryCode)) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Invalid recovery code.',
            ];
        }

        if (!$driver->consumeRecoveryCode($user, $recoveryCode)) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return [
                'ok' => false,
                'status' => 409,
                'message' => 'Recovery code could not be consumed.',
            ];
        }

        $this->consumePendingAndLogin($guardName, $challenge, $user, $remember);

        $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

        event(new AuthKitTwoFactorRecovered($user, $guardName, $remember, $driver->key()));
        event(new AuthKitLoggedIn($user, $guardName, $remember));

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Recovered and logged in.',
            'two_factor_recovered' => true,
            'user_id' => (string) $user->getAuthIdentifier(),
        ];
    }

    /**
     * Resolve an Authenticatable user from the pending payload using the configured guard provider.
     *
     * @param string $guardName
     * @param array<string, mixed> $payload
     * @return Authenticatable|null
     */
    protected function resolveUserFromPayload(string $guardName, array $payload): ?object
    {
        $userId = (string) ($payload['user_id'] ?? '');

        if ($userId === '') {
            return null;
        }

        $guard = $this->auth->guard($guardName);

        if (!$guard instanceof StatefulGuard) {
            throw new RuntimeException("AuthKit guard [{$guardName}] must be stateful.");
        }

        $provider = method_exists($guard, 'getProvider') ? $guard->getProvider() : null;

        if (!$provider instanceof UserProvider) {
            throw new RuntimeException("AuthKit guard [{$guardName}] must have a user provider.");
        }

        $user = $provider->retrieveById($userId);

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * Consume the pending challenge and perform a session login.
     *
     * @param string $guardName
     * @param string $challenge
     * @param Authenticatable $user
     * @param bool $remember
     * @return void
     */
    protected function consumePendingAndLogin(string $guardName, string $challenge, Authenticatable $user, bool $remember): void
    {
        $guard = $this->auth->guard($guardName);

        if (!$guard instanceof StatefulGuard) {
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
}