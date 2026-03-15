<?php

namespace Xul\AuthKit\Tests\Feature;

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Xul\AuthKit\Listeners\SendEmailVerificationNotification;
use Xul\AuthKit\Jobs\SendEmailVerificationNotificationJob;
use Xul\AuthKit\Contracts\EmailVerificationNotifierContract;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;

/**
 * TestEmailVerificationNotifier
 *
 * Test double used to capture notifier calls during listener tests.
 */
final class TestEmailVerificationNotifier implements EmailVerificationNotifierContract
{
    /**
     * Captured notifier calls.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $calls = [];

    /**
     * Record a send invocation.
     */
    public function send(
        $user,
        string $driver,
        string $email,
        string $token,
        ?string $url = null
    ): void {
        $this->calls[] = compact('user', 'driver', 'email', 'token', 'url');
    }
}

/**
 * TestEmailVerificationUser
 *
 * Minimal authenticatable user model for listener tests.
 */
final class TestEmailVerificationUser extends BaseUser
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['email'];
}

beforeEach(function () {
    $fresh = new Dispatcher($this->app);

    $this->app->instance('events', $fresh);
    Event::swap($fresh);

    $this->app->singleton(EmailVerificationNotifierContract::class, TestEmailVerificationNotifier::class);
});

it('delivers email verification synchronously when mode is sync', function () {
    config()->set('authkit.email_verification.delivery.mode', 'sync');

    /** @var TestEmailVerificationNotifier $notifier */
    $notifier = app(EmailVerificationNotifierContract::class);

    $listener = app(SendEmailVerificationNotification::class);

    $user = new TestEmailVerificationUser([
        'email' => 'meritinfos@gmail.com',
    ]);

    $event = new AuthKitEmailVerificationRequired(
        user: $user,
        email: 'meritinfos@gmail.com',
        driver: 'token',
        ttlMinutes: 5,
        token: '123456',
        url: null
    );

    $listener->handle($event);

    expect($notifier->calls)->toHaveCount(1)
        ->and($notifier->calls[0]['user'])->toBe($user)
        ->and($notifier->calls[0]['driver'])->toBe('token')
        ->and($notifier->calls[0]['email'])->toBe('meritinfos@gmail.com')
        ->and($notifier->calls[0]['token'])->toBe('123456')
        ->and($notifier->calls[0]['url'])->toBeNull();
});

it('dispatches a queued job when mode is queue', function () {
    Queue::fake();

    config()->set('authkit.email_verification.delivery.mode', 'queue');
    config()->set('authkit.email_verification.delivery.queue_connection', 'redis');
    config()->set('authkit.email_verification.delivery.queue', 'mail');
    config()->set('authkit.email_verification.delivery.delay', 15);

    /** @var TestEmailVerificationNotifier $notifier */
    $notifier = app(EmailVerificationNotifierContract::class);

    $listener = app(SendEmailVerificationNotification::class);

    $user = new TestEmailVerificationUser([
        'email' => 'meritinfos@gmail.com',
    ]);

    $event = new AuthKitEmailVerificationRequired(
        user: $user,
        email: 'meritinfos@gmail.com',
        driver: 'link',
        ttlMinutes: 5,
        token: 'toktoktok',
        url: 'https://example.test/verify'
    );

    $listener->handle($event);

    Queue::assertPushed(SendEmailVerificationNotificationJob::class, function (SendEmailVerificationNotificationJob $job) {
        return $job->connection === 'redis'
            && $job->queue === 'mail'
            && $job->delay !== null;
    });

    expect($notifier->calls)->toHaveCount(0);
});

it('delivers email verification after response when mode is after_response', function () {
    config()->set('authkit.email_verification.delivery.mode', 'after_response');

    /** @var TestEmailVerificationNotifier $notifier */
    $notifier = app(EmailVerificationNotifierContract::class);

    $listener = app(SendEmailVerificationNotification::class);

    $user = new TestEmailVerificationUser([
        'email' => 'meritinfos@gmail.com',
    ]);

    $event = new AuthKitEmailVerificationRequired(
        user: $user,
        email: 'meritinfos@gmail.com',
        driver: 'link',
        ttlMinutes: 5,
        token: 'toktoktok',
        url: 'https://example.test/verify'
    );

    $listener->handle($event);

    expect($notifier->calls)->toHaveCount(0);

    App::terminate();

    expect($notifier->calls)->toHaveCount(1)
        ->and($notifier->calls[0]['user'])->toBe($user)
        ->and($notifier->calls[0]['driver'])->toBe('link')
        ->and($notifier->calls[0]['email'])->toBe('meritinfos@gmail.com')
        ->and($notifier->calls[0]['token'])->toBe('toktoktok')
        ->and($notifier->calls[0]['url'])->toBe('https://example.test/verify');
});