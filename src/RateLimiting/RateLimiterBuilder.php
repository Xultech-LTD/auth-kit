<?php
// file: src/RateLimiting/RateLimiterBuilder.php

namespace Xul\AuthKit\RateLimiting;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Xul\AuthKit\RateLimiting\Contracts\ChallengeResolverContract;
use Xul\AuthKit\RateLimiting\Contracts\CustomLimiterResolverContract;
use Xul\AuthKit\RateLimiting\Contracts\IdentityResolverContract;
use Xul\AuthKit\RateLimiting\Contracts\IpResolverContract;

/**
 * Class RateLimiterBuilder
 *
 * Central builder for AuthKit's named RateLimiters.
 *
 * Responsibilities:
 * - Translate a logical limiter key (e.g. "login") into one or more Limit buckets.
 * - Apply the configured protection strategy:
 *   - dual:         per-ip + per-identity
 *   - per_ip:       per-ip only
 *   - per_identity: per-identity only (falls back to per-ip when identity is missing)
 *   - custom:       delegated to a consumer-provided CustomLimiterResolverContract
 *
 * Security posture:
 * - Always produces at least one bucket (per-ip) to avoid unthrottled endpoints.
 * - Never emits empty throttle keys (prevents bucket collapse).
 * - Treats malformed/missing configuration as a safe-default scenario.
 */
final class RateLimiterBuilder
{
    /**
     * @param ThrottleKeyFactory         $keys      Throttle key factory used to build stable bucket keys.
     * @param IpResolverContract         $ip        Resolver for client IP identifiers.
     * @param IdentityResolverContract   $identity  Resolver for normalized identity identifiers (e.g. email).
     * @param ChallengeResolverContract  $challenge Resolver for per-challenge identifiers (primarily 2FA flows).
     */
    public function __construct(
        private readonly ThrottleKeyFactory $keys,
        private readonly IpResolverContract $ip,
        private readonly IdentityResolverContract $identity,
        private readonly ChallengeResolverContract $challenge,
    ) {}

    /**
     * Build the Limits for a given logical limiter key.
     *
     * The returned value is suitable for RateLimiter::for():
     * - a single Limit instance
     * - or an array of Limit instances (multi-bucket throttling)
     *
     * @param  string  $limiterKey  Logical limiter key (e.g. "login", "email_verify_token").
     * @param  Request $request     The current request being evaluated by the limiter.
     * @return Limit|array<int, Limit>
     */
    public function build(string $limiterKey, Request $request): Limit|array
    {
        $limiterKey = $this->normalizeLimiterKey($limiterKey);

        $strategy = $this->strategyFor($limiterKey);

        if ($strategy === 'custom') {
            $custom = $this->resolveCustomLimiter();

            if ($custom instanceof CustomLimiterResolverContract) {
                $limits = $custom->resolve($limiterKey, $request);

                return $this->normalizeLimitReturn($limits, $limiterKey, $request);
            }

            $strategy = 'dual';
        }

        $bucketConfig = $this->bucketConfigFor($limiterKey);

        $ipKey = $this->keys->make($limiterKey, 'ip', $this->safeResolveIp($request));
        $idValue = $this->safeResolveIdentity($request);
        $idKey = $idValue !== null ? $this->keys->make($limiterKey, 'identity', $idValue) : null;

        $limits = [];

        if ($strategy === 'per_ip') {
            $limits[] = $this->makeLimit((array) ($bucketConfig['per_ip'] ?? []), $ipKey);

            return $limits;
        }

        if ($strategy === 'per_identity') {
            if ($idKey === null) {
                $limits[] = $this->makeLimit((array) ($bucketConfig['per_ip'] ?? []), $ipKey);

                return $limits;
            }

            $limits[] = $this->makeLimit((array) ($bucketConfig['per_identity'] ?? []), $idKey);

            return $limits;
        }

        $limits[] = $this->makeLimit((array) ($bucketConfig['per_ip'] ?? []), $ipKey);

        if ($idKey !== null) {
            $limits[] = $this->makeLimit((array) ($bucketConfig['per_identity'] ?? []), $idKey);
        }

        return $limits;
    }

