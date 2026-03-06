<?php

namespace Xul\AuthKit\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Xul\AuthKit\Contracts\TokenRepositoryContract;

/**
 * CacheTokenRepository
 *
 * Cache-backed implementation of TokenRepositoryContract.
 *
 * Storage model:
 * - The raw token is generated and returned to the caller.
 * - A SHA-256 hash of the raw token is used to build the cache key.
 * - The payload is stored under the derived cache key for the configured TTL.
 *
 * Single-use model:
 * - validate() consumes the token using an atomic pull() operation.
 *
 * Token generation model:
 * - A driver is resolved for certain token types (e.g. email_verification, password_reset).
 * - If the resolved driver is "link", a long URL-suitable token is enforced:
 *   - length: 64
 *   - alphabet: alnum
 * - If the resolved driver is "token", token shape is resolved using configuration and overrides.
 *
 * Configuration model:
 * - Defaults: authkit.tokens.default
 * - Per-type overrides: authkit.tokens.types.{type}
 * - Runtime overrides: $options argument passed to create()
 *
 * Supported token option keys:
 * - length: int
 * - alphabet: string (digits|alpha|alnum|hex)
 * - uppercase: bool (applies to alpha/alnum)
 */
final class CacheTokenRepository implements TokenRepositoryContract
{
    /**
     * Create a new instance.
     *
     * @param CacheRepository $cache
     */
    public function __construct(
        protected CacheRepository $cache
    ) {}

    /**
     * Create a token and persist its payload.
     *
     * The token returned is the raw token. Only a hashed representation is used for storage.
     *
     * Token options are resolved using:
     * - authkit.tokens.default
     * - authkit.tokens.types.{type}
     * - $options overrides
     *
     * If the resolved driver for the token type is "link", the token is forced to:
     * - length: 64
     * - alphabet: alnum
     *
     * @param string $type
     * @param string $identifier
     * @param int $ttlMinutes
     * @param array $payload
     * @param array $options
     *
     * @return string
     */
    public function create(
        string $type,
        string $identifier,
        int $ttlMinutes,
        array $payload = [],
        array $options = []
    ): string {
        $driver = $this->resolveDriverForType($type);

        $tokenOptions = $this->resolveTokenOptions(
            type: $type,
            driver: $driver,
            overrides: $options
        );

        $rawToken = $this->generateToken($tokenOptions);

        $hashed = hash('sha256', $rawToken);
        $key = $this->key($type, $identifier, $hashed);

        $this->cache->put(
            $key,
            $payload,
            now()->addMinutes($ttlMinutes)
        );

        return $rawToken;
    }

    /**
     * Validate and consume a token.
     *
     * On success, the token is consumed atomically and the payload is returned.
     * On failure, null is returned.
     *
     * @param string $type
     * @param string $identifier
     * @param string $token
     *
     * @return array|null
     */
    public function validate(
        string $type,
        string $identifier,
        string $token
    ): ?array {
        $hashed = hash('sha256', $token);
        $key = $this->key($type, $identifier, $hashed);

        $payload = $this->cache->pull($key);

        return $payload ?: null;
    }

    /**
     * Delete a token without validating it.
     *
     * @param string $type
     * @param string $identifier
     * @param string $token
     *
     * @return void
     */
    public function delete(
        string $type,
        string $identifier,
        string $token
    ): void {
        $hashed = hash('sha256', $token);
        $key = $this->key($type, $identifier, $hashed);

        $this->cache->forget($key);
    }

