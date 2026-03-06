<?php

namespace Xul\AuthKit\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Psr\SimpleCache\InvalidArgumentException;
use Xul\AuthKit\Contracts\TokenRepositoryContract;

/**
 * PendingEmailVerification
 *
 * Tracks pending email verification state.
 *
 * Token driver:
 * - Creates a single-use token in TokenRepository keyed by email.
 * - Also sets a short-lived "presence" key keyed by email so the UI middleware
 *   can confirm a pending verification exists without knowing the raw token.
 * - Token shape is influenced by authkit.tokens configuration.
 *
 * Link driver:
 * - * This is used by the link driver where the user receives a signed URL
 *   and no token is created.
 * - Validates incoming {id}/{hash} route params against a real user record.
 */
final class PendingEmailVerification
{
    /**
     * Create a new instance.
     *
     * @param TokenRepositoryContract $tokens
     * @param CacheRepository $cache
     * @param AuthFactory $auth
     */
    public function __construct(
        protected TokenRepositoryContract $tokens,
        protected CacheRepository $cache,
        protected AuthFactory $auth
    ) {}

    /**
     * Create a pending verification token for an email.
     *
     * Presence tracking:
     * - A presence key is stored so UI middleware can assert that a verification flow exists.
     *
     * Token generation:
     * - The repository resolves the active driver (link vs token) for the email_verification type.
     * - When driver is token, options are supplied from:
     *   - authkit.tokens.types.email_verification
     * - When driver is link, options are not supplied here; the repository enforces a long token.
     *
     * @param string $email
     * @param int $ttlMinutes
     * @param array $payload
     *
     * @return string
     */
    public function createForEmail(string $email, int $ttlMinutes, array $payload = []): string
    {
        $emailKey = $this->emailKey($email);

        $this->cache->put(
            $this->presenceKey($emailKey),
            true,
            now()->addMinutes($ttlMinutes)
        );

        $driver = (string) config('authkit.email_verification.driver', 'link');

        $options = $driver === 'token'
            ? (array) config('authkit.tokens.types.email_verification', [])
            : [];

        return $this->tokens->create(
            type: 'email_verification',
            identifier: $emailKey,
            ttlMinutes: $ttlMinutes,
            payload: array_merge([
                'email' => $emailKey,
                'created_at' => now()->toISOString(),
            ], $payload),
            options: $options
        );
    }

    /**
     * Mark an email as having a pending verification flow.
     *
     * This is useful when a caller wants UI middleware to recognize
     * a pending verification context without creating or reissuing
     * a new verification token.
     *
     * @param string $email
     * @param int $ttlMinutes
     * @return void
     */
    public function markPendingForEmail(string $email, int $ttlMinutes): void
    {
        $emailKey = $this->emailKey($email);

        $this->cache->put(
            $this->presenceKey($emailKey),
            true,
            now()->addMinutes($ttlMinutes)
        );
    }

    /**
     * Determine if an email currently has a pending verification.
     *
     * @param string $email
     * @return bool
     * @throws InvalidArgumentException
     */
    public function hasPendingForEmail(string $email): bool
    {
        $emailKey = $this->emailKey($email);

        return (bool) $this->cache->get($this->presenceKey($emailKey), false);
    }

    /**
     * Validate and consume a pending verification token.
     *
     * @param string $email
     * @param string $token
     * @return array|null
     */
    public function consumeToken(string $email, string $token): ?array
    {
        $emailKey = $this->emailKey($email);

        $payload = $this->tokens->validate(
            type: 'email_verification',
            identifier: $emailKey,
            token: $token
        );

        if ($payload) {
            $this->cache->forget($this->presenceKey($emailKey));
        }

        return $payload;
    }

    /**
     * Validate link-based verification route params against a user.
     *
     * @param string $id
     * @param string $hash
     * @return bool
     */
    public function isLinkContextValid(string $id, string $hash): bool
    {
        $user = $this->retrieveById($id);

        if (!$user) {
            return false;
        }

        $email = (string) ($user->email ?? '');

        if ($email === '') {
            return false;
        }

        $emailKey = $this->emailKey($email);

        $payload = $this->tokens->peek(
            type: 'email_verification',
            identifier: $emailKey,
            token: $hash
        );

        return is_array($payload);
    }

    /**
     * Retrieve a user by ID using the configured auth provider.
     *
     * @param string $id
     * @return Authenticatable|null
     */
    protected function retrieveById(string $id): ?Authenticatable
    {
        $guardName = (string) config('authkit.auth.guard', 'web');

        $guard = $this->auth->guard($guardName);

        $provider = $guard->getProvider();

        $user = $provider instanceof UserProvider
            ? $provider->retrieveById($id)
            : null;

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * Normalize email into a stable identifier.
     *
     * @param string $email
     * @return string
     */
    protected function emailKey(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Build the presence key for a given email identifier.
     *
     * @param string $emailKey
     * @return string
     */
    protected function presenceKey(string $emailKey): string
    {
        return "authkit:email_verification:pending:{$emailKey}";
    }
}