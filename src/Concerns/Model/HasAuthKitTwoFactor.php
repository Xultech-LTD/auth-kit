<?php

namespace Xul\AuthKit\Concerns\Model;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * HasAuthKitTwoFactor
 *
 * Adds a consistent, config-driven interface for AuthKit two-factor fields
 * without forcing a fixed database column naming scheme.
 *
 * Column names are resolved from:
 * - authkit.two_factor.columns.enabled
 * - authkit.two_factor.columns.secret
 * - authkit.two_factor.columns.recovery_codes
 * - authkit.two_factor.columns.methods
 *
 * Recommended storage:
 * - enabled: boolean
 * - secret: string (Base32 for TOTP)
 * - recovery_codes: json (array of strings)
 * - methods: json (array of strings)
 *
 * Usage:
 * - Add this trait to your Authenticatable user model.
 * - Publish and run the AuthKit two-factor migration (or implement equivalent schema).
 */
trait HasAuthKitTwoFactor
{
    /**
     * Determine if two-factor is enabled for the model.
     *
     * @return bool
     */
    public function hasTwoFactorEnabled(): bool
    {
        $col = $this->authKitTwoFactorEnabledColumn();

        return (bool) data_get($this, $col, false);
    }

    /**
     * Enable two-factor for the model.
     *
     * @return void
     */
    public function enableTwoFactor(): void
    {
        $col = $this->authKitTwoFactorEnabledColumn();

        if (method_exists($this, 'setAttribute')) {
            $this->setAttribute($col, true);
        } else {
            $this->{$col} = true;
        }
    }

    /**
     * Disable two-factor for the model.
     *
     * @return void
     */
    public function disableTwoFactor(): void
    {
        $col = $this->authKitTwoFactorEnabledColumn();

        if (method_exists($this, 'setAttribute')) {
            $this->setAttribute($col, false);
        } else {
            $this->{$col} = false;
        }
    }

