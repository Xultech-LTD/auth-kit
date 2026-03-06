<?php

namespace Xul\AuthKit\Support\PasswordReset;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Xul\AuthKit\Contracts\PasswordReset\PasswordUpdaterContract;

/**
 * DefaultPasswordUpdater
 *
 * Default implementation for persisting a new password during a reset flow.
 *
 * Responsibilities:
 * - Hash the incoming raw password.
 * - Persist the updated password.
 * - Optionally refresh the remember token to invalidate existing "remember me" cookies.
 *
 * Notes:
 * - This implementation assumes the user model has a "password" attribute.
 * - Consumers with custom password columns or audit requirements should provide
 *   their own PasswordUpdaterContract implementation.
 */
final class DefaultPasswordUpdater implements PasswordUpdaterContract
{
    /**
     * Update the user's password.
     */
    public function update(Authenticatable $user, string $newPasswordRaw, bool $refreshRememberToken = true): void
    {
        $user->forceFill([
            'password' => Hash::make($newPasswordRaw),
        ]);

        if ($refreshRememberToken && method_exists($user, 'setRememberToken')) {
            $user->setRememberToken(str()->random(60));
        }

        if (method_exists($user, 'save')) {
            $user->save();
        }
    }
}