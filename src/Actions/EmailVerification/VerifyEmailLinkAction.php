<?php

namespace Xul\AuthKit\Actions\EmailVerification;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Xul\AuthKit\Events\AuthKitEmailVerified;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Support\PendingEmailVerification;

/**
 * VerifyEmailLinkAction
 *
 * Verifies a user via signed email verification link route parameters.
 *
 * Responsibilities:
 * - Resolve the user by ID via the configured auth provider
 * - Validate and consume the verification token (hash param is the raw token)
 * - Mark the user as verified (if supported)
 * - Dispatch Laravel's Verified event
 */
final class VerifyEmailLinkAction
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
     * Execute the verification.
     *
     * @param string $id
     * @param string $hash
     * @return VerifyEmailLinkResult
     */
    public function execute(string $id, string $hash): VerifyEmailLinkResult
    {
        $id = trim($id);
        $hash = trim($hash);

        if ($id === '' || $hash === '') {
            return VerifyEmailLinkResult::failed('Invalid verification link.');
        }

        $user = $this->retrieveById($id);

        if (!$user) {
            return VerifyEmailLinkResult::failed('Invalid verification link.');
        }

        $email = (string) ($user->email ?? '');

        if ($email === '') {
            return VerifyEmailLinkResult::failed('Invalid verification link.');
        }

        if ($this->userHasVerifiedEmail($user)) {
            return VerifyEmailLinkResult::alreadyVerified();
        }

        if (! $this->pending->isLinkContextValid($id, $hash)) {
            return VerifyEmailLinkResult::failed('Invalid or expired verification link.');
        }

        $payload = $this->pending->consumeToken($email, $hash);

        if (!$payload) {
            return VerifyEmailLinkResult::failed('Invalid or expired verification link.');
        }


        if (!$this->markUserVerified($user)) {
            return VerifyEmailLinkResult::failed('Email verification is not supported by this user model.');
        }

        if ($user instanceof MustVerifyEmail) {
            event(new Verified($user));
        }

        event(new AuthKitEmailVerified(
            user: $user,
            driver: 'link'
        ));

        $this->loginAfterVerify($user);

        return VerifyEmailLinkResult::verified();
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