<?php

namespace Xul\AuthKit\Actions\EmailVerification;

/**
 * SendEmailVerificationResult
 *
 * Outcome object for the email verification send/resend action.
 *
 * Properties:
 * - ok: Whether the operation succeeded logically.
 * - message: Human-friendly message for UI/JSON responses.
 * - driver: Verification driver used (link|token).
 */
final class SendEmailVerificationResult
{
    /**
     * @param bool $ok
     * @param string $message
     * @param string $driver
     */
    public function __construct(
        public bool $ok,
        public string $message,
        public string $driver = ''
    ) {}

    /**
     * @param string $driver
     * @return self
     */
    public static function sent(string $driver): self
    {
        return new self(true, 'Verification message sent.', $driver);
    }

    /**
     * @return self
     */
    public static function alreadyVerified(): self
    {
        return new self(true, 'Your email is already verified.');
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