    /**
     * Retrieve a token payload without consuming it.
     *
     * @param string $type
     * @param string $identifier
     * @param string $token
     *
     * @return array|null
     */
    public function peek(
        string $type,
        string $identifier,
        string $token
    ): ?array {
        $hashed = hash('sha256', $token);
        $key = $this->key($type, $identifier, $hashed);

        $payload = $this->cache->get($key);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Resolve the driver used for a given token type.
     *
     * Certain token types are governed by module-level driver configuration
     * (e.g. email verification and password reset).
     *
     * Types not explicitly mapped here are treated as token-driver flows.
     *
     * @param string $type
     *
     * @return string
     */
    protected function resolveDriverForType(string $type): string
    {
        return match ($type) {
            'email_verification' => (string) config('authkit.email_verification.driver', 'link'),
            'password_reset' => (string) config('authkit.password_reset.driver', 'link'),
            default => 'token',
        };
    }

    /**
     * Resolve token generation options for the given type and driver.
     *
     * Resolution order:
     * - authkit.tokens.default
     * - authkit.tokens.types.{type}
     * - runtime overrides
     *
     * If driver is "link", options are forced to a long URL-suitable token:
     * - length: 64
     * - alphabet: alnum
     *
     * Normalization rules:
     * - length is coerced to int and clamped to >= 1
     * - alphabet is coerced to string
     * - uppercase is coerced to bool
     *
     * @param string $type
     * @param string $driver
     * @param array $overrides
     *
     * @return array
     */
    protected function resolveTokenOptions(string $type, string $driver, array $overrides = []): array
    {
        $defaults = (array) config('authkit.tokens.default', []);
        $byType = (array) config("authkit.tokens.types.{$type}", []);

        $resolved = array_merge($defaults, $byType, $overrides);

        if ($driver === 'link') {
            $resolved['length'] = 64;
            $resolved['alphabet'] = 'alnum';
        }

        $resolved['length'] = max(1, (int) ($resolved['length'] ?? 64));
        $resolved['alphabet'] = (string) ($resolved['alphabet'] ?? 'alnum');
        $resolved['uppercase'] = (bool) ($resolved['uppercase'] ?? false);

        return $resolved;
    }

    /**
     * Generate a raw token based on resolved options.
     *
     * Supported alphabets:
     * - digits: 0-9
     * - alpha: a-z
     * - alnum: a-z + 0-9
     * - hex: 0-9 + a-f
     *
     * Uppercasing applies only to alpha and alnum tokens.
     *
     * @param array $options
     *
     * @return string
     */
    protected function generateToken(array $options): string
    {
        $length = (int) $options['length'];
        $alphabet = (string) $options['alphabet'];
        $uppercase = (bool) $options['uppercase'];

        $token = match ($alphabet) {
            'digits' => $this->randomDigits($length),
            'alpha' => $this->randomFromCharset('abcdefghijklmnopqrstuvwxyz', $length),
            'alnum' => $this->randomFromCharset('abcdefghijklmnopqrstuvwxyz0123456789', $length),
            'hex' => $this->randomHex($length),
            default => $this->randomFromCharset('abcdefghijklmnopqrstuvwxyz0123456789', $length),
        };

        if ($uppercase && in_array($alphabet, ['alpha', 'alnum'], true)) {
            $token = mb_strtoupper($token);
        }

        return $token;
    }

    /**
     * Generate a cryptographically secure numeric token of the given length.
     *
     * Leading zeros are permitted.
     *
     * @param int $length
     *
     * @return string
     */
    protected function randomDigits(int $length): string
    {
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= (string) random_int(0, 9);
        }

        return $out;
    }

    /**
     * Generate a cryptographically secure token using a fixed character set.
     *
     * @param string $charset
     * @param int $length
     *
     * @return string
     */
    protected function randomFromCharset(string $charset, int $length): string
    {
        $out = '';
        $max = mb_strlen($charset) - 1;

        for ($i = 0; $i < $length; $i++) {
            $out .= $charset[random_int(0, $max)];
        }

        return $out;
    }

    /**
     * Generate a cryptographically secure hexadecimal token of the given length.
     *
     * @param int $length
     *
     * @return string
     */
    protected function randomHex(int $length): string
    {
        $bytes = (int) ceil($length / 2);
        $hex = bin2hex(random_bytes($bytes));

        return substr($hex, 0, $length);
    }

    /**
     * Build the cache key used to store a token payload.
     *
     * Key format:
     * authkit:{type}:{identifier}:{hash}
     *
     * @param string $type
     * @param string $identifier
     * @param string $hash
     *
     * @return string
     */
    protected function key(
        string $type,
        string $identifier,
        string $hash
    ): string {
        return "authkit:{$type}:{$identifier}:{$hash}";
    }
}