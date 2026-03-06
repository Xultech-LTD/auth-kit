<?php

namespace Xul\AuthKit\Contracts\PasswordReset;

/**
 * PasswordResetNotifierContract
 *
 * Contract for delivering password reset instructions to an identity address.
 *
 * AuthKit supports multiple password reset delivery drivers:
 * - link  : deliver a reset URL containing the raw token.
 * - token : deliver a short reset code/token for manual entry.
 *
 * Implementations may deliver via email, SMS, WhatsApp, queue workers, etc.
 *
 * Notes:
 * - The $token provided is always the raw token returned by TokenRepositoryContract::create().
 * - The notifier MUST NOT persist the raw token.
 * - For driver=link, $url will be provided and should be sent to the user.
 * - For driver=token, $url may be null and the token should be delivered for manual entry.
 */
interface PasswordResetNotifierContract
{
    /**
     * Deliver password reset instructions.
     *
     * @param string      $driver  Reset driver ('link'|'token').
     * @param string      $email   Normalized identity destination (typically email).
     * @param string      $token   Raw reset token/code.
     * @param string|null $url     Reset URL (link driver only).
     *
     * @return void
     */
    public function send(string $driver, string $email, string $token, ?string $url = null): void;
}