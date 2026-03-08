<?php

use Xul\AuthKit\Support\PendingPasswordReset;

it('renders the password reset token page when pending reset exists', function () {
    config()->set('authkit.password_reset.driver', 'token');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['password_reset_token_page'] ?? 'authkit.web.password.reset.token');

    app(PendingPasswordReset::class)->markPendingForEmail(
        email: 'michael@example.com',
        ttlMinutes: 5
    );

    $this->get(route($routeName, ['email' => 'michael@example.com']))
        ->assertOk()
        ->assertSee('Enter reset code')
        ->assertSee('michael@example.com')
        ->assertSee('Reset code')
        ->assertSee('New password')
        ->assertSee('Confirm password')
        ->assertSee('Reset password');
});

it('redirects from the password reset token page when no pending reset exists', function () {
    config()->set('authkit.password_reset.driver', 'token');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['password_reset_token_page'] ?? 'authkit.web.password.reset.token');

    $this->get(route($routeName, ['email' => 'michael@example.com']))
        ->assertRedirect();
});