<?php

namespace Xul\AuthKit\Tests\Feature\Api\EmailVerification;

use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Events\AuthKitEmailVerified;
use Xul\AuthKit\Support\PendingEmailVerification;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

final class TestUser extends BaseUser implements MustVerifyEmailContract
{
    use Notifiable;
    use MustVerifyEmail;

    /**
     * @var string
     */
    protected $table = 'users';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'remember_token',
    ];
}

beforeEach(function () {
    /**
     * Minimal routes needed by redirect flows.
     */
    Route::get('/dashboard', fn () => 'ok')->name('dashboard');

    /**
     * Test users table.
     */
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Ensure the auth provider uses our test user model.
     */
    config()->set('auth.providers.users.model', TestUser::class);

    /**
     * Ensure AuthKit uses the same guard during tests.
     */
    config()->set('authkit.auth.guard', 'web');

    /**
     * Ensure we have a stable dashboard route target.
     */
    config()->set('authkit.login.dashboard_route', 'dashboard');
});

it('verifies email via token (JSON) and logs the user in when enabled', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class]);

    config()->set('authkit.email_verification.driver', 'token');
    config()->set('authkit.email_verification.ttl_minutes', 10);
    config()->set('authkit.email_verification.post_verify.login_after_verify', true);
    config()->set('authkit.email_verification.post_verify.remember', true);

    $user = TestUser::query()->create([
        'name' => 'Token Verify',
        'email' => 'verify-token@example.com',
        'password' => bcrypt('password'),
        'email_verified_at' => null,
    ]);

    /** @var PendingEmailVerification $pending */
    $pending = app(PendingEmailVerification::class);

    $token = $pending->createForEmail($user->email, 10, [
        'user_id' => (string) $user->getAuthIdentifier(),
        'driver' => 'token',
    ]);

    $route = (string) data_get(
        config('authkit.route_names.api', []),
        'verify_token',
        'authkit.api.email.verification.verify.token'
    );

    $res = $this->postJson(route($route), [
        'email' => $user->email,
        'token' => $token,
    ]);

    $res->assertStatus(200)->assertJson([
        'ok' => true,
    ]);

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull();

    $this->assertAuthenticated('web');
    $this->assertAuthenticatedAs($user, 'web');

    Event::assertDispatched(Verified::class);
    Event::assertDispatched(AuthKitEmailVerified::class, function ($e) use ($user) {
        return (string) $e->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $e->driver === 'token';
    });
});

it('verifies email via token (JSON) but does not log the user in when disabled', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class]);

    config()->set('authkit.email_verification.driver', 'token');
    config()->set('authkit.email_verification.ttl_minutes', 10);
    config()->set('authkit.email_verification.post_verify.login_after_verify', false);

    $user = TestUser::query()->create([
        'name' => 'Token Verify No Login',
        'email' => 'verify-token-nologin@example.com',
        'password' => bcrypt('password'),
        'email_verified_at' => null,
    ]);

    /** @var PendingEmailVerification $pending */
    $pending = app(PendingEmailVerification::class);

    $token = $pending->createForEmail($user->email, 10, [
        'user_id' => (string) $user->getAuthIdentifier(),
        'driver' => 'token',
    ]);

    $route = (string) data_get(
        config('authkit.route_names.api', []),
        'verify_token',
        'authkit.api.email.verification.verify.token'
    );

    $res = $this->postJson(route($route), [
        'email' => $user->email,
        'token' => $token,
    ]);

    $res->assertStatus(200)->assertJson([
        'ok' => true,
    ]);

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull();

    $this->assertGuest('web');

    Event::assertDispatched(Verified::class);
    Event::assertDispatched(AuthKitEmailVerified::class, function ($e) use ($user) {
        return (string) $e->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $e->driver === 'token';
    });
});

it('verifies email via signed link and logs the user in when enabled', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class]);

    config()->set('authkit.email_verification.driver', 'link');
    config()->set('authkit.email_verification.ttl_minutes', 10);
    config()->set('authkit.email_verification.post_verify.mode', 'redirect');
    config()->set('authkit.email_verification.post_verify.redirect_route', null);
    config()->set('authkit.email_verification.post_verify.login_after_verify', true);
    config()->set('authkit.email_verification.post_verify.remember', true);

    $user = TestUser::query()->create([
        'name' => 'Link Verify',
        'email' => 'verify-link@example.com',
        'password' => bcrypt('password'),
        'email_verified_at' => null,
    ]);

    /** @var PendingEmailVerification $pending */
    $pending = app(PendingEmailVerification::class);

    $token = $pending->createForEmail($user->email, 10, [
        'user_id' => (string) $user->getAuthIdentifier(),
        'driver' => 'link',
    ]);

    $verifyLinkRoute = (string) data_get(
        config('authkit.route_names.web', []),
        'verify_link',
        'authkit.web.email.verification.verify.link'
    );

    $signedUrl = URL::temporarySignedRoute(
        name: $verifyLinkRoute,
        expiration: now()->addMinutes(10),
        parameters: [
            'id' => (string) $user->getAuthIdentifier(),
            'hash' => $token,
            'email' => $user->email,
        ]
    );

    $res = $this->get($signedUrl);

    $res->assertRedirect(route('dashboard'));

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull();

    $this->assertAuthenticated('web');
    $this->assertAuthenticatedAs($user, 'web');

    Event::assertDispatched(Verified::class);
    Event::assertDispatched(AuthKitEmailVerified::class, function ($e) use ($user) {
        return (string) $e->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $e->driver === 'link';
    });
});