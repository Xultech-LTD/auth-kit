<?php

namespace Xul\AuthKit\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * TwoFactorResendableContract
 *
 * Optional driver capability for resending a two-factor challenge code.
 */
interface TwoFactorResendableContract
{
    /**
     * Resend a two-factor challenge code for a user.
     *
     * @param Authenticatable $user
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function resend(Authenticatable $user, array $context = []): array;
}