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
     * @param string|null $redirectUrl
     */
    public function __construct(
        public bool $ok,
        public string $message,
        public string $driver = '',
        public ?string $redirectUrl = null
    ) {}

    /**
     * @param string $driver
     * @param string|null $redirectUrl
     * @return self
     */
    public static function sent(string $driver, string $redirectUrl=null): self
    {
        return new self(true, 'Verification message sent.', $driver, $redirectUrl);
    }

    /**
     * @param string|null $redirectUrl
     * @return self
     */
    public static function alreadyVerified(string $redirectUrl=null): self
    {
        return new self(true, 'Your email is already verified.', $redirectUrl);
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