<?php

namespace Xul\AuthKit\Actions\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Events\AuthKitTwoFactorRequired;
use Xul\AuthKit\Support\PendingLogin;
use Xul\AuthKit\Support\PendingEmailVerification;
use Xul\AuthKit\Support\TwoFactor\TwoFactorManager;

/**
 * LoginAction
 *
 * Validates user credentials and conditionally performs session login.
 *
 * Behavior:
 * - Always validates user credentials against the configured guard provider.
 * - If two-factor is globally enabled AND the user has two-factor enabled:
 *   - creates a PendingLogin challenge
 *   - dispatches AuthKitTwoFactorRequired for delivery/coordination
 *   - does not authenticate the session yet
 * - Otherwise:
 *   - authenticates the session
 *   - dispatches AuthKitLoggedIn
 *
 * Security:
 * - This action must never return pending login challenge tokens to the caller.
 * - Challenge transport is handled by the HTTP layer (session/redirects) and events.
 */
final class LoginAction
{
    /**
     * Create a new instance.
     *
     * @param AuthFactory $auth
     * @param PendingLogin $pendingLogin
     * @param TwoFactorManager $twoFactor
     * @param PendingEmailVerification $pendingEmailVerification
     */
    public function __construct(
        protected AuthFactory $auth,
        protected PendingLogin $pendingLogin,
        protected TwoFactorManager $twoFactor,
        protected PendingEmailVerification $pendingEmailVerification,
    ) {}

