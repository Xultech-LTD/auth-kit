<?php

namespace Xul\AuthKit\Actions\PasswordReset;

use Illuminate\Support\Facades\Event;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUrlGeneratorContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract;
use Xul\AuthKit\Events\AuthKitPasswordResetRequested;
use Xul\AuthKit\Support\PendingPasswordReset;

/**
 * RequestPasswordResetAction
 *
 * Starts a password reset request for a given identity value (email).
 *
 * Key goals:
 * - Support "do not reveal user existence" (privacy mode).
 * - Generate tokens using PendingPasswordReset (single source of truth).
 * - Dispatch AuthKitPasswordResetRequested only when a real user exists,
 *   so delivery listeners are not triggered for unknown identities.
 *
 * Token lifecycle:
 * - PendingPasswordReset creates and stores the token payload via TokenRepositoryContract.
 * - PendingPasswordReset stores a short-lived presence key for UI/middleware.
 * - Delivery listener receives the raw token via event and sends it (link/token).
 */
final class RequestPasswordResetAction
{
    public function __construct(
        protected PendingPasswordReset $pending,
        protected PasswordResetUserResolverContract $users,
        protected PasswordResetPolicyContract $policy,
        protected PasswordResetUrlGeneratorContract $urls,
    ) {}

    /**
     * Execute the reset request.
     *
     * @param string $email Normalized identity (email).
     */
    public function execute(string $email): PasswordResetRequestResult
    {
        $driver = (string) config('authkit.password_reset.driver', 'link');
        $ttlMinutes = (int) config('authkit.password_reset.ttl_minutes', 30);

        $privacy = (array) config('authkit.password_reset.privacy', []);
        $hideExistence = (bool) data_get($privacy, 'hide_user_existence', true);

        $genericMessage = (string) data_get(
            $privacy,
            'generic_message',
            'If an account exists for this email, password reset instructions have been sent.'
        );

        $privacySafeSent = PasswordResetRequestResult::sent($driver, $genericMessage);

        if (! $this->policy->canRequest($email)) {
            return $hideExistence
                ? $privacySafeSent
                : PasswordResetRequestResult::failed('Password reset is not available for this account.', $driver);
        }

        $user = $this->users->resolve($email);

        if (! $user) {
            return $hideExistence
                ? $privacySafeSent
                : PasswordResetRequestResult::failed('We could not find an account with that email address.', $driver);
        }

        $token = $this->pending->createForEmail(
            email: $email,
            ttlMinutes: $ttlMinutes,
            payload: [
                // Reserved for future diagnostics and audit logging.
                'flow' => 'password_reset_request',
            ]
        );

        $url = null;

        if ($driver === 'link') {
            $url = $this->urls->make($email, $token);
        }

        Event::dispatch(new AuthKitPasswordResetRequested(
            driver: $driver,
            email: $email,
            token: $token,
            url: $url,
            user: $user,
            meta: [
                'ttl_minutes' => $ttlMinutes,
            ]
        ));


        if ($hideExistence) {
            return $privacySafeSent;
        }

        $message = $driver === 'token'
            ? 'A password reset code has been sent.'
            : 'A password reset link has been sent.';

        $mode = (string) data_get(config('authkit.password_reset.post_request', []), 'mode', 'sent_page');

        $route = (string) data_get(
            config('authkit.password_reset.post_request', []),
            'sent_route',
            (string) ($webNames['password_forgot_sent'] ?? 'authkit.web.password.forgot.sent')
        );;

        if ($mode === 'token_page') {
            $tokenRoute = (string) data_get(
                config('authkit.password_reset.post_request', []),
                'token_route',
                'authkit.web.password.reset.token'
            );

            $route = route($tokenRoute, ['email' => $email]);
        }
        return PasswordResetRequestResult::sent($driver, $message, $route);
    }
}