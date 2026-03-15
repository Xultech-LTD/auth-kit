<?php

namespace Xul\AuthKit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetNotifierContract;

/**
 * SendPasswordResetNotificationJob
 *
 * Queued job responsible for delivering password reset instructions.
 */
final class SendPasswordResetNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $driver,
        protected string $email,
        protected string $token,
        protected ?string $url
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PasswordResetNotifierContract $notifier): void
    {
        $notifier->send(
            driver: $this->driver,
            email: $this->email,
            token: $this->token,
            url: $this->url
        );
    }
}