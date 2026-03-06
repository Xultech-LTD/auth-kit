<?php

namespace Xul\AuthKit\Support\Notifiers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\Notifiable;
use Xul\AuthKit\Contracts\EmailVerificationNotifierContract;
use Xul\AuthKit\Notifications\AuthKitVerifyEmailLinkNotification;
use Xul\AuthKit\Notifications\AuthKitVerifyEmailTokenNotification;

/**
 * EmailVerificationNotifier
 *
 * Default email verification sender for AuthKit.
 *
 * This implementation uses Laravel Notifications when the user model
 * uses the Notifiable trait.
 *
 * If the user is not notifiable, this notifier performs no action.
 * Consumers may bind a custom notifier to handle other delivery strategies.
 */
final class EmailVerificationNotifier implements EmailVerificationNotifierContract
{
    /**
     * Send the verification message based on driver.
     */
    public function send(
        Authenticatable $user,
        string $driver,
        string $email,
        string $token,
        ?string $url = null
    ): void {
        if (!$this->isNotifiable($user)) {
            return;
        }

        if ($driver === 'token') {
            $user->notify(new AuthKitVerifyEmailTokenNotification($email, $token));

            return;
        }

        $user->notify(new AuthKitVerifyEmailLinkNotification($email, (string) $url, $token));
    }

    /**
     * Determine if the user can receive notifications.
     */
    protected function isNotifiable(Authenticatable $user): bool
    {
        return in_array(Notifiable::class, class_uses_recursive($user), true)
            && method_exists($user, 'notify');
    }
}