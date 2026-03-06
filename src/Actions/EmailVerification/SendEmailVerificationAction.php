<?php

namespace Xul\AuthKit\Actions\EmailVerification;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;
use Xul\AuthKit\Support\PendingEmailVerification;

/**
 * SendEmailVerificationAction
 *
 * Resends an email verification message (link or token) to a user.
 *
 * Responsibilities:
 * - Normalize the email input.
 * - Resolve the user by email using the configured guard provider.
 * - Prevent resending to a different email than the authenticated user's email.
 * - Skip sending if the user is already verified.
 * - Create a verification token context via PendingEmailVerification.
 * - Build a signed URL when driver=link.
 * - Dispatch AuthKitEmailVerificationRequired for external delivery.
 *
 * Security:
 * - This action never returns raw tokens or URLs to the caller.
 * - Tokens/URLs are only emitted via the event for delivery purposes.
 */
final class SendEmailVerificationAction
{
    /**
     * @param PendingEmailVerification $pending
     * @param AuthFactory $auth
     */
    public function __construct(
        protected PendingEmailVerification $pending,
        protected AuthFactory $auth
    ) {}

    /**
     * @param string $email
     * @return SendEmailVerificationResult
     */
    public function execute(string $email): SendEmailVerificationResult
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            return SendEmailVerificationResult::failed('Email is required.');
        }

        $guardName = (string) config('authkit.auth.guard', 'web');
        $guard = $this->auth->guard($guardName);

        $sessionUser = $guard->user();

        if ($sessionUser instanceof Authenticatable) {
            $sessionEmail = mb_strtolower(trim((string) ($sessionUser->email ?? '')));

            if ($sessionEmail !== '' && $sessionEmail !== $email) {
                return SendEmailVerificationResult::failed('Invalid email verification context.');
            }
        }

        $user = $this->retrieveByEmail($email);

        if (! $user) {
            return SendEmailVerificationResult::failed('We could not find a user with that email address.');
        }

        if ($this->userHasVerifiedEmail($user)) {
            return SendEmailVerificationResult::alreadyVerified();
        }

        $driver = (string) config('authkit.email_verification.driver', 'link');
        $ttl = (int) config('authkit.email_verification.ttl_minutes', 30);

        $token = $this->pending->createForEmail($email, $ttl, [
            'user_id' => (string) $user->getAuthIdentifier(),
            'driver' => $driver,
        ]);

        if (! is_string($token) || $token === '') {
            return SendEmailVerificationResult::failed('Unable to create verification token.');
        }

        $url = $driver === 'link'
            ? $this->buildSignedLinkUrl($user, $email, $ttl, $token)
            : null;

        event(new AuthKitEmailVerificationRequired(
            user: $user,
            email: $email,
            driver: $driver,
            ttlMinutes: $ttl,
            token: $token,
            url: $url
        ));

        return SendEmailVerificationResult::sent($driver);
    }

    /**
     * @param string $email
     * @return Authenticatable|null
     */
    protected function retrieveByEmail(string $email): ?Authenticatable
    {
        $guardName = (string) config('authkit.auth.guard', 'web');
        $guard = $this->auth->guard($guardName);

        $provider = $guard->getProvider();

        if (! $provider instanceof UserProvider) {
            return null;
        }

        $user = $provider->retrieveByCredentials(['email' => $email]);

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * @param Authenticatable $user
     * @return bool
     */
    protected function userHasVerifiedEmail(Authenticatable $user): bool
    {
        if (method_exists($user, 'hasVerifiedEmail')) {
            return (bool) $user->hasVerifiedEmail();
        }

        $verifiedAt = $user->email_verified_at ?? null;

        return $verifiedAt !== null && $verifiedAt !== '';
    }

    /**
     * @param Authenticatable $user
     * @param string $email
     * @param int $ttlMinutes
     * @param string $token
     * @return string
     */
    protected function buildSignedLinkUrl(Authenticatable $user, string $email, int $ttlMinutes, string $token): string
    {
        $routeName = (string) data_get(
            config('authkit.route_names.web', []),
            'verify_link',
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
}