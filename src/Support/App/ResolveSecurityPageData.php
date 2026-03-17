<?php

namespace Xul\AuthKit\Support\App;

/**
 * ResolveSecurityPageData
 *
 * Resolves lightweight security-page display data for the authenticated user.
 *
 * Responsibilities:
 * - Resolve two-factor enabled status.
 * - Resolve configured two-factor methods.
 * - Resolve whether recovery codes exist.
 *
 * Notes:
 * - This class is read-only.
 * - It is intended for authenticated page presentation only.
 */
final class ResolveSecurityPageData
{
    /**
     * Resolve normalized security page data.
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
            ];
        }

        return [
            'twoFactorEnabled' => $this->resolveTwoFactorEnabled($user),
            'twoFactorMethods' => $this->resolveTwoFactorMethods($user),
            'hasRecoveryCodes' => $this->resolveHasRecoveryCodes($user),
        ];
    }

    /**
     * Resolve whether two-factor is enabled.
     */
    protected function resolveTwoFactorEnabled(object $user): bool
    {
        if (method_exists($user, 'hasTwoFactorEnabled')) {
            return (bool) $user->hasTwoFactorEnabled();
        }

        $column = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');

        return (bool) data_get($user, $column, false);
    }

    /**
     * Resolve enabled two-factor methods.
     *
     * @return array<int, string>
     */
    protected function resolveTwoFactorMethods(object $user): array
    {
        if (method_exists($user, 'twoFactorMethods')) {
            return $this->normalizeStringArray((array) $user->twoFactorMethods());
        }

        $column = (string) config('authkit.two_factor.columns.methods', 'two_factor_methods');

        return $this->normalizeStringArray((array) data_get($user, $column, []));
    }

    /**
     * Resolve whether recovery codes are present.
     */
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

    /**
     * Normalize an array into a clean list of non-empty strings.
     *
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