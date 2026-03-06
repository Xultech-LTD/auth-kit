<?php

namespace Xul\AuthKit\Tests\Feature;

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Event;
use Xul\AuthKit\AuthKitServiceProvider;
use Xul\AuthKit\Contracts\EmailVerificationNotifierContract;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;

final class EmailVerificationDeliveryListenerTest implements EmailVerificationNotifierContract
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $calls = [];

    public function send($user, string $driver, string $email, string $token, ?string $url = null): void
    {
        $this->calls[] = compact('user', 'driver', 'email', 'token', 'url');
    }
}

final class TestUser extends BaseUser
{
    use Notifiable;

    protected $fillable = ['email'];
}

beforeEach(function () {
    // IMPORTANT: hard reset the event dispatcher so no previous listeners leak in.
    $fresh = new Dispatcher($this->app);

    $this->app->instance('events', $fresh);
    Event::swap($fresh);
});

it('registers the email verification delivery listener when enabled', function () {
    config()->set('authkit.email_verification.delivery.use_listener', true);
    config()->set('authkit.email_verification.delivery.notifier', EmailVerificationDeliveryListenerTest::class);

    $provider = new AuthKitServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $listeners = app('events')->getListeners(AuthKitEmailVerificationRequired::class);
    expect($listeners)->toHaveCount(1);

    /** @var EmailVerificationDeliveryListenerTest $notifier */
    $notifier = app(EmailVerificationNotifierContract::class);

    $user = new TestUser(['email' => 'meritinfos@gmail.com']);

    app('events')->dispatch(new AuthKitEmailVerificationRequired(
        user: $user,
        email: 'meritinfos@gmail.com',
        driver: 'token',
        ttlMinutes: 5,
        token: '123456',
        url: null
    ));

    expect($notifier->calls)->toHaveCount(1);
});

it('does not register the email verification delivery listener when disabled', function () {
    config()->set('authkit.email_verification.delivery.use_listener', false);
    config()->set('authkit.email_verification.delivery.notifier', EmailVerificationDeliveryListenerTest::class);

    $provider = new AuthKitServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $listeners = app('events')->getListeners(AuthKitEmailVerificationRequired::class);
    expect($listeners)->toHaveCount(0);

    /** @var EmailVerificationDeliveryListenerTest $notifier */
    $notifier = app(EmailVerificationNotifierContract::class);

    $user = new TestUser(['email' => 'meritinfos@gmail.com']);

    app('events')->dispatch(new AuthKitEmailVerificationRequired(
        user: $user,
        email: 'meritinfos@gmail.com',
        driver: 'link',
        ttlMinutes: 5,
        token: 'toktoktok',
        url: 'https://example.test/verify'
    ));

    expect($notifier->calls)->toHaveCount(0);
});