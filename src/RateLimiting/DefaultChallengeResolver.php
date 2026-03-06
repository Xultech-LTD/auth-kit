<?php
// file: src/RateLimiting/DefaultChallengeResolver.php

namespace Xul\AuthKit\RateLimiting;

use Illuminate\Http\Request;
use Xul\AuthKit\RateLimiting\Contracts\ChallengeResolverContract;

/**
 * Class DefaultChallengeResolver
 *
 * Default resolver for "challenge" identifiers used in per-challenge throttling buckets.
 *
 * Primary use case:
 * - Two-factor challenge endpoints that accept a "challenge" reference in the request.
 *
 * Return behavior:
 * - Returns null when the challenge is missing or non-scalar, allowing callers to
 *   skip the per-challenge bucket when not applicable.
 */
final class DefaultChallengeResolver implements ChallengeResolverContract
{
    /**
     * Resolve the challenge reference for throttling.
     *
     * @param  Request $request
     * @return string|null
     */
    public function resolve(Request $request): ?string
    {
        $raw = $request->input('challenge');

        if (! is_scalar($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        return $value !== '' ? $value : null;
    }
}