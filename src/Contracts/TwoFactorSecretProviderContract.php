<?php

namespace Xul\AuthKit\Contracts;

/**
 * TwoFactorSecretProviderContract
 *
 * Optional extension contract for two-factor drivers that require a provisioned secret.
 *
 * Examples:
 * - TOTP drivers need a shared secret (Base32) to generate/verify codes.
 *
 * Drivers that do not require a secret (e.g. link/email) should NOT implement this.
 */
interface TwoFactorSecretProviderContract
{
    /**
     * Generate a new secret suitable for the driver.
     *
     * Returned value should be safe to persist (typically Base32 for TOTP).
     *
     * @return string
     */
    public function generateSecret(): string;
}