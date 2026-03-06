<?php
// file: src/RateLimiting/RateLimitingServiceProviderMixin.php

namespace Xul\AuthKit\RateLimiting;

use Illuminate\Contracts\Container\BindingResolutionException;
use Xul\AuthKit\RateLimiting\Contracts\ChallengeResolverContract;
use Xul\AuthKit\RateLimiting\Contracts\IdentityResolverContract;
use Xul\AuthKit\RateLimiting\Contracts\IpResolverContract;

/**
 * Trait RateLimitingServiceProviderMixin
 *
 * ServiceProvider mixin that wires AuthKit rate limiting into the container.
 *
 * Intended usage:
 * - Call registerAuthKitRateLimiting() from your package ServiceProvider::register()
 * - Call bootAuthKitRateLimiting() from your package ServiceProvider::boot()
 *
 * Extensibility:
 * - Resolvers may be replaced via config:
 *   - authkit.rate_limiting.resolvers.ip
 *   - authkit.rate_limiting.resolvers.identity
 *   - authkit.rate_limiting.resolvers.challenge
 *   - authkit.rate_limiting.resolvers.limiter (custom builder)
 *
 * Safety:
 * - If a configured resolver cannot be resolved or does not implement the expected contract,
 *   AuthKit falls back to the default resolver implementation.
 */
trait RateLimitingServiceProviderMixin
{
    /**
     * Register AuthKit rate-limiting bindings.
     *
     * @return void
     */
    protected function registerAuthKitRateLimiting(): void
    {
        $this->app->singleton(ThrottleKeyFactory::class);

        $this->app->singleton(IpResolverContract::class, function () {
            $class = config('authkit.rate_limiting.resolvers.ip');

            if (is_string($class) && trim($class) !== '') {
                try {
                    $resolved = app($class);

                    if ($resolved instanceof IpResolverContract) {
                        return $resolved;
                    }
                } catch (\Throwable) {
                }
            }

            return new DefaultIpResolver();
        });

        $this->app->singleton(IdentityResolverContract::class, function () {
            $class = config('authkit.rate_limiting.resolvers.identity');

            if (is_string($class) && trim($class) !== '') {
                try {
                    $resolved = app($class);

                    if ($resolved instanceof IdentityResolverContract) {
                        return $resolved;
                    }
                } catch (\Throwable) {
                }
            }

            return new DefaultIdentityResolver();
        });

        $this->app->singleton(ChallengeResolverContract::class, function () {
            $class = config('authkit.rate_limiting.resolvers.challenge');

            if (is_string($class) && trim($class) !== '') {
                try {
                    $resolved = app($class);

                    if ($resolved instanceof ChallengeResolverContract) {
                        return $resolved;
                    }
                } catch (\Throwable) {
                }
            }

            return new DefaultChallengeResolver();
        });

        $this->app->singleton(RateLimiterBuilder::class, function ($app) {
            return new RateLimiterBuilder(
                keys: $app->make(ThrottleKeyFactory::class),
                ip: $app->make(IpResolverContract::class),
                identity: $app->make(IdentityResolverContract::class),
                challenge: $app->make(ChallengeResolverContract::class),
            );
        });

        $this->app->singleton(AuthKitRateLimiterRegistrar::class);

        $this->app->singleton(RateLimitMiddlewareFactory::class);
    }

    /**
     * Boot AuthKit rate-limiting registration.
     *
     * @return void
     * @throws \Throwable
     */
    protected function bootAuthKitRateLimiting(): void
    {
        $this->app->make(AuthKitRateLimiterRegistrar::class)->register();
    }
}