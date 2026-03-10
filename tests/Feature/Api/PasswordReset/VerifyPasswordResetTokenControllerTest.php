<?php

namespace Xul\AuthKit\Tests\Feature\Api\PasswordReset;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Xul\AuthKit\Actions\PasswordReset\VerifyPasswordResetTokenAction;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Support\CacheTokenRepository;
use Xul\AuthKit\Support\PendingPasswordReset;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');

    config()->set('authkit.password_reset.driver', 'token');
    config()->set('authkit.password_reset.ttl_minutes', 5);
    config()->set('authkit.password_reset.token.max_attempts', 5);
    config()->set('authkit.password_reset.token.decay_minutes', 1);

    config()->set('authkit.route_names.api.password_verify_token', 'authkit.api.password.reset.verify.token');
    config()->set('authkit.route_names.web.password_reset_token_page', 'authkit.web.password.reset.token');
    config()->set('authkit.route_names.web.login', 'authkit.web.login');

    Route::post('/authkit/password/reset/verify-token', \Xul\AuthKit\Http\Controllers\Api\PasswordReset\VerifyPasswordResetTokenController::class)
        ->middleware(['web'])
        ->name('authkit.api.password.reset.verify.token');

    Route::get('/password/reset/token', fn () => 'token-page')
        ->name('authkit.web.password.reset.token');

    Route::get('/login', fn () => 'login')
        ->name('authkit.web.login');

    app()->singleton(TokenRepositoryContract::class, function ($app) {
        return new CacheTokenRepository($app['cache']->store());
    });

    app()->singleton(PendingPasswordReset::class, function ($app) {
        return new PendingPasswordReset(
            $app->make(TokenRepositoryContract::class),
            $app['cache']->store()
        );
    });
});

it('returns 200 when token is valid for token driver verification', function () {
    config()->set('authkit.password_reset.driver', 'token');
    config()->set('authkit.password_reset.ttl_minutes', 5);

    $routeName = (string) data_get(
        config('authkit.route_names.api', []),
        'password_verify_token',
        'authkit.api.password.reset.verify.token'
    );

    $ttl = (int) config('authkit.password_reset.ttl_minutes', 30);

    $pending = app(PendingPasswordReset::class);

    $token = $pending->createForEmail(
        email: 'michael@example.com',
        ttlMinutes: $ttl
    );

    $this->postJson(route($routeName), [
        'email' => 'michael@example.com',
        'token' => $token,
    ])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Reset token verified successfully.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.email', 'michael@example.com')
        ->assertJsonPath('payload.token_verified', true);
});

it('returns 422 when token is invalid for token driver verification', function () {
    config()->set('authkit.password_reset.driver', 'token');
    config()->set('authkit.password_reset.ttl_minutes', 5);

    $routeName = (string) data_get(
        config('authkit.route_names.api', []),
        'password_verify_token',
        'authkit.api.password.reset.verify.token'
    );

    $ttl = (int) config('authkit.password_reset.ttl_minutes', 30);

    app(PendingPasswordReset::class)->createForEmail(
        email: 'michael@example.com',
        ttlMinutes: $ttl
    );

    $this->postJson(route($routeName), [
        'email' => 'michael@example.com',
        'token' => '000000',
    ])
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'Invalid reset token.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'invalid_reset_token');
});

it('returns 410 when reset request is missing or expired for token driver verification', function () {
    config()->set('authkit.password_reset.driver', 'token');

    $routeName = (string) data_get(
        config('authkit.route_names.api', []),
        'password_verify_token',
        'authkit.api.password.reset.verify.token'
    );

    $this->postJson(route($routeName), [
        'email' => 'michael@example.com',
        'token' => '123456',
    ])
        ->assertStatus(410)
        ->assertJson([
            'ok' => false,
            'status' => 410,
            'message' => 'Password reset request has expired.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'password_reset_request_expired');
});

it('returns a standardized action result for valid token verification', function () {
    config()->set('authkit.password_reset.driver', 'token');
    config()->set('authkit.password_reset.ttl_minutes', 5);

    $pending = app(PendingPasswordReset::class);

    $token = $pending->createForEmail(
        email: 'michael@example.com',
        ttlMinutes: 5
    );

    /** @var VerifyPasswordResetTokenAction $action */
    $action = app(VerifyPasswordResetTokenAction::class);

    $result = $action->handle('michael@example.com', $token);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('email'))->toBe('michael@example.com')
        ->and($result->payload?->get('token_verified'))->toBeTrue()
        ->and($result->redirect?->target)->toBe('authkit.web.login');
});