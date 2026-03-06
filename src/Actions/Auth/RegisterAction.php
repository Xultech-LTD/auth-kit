<?php

namespace Xul\AuthKit\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Events\AuthKitRegistered;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;
use Xul\AuthKit\Support\PendingEmailVerification;

/**
 * RegisterAction
 *
 * Creates a new user account and initializes the email verification flow.
 *
 * Verification token model:
 * - A raw token is generated and returned by TokenRepositoryContract::create().
 * - Only a derived representation (sha256) is stored by the repository.
 * - For link driver, the raw token is placed into the signed URL as {hash}.
 * - For token driver, the raw token is delivered to the user (email/SMS/etc).
 *
 * Delivery model:
 * - This action dispatches AuthKitEmailVerificationRequired after creating the pending token.
 * - Delivery is handled externally (e.g. by listeners) to keep the action package-extensible.
 */
final class RegisterAction
{
    /**
     * Create a new instance.
     */
    public function __construct(
        protected PendingEmailVerification $pendingEmailVerification
    ) {}

    /**
     * Register a user and start email verification flow.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $user = $this->createUser($data);

        if (! $user) {
            return [
                'ok' => false,
                'message' => 'Registration failed.',
            ];
        }

        event(new AuthKitRegistered($user));

        $email = (string) ($data['email'] ?? '');

        if ($email === '') {
            return [
                'ok' => true,
                'message' => 'Account created.',
                'user_id' => $user->getAuthIdentifier(),
            ];
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

        return [
            'ok' => true,
            'message' => 'Account created. Please verify your email.',
            'user_id' => $user->getAuthIdentifier(),
            'email' => $email,
            'redirect_params' => ['email' => $email],
        ];
    }

    /**
     * Build the signed verification URL for the link driver.
     *
     * Route parameters:
     * - id: user identifier
     * - hash: raw verification token (repository stores only sha256(token))
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
     * @param  array<string, mixed>  $data
     * @return Authenticatable|null
     */
    protected function createUser(array $data): ?Authenticatable
    {
        $guard = (string) config('authkit.auth.guard', 'web');
        $provider = auth()->guard($guard)->getProvider();

        if (!$provider instanceof UserProvider) {
            return null;
        }

        $model = $this->createProviderModel($provider);

        if (!$model) {
            return null;
        }

        $payload = $this->userPayload($data);

        foreach ($payload as $k => $v) {
            $model->{$k} = $v;
        }

        if (!method_exists($model, 'save')) {
            return null;
        }

        $model->save();

        return $model instanceof Authenticatable ? $model : null;
    }

    /**
     * Attempt to create a new model instance from a provider.
     *
     * @return object|null
     */
    protected function createProviderModel(UserProvider $provider): ?object
    {
        if (method_exists($provider, 'createModel')) {
            $m = $provider->createModel();

            return is_object($m) ? $m : null;
        }

        return null;
    }

    /**
     * Prepare the user payload from request data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function userPayload(array $data): array
    {
        $out = [];

        if (array_key_exists('name', $data) && is_string($data['name'])) {
            $out['name'] = trim($data['name']);
        }

        if (array_key_exists('email', $data) && is_string($data['email'])) {
            $out['email'] = mb_strtolower(trim($data['email']));
        }

        if (array_key_exists('password', $data) && is_string($data['password'])) {
            $out['password'] = Hash::make($data['password']);
        }

        return $out;
    }
}