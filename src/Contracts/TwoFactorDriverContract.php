<?php

namespace Xul\AuthKit\Contracts;

/**
 * TwoFactorDriverContract
 *
 * Pluggable two-factor driver contract.
 *
 * Drivers are responsible for:
 * - Detecting whether two-factor is enabled for a given user.
 * - Declaring which methods they support for that user (e.g. "totp").
 * - Verifying a submitted code.
 *
 * Notes:
 * - Secret provisioning differs per driver, so this contract does not force a
 *   "generateSecret" method (not all drivers need one).
 * - Recovery codes are a common requirement across drivers; we expose a default
 *   recovery code generator hook so AuthKit can standardize the UX.
 */
interface TwoFactorDriverContract
{
    /**
     * Driver key (e.g. "totp").
     *
     * @return string
     */
    public function key(): string;

    /**
     * Return methods supported by this driver for the given user.
     *
     * @param object $user
     * @return array<int, string>
     */
    public function methods(object $user): array;

    /**
     * Determine whether two-factor is enabled for this user.
     *
     * @param object $user
     * @return bool
     */
    public function enabled(object $user): bool;

    /**
     * Verify a submitted two-factor code for the user.
     *
     * @param object $user
     * @param string $code
     * @return bool
     */
    public function verify(object $user, string $code): bool;

    /**
     * Verify a recovery code for the user.
     *
     * @param object $user
     * @param string $recoveryCode
     * @return bool
     */
    public function verifyRecoveryCode(object $user, string $recoveryCode): bool;

    /**
     * Consume a recovery code after successful use.
     *
     * @param object $user
     * @param string $recoveryCode
     * @return bool
     */
    public function consumeRecoveryCode(object $user, string $recoveryCode): bool;

    /**
     * Generate recovery codes for the user.
     *
     * Drivers may override this if they want custom shapes or formats.
     * AuthKit uses these codes as single-use fallbacks when the primary method
     * is unavailable.
     *
     * @param int $count
     * @param int $length
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8, int $length = 10): array;
}