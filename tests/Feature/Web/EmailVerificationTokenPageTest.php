<?php

use Xul\AuthKit\Support\PendingEmailVerification;

/**
 * EmailVerificationTokenPageTest
 *
 * Ensures the token verification page renders only when a pending verification exists.
 */
it('renders the token verification page when pending verification exists', function () {
    config()->set('authkit.email_verification.driver', 'token');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['verify_token_page'] ?? 'authkit.web.email.verify.token');

    app(PendingEmailVerification::class)->markPendingForEmail(
        email: 'michael@example.com',
        ttlMinutes: 5
    );

    $this->get(route($routeName, ['email' => 'michael@example.com']))
        ->assertOk()
        ->assertSee('Enter verification code')
        ->assertSee('michael@example.com')
        ->assertSee('Verification code')
        ->assertSee('Verify email');
});

/**
 * EmailVerificationTokenPageRedirectTest
 *
 * Ensures the token verification page redirects when no pending verification exists.
 */
it('redirects from the token verification page when there is no pending verification', function () {
    config()->set('authkit.email_verification.driver', 'token');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['verify_token_page'] ?? 'authkit.web.email.verify.token');

    $this->get(route($routeName, ['email' => 'michael@example.com']))
        ->assertRedirect();
});