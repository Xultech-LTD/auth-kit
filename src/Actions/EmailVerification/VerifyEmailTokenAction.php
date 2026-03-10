<?php

namespace Xul\AuthKit\Actions\EmailVerification;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Carbon;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Events\AuthKitEmailVerified;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Support\PendingEmailVerification;

/**
 * VerifyEmailTokenAction
 *
 * Verifies a user's email using a token or code.
 *
 * Responsibilities:
 * - Validate and consume the verification token using PendingEmailVerification.
 * - Resolve the user referenced by the token payload via the configured provider.
 * - Mark the user as verified when supported by the user model.
 * - Dispatch Laravel's Verified event when applicable.
 * - Dispatch AuthKitEmailVerified after successful verification.
 * - Optionally authenticate the user after successful verification.
 * - Return a standardized AuthKitActionResult for all outcomes.
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
     * @return AuthKitActionResult
     */
    public function handle(string $email, string $token): AuthKitActionResult
    {
        $email = mb_strtolower(trim($email));
        $token = trim($token);

        if ($email === '' || $token === '') {
            return AuthKitActionResult::failure(
                message: 'Email and verification code are required.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::validation('email', 'The email field is required.', 'missing_email'),
                    AuthKitError::validation('token', 'The token field is required.', 'missing_token'),
                ],
                redirect: $this->noticeRedirect($email)
            );
        }

        $payload = $this->pending->consumeToken($email, $token);

        if (! is_array($payload)) {
            return AuthKitActionResult::failure(
                message: 'Invalid or expired verification code.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_or_expired_verification_code', 'Invalid or expired verification code.'),
                ],
                redirect: $this->noticeRedirect($email)
            );
        }

        $userId = (string) ($payload['user_id'] ?? '');

        if ($userId === '') {
            return AuthKitActionResult::failure(
                message: 'Invalid verification context.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_verification_context', 'Invalid verification context.'),
                ],
                redirect: $this->noticeRedirect($email)
            );
        }

        $user = $this->retrieveById($userId);

        if (! $user) {
            return AuthKitActionResult::failure(
                message: 'Invalid verification context.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_verification_context', 'Invalid verification context.'),
                ],
                redirect: $this->noticeRedirect($email)
            );
        }

        $userEmail = mb_strtolower(trim((string) ($user->email ?? '')));

        if ($userEmail === '' || $userEmail !== $email) {
            return AuthKitActionResult::failure(
                message: 'Invalid verification context.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_verification_context', 'Invalid verification context.'),
                ],
                redirect: $this->noticeRedirect($email)
            );
        }

        if ($this->userHasVerifiedEmail($user)) {
            return AuthKitActionResult::success(
                message: 'Your email is already verified.',
                status: 200,
                flow: AuthKitFlowStep::completed(),
                redirect: $this->postVerifyRedirect(),
                payload: AuthKitPublicPayload::make([
                    'email' => $email,
                    'already_verified' => true,
                ])
            );
        }

        if (! $this->markUserVerified($user)) {
            return AuthKitActionResult::failure(
                message: 'Email verification is not supported by this user model.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('email_verification_not_supported', 'Email verification is not supported by this user model.'),
                ],
                redirect: $this->noticeRedirect($email)
            );
        }

        if ($user instanceof MustVerifyEmail) {
            event(new Verified($user));
        }

        event(new AuthKitEmailVerified(
            user: $user,
            driver: 'token'
        ));

        $loggedIn = $this->loginAfterVerify($user);

        return AuthKitActionResult::success(
            message: 'Email verified successfully.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $this->postVerifyRedirect(),
            payload: AuthKitPublicPayload::make([
                'email' => $email,
                'verified' => true,
                'logged_in' => $loggedIn,
            ])
        );
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
     * Determine whether the user is already verified.
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
     *
     * @param Authenticatable $user
     * @return bool
     */
    protected function loginAfterVerify(Authenticatable $user): bool
    {
        $enabled = (bool) data_get(config('authkit.email_verification.post_verify', []), 'login_after_verify', false);

        if (! $enabled) {
            return false;
        }

        $remember = (bool) data_get(config('authkit.email_verification.post_verify', []), 'remember', true);
        $guardName = (string) config('authkit.auth.guard', 'web');

        $this->auth->guard($guardName)->login($user, $remember);

        event(new AuthKitLoggedIn(
            user: $user,
            guard: $guardName,
            remember: $remember
        ));

        return true;
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

    /**
     * Resolve the redirect after successful verification.
     *
     * @return AuthKitRedirect
     */
    protected function postVerifyRedirect(): AuthKitRedirect
    {
        $webNames = (array) config('authkit.route_names.web', []);
        $mode = (string) data_get(config('authkit.email_verification.post_verify', []), 'mode', 'redirect');

        if ($mode === 'success_page') {
            $successRoute = (string) ($webNames['verify_success'] ?? 'authkit.web.email.verify.success');

            return AuthKitRedirect::route(
                routeName: $successRoute,
                parameters: [],
                url: route($successRoute)
            );
        }

        $configuredRedirectRoute = (string) (data_get(config('authkit.email_verification.post_verify', []), 'redirect_route') ?? '');

        if ($configuredRedirectRoute !== '') {
            return AuthKitRedirect::route(
                routeName: $configuredRedirectRoute,
                parameters: [],
                url: route($configuredRedirectRoute)
            );
        }

        $loginAfterVerify = (bool) data_get(config('authkit.email_verification.post_verify', []), 'login_after_verify', false);

        if ($loginAfterVerify) {
            $redirectRoute = data_get(config('authkit.login', []), 'redirect_route');
            $dashboardRoute = (string) data_get(config('authkit.login', []), 'dashboard_route', 'dashboard');

            $target = is_string($redirectRoute) && $redirectRoute !== ''
                ? $redirectRoute
                : $dashboardRoute;

            if ($target !== '') {
                return AuthKitRedirect::route(
                    routeName: $target,
                    parameters: [],
                    url: route($target)
                );
            }
        }

        $loginRoute = (string) ($webNames['login'] ?? 'authkit.web.login');

        return AuthKitRedirect::route(
            routeName: $loginRoute,
            parameters: [],
            url: route($loginRoute)
        );
    }
}