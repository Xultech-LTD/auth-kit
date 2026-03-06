<?php

namespace Xul\AuthKit\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * AuthKitPasswordResetLinkNotification
 *
 * Default notification for link-driver password reset.
 *
 * Notes:
 * - Keep the content generic to avoid revealing account existence.
 * - Consumers are encouraged to publish/override notifications for branding.
 */
final class AuthKitPasswordResetLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $email,
        public readonly string $url,
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
            ->subject('Password reset')
            ->line('If you requested a password reset, use the link below to set a new password.')
            ->action('Reset password', $this->url)
            ->line('If you did not request a password reset, you can safely ignore this message.');
    }
}