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
 * VerifyEmailLinkAction
 *
 * Verifies a user via signed email verification link route parameters.
 *
 * Responsibilities:
 * - Resolve the user by ID through the configured auth provider.
 * - Validate and consume the verification token from the signed link.
 * - Mark the user as verified when supported by the user model.
 * - Dispatch Laravel's Verified event when applicable.
 * - Dispatch AuthKitEmailVerified after successful verification.
 * - Optionally authenticate the user after successful verification.
 * - Return a standardized AuthKitActionResult for all outcomes.
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
     * Execute the verification flow.
     *
     * @param string $id
     * @param string $hash
     * @return AuthKitActionResult
     */
    public function handle(string $id, string $hash): AuthKitActionResult
    {
        $id = trim($id);
        $hash = trim($hash);

        if ($id === '' || $hash === '') {
            return AuthKitActionResult::failure(
                message: 'Invalid verification link.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_verification_link', 'Invalid verification link.'),
                ],
                redirect: $this->loginRedirect()
            );
        }

        $user = $this->retrieveById($id);

        if (! $user) {
            return AuthKitActionResult::failure(
                message: 'Invalid verification link.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_verification_link', 'Invalid verification link.'),
                ],
                redirect: $this->loginRedirect()
            );
        }

        $email = mb_strtolower(trim((string) ($user->email ?? '')));

        if ($email === '') {
            return AuthKitActionResult::failure(
                message: 'Invalid verification link.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_verification_link', 'Invalid verification link.'),
                ],
                redirect: $this->loginRedirect()
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
                    'driver' => 'link',
                ])
            );
        }

        if (! $this->pending->isLinkContextValid($id, $hash)) {
            return AuthKitActionResult::failure(
                message: 'Invalid or expired verification link.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_or_expired_verification_link', 'Invalid or expired verification link.'),
                ],
                redirect: $this->loginRedirect()
            );
        }

        $payload = $this->pending->consumeToken($email, $hash);

        if (! is_array($payload)) {
            return AuthKitActionResult::failure(
                message: 'Invalid or expired verification link.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_or_expired_verification_link', 'Invalid or expired verification link.'),
                ],
                redirect: $this->loginRedirect()
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
                redirect: $this->loginRedirect()
            );
        }

        if ($user instanceof MustVerifyEmail) {
            event(new Verified($user));
        }

        event(new AuthKitEmailVerified(
            user: $user,
            driver: 'link'
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
                'driver' => 'link',
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

        return $this->loginRedirect();
    }

    /**
     * Resolve the login redirect.
     *
     * @return AuthKitRedirect
     */
    protected function loginRedirect(): AuthKitRedirect
    {
        $login = (string) data_get(
            config('authkit.route_names.web', []),
            'login',
            (string) data_get(config('authkit.email_verification.post_verify', []), 'login_route', 'authkit.web.login')
        );

        return AuthKitRedirect::route(
            routeName: $login,
            parameters: [],
            url: route($login)
        );
    }
}