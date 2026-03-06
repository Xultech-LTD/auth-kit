<?php
// file: src/RateLimiting/DefaultIdentityResolver.php

namespace Xul\AuthKit\RateLimiting;

use Illuminate\Http\Request;
use Xul\AuthKit\RateLimiting\Contracts\IdentityResolverContract;

/**
 * Class DefaultIdentityResolver
 *
 * Default resolver for identity identifiers used in per-identity throttling buckets.
 *
 * Identity field:
 * - Determined by authkit.identity.login.field (defaults to "email")
 *
 * Normalization:
 * - Controlled by authkit.identity.login.normalize
 *   - lower: lowercases the value
 *   - trim:  trims the value (default behavior already includes trimming)
 *   - null/other: no additional normalization
 *
 * Return behavior:
 * - Returns null when the identity is missing or non-scalar, allowing callers to
 *   safely skip the per-identity bucket when not applicable.
 */
final class DefaultIdentityResolver implements IdentityResolverContract
{
    /**
     * Resolve the normalized identity value for throttling.
     *
     * @param  Request $request
     * @return string|null
     */
    public function resolve(Request $request): ?string
    {
        $field = trim((string) config('authkit.identity.login.field', 'email'));
        $normalize = config('authkit.identity.login.normalize', 'lower');

        if ($field === '') {
            $field = 'email';
        }

        $raw = $request->input($field);

        if (! is_scalar($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        if ($value === '') {
            return null;
        }

        return match ($normalize) {
            'lower' => mb_strtolower($value),
            default => $value,
        };
    }
}