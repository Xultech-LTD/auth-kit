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
        public string $message,
        public ?string $redirectUrl = null
    ) {}

    public static function verified( string $redirectUrl=null): self
    {
        return new self(true, 'Email verified successfully.', $redirectUrl);
    }

    public static function alreadyVerified( string $redirectUrl=null): self
    {
        return new self(true, 'Email is already verified.', $redirectUrl);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}