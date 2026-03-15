<?php

namespace Xul\AuthKit\Listeners;

use Illuminate\Support\Facades\App;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetNotifierContract;
use Xul\AuthKit\Events\AuthKitPasswordResetRequested;
use Xul\AuthKit\Jobs\SendPasswordResetNotificationJob;

/**
 * SendPasswordResetNotification
 *
 * Default listener responsible for delivering password reset instructions.
 *
 * Execution mode is controlled by:
 * authkit.password_reset.delivery.mode
 */
final class SendPasswordResetNotification
{
    public function __construct(
        protected PasswordResetNotifierContract $notifier
    ) {}

    /**
     * Handle the event.
     */
    public function handle(AuthKitPasswordResetRequested $event): void
    {
        $config = config('authkit.password_reset.delivery');

        $mode = $config['mode'] ?? 'sync';

        if ($mode === 'queue') {
            $job = new SendPasswordResetNotificationJob(
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
    protected function deliver(AuthKitPasswordResetRequested $event): void
    {
        $this->notifier->send(
            driver: $event->driver,
            email: $event->email,
            token: $event->token,
            url: $event->url
        );
    }
}