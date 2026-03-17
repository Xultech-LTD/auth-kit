<?php

namespace Xul\AuthKit\Support\App;

use Throwable;
use Xul\AuthKit\Support\TwoFactor\EnsureTwoFactorSecretForUser;
use Xul\AuthKit\Support\TwoFactor\TwoFactorOtpUriFactory;

/**
 * ResolveTwoFactorSettingsPageData
 *
 * Resolves read-oriented display data for the authenticated two-factor
 * management page.
 *
 * Responsibilities:
 * - Resolve whether two-factor is enabled.
 * - Resolve enabled methods.
 * - Resolve whether recovery codes are present.
 * - Ensure a setup secret exists for users who have not yet enabled two-factor.
 * - Resolve the manual secret and otpauth URI for setup UX.
 *
 * Notes:
 * - This class is read-first and page-oriented.
 * - It does not confirm, disable, or regenerate two-factor state.
 * - It may ensure a secret exists for setup flows when the user is valid.
 */
final class ResolveTwoFactorSettingsPageData
{
    public function __construct(
        protected EnsureTwoFactorSecretForUser $ensureSecret,
        protected TwoFactorOtpUriFactory $otpUriFactory
    ) {}

    /**
     * Resolve normalized page data.
     *
     * @param mixed $user
     * @return array<string, mixed>
     */
    public function resolve(mixed $user): array
    {
        if (! is_object($user)) {
            return [
                'twoFactorEnabled' => false,
                'twoFactorMethods' => [],
                'hasRecoveryCodes' => false,
                'manualSecret' => '',
                'otpUri' => '',
                'setupAvailable' => false,
            ];
        }

        $twoFactorEnabled = $this->resolveTwoFactorEnabled($user);

        if (! $twoFactorEnabled) {
            try {
                $this->ensureSecret->ensure($user);
            } catch (Throwable) {
                // fail softly for the page
            }
        }

        $manualSecret = $twoFactorEnabled ? '' : $this->resolveSecret($user);
        $otpUri = $twoFactorEnabled ? '' : $this->otpUriFactory->make($user);

        return [
            'twoFactorEnabled' => $twoFactorEnabled,
            'twoFactorMethods' => $this->resolveMethods($user),
            'hasRecoveryCodes' => $this->resolveHasRecoveryCodes($user),
            'manualSecret' => $manualSecret,
            'otpUri' => $otpUri,
            'setupAvailable' => $manualSecret !== '' || $otpUri !== '',
        ];
    }

    protected function resolveTwoFactorEnabled(object $user): bool
    {
        if (method_exists($user, 'hasTwoFactorEnabled')) {
            return (bool) $user->hasTwoFactorEnabled();
        }

        $column = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');

        return (bool) data_get($user, $column, false);
    }

    /**
     * @return array<int, string>
     */
    protected function resolveMethods(object $user): array
    {
        if (method_exists($user, 'twoFactorMethods')) {
            return $this->normalizeStringArray((array) $user->twoFactorMethods());
        }

        $column = (string) config('authkit.two_factor.columns.methods', 'two_factor_methods');

        return $this->normalizeStringArray((array) data_get($user, $column, []));
    }

    protected function resolveHasRecoveryCodes(object $user): bool
    {
        if (method_exists($user, 'twoFactorRecoveryCodes')) {
            return count((array) $user->twoFactorRecoveryCodes()) > 0;
        }

        $column = (string) config('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
        $value = data_get($user, $column, []);

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return is_array($value) && count($value) > 0;
    }

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
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    protected function normalizeStringArray(array $values): array
    {
        return array_values(array_filter(
            array_map(
                static fn ($value) => is_string($value) ? trim($value) : '',
                $values
            ),
            static fn (string $value) => $value !== ''
        ));
    }
}