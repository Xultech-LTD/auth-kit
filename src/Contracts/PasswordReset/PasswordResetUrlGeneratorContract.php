<?php

namespace Xul\AuthKit\Contracts\PasswordReset;

/**
 * PasswordResetUrlGeneratorContract
 *
 * Contract for generating password reset URLs for link-driver flows.
 *
 * Consumers may override this if:
 * - the application uses a different route shape,
 * - additional signed parameters are required,
 * - the reset flow is hosted on a different domain/frontend.
 *
 * Notes:
 * - This is only used when authkit.password_reset.driver = 'link'.
 * - The $token provided is the raw reset token.
 */
interface PasswordResetUrlGeneratorContract
{
    /**
     * Generate a reset URL that the user can open in a browser.
     *
     * @param string $email Normalized identity value.
     * @param string $token Raw reset token.
     *
     * @return string
     */
    public function make(string $email, string $token): string;
}