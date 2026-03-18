<?php

namespace Xul\AuthKit\Actions\PasswordReset;

use Illuminate\Support\Facades\Event;
use Xul\AuthKit\Concerns\Actions\InteractsWithMappedPayload;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUrlGeneratorContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Events\AuthKitPasswordResetRequested;
use Xul\AuthKit\Support\PendingPasswordReset;

/**
 * RequestPasswordResetAction
 *
 * Starts a password reset request for a given identity value.
 *
 * Key goals:
 * - Support privacy mode that does not reveal whether a user exists.
 * - Generate reset tokens using PendingPasswordReset as the source of truth.
 * - Dispatch AuthKitPasswordResetRequested only when a real user exists.
 * - Persist mapper-approved attributes when the resolved model supports
 *   AuthKit mapped persistence.
 * - Return a standardized AuthKitActionResult for all outcomes.
 *
 * Token lifecycle:
 * - PendingPasswordReset creates and stores the token payload.
 * - PendingPasswordReset stores a short-lived presence key for UI and middleware.
 * - Delivery listeners receive the raw token via AuthKitPasswordResetRequested.
 */
final class RequestPasswordResetAction
{
    use InteractsWithMappedPayload;

    /**
     * Create a new instance.
     *
     * @param PendingPasswordReset $pending
     * @param PasswordResetUserResolverContract $users
     * @param PasswordResetPolicyContract $policy
     * @param PasswordResetUrlGeneratorContract $urls
     */
    public function __construct(
        protected PendingPasswordReset $pending,
        protected PasswordResetUserResolverContract $users,
        protected PasswordResetPolicyContract $policy,
        protected PasswordResetUrlGeneratorContract $urls,
    ) {}

    /**
     * Execute the password reset request flow.
     *
     * @param array<string, mixed> $input
     * @return AuthKitActionResult
     */
    public function handle(array $input): AuthKitActionResult
    {
        $attributes = $this->payloadAttributes($input);

        $email = mb_strtolower(trim((string) ($attributes['email'] ?? '')));

        $driver = (string) config('authkit.password_reset.driver', 'link');
        $ttlMinutes = (int) config('authkit.password_reset.ttl_minutes', 30);

        $privacy = (array) config('authkit.password_reset.privacy', []);
        $hideExistence = (bool) data_get($privacy, 'hide_user_existence', true);

        $genericMessage = (string) data_get(
            $privacy,
            'generic_message',
            'If an account exists for this email, password reset instructions have been sent.'
        );

        $privacySafeResult = AuthKitActionResult::success(
            message: $genericMessage,
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $this->postRequestRedirect($email),
            payload: AuthKitPublicPayload::make([
                'email' => $email,
                'driver' => $driver,
                'privacy_mode' => true,
            ])
        );

        if (! $this->policy->canRequest($email)) {
            return $hideExistence
                ? $privacySafeResult
                : AuthKitActionResult::failure(
                    message: 'Password reset is not available for this account.',
                    status: 403,
                    flow: AuthKitFlowStep::failed(),
                    errors: [
                        AuthKitError::make(
                            'password_reset_not_available',
                            'Password reset is not available for this account.'
                        ),
                    ],
                    redirect: $this->forgotRedirect()
                );
        }

        $user = $this->users->resolve($email);

        if (! $user) {
            return $hideExistence
                ? $privacySafeResult
                : AuthKitActionResult::failure(
                    message: 'We could not find an account with that email address.',
                    status: 404,
                    flow: AuthKitFlowStep::failed(),
                    errors: [
                        AuthKitError::make(
                            'password_reset_user_not_found',
                            'We could not find an account with that email address.'
                        ),
                    ],
                    redirect: $this->forgotRedirect()
                );
        }

        /**
         * Intentionally persistence-aware.
         *
         * Forgot-password does not persist fields by default because the packaged
         * mapper marks the default field set as non-persistable. This call keeps
         * the action forward-compatible so a consumer may extend the mapper and
         * persist additional mapped attributes onto the resolved user model.
         */
        $this->persistMappedAttributesIfSupported($user, 'password_forgot', $input);

        $token = $this->pending->createForEmail(
            email: $email,
            ttlMinutes: $ttlMinutes,
            payload: [
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
            return $privacySafeResult;
        }

        $message = $driver === 'token'
            ? 'A password reset code has been sent.'
            : 'A password reset link has been sent.';

        return AuthKitActionResult::success(
            message: $message,
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $this->postRequestRedirect($email),
            payload: AuthKitPublicPayload::make([
                'email' => $email,
                'driver' => $driver,
                'privacy_mode' => false,
            ])
        );
    }

    /**
     * Resolve the forgot password page redirect.
     *
     * @return AuthKitRedirect
     */
    protected function forgotRedirect(): AuthKitRedirect
    {
        $webNames = (array) config('authkit.route_names.web', []);
        $routeName = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');

        return AuthKitRedirect::route(
            routeName: $routeName,
            parameters: [],
            url: route($routeName)
        );
    }

    /**
     * Resolve the post-request redirect target.
     *
     * @param string $email
     * @return AuthKitRedirect
     */
    protected function postRequestRedirect(string $email): AuthKitRedirect
    {
        $webNames = (array) config('authkit.route_names.web', []);
        $mode = (string) data_get(config('authkit.password_reset.post_request', []), 'mode', 'sent_page');

        if ($mode === 'token_page') {
            $routeName = (string) data_get(
                config('authkit.password_reset.post_request', []),
                'token_route',
                'authkit.web.password.reset.token'
            );

            return AuthKitRedirect::route(
                routeName: $routeName,
                parameters: ['email' => $email],
                url: route($routeName, ['email' => $email])
            );
        }

        $routeName = (string) data_get(
            config('authkit.password_reset.post_request', []),
            'sent_route',
            (string) ($webNames['password_forgot_sent'] ?? 'authkit.web.password.forgot.sent')
        );

        return AuthKitRedirect::route(
            routeName: $routeName,
            parameters: ['email' => $email],
            url: route($routeName, ['email' => $email])
        );
    }
}