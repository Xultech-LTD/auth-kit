<?php
// file: src/RateLimiting/AuthKitRateLimiterRegistrar.php

namespace Xul\AuthKit\RateLimiting;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Class AuthKitRateLimiterRegistrar
 *
 * Registers Laravel RateLimiter names for AuthKit, based on configuration.
 *
 * Configuration contract:
 * - authkit.rate_limiting.map: [ limiter_key => limiter_name|null ]
 *
 * Behavior:
 * - Skips invalid/disabled mappings.
 * - Registers RateLimiter::for($limiterName, fn ($request) => $builder->build($limiterKey, $request))
 */
final class AuthKitRateLimiterRegistrar
{
    /**
     * @param RateLimiterBuilder $builder Limiter builder used to produce one or more Limit buckets.
     */
    public function __construct(
        private readonly RateLimiterBuilder $builder,
    ) {}

    /**
     * Register all AuthKit RateLimiter names defined in config.
     *
     * @return void
     */
    public function register(): void
    {
        $map = config('authkit.rate_limiting.map', []);

        if (! is_array($map)) {
            return;
        }

        foreach ($map as $limiterKey => $limiterName) {
            if ($limiterName === null) {
                continue;
            }

            if (! is_string($limiterName)) {
                continue;
            }

            $limiterName = trim($limiterName);

            if ($limiterName === '') {
                continue;
            }

            $key = trim((string) $limiterKey);

            if ($key === '') {
                $key = 'unknown';
            }

            RateLimiter::for($limiterName, function ($request) use ($key) {
                return $this->builder->build($key, $request);
            });
        }
    }
}