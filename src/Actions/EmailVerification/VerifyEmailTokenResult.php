<?php

namespace Xul\AuthKit\Actions\EmailVerification;

/**
 * VerifyEmailTokenResult
 *
 * Result object returned by VerifyEmailTokenAction.
 */
final class VerifyEmailTokenResult
{
    public function __construct(
        public bool $ok,
        public string $message
    ) {}

    public static function verified(): self
    {
        return new self(true, 'Email verified successfully.');
    }

    public static function alreadyVerified(): self
    {
        return new self(true, 'Email is already verified.');
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}