<?php

namespace Xul\AuthKit\Actions\PasswordReset;

/**
 * PasswordResetRequestResult
 *
 * Outcome object for the "forgot password" request flow.
 *
 * This DTO intentionally supports privacy-preserving behavior where:
 * - The client receives a generic success message regardless of user existence.
 * - The backend may or may not generate tokens / dispatch events internally.
 */
final class PasswordResetRequestResult
{
    /**
     * @param bool   $ok
     * @param string $message
     * @param string $driver
     */
    public function __construct(
        public bool $ok,
        public string $message,
        public string $driver = ''
    ) {}

    /**
     * Generic "sent" response.
     *
     * Used for privacy-safe flows to avoid revealing whether the user exists.
     */
    public static function sent(string $driver, string $message): self
    {
        return new self(true, $message, $driver);
    }

    /**
     * Failure response.
     *
     * Note: in privacy mode, controllers should generally avoid using this
     * for "user not found" to prevent user enumeration.
     */
    public static function failed(string $message, string $driver = ''): self
    {
        return new self(false, $message, $driver);
    }
}