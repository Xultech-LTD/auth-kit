<?php

use Xul\AuthKit\Support\PendingEmailVerification;

/**
 * EmailVerificationNoticePageTest
 *
 * Ensures the email verification notice page renders only when a pending verification exists.
 */
it('renders the email verification notice page when pending verification exists', function () {

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['verify_notice'] ?? 'authkit.web.email.verify.notice');

    app(PendingEmailVerification::class)->markPendingForEmail(
        email: 'michael@example.com',
        ttlMinutes: 5
    );

    $this->get(route($routeName, ['email' => 'michael@example.com']))
        ->assertOk()
        ->assertSee('Verify your email');
});

/**
 * EmailVerificationNoticePageRedirectTest
 *
 * Ensures the notice page redirects when no pending verification exists.
 */
it('redirects from the notice page when there is no pending verification', function () {

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['verify_notice'] ?? 'authkit.web.email.verify.notice');

    $this->get(route($routeName, ['email' => 'michael@example.com']))
        ->assertRedirect();
});