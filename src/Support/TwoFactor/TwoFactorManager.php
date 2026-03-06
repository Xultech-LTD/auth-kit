<?php

namespace Xul\AuthKit\Support\TwoFactor;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use RuntimeException;
use Xul\AuthKit\Contracts\TwoFactorDriverContract;

/**
 * TwoFactorManager
 *
 * Resolves the active two-factor driver from configuration.
 *
 * Resolution order:
 * - Explicit driver name argument (if provided)
 * - authkit.two_factor.driver (default)
 *
 * Driver binding:
 * - authkit.two_factor.drivers: [name => class-string]
 *
 * @final
 */
final class TwoFactorManager
{
    /**
     * Create a new instance.
     *
     * @param Container $app
     */
    public function __construct(
        protected Container $app
    ) {}

    /**
     * Resolve a two-factor driver by name.
     *
     * @param string|null $name
     * @return TwoFactorDriverContract
     * @throws BindingResolutionException
     */
    public function driver(?string $name = null): TwoFactorDriverContract
    {
        $driverName = is_string($name) && $name !== ''
            ? $name
            : (string) config('authkit.two_factor.driver', 'totp');

        $map = (array) config('authkit.two_factor.drivers', []);

        $class = $map[$driverName] ?? null;

        if (!is_string($class) || $class === '') {
            throw new RuntimeException("AuthKit two-factor driver [{$driverName}] is not configured.");
        }

        $driver = $this->app->make($class);

        if (!$driver instanceof TwoFactorDriverContract) {
            throw new RuntimeException("AuthKit two-factor driver [{$driverName}] must implement TwoFactorDriverContract.");
        }

        return $driver;
    }
}