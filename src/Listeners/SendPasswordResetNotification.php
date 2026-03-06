<?php

namespace Xul\AuthKit\Listeners;

use Xul\AuthKit\Contracts\PasswordReset\PasswordResetNotifierContract;
use Xul\AuthKit\Events\AuthKitPasswordResetRequested;

/**
 * SendPasswordResetNotification
 *
 * Default listener that delivers password reset instructions using the configured notifier.
 *
 * Consumers can disable this listener via:
 * authkit.password_reset.delivery.use_listener = false
 *
 * Or swap:
 * - authkit.password_reset.delivery.listener
 * - authkit.password_reset.delivery.notifier
 */
final class SendPasswordResetNotification
{
    public function __construct(
        protected PasswordResetNotifierContract $notifier
    ) {}

    /**
     * Handle the event.
     *
     * @param AuthKitPasswordResetRequested $event
     * @return void
     */
    public function handle(AuthKitPasswordResetRequested $event): void
    {
        $this->notifier->send(
            driver: $event->driver,
            email: $event->email,
            token: $event->token,
            url: $event->url
        );
    }
}