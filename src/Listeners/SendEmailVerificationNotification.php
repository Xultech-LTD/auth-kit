<?php

namespace Xul\AuthKit\Listeners;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use Xul\AuthKit\Contracts\EmailVerificationNotifierContract;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;
use Xul\AuthKit\Jobs\SendEmailVerificationNotificationJob;

/**
 * SendEmailVerificationNotification
 *
 * Default delivery listener for email verification.
 *
 * This listener is registered by AuthKit when:
 * - authkit.email_verification.delivery.use_listener = true
 *
 * Responsibilities:
 * - Resolve the configured notifier.
 * - Execute delivery according to the configured execution mode.
 *
 * Supported modes:
 * - sync           : deliver immediately during the current request
 * - queue          : dispatch a queued job
 * - after_response : defer delivery until after the HTTP response
 */
final class SendEmailVerificationNotification
{
    /**
     * Create a new instance.
     */
    public function __construct(
        protected EmailVerificationNotifierContract $notifier
    ) {}

    /**
     * Handle the event.
     */
    public function handle(AuthKitEmailVerificationRequired $event): void
    {
        $config = config('authkit.email_verification.delivery');

        $mode = $config['mode'] ?? 'sync';

        if ($mode === 'queue') {
            $job = new SendEmailVerificationNotificationJob(
                $event->user,
                $event->driver,
                $event->email,
                $event->token,
                $event->url
            );

            if ($config['queue_connection']) {
                $job->onConnection($config['queue_connection']);
            }

            if ($config['queue']) {
                $job->onQueue($config['queue']);
            }

            if (($config['delay'] ?? 0) > 0) {
                $job->delay(now()->addSeconds($config['delay']));
            }

            dispatch($job);

            return;
        }

        if ($mode === 'after_response') {
            App::terminating(function () use ($event) {
                $this->deliver($event);
            });

            return;
        }

        $this->deliver($event);
    }

    /**
     * Execute notifier delivery.
     */
    protected function deliver(AuthKitEmailVerificationRequired $event): void
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