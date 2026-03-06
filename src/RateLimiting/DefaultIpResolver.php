<?php
// file: src/RateLimiting/DefaultIpResolver.php

namespace Xul\AuthKit\RateLimiting;

use Illuminate\Http\Request;
use Xul\AuthKit\RateLimiting\Contracts\IpResolverContract;

/**
 * Class DefaultIpResolver
 *
 * Default resolver for client IP identifiers used in per-IP throttling buckets.
 *
 * Notes:
 * - Returns a non-empty string in all cases.
 * - Consumers behind proxies/load balancers may override this resolver to ensure
 *   the correct client IP is used (e.g. trusted proxies configuration).
 */
final class DefaultIpResolver implements IpResolverContract
{
    /**
     * Resolve a stable IP identifier for throttling.
     *
     * @param  Request $request
     * @return string
     */
    public function resolve(Request $request): string
    {
        $ip = trim((string) ($request->ip() ?? ''));

        return $ip !== '' ? $ip : 'unknown';
    }
}