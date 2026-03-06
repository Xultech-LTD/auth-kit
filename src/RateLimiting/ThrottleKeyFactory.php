<?php
// file: src/RateLimiting/ThrottleKeyFactory.php

namespace Xul\AuthKit\RateLimiting;

/**
 * Class ThrottleKeyFactory
 *
 * Builds stable throttle keys used as the "by()" identifier for Laravel's Limit buckets.
 *
 * Format:
 * - authkit|{limiter_key}|{bucket_type}|{bucket_value}
 *
 * Design goals:
 * - Prefix all keys to avoid collisions with host application throttles.
 * - Ensure no segment is empty to prevent accidental bucket collapse.
 */
final class ThrottleKeyFactory
{
    /**
     * Build a stable throttle key for a limiter and bucket.
     *
     * @param  string $limiterKey   Logical limiter key (e.g. "login").
     * @param  string $bucketType   Bucket type (e.g. "ip", "identity", "challenge").
     * @param  string $bucketValue  Bucket value (e.g. IP string, normalized email).
     * @return string
     */
    public function make(string $limiterKey, string $bucketType, string $bucketValue): string
    {
        $limiterKey = trim($limiterKey);
        $bucketType = trim($bucketType);
        $bucketValue = trim($bucketValue);

        if ($limiterKey === '') {
            $limiterKey = 'unknown';
        }

        if ($bucketType === '') {
            $bucketType = 'unknown';
        }

        if ($bucketValue === '') {
            $bucketValue = 'unknown';
        }

        return "authkit|{$limiterKey}|{$bucketType}|{$bucketValue}";
    }
}