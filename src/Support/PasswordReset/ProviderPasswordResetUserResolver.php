<?php

namespace Xul\AuthKit\Support\PasswordReset;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Str;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract;

/**
 * ProviderPasswordResetUserResolver
 *
 * Default user resolver that uses the configured guard's UserProvider to locate users.
 *
 * This keeps AuthKit compatible with custom user providers, multiple auth guards,
 * and alternative user models, without hard-coding Eloquent queries.
 *
 * Notes:
 * - We intentionally attempt both "retrieveByCredentials" and common fallbacks to keep
 *   the resolver compatible across providers.
 * - This resolver returns null when a user cannot be found.
 */
final class ProviderPasswordResetUserResolver implements PasswordResetUserResolverContract
{
    public function __construct(
        protected AuthFactory $auth
    ) {}

    /**
     * Resolve a user model for the provided identity value.
     */
    public function resolve(string $identityValue): ?Authenticatable
    {
        $guard = (string) config('authkit.auth.guard', 'web');

        /** @var UserProvider $provider */
        $provider = $this->auth->guard($guard)->getProvider();

        $identityField = (string) config('authkit.identity.login.field', 'email');

        // Prefer provider credential resolution where possible.
        if (method_exists($provider, 'retrieveByCredentials')) {
            $user = $provider->retrieveByCredentials([$identityField => $identityValue]);

            return $user instanceof Authenticatable ? $user : null;
        }

        // Fallback to Eloquent-style providers (best-effort).
        if (method_exists($provider, 'createModel')) {
            $model = $provider->createModel();

            if (method_exists($model, 'newQuery')) {
                $row = $model->newQuery()->where($identityField, $identityValue)->first();

                return $row instanceof Authenticatable ? $row : null;
            }
        }

        return null;
    }
}