<?php

namespace Xul\AuthKit\Tests\Feature;

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Event;
use Xul\AuthKit\AuthKitServiceProvider;
use Xul\AuthKit\Contracts\EmailVerificationNotifierContract;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;

/**
 * RegisteredEmailVerificationNotifierFake
 *
 * Test double used to verify listener registration behavior.
 */
final class RegisteredEmailVerificationNotifierFake implements EmailVerificationNotifierContract
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
    public function send($user, string $driver, string $email, string $token, ?string $url = null): void
    {
        $this->calls[] = compact('user', 'driver', 'email', 'token', 'url');
    }
}

/**
 * RegistrationTestUser
 *
 * Minimal user model used for registration tests.
 */
final class RegistrationTestUser extends BaseUser
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
});

it('registers the email verification delivery listener when enabled', function () {
    config()->set('authkit.email_verification.delivery.use_listener', true);
    config()->set('authkit.email_verification.delivery.notifier', RegisteredEmailVerificationNotifierFake::class);

    $provider = new AuthKitServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $listeners = app('events')->getListeners(AuthKitEmailVerificationRequired::class);

    expect($listeners)->toHaveCount(1);

    /** @var RegisteredEmailVerificationNotifierFake $notifier */
    $notifier = app(EmailVerificationNotifierContract::class);

    $user = new RegistrationTestUser([
        'email' => 'michael@gmail.com',
    ]);

    app('events')->dispatch(new AuthKitEmailVerificationRequired(
        user: $user,
        email: 'michael@gmail.com',
        driver: 'token',
        ttlMinutes: 5,
        token: '123456',
        url: null
    ));

    expect($notifier->calls)->toHaveCount(1);
});

it('does not register the email verification delivery listener when disabled', function () {
    config()->set('authkit.email_verification.delivery.use_listener', false);
    config()->set('authkit.email_verification.delivery.notifier', RegisteredEmailVerificationNotifierFake::class);

    $provider = new AuthKitServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $listeners = app('events')->getListeners(AuthKitEmailVerificationRequired::class);

    expect($listeners)->toHaveCount(0);

    /** @var RegisteredEmailVerificationNotifierFake $notifier */
    $notifier = app(EmailVerificationNotifierContract::class);

    $user = new RegistrationTestUser([
        'email' => 'michael@gmail.com',
    ]);

    app('events')->dispatch(new AuthKitEmailVerificationRequired(
        user: $user,
        email: 'michael@gmail.com',
        driver: 'link',
        ttlMinutes: 5,
        token: 'toktoktok',
        url: 'https://example.test/verify'
    ));

    expect($notifier->calls)->toHaveCount(0);
});