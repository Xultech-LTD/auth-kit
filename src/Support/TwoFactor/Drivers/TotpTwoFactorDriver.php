<?php

namespace Xul\AuthKit\Support\TwoFactor\Drivers;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Random\RandomException;
use Xul\AuthKit\Contracts\TwoFactorDriverContract;
use Xul\AuthKit\Contracts\TwoFactorSecretProviderContract;

/**
 * TotpTwoFactorDriver
 *
 * Default two-factor driver implementation for TOTP (RFC 6238 style).
 *
 * Implements:
 * - TwoFactorDriverContract
 * - TwoFactorSecretProviderContract (because TOTP requires a secret)
 *
 * @final
 */
final class TotpTwoFactorDriver implements TwoFactorDriverContract, TwoFactorSecretProviderContract
{
    /**
     * Driver key.
     *
     * @return string
     */
    public function key(): string
    {
        return 'totp';
    }

    /**
     * Generate a new Base32-encoded TOTP secret.
     *
     * Default length is 20 random bytes (~160 bits), which is a common TOTP secret size.
     *
     * @return string
     * @throws RandomException
     */
    public function generateSecret(): string
    {
        $bytes = random_bytes(20);

        return $this->base32Encode($bytes);
    }

    /**
     * Supported methods for this driver.
     *
     * @param object $user
     * @return array<int, string>
     */
    public function methods(object $user): array
    {
        return ['totp'];
    }

    /**
     * Determine if 2FA is enabled for the user.
     *
     * Resolution order:
     * - User model method: hasTwoFactorEnabled()
     * - Config-mapped enabled column (authkit.two_factor.columns.enabled)
     *
     * @param object $user
     * @return bool
     */
    public function enabled(object $user): bool
    {
        if (!config('authkit.two_factor.enabled', true)) {
            return false;
        }

        if (method_exists($user, 'hasTwoFactorEnabled')) {
            return (bool) $user->hasTwoFactorEnabled();
        }

        $col = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');

        return (bool) data_get($user, $col, false);
    }

    /**
     * Verify a submitted TOTP code for the user.
     *
     * @param object $user
     * @param string $code
     * @return bool
     */
    public function verify(object $user, string $code): bool
    {
        $code = trim($code);

        if ($code === '' || !ctype_digit($code)) {
            return false;
        }

        $secret = $this->resolveSecret($user);

        if ($secret === '') {
            return false;
        }

        $digits = (int) config('authkit.two_factor.totp.digits', 6);
        $period = (int) config('authkit.two_factor.totp.period', 30);
        $window = (int) config('authkit.two_factor.totp.window', 1);
        $algo = (string) config('authkit.two_factor.totp.algo', 'sha1');

        return $this->verifyTotp(
            base32Secret: $secret,
            code: $code,
            digits: $digits,
            period: $period,
            window: $window,
            algo: $algo
        );
    }

    /**
     * Generate recovery codes.
     *
     * @param int $count
     * @param int $length
     * @return array<int, string>
     * @throws RandomException
     */
    public function generateRecoveryCodes(int $count = 8, int $length = 10): array
    {
        $count = max(1, $count);
        $length = max(6, $length);

        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $out[] = $this->randomReadableCode($length);
        }

