<?php

namespace Xul\AuthKit\Actions\EmailVerification;

/**
 * VerifyEmailLinkResult
 *
 * Simple result object for email verification via link.
 */
final class VerifyEmailLinkResult
{
    /**
     * Create a new instance.
     *
     * @param bool $ok
     * @param bool $alreadyVerified
     * @param string $message
     */
    private function __construct(
        public readonly bool $ok,
        public readonly bool $alreadyVerified,
        public readonly string $message
    ) {}

    /**
     * Verification succeeded.
     *
     * @return self
     */
    public static function verified(): self
    {
        return new self(true, false, 'Your email has been verified.');
    }

    /**
     * Email was already verified.
     *
     * @return self
     */
    public static function alreadyVerified(): self
    {
        return new self(true, true, 'Your email is already verified.');
    }

    /**
     * Verification failed.
     *
     * @param string $message
     * @return self
     */
    public static function failed(string $message): self
    {
        return new self(false, false, $message);
    }
}