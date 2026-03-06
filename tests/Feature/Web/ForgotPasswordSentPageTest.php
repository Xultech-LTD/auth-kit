<?php

use Xul\AuthKit\Support\PendingPasswordReset;

/**
 * ForgotPasswordSentPageTest
 *
 * Ensures the "sent" page renders only when a pending reset context exists.
 */
it('renders the forgot password sent page when pending reset exists', function () {

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['password_forgot_sent'] ?? 'authkit.web.password.forgot.sent');

    app(PendingPasswordReset::class)->markPendingForEmail(
        email: 'michael@example.com',
        ttlMinutes: 5
    );

    $this->get(route($routeName, ['email' => 'michael@example.com']))
        ->assertOk()
        ->assertSee('Check your email');
});

it('redirects from the forgot password sent page when there is no pending reset', function () {

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['password_forgot_sent'] ?? 'authkit.web.password.forgot.sent');

    $this->get(route($routeName, ['email' => 'michael@example.com']))
        ->assertRedirect();
});