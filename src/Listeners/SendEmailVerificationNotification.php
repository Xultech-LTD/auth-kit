<?php

namespace Xul\AuthKit\Listeners;

use Xul\AuthKit\Contracts\EmailVerificationNotifierContract;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;

/**
 * SendEmailVerificationNotification
 *
 * Default delivery listener for email verification.
 *
 * This listener is registered by AuthKit when:
 * - authkit.email_verification.delivery.use_listener = true
 *
 * It delegates actual delivery to the configured notifier implementation
 * (EmailVerificationNotifierContract), allowing consumers to replace the
 * delivery strategy without modifying package actions.
 */
final class SendEmailVerificationNotification
{
    /**
     * Create a new instance.
     *
     * @param EmailVerificationNotifierContract $notifier
     */
    public function __construct(
        protected EmailVerificationNotifierContract $notifier
    ) {}

    /**
     * Handle the event.
     *
     * @param AuthKitEmailVerificationRequired $event
     * @return void
     */
    public function handle(AuthKitEmailVerificationRequired $event): void
    {
        $this->notifier->send(
            user: $event->user,
            driver: $event->driver,
            email: $event->email,
            token: $event->token,
            url: $event->url
        );
    }
}