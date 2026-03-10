<?php

namespace Xul\AuthKit\Tests\Feature\Api\EmailVerification;

use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\EmailVerification\VerifyEmailTokenAction;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Events\AuthKitEmailVerified;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Http\Controllers\Api\EmailVerification\VerifyEmailTokenController;
use Xul\AuthKit\Support\PendingEmailVerification;
use Xul\AuthKit\Tests\Support\ArrayTokenRepository;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

final class TestUser extends BaseUser implements MustVerifyEmailContract
{
    use Notifiable;
    use MustVerifyEmail;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'remember_token',
    ];
}

beforeEach(function () {
    Route::middleware(['web'])->group(function (): void {
        Route::get('/dashboard', fn () => 'ok')->name('authkit.web.dashboard');
        Route::get('/login', fn () => 'login')->name('authkit.web.login');
        Route::get('/email/verify/notice', fn () => 'notice')->name('authkit.web.email.verify.notice');
        Route::get('/email/verify/success', fn () => 'success')->name('authkit.web.email.verify.success');

        Route::post('/email/verify/token', VerifyEmailTokenController::class)
            ->name('authkit.api.email.verification.verify.token');
    });

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    config()->set('auth.providers.users.model', TestUser::class);
    config()->set('authkit.auth.guard', 'web');
    config()->set('authkit.login.dashboard_route', 'authkit.web.dashboard');
    config()->set('authkit.route_names.web.login', 'authkit.web.login');
    config()->set('authkit.route_names.web.verify_notice', 'authkit.web.email.verify.notice');
    config()->set('authkit.route_names.web.verify_success', 'authkit.web.email.verify.success');
    config()->set('authkit.route_names.web.dashboard_web', 'authkit.web.dashboard');
    config()->set('authkit.route_names.api.verify_token', 'authkit.api.email.verification.verify.token');
    config()->set('authkit.email_verification.columns.verified_at', 'email_verified_at');

    app()->singleton(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());
});

it('verifies email via token in json mode and logs the user in when enabled', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class, AuthKitLoggedIn::class]);

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

    $response = $this->postJson(route($route), [
        'email' => $user->email,
        'token' => $token,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Email verified successfully.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.email', $user->email)
        ->assertJsonPath('payload.verified', true)
        ->assertJsonPath('payload.logged_in', true)
        ->assertJsonPath('redirect.target', 'authkit.web.dashboard');

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull();

    $this->assertAuthenticated('web');
    $this->assertAuthenticatedAs($user, 'web');

    Event::assertDispatched(Verified::class);
    Event::assertDispatched(AuthKitEmailVerified::class, function ($event) use ($user) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->driver === 'token';
    });
    Event::assertDispatched(AuthKitLoggedIn::class, function ($event) use ($user) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->guard === 'web'
            && $event->remember === true;
    });
});

it('verifies email via token in json mode but does not log the user in when disabled', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class, AuthKitLoggedIn::class]);

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

    $response = $this->postJson(route($route), [
        'email' => $user->email,
        'token' => $token,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Email verified successfully.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.email', $user->email)
        ->assertJsonPath('payload.verified', true)
        ->assertJsonPath('payload.logged_in', false);

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull();

    $this->assertGuest('web');

    Event::assertDispatched(Verified::class);
    Event::assertDispatched(AuthKitEmailVerified::class, function ($event) use ($user) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->driver === 'token';
    });
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('returns a standardized action result from verify email token action', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class, AuthKitLoggedIn::class]);

    config()->set('authkit.email_verification.driver', 'token');
    config()->set('authkit.email_verification.ttl_minutes', 10);
    config()->set('authkit.email_verification.post_verify.login_after_verify', false);

    $user = TestUser::query()->create([
        'name' => 'Action Verify',
        'email' => 'action-verify@example.com',
        'password' => bcrypt('password'),
        'email_verified_at' => null,
    ]);

    /** @var PendingEmailVerification $pending */
    $pending = app(PendingEmailVerification::class);

    $token = $pending->createForEmail($user->email, 10, [
        'user_id' => (string) $user->getAuthIdentifier(),
        'driver' => 'token',
    ]);

    /** @var VerifyEmailTokenAction $action */
    $action = app(VerifyEmailTokenAction::class);

    $result = $action->handle($user->email, $token);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('email'))->toBe($user->email)
        ->and($result->payload?->get('verified'))->toBeTrue()
        ->and($result->redirect?->target)->toBe('authkit.web.dashboard');
});

it('returns 422 for invalid or expired token', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class, AuthKitLoggedIn::class]);

    $user = TestUser::query()->create([
        'name' => 'Invalid Token User',
        'email' => 'invalid-token@example.com',
        'password' => bcrypt('password'),
        'email_verified_at' => null,
    ]);

    $route = (string) data_get(
        config('authkit.route_names.api', []),
        'verify_token',
        'authkit.api.email.verification.verify.token'
    );

    $response = $this->postJson(route($route), [
        'email' => $user->email,
        'token' => 'bad-token',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'Invalid or expired verification code.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'invalid_or_expired_verification_code');

    $this->assertGuest('web');

    Event::assertNotDispatched(Verified::class);
    Event::assertNotDispatched(AuthKitEmailVerified::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('returns already verified success when the user email is already verified', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class, AuthKitLoggedIn::class]);

    config()->set('authkit.email_verification.post_verify.login_after_verify', false);

    $user = TestUser::query()->create([
        'name' => 'Already Verified',
        'email' => 'already-verified@example.com',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
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

    $response = $this->postJson(route($route), [
        'email' => $user->email,
        'token' => $token,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Your email is already verified.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.email', $user->email)
        ->assertJsonPath('payload.already_verified', true);

    Event::assertNotDispatched(Verified::class);
    Event::assertNotDispatched(AuthKitEmailVerified::class);
});

it('redirects to dashboard for web flow when login after verify is enabled', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class, AuthKitLoggedIn::class]);

    config()->set('authkit.email_verification.driver', 'token');
    config()->set('authkit.email_verification.ttl_minutes', 10);
    config()->set('authkit.email_verification.post_verify.login_after_verify', true);
    config()->set('authkit.email_verification.post_verify.remember', true);

    $user = TestUser::query()->create([
        'name' => 'Web Verify',
        'email' => 'web-verify@example.com',
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

    $response = $this->post(route($route), [
        'email' => $user->email,
        'token' => $token,
    ]);

    $response->assertRedirect(route('authkit.web.dashboard'))
        ->assertSessionHas('status', 'Email verified successfully.');

    $this->assertAuthenticated('web');
    $this->assertAuthenticatedAs($user, 'web');
});