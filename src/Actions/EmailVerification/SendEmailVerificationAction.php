<?php

namespace Xul\AuthKit\Actions\EmailVerification;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Concerns\Actions\InteractsWithMappedPayload;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;
use Xul\AuthKit\Support\PendingEmailVerification;

/**
 * SendEmailVerificationAction
 *
 * Resends an email verification message to a user.
 *
 * Responsibilities:
 * - Consume normalized mapped email input.
 * - Resolve the user by email using the configured guard provider.
 * - Prevent resending to a different email than the authenticated user's email.
 * - Persist mapper-approved attributes when the resolved model supports
 *   AuthKit mapped persistence.
 * - Skip sending if the user is already verified.
 * - Create a verification token context through PendingEmailVerification.
 * - Build a signed URL when the active driver is link-based.
 * - Dispatch AuthKitEmailVerificationRequired for external delivery handling.
 * - Return a standardized AuthKitActionResult for all outcomes.
 *
 * Security:
 * - This action never returns raw tokens or signed URLs to the caller.
 * - Tokens and URLs are only emitted through the event for delivery purposes.
 */
final class SendEmailVerificationAction
{
    use InteractsWithMappedPayload;

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
     * Resend the email verification message.
     *
     * @param array<string, mixed> $input
     * @return AuthKitActionResult
     */
    public function handle(array $input): AuthKitActionResult
    {
        $attributes = $this->payloadAttributes($input);

        $email = mb_strtolower(trim((string) ($attributes['email'] ?? '')));

        if ($email === '') {
            return AuthKitActionResult::failure(
                message: 'Email is required.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::validation('email', 'The email field is required.', 'missing_email'),
                ],
            );
        }

        $guardName = (string) config('authkit.auth.guard', 'web');
        $guard = $this->auth->guard($guardName);

        $sessionUser = $guard->user();

        if ($sessionUser instanceof Authenticatable) {
            $sessionEmail = mb_strtolower(trim((string) ($sessionUser->email ?? '')));

            if ($sessionEmail !== '' && $sessionEmail !== $email) {
                return AuthKitActionResult::failure(
                    message: 'Invalid email verification context.',
                    status: 403,
                    flow: AuthKitFlowStep::failed(),
                    errors: [
                        AuthKitError::make(
                            'invalid_email_verification_context',
                            'Invalid email verification context.'
                        ),
                    ],
                    redirect: $this->noticeRedirect($email)
                );
            }
        }

        $user = $this->retrieveByEmail($email);

        if (! $user) {
            return AuthKitActionResult::failure(
                message: 'We could not find a user with that email address.',
                status: 404,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make(
                        'email_verification_user_not_found',
                        'We could not find a user with that email address.'
                    ),
                ],
                redirect: $this->noticeRedirect($email)
            );
        }

        /**
         * Intentionally persistence-aware.
         *
         * The packaged resend-email-verification mapper does not persist fields
         * by default. This call keeps the action forward-compatible so consumer
         * mapper extensions can mark additional resend attributes as persistable
         * and have them written automatically when the resolved user model
         * supports AuthKit mapped persistence.
         */
        $this->persistMappedAttributesIfSupported($user, 'email_verification_send', $input);

        if ($this->userHasVerifiedEmail($user)) {
            return AuthKitActionResult::success(
                message: 'Your email is already verified.',
                status: 200,
                flow: AuthKitFlowStep::completed(),
                redirect: $this->noticeRedirect($email),
                payload: AuthKitPublicPayload::make([
                    'email' => $email,
                    'already_verified' => true,
                ])
            );
        }

        $driver = (string) config('authkit.email_verification.driver', 'link');
        $ttl = (int) config('authkit.email_verification.ttl_minutes', 30);

        $token = $this->pending->createForEmail($email, $ttl, [
            'user_id' => (string) $user->getAuthIdentifier(),
            'driver' => $driver,
        ]);

        if (! is_string($token) || $token === '') {
            return AuthKitActionResult::failure(
                message: 'Unable to create verification token.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make(
                        'email_verification_token_creation_failed',
                        'Unable to create verification token.'
                    ),
                ],
                redirect: $this->noticeRedirect($email)
            );
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

        return AuthKitActionResult::success(
            message: 'Verification message sent.',
            status: 200,
            flow: AuthKitFlowStep::emailVerificationNotice(),
            redirect: $this->noticeRedirect($email),
            payload: AuthKitPublicPayload::make([
                'email' => $email,
                'driver' => $driver,
            ])
        );
    }

    /**
     * Resolve a user by email using the configured guard provider.
     *
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
     * Determine whether the given user has already verified their email address.
     *
     * @param Authenticatable $user
     * @return bool
     */
    protected function userHasVerifiedEmail(Authenticatable $user): bool
    {
        if (method_exists($user, 'hasVerifiedEmail')) {
            return (bool) $user->hasVerifiedEmail();
        }

        $verifiedAtColumn = (string) config('authkit.email_verification.columns.verified_at', 'email_verified_at');
        $verifiedAt = $user->{$verifiedAtColumn} ?? null;

        return $verifiedAt !== null && $verifiedAt !== '';
    }

    /**
     * Build the signed verification URL for the link driver.
     *
     * @param Authenticatable $user
     * @param string $email
     * @param int $ttlMinutes
     * @param string $token
     * @return string
     */
    protected function buildSignedLinkUrl(
        Authenticatable $user,
        string $email,
        int $ttlMinutes,
        string $token
    ): string {
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

    /**
     * Resolve the verification notice redirect.
     *
     * @param string $email
     * @return AuthKitRedirect
     */
    protected function noticeRedirect(string $email): AuthKitRedirect
    {
        $routeName = (string) data_get(
            config('authkit.route_names.web', []),
            'verify_notice',
            'authkit.web.email.verify.notice'
        );

        $parameters = $email !== '' ? ['email' => $email] : [];

        return AuthKitRedirect::route(
            routeName: $routeName,
            parameters: $parameters,
            url: route($routeName, $parameters)
        );
    }
}