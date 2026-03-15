<?php

namespace Xul\AuthKit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Xul\AuthKit\Contracts\EmailVerificationNotifierContract;

/**
 * SendEmailVerificationNotificationJob
 *
 * Queued job responsible for delivering email verification messages.
 *
 * This job delegates the actual delivery to the configured
 * EmailVerificationNotifierContract implementation.
 */
final class SendEmailVerificationNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Authenticatable $user,
        protected string $driver,
        protected string $email,
        protected string $token,
        protected ?string $url
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmailVerificationNotifierContract $notifier): void
    {
        $notifier->send(
            user: $this->user,
            driver: $this->driver,
            email: $this->email,
            token: $this->token,
            url: $this->url
        );
    }
}