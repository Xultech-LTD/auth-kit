<?php

namespace Xul\AuthKit\Support\Notifiers;

use Illuminate\Notifications\AnonymousNotifiable;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetNotifierContract;
use Xul\AuthKit\Notifications\AuthKitPasswordResetLinkNotification;
use Xul\AuthKit\Notifications\AuthKitPasswordResetTokenNotification;

/**
 * PasswordResetNotifier
 *
 * Default password reset sender for AuthKit.
 *
 * Design goals:
 * - Do not require a resolved user instance (supports privacy-preserving flows).
 * - Always route delivery to the provided identity destination (typically email).
 * - Keep implementation simple and swappable via configuration.
 *
 * Delivery mechanism:
 * - Uses Laravel Notifications via AnonymousNotifiable to route to an email address.
 *
 * Security notes:
 * - The raw token is sensitive. This notifier must never persist it.
 * - When the driver is "link", the URL should be a temporary signed URL (recommended).
 */
final class PasswordResetNotifier implements PasswordResetNotifierContract
{
    /**
     * Deliver password reset instructions to the destination identity.
     */
    public function send(string $driver, string $email, string $token, ?string $url = null): void
    {
        $notifiable = (new AnonymousNotifiable())->route('mail', $email);

        if ($driver === 'token') {
            $notifiable->notify(new AuthKitPasswordResetTokenNotification($email, $token));

            return;
        }

        $notifiable->notify(new AuthKitPasswordResetLinkNotification($email, (string) $url, $token));
    }
}