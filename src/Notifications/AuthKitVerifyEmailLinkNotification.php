<?php

namespace Xul\AuthKit\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * AuthKitVerifyEmailLinkNotification
 *
 * Sends a signed verification link for the link driver.
 */
final class AuthKitVerifyEmailLinkNotification extends Notification
{
    use Queueable;

    /**
     * Create a new instance.
     */
    public function __construct(
        protected string $email,
        protected string $url,
        protected string $token
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Verify your email')
            ->line('Click the button below to verify your email address.')
            ->action('Verify email', $this->url);
    }
}