        return $out;
    }

    /**
     * Verify a recovery code for the user.
     *
     * @param object $user
     * @param string $recoveryCode
     * @return bool
     */
    public function verifyRecoveryCode(object $user, string $recoveryCode): bool
    {
        $recoveryCode = trim($recoveryCode);

        if ($recoveryCode === '') {
            return false;
        }

        $codes = $this->resolveRecoveryCodes($user);

        if ($codes === []) {
            return false;
        }

        $hashing = (bool) config('authkit.two_factor.security.hash_recovery_codes', true);

        if ($hashing) {
            foreach ($codes as $hash) {
                if (is_string($hash) && $hash !== '' && Hash::check($recoveryCode, $hash)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($codes as $plain) {
            if (is_string($plain) && $plain !== '' && hash_equals($plain, $recoveryCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Consume a recovery code.
     *
     * @param object $user
     * @param string $recoveryCode
     * @return bool
     */
    public function consumeRecoveryCode(object $user, string $recoveryCode): bool
    {
        $recoveryCode = trim($recoveryCode);

        if ($recoveryCode === '') {
            return false;
        }

        if (method_exists($user, 'consumeTwoFactorRecoveryCode')) {
            return (bool) $user->consumeTwoFactorRecoveryCode($recoveryCode);
        }

        $codes = $this->resolveRecoveryCodes($user);

        if ($codes === []) {
            return false;
        }

        $hashing = (bool) config('authkit.two_factor.security.hash_recovery_codes', true);

        if ($hashing) {
            foreach ($codes as $i => $hash) {
                if (is_string($hash) && $hash !== '' && Hash::check($recoveryCode, $hash)) {
                    unset($codes[$i]);

                    $this->persistRecoveryCodesFallback($user, array_values($codes));

                    return true;
                }
            }

            return false;
        }

        $idx = array_search($recoveryCode, $codes, true);

        if ($idx === false) {
            return false;
        }

        unset($codes[$idx]);

        $this->persistRecoveryCodesFallback($user, array_values($codes));

        return true;
    }

    /**
     * Resolve the user's TOTP secret (Base32).
     *
     * Resolution order:
     * - User model method: twoFactorSecret() (preferred; supports encryption via trait)
     * - Raw configured column: authkit.two_factor.columns.secret
     * - If encrypt_secret is enabled, attempt Crypt::decryptString() on raw column value
     *
     * @param object $user
     * @return string
     */
    protected function resolveSecret(object $user): string
    {
        if (method_exists($user, 'twoFactorSecret')) {
            $val = $user->twoFactorSecret();

            return is_string($val) ? trim($val) : '';
        }

        $secretCol = (string) config('authkit.two_factor.columns.secret', 'two_factor_secret');
        $raw = data_get($user, $secretCol, '');

        if (!is_string($raw) || trim($raw) === '') {
            return '';
        }

        $raw = trim($raw);

        if (!(bool) config('authkit.two_factor.security.encrypt_secret', true)) {
            return $raw;
        }

        try {
            return (string) Crypt::decryptString($raw);
        } catch (\Throwable) {
            return $raw;
        }
    }

    /**
     * Resolve recovery codes using the trait interface when available.
     *
     * @param object $user
     * @return array<int, string>
     */
    protected function resolveRecoveryCodes(object $user): array
    {
        if (method_exists($user, 'twoFactorRecoveryCodes')) {
            $val = $user->twoFactorRecoveryCodes();

            return is_array($val) ? array_values(array_filter($val, fn ($v) => is_string($v) && trim($v) !== '')) : [];
        }

        $col = (string) config('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
        $raw = data_get($user, $col);

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
            if (is_string($v) && trim($v) !== '') {
                $out[] = (string) $v;
            }
        }

        return array_values($out);
    }

    /**
     * Persist recovery codes when the user does not implement the trait setter.
     *
     * @param object $user
     * @param array<int, string> $codes
     * @return void
     */
    protected function persistRecoveryCodesFallback(object $user, array $codes): void
    {
        $col = (string) config('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');

        if (method_exists($user, 'forceFill')) {
            $user->forceFill([$col => $codes === [] ? null : $codes]);

            if (method_exists($user, 'save')) {
                $user->save();
            }

            return;
        }

        data_set($user, $col, $codes === [] ? null : $codes);

        if (method_exists($user, 'save')) {
            $user->save();
        }
    }

    /**
     * Verify TOTP codes across a clock-drift window.
     *
     * @param string $base32Secret
     * @param string $code
     * @param int $digits
     * @param int $period
     * @param int $window
     * @param string $algo
     * @return bool
     */
    protected function verifyTotp(string $base32Secret, string $code, int $digits, int $period, int $window, string $algo): bool
    {
        $secret = $this->base32Decode($base32Secret);

        if ($secret === '') {
            return false;
        }

        $period = max(1, $period);
        $digits = max(1, $digits);
        $window = max(0, $window);

        $step = (int) floor(time() / $period);

        $code = str_pad($code, $digits, '0', STR_PAD_LEFT);

        for ($i = -$window; $i <= $window; $i++) {
            $expected = $this->hotp($secret, $step + $i, $digits, $algo);

            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate an HOTP code for a given counter.
     *
     * @param string $secret
     * @param int $counter
     * @param int $digits
     * @param string $algo
     * @return string
     */
    protected function hotp(string $secret, int $counter, int $digits, string $algo): string
    {
        $binCounter = pack('N*', 0) . pack('N*', $counter);

        $hash = hash_hmac($algo, $binCounter, $secret, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;

        $mod = 10 ** $digits;

        return str_pad((string) ($value % $mod), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a Base32-encoded secret into raw bytes.
     *
     * @param string $s
     * @return string
     */
    protected function base32Decode(string $s): string
    {
        $s = strtoupper((string) preg_replace('/[^A-Z2-7]/', '', $s));

        if ($s === '') {
            return '';
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $out = '';

        $len = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $val = strpos($alphabet, $s[$i]);

            if ($val === false) {
                return '';
            }

            $buffer = ($buffer << 5) | $val;
            $bits += 5;

            while ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $out;
    }

    /**
     * Encode raw bytes into Base32 (RFC 4648 alphabet).
     *
     * @param string $bytes
     * @return string
     */
    protected function base32Encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $out = '';

        $len = strlen($bytes);

        for ($i = 0; $i < $len; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $out .= $alphabet[($buffer >> $bits) & 0x1F];
            }
        }

        if ($bits > 0) {
            $out .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
        }

        return $out;
    }

    /**
     * Generate a readable recovery code (grouped for UX).
     *
     * @param int $length
     * @return string
     * @throws RandomException
     */
    protected function randomReadableCode(int $length): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $max = strlen($alphabet) - 1;

        $raw = '';

        for ($i = 0; $i < $length; $i++) {
            $raw .= $alphabet[random_int(0, $max)];
        }

        return implode('-', str_split($raw, 5));
    }
}