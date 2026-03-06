<?php

namespace Xul\AuthKit\Tests\Feature\Api\PasswordReset;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Xul\AuthKit\Support\PendingPasswordReset;

uses(RefreshDatabase::class);

it('returns 200 when token is valid for token-driver verification', function () {
    config()->set('authkit.password_reset.driver', 'token');
    config()->set('authkit.password_reset.ttl_minutes', 5);

    $apiNames = (array) config('authkit.route_names.api', []);
    $routeName = (string) ($apiNames['password_verify_token'] ?? 'authkit.api.password.reset.verify.token');

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
        ]);
});

it('returns 422 when token is invalid for token-driver verification', function () {
    config()->set('authkit.password_reset.driver', 'token');
    config()->set('authkit.password_reset.ttl_minutes', 5);

    $apiNames = (array) config('authkit.route_names.api', []);
    $routeName = (string) ($apiNames['password_verify_token'] ?? 'authkit.api.password.reset.verify.token');

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
        ]);
});

it('returns 410 when reset request is missing or expired for token-driver verification', function () {
    config()->set('authkit.password_reset.driver', 'token');

    $apiNames = (array) config('authkit.route_names.api', []);
    $routeName = (string) ($apiNames['password_verify_token'] ?? 'authkit.api.password.reset.verify.token');

    $this->postJson(route($routeName), [
        'email' => 'michael@example.com',
        'token' => '123456',
    ])
        ->assertStatus(410)
        ->assertJson([
            'ok' => false,
        ]);
});