<?php
// file: src/RateLimiting/RateLimitMiddlewareFactory.php

namespace Xul\AuthKit\RateLimiting;

/**
 * Class RateLimitMiddlewareFactory
 *
 * Produces throttle middleware strings for AuthKit routes without hardcoding limiter names.
 *
 * Example:
 * - authkit.rate_limiting.map.login = "authkit.auth.login"
 * - middlewareFor("login") => "throttle:authkit.auth.login"
 *
 * Behavior:
 * - Returns null if the limiter key is unmapped/disabled.
 * - Normalizes input and output to avoid invalid middleware declarations.
 */
final class RateLimitMiddlewareFactory
{
    /**
     * Resolve a throttle middleware string for a given AuthKit limiter key.
     *
     * @param  string $limiterKey
     * @return string|null
     */
    public function middlewareFor(string $limiterKey): ?string
    {
        $limiterKey = trim((string) $limiterKey);

        if ($limiterKey === '') {
            return null;
        }

        $name = config("authkit.rate_limiting.map.{$limiterKey}");

        if ($name === null) {
            return null;
        }

        if (! is_string($name)) {
            return null;
        }

        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return "throttle:{$name}";
    }
}