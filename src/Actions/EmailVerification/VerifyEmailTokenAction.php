<?php

namespace Xul\AuthKit\Actions\EmailVerification;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Carbon;
use Xul\AuthKit\Events\AuthKitEmailVerified;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Support\PendingEmailVerification;

/**
 * VerifyEmailTokenAction
 *
 * Verifies a user's email using a token/code.
 *
 * Responsibilities:
 * - Validate and consume the verification token using PendingEmailVerification
 * - Resolve the user referenced by the token payload (user_id) via the configured provider
 * - Mark the user as verified (if supported)
 * - Dispatch Laravel's Verified event (if MustVerifyEmail)
 * - Dispatch AuthKitEmailVerified event (driver=token)
 */
final class VerifyEmailTokenAction
{
    /**
     * Create a new instance.
     *
     * @param PendingEmailVerification $pending
     * @param AuthFactory $auth
     */
    public function __construct(
        protected PendingEmailVerification $pending,
        protected AuthFactory $auth
    ) {}

    /**
     * Execute token verification.
     *
     * @param string $email
     * @param string $token
     * @return VerifyEmailTokenResult
     */
    public function execute(string $email, string $token): VerifyEmailTokenResult
    {
        $email = mb_strtolower(trim($email));
        $token = trim($token);

        if ($email === '' || $token === '') {
            return VerifyEmailTokenResult::failed('Email and verification code are required.');
        }

        $payload = $this->pending->consumeToken($email, $token);

        if (! is_array($payload)) {
            return VerifyEmailTokenResult::failed('Invalid or expired verification code.');
        }

        $userId = (string) ($payload['user_id'] ?? '');

        if ($userId === '') {
            return VerifyEmailTokenResult::failed('Invalid verification context.');
        }

        $user = $this->retrieveById($userId);

        if (! $user) {
            return VerifyEmailTokenResult::failed('Invalid verification context.');
        }

        $userEmail = mb_strtolower(trim((string) ($user->email ?? '')));

        if ($userEmail === '' || $userEmail !== $email) {
            return VerifyEmailTokenResult::failed('Invalid verification context.');
        }

        if ($this->userHasVerifiedEmail($user)) {
            return VerifyEmailTokenResult::alreadyVerified();
        }

        if (! $this->markUserVerified($user)) {
            return VerifyEmailTokenResult::failed('Email verification is not supported by this user model.');
        }

        if ($user instanceof MustVerifyEmail) {
            event(new Verified($user));
        }

        event(new AuthKitEmailVerified(
            user: $user,
            driver: 'token'
        ));

        $this->loginAfterVerify($user);

        return VerifyEmailTokenResult::verified();
    }

    /**
     * Retrieve a user by ID using the configured auth provider.
     *
     * @param string $id
     * @return Authenticatable|null
     */
    protected function retrieveById(string $id): ?Authenticatable
    {
        $guardName = (string) config('authkit.auth.guard', 'web');

        $guard = $this->auth->guard($guardName);

        $provider = $guard->getProvider();

        $user = $provider instanceof UserProvider
            ? $provider->retrieveById($id)
            : null;

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * Determine if the user is already verified.
     *
     * Behavior:
     * - If the user implements hasVerifiedEmail(), that method is used.
     * - Otherwise falls back to the configured verification timestamp column.
     *
     * @param Authenticatable $user
     * @return bool
     */
    protected function userHasVerifiedEmail(Authenticatable $user): bool
    {
        if (method_exists($user, 'hasVerifiedEmail')) {
            return (bool) $user->hasVerifiedEmail();
        }

        $column = $this->verifiedAtColumn();

        $verifiedAt = $user->{$column} ?? null;

        return $verifiedAt !== null && $verifiedAt !== '';
    }

    /**
     * Mark the user as verified.
     *
     * Behavior:
     * - If the user implements markEmailAsVerified(), that method is used.
     * - Otherwise AuthKit sets the configured verification timestamp column
     *   and persists the model when possible.
     *
     * @param Authenticatable $user
     * @return bool
     */
    protected function markUserVerified(Authenticatable $user): bool
    {
        if (method_exists($user, 'markEmailAsVerified')) {
            $user->markEmailAsVerified();

            return true;
        }

        $column = $this->verifiedAtColumn();

        $user->{$column} = Carbon::now();

        if (method_exists($user, 'save')) {
            $user->save();

            return true;
        }

        return false;
    }

    /**
     * Resolve the configured verification timestamp column name.
     *
     * @return string
     */
    protected function verifiedAtColumn(): string
    {
        return (string) config('authkit.email_verification.columns.verified_at', 'email_verified_at');
    }

    /**
     * Optionally authenticate the user after successful verification.
     */
    protected function loginAfterVerify(Authenticatable $user): void
    {
        $enabled = (bool) data_get(config('authkit.email_verification.post_verify', []), 'login_after_verify', false);

        if (! $enabled) {
            return;
        }

        $remember = (bool) data_get(config('authkit.email_verification.post_verify', []), 'remember', true);
        $guardName = (string) config('authkit.auth.guard', 'web');

        $this->auth->guard($guardName)->login($user, $remember);

        event(new AuthKitLoggedIn(
            user: $user,
            guard: $guardName,
            remember: $remember
        ));
    }
}