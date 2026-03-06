<?php

namespace Xul\AuthKit\Actions\PasswordReset;

/**
 * VerifyPasswordResetTokenResult
 *
 * Outcome object for the verify reset token action.
 *
 * Properties:
 * - ok: Whether the token is valid for the given identity.
 * - message: Human-friendly message for UI/JSON responses.
 */
final class VerifyPasswordResetTokenResult
{
    /**
     * @param bool $ok
     * @param string $message
     */
    public function __construct(
        public bool $ok,
        public string $message
    ) {}

    /**
     * @return self
     */
    public static function success(): self
    {
        return new self(true, 'Reset token verified.');
    }

    /**
     * @return self
     */
    public static function invalidToken(): self
    {
        return new self(false, 'This reset code is invalid or has expired.');
    }

    /**
     * @return self
     */
    public static function expired(): self
    {
        return new self(false, 'Reset request has expired.');
    }

    /**
     * @return self
     */
    public static function wrongDriver(): self
    {
        return new self(false, 'Token verification is not enabled for the current reset driver.');
    }

    /**
     * @return self
     */
    public static function throttled(): self
    {
        return new self(false, 'Too many attempts. Please try again later.');
    }

    /**
     * @param string $message
     * @return self
     */
    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}