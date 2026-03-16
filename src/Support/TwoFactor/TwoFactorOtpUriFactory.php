<?php

namespace Xul\AuthKit\Support\TwoFactor;

use Xul\AuthKit\Contracts\TwoFactorSecretProviderContract;

/**
 * TwoFactorOtpUriFactory
 *
 * Builds otpauth provisioning URIs for secret-based two-factor drivers such as TOTP.
 *
 * Responsibilities:
 * - Resolve the active two-factor driver.
 * - Ensure the active driver supports secrets.
 * - Resolve the user's current two-factor secret.
 * - Build a standards-compatible otpauth:// URI for authenticator apps.
 *
 * Notes:
 * - This class is intended for setup/management pages, not login challenge flows.
 * - The returned URI may be embedded in a QR code or displayed as manual setup metadata.
 * - For TOTP, the generated URI follows the common otpauth URI format used by
 *   Google Authenticator, 1Password, Authy, Microsoft Authenticator, and similar apps.
 */
final class TwoFactorOtpUriFactory
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
     * Build an otpauth provisioning URI for the given user.
     *
     * Returns an empty string when:
     * - the given user is invalid
     * - the active driver does not support secret-based setup
     * - the user does not have a resolvable secret
     *
     * @param mixed $user
     * @return string
     */
    public function make(mixed $user): string
    {
        if (! is_object($user)) {
            return '';
        }

        $driver = $this->manager->driver();

        if (! $driver instanceof TwoFactorSecretProviderContract) {
            return '';
        }

        $secret = $this->resolveSecret($user);

        if ($secret === '') {
            return '';
        }

        $issuer = $this->resolveIssuer();
        $account = $this->resolveAccountLabel($user);
        $label = $this->makeLabel($issuer, $account);

        $query = array_filter([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper((string) config('authkit.two_factor.totp.algo', 'sha1')),
            'digits' => (int) config('authkit.two_factor.totp.digits', 6),
            'period' => (int) config('authkit.two_factor.totp.period', 30),
        ], static fn ($value) => $value !== '' && $value !== null);

        return 'otpauth://totp/' . rawurlencode($label) . '?' . http_build_query(
                $query,
                '',
                '&',
                PHP_QUERY_RFC3986
            );
    }

    /**
     * Resolve the current user's two-factor secret.
     *
     * @param object $user
     * @return string
     */
    protected function resolveSecret(object $user): string
    {
        if (method_exists($user, 'twoFactorSecret')) {
            $secret = $user->twoFactorSecret();

            return is_string($secret) ? trim($secret) : '';
        }

        $column = (string) config('authkit.two_factor.columns.secret', 'two_factor_secret');
        $secret = data_get($user, $column);

        return is_string($secret) ? trim($secret) : '';
    }

    /**
     * Resolve the issuer name used by authenticator apps.
     *
     * Resolution order:
     * - authkit.two_factor.issuer
     * - app.name
     * - AuthKit
     *
     * @return string
     */
    protected function resolveIssuer(): string
    {
        $issuer = (string) config('authkit.two_factor.issuer', '');

        if ($issuer !== '') {
            return $issuer;
        }

        $appName = (string) config('app.name', '');

        return $appName !== '' ? $appName : 'AuthKit';
    }

    /**
     * Resolve the account label shown in authenticator apps.
     *
     * Resolution order:
     * - name
     * - email
     * - configured identity field
     * - auth identifier
     * - user
     *
     * @param object $user
     * @return string
     */
    protected function resolveAccountLabel(object $user): string
    {
        $identityField = (string) config('authkit.identity.login.field', 'email');

        $label = (string) (
        data_get($user, 'name')
            ?: data_get($user, 'email')
            ?: data_get($user, $identityField)
        );

        if ($label !== '') {
            return $label;
        }

        if (method_exists($user, 'getAuthIdentifier')) {
            $identifier = $user->getAuthIdentifier();

            if (is_scalar($identifier) || $identifier === null) {
                $label = (string) $identifier;
            }
        }

        return $label !== '' ? $label : 'user';
    }

    /**
     * Build the final otpauth label.
     *
     * Format:
     * - "{issuer}:{account}"
     *
     * @param string $issuer
     * @param string $account
     * @return string
     */
    protected function makeLabel(string $issuer, string $account): string
    {
        $issuer = trim($issuer);
        $account = trim($account);

        if ($issuer === '') {
            return $account !== '' ? $account : 'user';
        }

        if ($account === '') {
            return $issuer;
        }

        return $issuer . ':' . $account;
    }
}