<?php

namespace Xul\AuthKit\Support\PasswordReset;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUrlGeneratorContract;

/**
 * PasswordResetUrlGenerator
 *
 * Default URL generator for link-driver password reset flows.
 *
 * This implementation generates a *temporary signed URL* that expires using the
 * configured password reset TTL:
 *
 *   authkit.password_reset.ttl_minutes
 *
 * Why signed URLs:
 * - Prevents link tampering (email/token parameters cannot be modified).
 * - Provides an additional expiry mechanism at the URL layer.
 *
 * Notes:
 * - The token is still the raw token produced by TokenRepositoryContract::create().
 * - The reset verification logic should still validate the token (do not rely on URL signing alone).
 * - Ensure the receiving route uses Laravel's "signed" middleware (or validates signatures).
 */
final class PasswordResetUrlGenerator implements PasswordResetUrlGeneratorContract
{
    /**
     * Generate a temporary signed reset URL.
     */
    public function make(string $email, string $token): string
    {
        $route = (string) config('authkit.route_names.web.password_reset', 'authkit.web.password.reset');

        $ttlMinutes = (int) config('authkit.password_reset.ttl_minutes', 30);
        $expiresAt = Carbon::now()->addMinutes(max(1, $ttlMinutes));

        return URL::temporarySignedRoute($route, $expiresAt, [
            'email' => $email,
            'token' => $token,
        ]);
    }
}