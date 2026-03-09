<?php

namespace Xul\AuthKit\Actions\PasswordReset;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * ResetPasswordResult
 *
 * Outcome object for the reset password action.
 *
 * Properties:
 * - ok: Whether the operation succeeded logically.
 * - message: Human-friendly message for UI/JSON responses.
 * - user: The resolved user (present on success when available).
 */
final class ResetPasswordResult
{
    /**
     * @param bool $ok
     * @param string $message
     * @param Authenticatable|null $user
     * @param string|null $redirectUrl
     */
    public function __construct(
        public bool $ok,
        public string $message,
        public ?Authenticatable $user = null,
        public ?string $redirectUrl = null,
    ) {}

    /**
     * @param Authenticatable|null $user
     * @param string|null $redirectUrl
     * @return self
     */
    public static function success(?Authenticatable $user = null, ?string $redirectUrl = null): self
    {
        return new self(true, 'Password reset successful.', $user, $redirectUrl);
    }

    /**
     * @return self
     */
    public static function invalidToken(): self
    {
        return new self(false, 'This reset link or code is invalid or has expired.');
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