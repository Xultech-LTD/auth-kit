<?php

use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Support\PendingPasswordReset;

it('renders the reset password page when pending reset exists and token is valid', function () {
    config()->set('authkit.password_reset.driver', 'link');
    config()->set('authkit.password_reset.ttl_minutes', 5);

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['password_reset'] ?? 'authkit.web.password.reset');

    $pending = app(PendingPasswordReset::class);

    $ttl = (int) config('authkit.password_reset.ttl_minutes', 30);

    $token = $pending->createForEmail(
        email: 'michael@example.com',
        ttlMinutes: $ttl
    );

    $url = URL::temporarySignedRoute(
        name: $routeName,
        expiration: now()->addMinutes($ttl),
        parameters: [
            'token' => $token,
            'email' => 'michael@example.com',
        ]
    );

    $this->get($url)
        ->assertOk()
        ->assertSee('Reset your password');
});

it('redirects from the reset password page when token is invalid', function () {
    config()->set('authkit.password_reset.driver', 'link');
    config()->set('authkit.password_reset.ttl_minutes', 5);

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['password_reset'] ?? 'authkit.web.password.reset');

    $ttl = (int) config('authkit.password_reset.ttl_minutes', 30);

    app(PendingPasswordReset::class)->markPendingForEmail(
        email: 'michael@example.com',
        ttlMinutes: $ttl
    );

    $url = URL::temporarySignedRoute(
        name: $routeName,
        expiration: now()->addMinutes($ttl),
        parameters: [
            'token' => 'invalid-token',
            'email' => 'michael@example.com',
        ]
    );

    $this->get($url)
        ->assertRedirect();
});

it('redirects from the reset password page when email is missing', function () {
    config()->set('authkit.password_reset.driver', 'link');
    config()->set('authkit.password_reset.ttl_minutes', 5);

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['password_reset'] ?? 'authkit.web.password.reset');

    $ttl = (int) config('authkit.password_reset.ttl_minutes', 30);

    $url = URL::temporarySignedRoute(
        name: $routeName,
        expiration: now()->addMinutes($ttl),
        parameters: [
            'token' => 'some-token',
        ]
    );

    $this->get($url)
        ->assertRedirect();
});