    /**
     * Attempt login.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     * @throws \Throwable
     */
    public function handle(array $data): array
    {
        $guardName = (string) config('authkit.auth.guard', 'web');
        $guard = $this->auth->guard($guardName);

        if (! $guard instanceof StatefulGuard) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Auth guard is not stateful.',
            ];
        }

        $provider = $guard->getProvider();

        if (! $provider instanceof UserProvider) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Invalid auth provider.',
            ];
        }

        $identityField = (string) data_get(config('authkit.identity.login', []), 'field', 'email');

        $identity = (string) ($data[$identityField] ?? '');
        $password = (string) ($data['password'] ?? '');
        $remember = (bool) ($data['remember'] ?? false);

        if (trim($identity) === '' || $password === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Missing credentials.',
            ];
        }

        $user = $provider->retrieveByCredentials([
            $identityField => $identity,
            'password' => $password,
        ]);

        if (! $user || ! $provider->validateCredentials($user, ['password' => $password])) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Invalid credentials.',
            ];
        }

        if ($this->shouldRequireEmailVerification($user)) {
            $email = $this->resolveUserEmail($user, $identityField, $identity);

            if ($email !== '') {
                $ttl = (int) config('authkit.email_verification.ttl_minutes', 30);
                $driver = (string) config('authkit.email_verification.driver', 'link');

                $token = $this->pendingEmailVerification->createForEmail($email, $ttl, [
                    'user_id' => (string) $user->getAuthIdentifier(),
                    'driver' => $driver,
                ]);

                $url = $driver === 'link'
                    ? $this->buildSignedVerifyLinkUrl($user, $email, $ttl, $token)
                    : null;

                event(new AuthKitEmailVerificationRequired(
                    user: $user,
                    email: $email,
                    driver: $driver,
                    ttlMinutes: $ttl,
                    token: $token,
                    url: $url
                ));
            }

            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Email verification required.',
                'email_verification_required' => true,
                'redirect_params' => $email !== '' ? ['email' => $email] : [],
            ];
        }

        if ($this->shouldRequireTwoFactor($user)) {
            $ttl = (int) config('authkit.two_factor.ttl_minutes', 10);

            $driverName = (string) config('authkit.two_factor.driver', 'totp');
            $driver = $this->twoFactor->driver($driverName);

            $methods = $driver->methods($user);

            if ($methods === []) {
                $methods = (array) config('authkit.two_factor.methods', ['totp']);
            }

            $methods = array_values(array_filter($methods, static fn ($v) => is_string($v) && $v !== ''));

            if ($methods === []) {
                $methods = ['totp'];
            }

            $challenge = $this->pendingLogin->create(
                userId: (string) $user->getAuthIdentifier(),
                remember: $remember,
                ttlMinutes: $ttl,
                methods: $methods
            );

            event(new AuthKitTwoFactorRequired(
                user: $user,
                guard: $guardName,
                challenge: $challenge,
                methods: $methods,
                remember: $remember
            ));

            return [
                'ok' => true,
                'status' => 200,
                'message' => 'Two-factor authentication required.',
                'two_factor_required' => true,
                'methods' => $methods,
                'internal_challenge' => $challenge,
            ];
        }

        $guard->login($user, $remember);

        event(new AuthKitLoggedIn(
            user: $user,
            guard: $guardName,
            remember: $remember
        ));

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Logged in.',
            'two_factor_required' => false,
            'user_id' => $user->getAuthIdentifier(),
        ];
    }

    /**
     * Determine whether the user must complete email verification before login.
     *
     * Behavior:
     * - Returns false when email verification is disabled in configuration.
     * - If the user implements MustVerifyEmail, defers to hasVerifiedEmail().
     * - Otherwise falls back to checking the configured verification timestamp column.
     *
     * Fallback column logic:
     * - The column defaults to "email_verified_at".
     * - A null/empty value means the user is not verified.
     *
     * @param  object  $user
     * @return bool
     */
    protected function shouldRequireEmailVerification(object $user): bool
    {
        if (! (bool) config('authkit.email_verification.enabled', true)) {
            return false;
        }

        if ($user instanceof MustVerifyEmail) {
            return ! $user->hasVerifiedEmail();
        }

        $column = (string) config('authkit.email_verification.columns.verified_at', 'email_verified_at');

        $verifiedAt = $user->{$column} ?? null;

        return $verifiedAt === null || $verifiedAt === '';
    }

    /**
     * Resolve the email address associated with the authenticated identity.
     *
     * Resolution order:
     * - Prefer the identity field on the user model (e.g. email).
     * - Fall back to the raw identity value used during login.
     *
     * The returned value is normalized to lowercase and trimmed.
     *
     * @param  object  $user
     * @param  string  $identityField
     * @param  string  $identity
     * @return string
     */
    protected function resolveUserEmail(object $user, string $identityField, string $identity): string
    {
        if (isset($user->{$identityField}) && is_string($user->{$identityField}) && trim($user->{$identityField}) !== '') {
            return mb_strtolower(trim((string) $user->{$identityField}));
        }

        return mb_strtolower(trim($identity));
    }

    /**
     * Build the signed verification URL for the link driver.
     *
     * Route parameters:
     * - id: user identifier
     * - hash: raw verification token (repository stores only sha256(token))
     * - email: verification email context
     *
     * The URL is temporary and expires according to the configured TTL.
     *
     * @param  object  $user
     * @param  string  $email
     * @param  int  $ttlMinutes
     * @param  string  $token
     * @return string
     */
    protected function buildSignedVerifyLinkUrl(object $user, string $email, int $ttlMinutes, string $token): string
    {
        $routeName = (string) config(
            'authkit.route_names.web.verify_link',
            'authkit.web.email.verification.verify.link'
        );

        return URL::temporarySignedRoute(
            name: $routeName,
            expiration: now()->addMinutes($ttlMinutes),
            parameters: [
                'id' => (string) $user->getAuthIdentifier(),
                'hash' => $token,
                'email' => $email,
            ]
        );
    }

    /**
     * Determine whether two-factor must be completed for this login attempt.
     *
     * @param object $user
     * @return bool
     * @throws \Throwable
     */
    protected function shouldRequireTwoFactor(object $user): bool
    {
        if (! (bool) config('authkit.two_factor.enabled', true)) {
            return false;
        }

        $driverName = (string) config('authkit.two_factor.driver', 'totp');
        $driver = $this->twoFactor->driver($driverName);

        return $driver->enabled($user);
    }
}