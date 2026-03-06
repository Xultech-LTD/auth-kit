<?php

namespace Xul\AuthKit\Actions\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Event;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;
use Xul\AuthKit\Contracts\TwoFactorResendableContract;
use Xul\AuthKit\Events\AuthKitTwoFactorResent;
use Xul\AuthKit\Support\PendingLogin;
use Xul\AuthKit\Support\TwoFactor\TwoFactorManager;
use Illuminate\Contracts\Session\Session;
use Xul\AuthKit\Support\AuthKitSessionKeys;

/**
 * TwoFactorResendAction
 *
 * Resends a two-factor challenge code for a pending login challenge,
 * when the active driver supports resending.
 *
 * @final
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
     * @return array<string, mixed>
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     */
    public function handle(array $input): array
    {
        $email = (string) ($input['email'] ?? '');
        if (trim($email) === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Invalid resend request.',
            ];
        }

        $challenge = (string) $this->session->get(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, '');
        if ($challenge === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Missing two-factor challenge.',
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
        $user = $this->resolveUserFromPayload($guardName, (array) $payload);

        if (!$user) {
            $this->session->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return [
                'ok' => false,
                'status' => 404,
                'message' => 'User not found for this challenge.',
            ];
        }

        $identityField = (string) data_get(config('authkit.identity.login', []), 'field', 'email');
        $userEmail = (string) data_get($user, $identityField, '');

        if ($userEmail === '' || strcasecmp($userEmail, $email) !== 0) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Invalid resend context.',
            ];
        }

        $driver = $this->twoFactor->driver();

        if (!$driver->enabled($user)) {
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'Two-factor authentication is not enabled for this account.',
            ];
        }

        if (!$driver instanceof TwoFactorResendableContract) {
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'Two-factor resend is not supported for the active driver.',
                'driver' => $driver->key(),
            ];
        }

        $result = $driver->resend($user, [
            'challenge' => $challenge,
            'payload' => (array) $payload,
        ]);

        $ok = (bool) ($result['ok'] ?? true);

        if (!$ok) {
            $status = (int) ($result['status'] ?? 422);

            return [
                'ok' => false,
                'status' => $status,
                'message' => (string) ($result['message'] ?? 'Resend failed.'),
                'driver' => $driver->key(),
            ];
        }

        Event::dispatch(new AuthKitTwoFactorResent($user, $guardName, $driver->key()));

        return [
            'ok' => true,
            'status' => 200,
            'message' => (string) ($result['message'] ?? 'Challenge resent.'),
            'driver' => $driver->key(),
        ];
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
}