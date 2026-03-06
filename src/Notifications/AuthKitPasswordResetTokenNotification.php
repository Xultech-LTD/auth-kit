<?php

namespace Xul\AuthKit\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * AuthKitPasswordResetTokenNotification
 *
 * Default notification for token-driver password reset.
 *
 * Notes:
 * - Keep the content generic to avoid revealing account existence.
 * - The token is a short code intended for manual entry.
 */
final class AuthKitPasswordResetTokenNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $email,
        public readonly string $token
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password reset code')
            ->line('If you requested a password reset, use the code below to continue:')
            ->line($this->token)
            ->line('If you did not request a password reset, you can safely ignore this message.');
    }
}