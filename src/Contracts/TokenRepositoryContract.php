<?php

namespace Xul\AuthKit\Contracts;

use Psr\SimpleCache\InvalidArgumentException;

/**
 * TokenRepositoryContract
 *
 * Contract for creating and managing short-lived tokens used by AuthKit flows.
 *
 * Typical token use cases include:
 * - Email verification
 * - Password reset
 * - Two-factor authentication challenges
 * - Pending login references
 *
 * Token invariants:
 * - Raw token values are returned to the caller for delivery to the user.
 * - Raw token values must not be persisted in storage.
 * - Validation must consume the token (single-use).
 * - Expiration is enforced by the underlying storage TTL.
 *
 * Token generation may be influenced by:
 * - Module driver configuration (e.g. "link" vs "token")
 * - Package defaults configured in authkit.tokens
 * - Per-token-type overrides configured in authkit.tokens.types
 * - Runtime overrides provided via the $options argument
 */
interface TokenRepositoryContract
{
    /**
     * Create a token and store its payload for a limited time.
     *
     * The returned value is the raw token intended for delivery to the user.
     * Implementations must store only a derived representation (e.g. a hash).
     *
     * Runtime options may override configured defaults. Supported options depend on
     * the repository implementation, but commonly include:
     * - length: int
     * - alphabet: string (digits|alpha|alnum|hex)
     * - uppercase: bool
     *
     * @param string $type        Logical token group (e.g. email_verification, password_reset, pending_login).
     * @param string $identifier  Token owner identifier within the group (e.g. normalized email, user id).
     * @param int    $ttlMinutes  Token lifetime in minutes.
     * @param array  $payload     Metadata stored alongside the token.
     * @param array  $options     Runtime token generation overrides.
     *
     * @return string             Raw token to be delivered to the user.
     */
    public function create(
        string $type,
        string $identifier,
        int $ttlMinutes,
        array $payload = [],
        array $options = []
    ): string;

    /**
     * Validate and consume a token.
     *
     * If valid:
     * - Returns the stored payload.
     * - Invalidates the token immediately (single-use).
     *
     * If invalid or expired:
     * - Returns null.
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
    ): ?array;

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
    ): void;

    /**
     * Retrieve a token payload without consuming it.
     *
     * Useful for multi-step flows where the presence of a token must be checked
     * before attempting validation.
     *
     * @param string $type
     * @param string $identifier
     * @param string $token
     *
     * @return array|null
     * @throws InvalidArgumentException
     */
    public function peek(
        string $type,
        string $identifier,
        string $token
    ): ?array;
}