    /**
     * Get the configured two-factor secret value.
     *
     * @return string
     */
    public function twoFactorSecret(): string
    {
        $col = $this->authKitTwoFactorSecretColumn();

        $raw = data_get($this, $col, '');

        if (!is_string($raw) || $raw === '') {
            return '';
        }

        if (!(bool) config('authkit.two_factor.security.encrypt_secret', true)) {
            return $raw;
        }

        try {
            return (string) Crypt::decryptString($raw);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Set the configured two-factor secret value.
     *
     * @param string $secret
     * @return void
     */
    public function setTwoFactorSecret(string $secret): void
    {
        $col = $this->authKitTwoFactorSecretColumn();

        $secret = trim($secret);

        $value = $secret;

        if ((bool) config('authkit.two_factor.security.encrypt_secret', true)) {
            $value = $secret !== '' ? Crypt::encryptString($secret) : '';
        }

        if (method_exists($this, 'setAttribute')) {
            $this->setAttribute($col, $value === '' ? null : $value);
        } else {
            $this->{$col} = $value === '' ? null : $value;
        }
    }

    /**
     * Get recovery codes.
     *
     * If hashing is enabled, this returns hashed codes (not plaintext).
     *
     * @return array<int, string>
     */
    public function twoFactorRecoveryCodes(): array
    {
        $col = $this->authKitTwoFactorRecoveryCodesColumn();

        $raw = data_get($this, $col);

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $v) {
            if (is_string($v) && $v !== '') {
                $out[] = $v;
            }
        }

        return array_values($out);
    }

    /**
     * Set recovery codes.
     *
     * When hashing is enabled, provide raw codes and AuthKit stores hashes.
     *
     * @param array<int, string> $codes
     * @return void
     */
    public function setTwoFactorRecoveryCodes(array $codes): void
    {
        $col = $this->authKitTwoFactorRecoveryCodesColumn();

        $out = [];

        foreach ($codes as $v) {
            if (is_string($v)) {
                $v = trim($v);

                if ($v !== '') {
                    $out[] = $v;
                }
            }
        }

        $out = array_values($out);

        if ((bool) config('authkit.two_factor.security.hash_recovery_codes', true)) {
            $driver = $this->recoveryHashDriver();

            $hashed = [];

            foreach ($out as $code) {
                $hashed[] = Hash::driver($driver)->make($code);
            }

            $out = $hashed;
        }

        if (method_exists($this, 'setAttribute')) {
            $this->setAttribute($col, $out === [] ? null : $out);
        } else {
            $this->{$col} = $out === [] ? null : $out;
        }
    }

    /**
     * Consume a recovery code if present.
     *
     * When hashing is enabled, the input is verified against stored hashes.
     *
     * @param string $code
     * @return bool
     */
    public function consumeTwoFactorRecoveryCode(string $code): bool
    {
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        $stored = $this->twoFactorRecoveryCodes();

        if ($stored === []) {
            return false;
        }

        $hashing = (bool) config('authkit.two_factor.security.hash_recovery_codes', true);
        $driver = $this->recoveryHashDriver();

        if ($hashing) {
            foreach ($stored as $i => $hash) {
                if (is_string($hash) && $hash !== '' && Hash::driver($driver)->check($code, $hash)) {
                    unset($stored[$i]);
                    $this->setTwoFactorRecoveryCodes(array_values($stored));

                    return true;
                }
            }

            return false;
        }

        $idx = array_search($code, $stored, true);

        if ($idx === false) {
            return false;
        }

        unset($stored[$idx]);

        $this->setTwoFactorRecoveryCodes(array_values($stored));

        return true;
    }

    /**
     * Get enabled two-factor methods for the user.
     *
     * @return array<int, string>
     */
    public function twoFactorMethods(): array
    {
        $col = $this->authKitTwoFactorMethodsColumn();

        $raw = data_get($this, $col);

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $v) {
            if (is_string($v) && $v !== '') {
                $out[] = $v;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Set enabled two-factor methods for the user.
     *
     * @param array<int, string> $methods
     * @return void
     */
    public function setTwoFactorMethods(array $methods): void
    {
        $col = $this->authKitTwoFactorMethodsColumn();

        $out = [];

        foreach ($methods as $v) {
            if (is_string($v) && $v !== '') {
                $out[] = $v;
            }
        }

        $out = array_values(array_unique($out));

        if (method_exists($this, 'setAttribute')) {
            $this->setAttribute($col, $out);
        } else {
            $this->{$col} = $out;
        }
    }

    /**
     * Resolve the configured "enabled" column name.
     *
     * @return string
     */
    public function authKitTwoFactorEnabledColumn(): string
    {
        return (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');
    }

    /**
     * Resolve the configured "secret" column name.
     *
     * @return string
     */
    public function authKitTwoFactorSecretColumn(): string
    {
        return (string) config('authkit.two_factor.columns.secret', 'two_factor_secret');
    }

    /**
     * Resolve the configured "recovery codes" column name.
     *
     * @return string
     */
    public function authKitTwoFactorRecoveryCodesColumn(): string
    {
        return (string) config('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
    }

    /**
     * Resolve the configured "methods" column name.
     *
     * @return string
     */
    public function authKitTwoFactorMethodsColumn(): string
    {
        return (string) config('authkit.two_factor.columns.methods', 'two_factor_methods');
    }

    /**
     * Resolve the configured recovery code hash driver.
     *
     * This determines which hashing driver is used when generating
     * and verifying two-factor recovery codes.
     *
     * The driver is resolved from:
     * - authkit.two_factor.security.recovery_hash_driver
     *
     * Supported drivers:
     * - bcrypt
     * - argon2i
     * - argon2id
     *
     * This method validates the configured driver to prevent the use
     * of unsupported hashing algorithms.
     *
     * @throws \RuntimeException If an invalid driver is configured.
     * @return string
     */
    protected function recoveryHashDriver(): string
    {
        $driver = (string) config('authkit.two_factor.security.recovery_hash_driver', 'bcrypt');

        if (!in_array($driver, ['bcrypt', 'argon2i', 'argon2id'], true)) {
            throw new \RuntimeException("Invalid recovery hash driver [$driver].");
        }

        return $driver;
    }
}