<?php

namespace Xul\AuthKit\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * AuthKitVerifyEmailTokenNotification
 *
 * Sends a verification token/code for the token driver.
 */
final class AuthKitVerifyEmailTokenNotification extends Notification
{
    use Queueable;

    /**
     * Create a new instance.
     */
    public function __construct(
        protected string $email,
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
            ->line('Use the code below to verify your email address.')
            ->line($this->token);
    }
}