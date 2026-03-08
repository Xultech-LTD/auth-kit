<?php

use Xul\AuthKit\Support\PendingLogin;
use Xul\AuthKit\Support\AuthKitSessionKeys;

/**
 * TwoFactorChallengePageTest
 *
 * Ensures the two-factor challenge page renders when a valid pending login challenge exists in session.
 */
it('renders the two-factor challenge page when a valid challenge exists in session', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['two_factor_challenge'] ?? 'authkit.web.twofactor.challenge');

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: 'user-1',
        remember: true,
        ttlMinutes: 5,
        methods: ['totp']
    );

    $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('Two-factor verification')
        ->assertSee('Authentication code')
        ->assertSee('Verify')
        ->assertSee('Use a recovery code');
});

/**
 * TwoFactorChallengePageRedirectTest
 *
 * Ensures the two-factor challenge page redirects when no challenge is provided
 * via query string or session.
 */
it('redirects when the two-factor challenge is missing', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['two_factor_challenge'] ?? 'authkit.web.twofactor.challenge');

    $this->get(route($routeName))
        ->assertRedirect();
});