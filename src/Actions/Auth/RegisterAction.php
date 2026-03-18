<?php

namespace Xul\AuthKit\Actions\Auth;

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
use Xul\AuthKit\Events\AuthKitRegistered;
use Xul\AuthKit\Support\PendingEmailVerification;

/**
 * RegisterAction
 *
 * Creates a new user account and initializes the email verification flow.
 *
 * Responsibilities:
 * - Create a new user using the configured auth provider when possible.
 * - Consume normalized mapped registration payload data.
 * - Persist only fields explicitly marked as persistable by the mapper layer.
 * - Dispatch AuthKitRegistered after successful account creation.
 * - Initialize email verification when an email address is available.
 * - Dispatch AuthKitEmailVerificationRequired for external delivery handling.
 * - Return a standardized AuthKitActionResult for all outcomes.
 *
 * Verification token model:
 * - A raw token is generated and returned by PendingEmailVerification.
 * - Only a derived representation is stored by the backing repository.
 * - For link driver, the raw token is placed into the signed URL as {hash}.
 * - For token driver, the raw token is delivered externally through event listeners.
 *
 * Security notes:
 * - This action never returns the raw verification token to the caller.
 * - This action never returns the signed verification URL to the caller.
 * - Verification delivery remains event-driven and package-extensible.
 *
 * Expected mapped payload shape:
 * - attributes: persisted/business attributes such as name, email, password
 * - options: behavioral flags when applicable
 * - meta: non-persisted supporting context
 */
final class RegisterAction
{
    use InteractsWithMappedPayload;

    /**
     * Create a new instance.
     *
     * @param PendingEmailVerification $pendingEmailVerification
     * @param AuthFactory $auth
     */
    public function __construct(
        protected PendingEmailVerification $pendingEmailVerification,
        protected AuthFactory $auth,
    ) {}

    /**
     * Register a user and start the email verification flow.
     *
     * @param array<string, mixed> $data
     * @return AuthKitActionResult
     */
    public function handle(array $data): AuthKitActionResult
    {
        $attributes = $this->payloadAttributes($data);

        $user = $this->createUser($data);

        if (! $user) {
            return AuthKitActionResult::failure(
                message: 'Registration failed.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('registration_failed', 'Registration failed.'),
                ],
            );
        }

        event(new AuthKitRegistered($user));

        $email = trim((string) ($attributes['email'] ?? ''));

        if ($email === '') {
            return AuthKitActionResult::success(
                message: 'Account created.',
                status: 201,
                flow: AuthKitFlowStep::registrationCompleted(),
                payload: AuthKitPublicPayload::make([
                    'user_id' => (string) $user->getAuthIdentifier(),
                ]),
            );
        }

        $ttl = (int) config('authkit.email_verification.ttl_minutes', 30);
        $driver = (string) config('authkit.email_verification.driver', 'link');

        $token = $this->pendingEmailVerification->createForEmail($email, $ttl, [
            'user_id' => (string) $user->getAuthIdentifier(),
            'driver' => $driver,
        ]);

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

        $routeName = (string) data_get(
            config('authkit.route_names.web', []),
            'verify_notice',
            'authkit.web.email.verify.notice'
        );

        $parameters = ['email' => $email];

        return AuthKitActionResult::success(
            message: 'Account created. Please verify your email.',
            status: 201,
            flow: AuthKitFlowStep::emailVerificationNotice(),
            redirect: AuthKitRedirect::route(
                routeName: $routeName,
                parameters: $parameters,
                url: route($routeName, $parameters)
            ),
            payload: AuthKitPublicPayload::make([
                'user_id' => (string) $user->getAuthIdentifier(),
                'email' => $email,
                'driver' => $driver,
            ]),
        );
    }

    /**
     * Build the signed verification URL for the link driver.
     *
     * Route parameters:
     * - id: user identifier
     * - hash: raw verification token
     * - email: verification email context
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
     * Create a user using the configured auth provider when possible.
     *
     * Persistence behavior:
     * - Only mapper-approved persistable attributes are written.
     * - The model must support AuthKit mapped persistence.
     *
     * @param array<string, mixed> $data
     * @return Authenticatable|null
     */
    protected function createUser(array $data): ?Authenticatable
    {
        $guardName = (string) config('authkit.auth.guard', 'web');
        $provider = $this->auth->guard($guardName)->getProvider();

        if (! $provider instanceof UserProvider) {
            return null;
        }

        $model = $this->createProviderModel($provider);

        if (! $model) {
            return null;
        }

        $this->persistMappedAttributesIfSupported($model, 'register', $data);

        if (! method_exists($model, 'save')) {
            return null;
        }

        $model->save();

        return $model instanceof Authenticatable ? $model : null;
    }

    /**
     * Attempt to create a new model instance from a provider.
     *
     * @param UserProvider $provider
     * @return object|null
     */
    protected function createProviderModel(UserProvider $provider): ?object
    {
        if (method_exists($provider, 'createModel')) {
            $model = $provider->createModel();

            return is_object($model) ? $model : null;
        }

        return null;
    }
}