    /**
     * Resolve the challenge reference for flows that want a dedicated per-challenge bucket.
     *
     * This is intentionally not applied automatically in build(), because not all endpoints
     * have a meaningful challenge identifier. Use this method inside a custom limiter or
     * in an endpoint-specific limiter builder if/when you decide to apply a third bucket.
     *
     * @param  Request $request
     * @return string|null
     */
    public function resolveChallenge(Request $request): ?string
    {
        try {
            $value = $this->challenge->resolve($request);

            if (! is_string($value)) {
                return null;
            }

            $value = trim($value);

            return $value !== '' ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build a stable per-challenge throttle key for a given limiter key.
     *
     * @param  string $limiterKey
     * @param  string $challenge
     * @return string
     */
    public function challengeKey(string $limiterKey, string $challenge): string
    {
        $limiterKey = $this->normalizeLimiterKey($limiterKey);
        $challenge = trim((string) $challenge);

        if ($challenge === '') {
            $challenge = 'unknown';
        }

        return $this->keys->make($limiterKey, 'challenge', $challenge);
    }

    /**
     * Create a Limit instance from bucket config and throttle key.
     *
     * Expected config shape:
     * - attempts: int >= 1
     * - decay_minutes: int >= 1
     *
     * @param  array  $bucketConfig
     * @param  string $key
     * @return Limit
     */
    private function makeLimit(array $bucketConfig, string $key): Limit
    {
        $attempts = (int) ($bucketConfig['attempts'] ?? 10);
        $decay = (int) ($bucketConfig['decay_minutes'] ?? 1);

        if ($attempts < 1) {
            $attempts = 1;
        }

        if ($decay < 1) {
            $decay = 1;
        }

        $key = trim($key);

        if ($key === '') {
            $key = $this->keys->make('unknown', 'unknown', 'unknown');
        }

        return Limit::perMinutes($decay, $attempts)->by($key);
    }

    /**
     * Resolve a consumer-provided custom limiter resolver, if configured.
     *
     * @return CustomLimiterResolverContract|null
     */
    private function resolveCustomLimiter(): ?CustomLimiterResolverContract
    {
        $class = config('authkit.rate_limiting.resolvers.limiter');

        if (! is_string($class) || trim($class) === '') {
            return null;
        }

        try {
            $resolved = app($class);

            return $resolved instanceof CustomLimiterResolverContract ? $resolved : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve the current protection strategy for a limiter key.
     *
     * @param  string $limiterKey
     * @return string
     */
    private function strategyFor(string $limiterKey): string
    {
        $strategy = (string) data_get(config('authkit.rate_limiting.strategy', []), $limiterKey, 'dual');

        $strategy = trim($strategy);

        return in_array($strategy, ['dual', 'per_ip', 'per_identity', 'custom'], true)
            ? $strategy
            : 'dual';
    }

    /**
     * Resolve the bucket configuration for a limiter key.
     *
     * @param  string $limiterKey
     * @return array
     */
    private function bucketConfigFor(string $limiterKey): array
    {
        $limits = data_get(config('authkit.rate_limiting.limits', []), $limiterKey, []);

        return is_array($limits) ? $limits : [];
    }

    /**
     * Normalize the limiter key into a stable non-empty string.
     *
     * @param  string $limiterKey
     * @return string
     */
    private function normalizeLimiterKey(string $limiterKey): string
    {
        $limiterKey = trim((string) $limiterKey);

        return $limiterKey !== '' ? $limiterKey : 'unknown';
    }

    /**
     * Safely resolve the client IP identifier for throttling.
     *
     * @param  Request $request
     * @return string
     */
    private function safeResolveIp(Request $request): string
    {
        try {
            $value = $this->ip->resolve($request);
            $value = trim((string) $value);

            return $value !== '' ? $value : 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Safely resolve the normalized identity value for throttling.
     *
     * @param  Request $request
     * @return string|null
     */
    private function safeResolveIdentity(Request $request): ?string
    {
        try {
            $value = $this->identity->resolve($request);

            if (! is_string($value)) {
                return null;
            }

            $value = trim($value);

            return $value !== '' ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalize the return value from a custom limiter into a safe array of Limit objects.
     *
     * If the custom limiter returns an invalid structure, this method falls back to the
     * default per-ip bucket for the limiter key.
     *
     * @param  mixed   $limits
     * @param  string  $limiterKey
     * @param  Request $request
     * @return array<int, Limit>
     */
    private function normalizeLimitReturn(mixed $limits, string $limiterKey, Request $request): array
    {
        if ($limits instanceof Limit) {
            return [$limits];
        }

        if (is_array($limits)) {
            $out = [];

            foreach ($limits as $limit) {
                if ($limit instanceof Limit) {
                    $out[] = $limit;
                }
            }

            if ($out !== []) {
                return $out;
            }
        }

        $ipKey = $this->keys->make($limiterKey, 'ip', $this->safeResolveIp($request));
        $bucketConfig = $this->bucketConfigFor($limiterKey);

        return [$this->makeLimit((array) ($bucketConfig['per_ip'] ?? []), $ipKey)];
    }
}