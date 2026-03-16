<?php

namespace Xul\AuthKit\Support\TwoFactor;

use RuntimeException;
use Throwable;
use Xul\AuthKit\Contracts\TwoFactorSecretProviderContract;

/**
 * EnsureTwoFactorSecretForUser
 *
 * Ensures that the current authenticated user has a stored two-factor secret
 * when the active driver requires one (for example TOTP).
 *
 * Responsibilities:
 * - Resolve the active two-factor driver.
 * - Determine whether the driver supports secret generation.
 * - Detect whether the user already has a stored secret.
 * - Generate and persist a new secret when missing.
 *
 * Notes:
 * - This class is intended for setup/management flows, not login challenge flows.
 * - It is safe to call repeatedly; existing secrets are preserved.
 */
final class EnsureTwoFactorSecretForUser
{
    /**
     * Create a new instance.
     *
     * @param TwoFactorManager $manager
     */
    public function __construct(
        protected TwoFactorManager $manager
    ) {}

    /**
     * Ensure the given user has a two-factor secret when supported by the active driver.
     *
     * @param mixed $user
     * @return void
     */
    public function ensure(mixed $user): void
    {
        if (! is_object($user)) {
            return;
        }

        $driver = $this->manager->driver();

        if (! $driver instanceof TwoFactorSecretProviderContract) {
            return;
        }

        if ($this->userHasSecret($user)) {
            return;
        }

        $secret = trim($driver->generateSecret());

        if ($secret === '') {
            throw new RuntimeException('AuthKit failed to generate a two-factor secret.');
        }

        $this->persistSecret($user, $secret);
    }

    /**
     * Determine whether the user already has a stored secret.
     *
     * @param object $user
     * @return bool
     */
    protected function userHasSecret(object $user): bool
    {
        if (method_exists($user, 'twoFactorSecret')) {
            $secret = $user->twoFactorSecret();

            return is_string($secret) && trim($secret) !== '';
        }

        $column = (string) config('authkit.two_factor.columns.secret', 'two_factor_secret');
        $value = data_get($user, $column);

        return is_string($value) && trim($value) !== '';
    }

    /**
     * Persist a generated secret onto the user.
     *
     * Resolution order:
     * - setTwoFactorSecret()
     * - forceFill([...]) + save()
     * - direct property set + save()
     *
     * @param object $user
     * @param string $secret
     * @return void
     */
    protected function persistSecret(object $user, string $secret): void
    {
        $column = (string) config('authkit.two_factor.columns.secret', 'two_factor_secret');

        if (method_exists($user, 'setTwoFactorSecret')) {
            $user->setTwoFactorSecret($secret);

            if (method_exists($user, 'save')) {
                $user->save();
            }

            return;
        }

        if (method_exists($user, 'forceFill')) {
            $user->forceFill([$column => $secret]);

            if (method_exists($user, 'save')) {
                $user->save();
            }

            return;
        }

        data_set($user, $column, $secret);

        if (method_exists($user, 'save')) {
            $user->save();
        }
    }
}