<?php

namespace Xul\AuthKit\Tests\Feature\Web;

use Illuminate\Support\Facades\Config;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\PendingLogin;
use Xul\AuthKit\Tests\Support\ArrayTokenRepository;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * TwoFactorPagesTest
 *
 * Ensures two-factor web pages render and redirect correctly based on pending login state.
 */

beforeEach(function () {
    Config::set('authkit.route_names.web.login', 'authkit.web.login');
    Config::set('authkit.route_names.web.two_factor_challenge', 'authkit.web.twofactor.challenge');
    Config::set('authkit.route_names.web.two_factor_recovery', 'authkit.web.twofactor.recovery');

    Config::set('authkit.tokens.types.pending_login', [
        'length' => 64,
        'alphabet' => 'alnum',
        'uppercase' => false,
    ]);

    app()->bind(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());
});

/**
 * TwoFactorChallengePageSessionTest
 *
 * Ensures the two-factor challenge page renders when a valid pending login challenge exists in session.
 */
it('renders the two-factor challenge page when challenge exists in session', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['two_factor_challenge'] ?? 'authkit.web.twofactor.challenge');

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: 'user-1',
        remember: true,
        ttlMinutes: 5,
        methods: ['totp']
    );

    $this->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('Two-factor verification');
});

/**
 * TwoFactorChallengePageMissingTest
 *
 * Ensures the two-factor challenge page redirects when no challenge is provided.
 */
it('redirects when the two-factor challenge is missing', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['two_factor_challenge'] ?? 'authkit.web.twofactor.challenge');

    $this->get(route($routeName))
        ->assertRedirect();
});

/**
 * TwoFactorChallengePageInvalidSessionTest
 *
 * Ensures the two-factor challenge page redirects when an invalid challenge exists in session.
 */
it('redirects when the two-factor challenge in session is invalid', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['two_factor_challenge'] ?? 'authkit.web.twofactor.challenge');

    $this->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => 'invalid-challenge'])
        ->get(route($routeName))
        ->assertRedirect();
});

/**
 * TwoFactorChallengePageQueryCompatTest
 *
 * Ensures the two-factor challenge page renders when a valid pending login challenge is provided via query param.
 */
it('renders the two-factor challenge page with a valid query challenge', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['two_factor_challenge'] ?? 'authkit.web.twofactor.challenge');

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: 'user-1',
        remember: true,
        ttlMinutes: 5,
        methods: ['totp']
    );

    $this->get(route($routeName, ['c' => $challenge]))
        ->assertOk()
        ->assertSee('Two-factor verification');
});

/**
 * TwoFactorRecoveryPageSessionTest
 *
 * Ensures the two-factor recovery page renders when a valid pending login challenge exists in session.
 */
it('renders the two-factor recovery page when challenge exists in session', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['two_factor_recovery'] ?? 'authkit.web.twofactor.recovery');

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: 'user-1',
        remember: true,
        ttlMinutes: 5,
        methods: ['totp']
    );

    $this->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('Use a recovery code')
        ->assertSee('Recovery code')
        ->assertSee('Remember me')
        ->assertSee('Continue');
});

/**
 * TwoFactorRecoveryPageMissingTest
 *
 * Ensures the two-factor recovery page redirects when no challenge is provided.
 */
it('redirects when the two-factor recovery challenge is missing', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['two_factor_recovery'] ?? 'authkit.web.twofactor.recovery');

    $this->get(route($routeName))
        ->assertRedirect();
});

/**
 * TwoFactorRecoveryPageInvalidSessionTest
 *
 * Ensures the two-factor recovery page redirects when an invalid challenge exists in session.
 */
it('redirects when the two-factor recovery challenge in session is invalid', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['two_factor_recovery'] ?? 'authkit.web.twofactor.recovery');

    $this->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => 'invalid-challenge'])
        ->get(route($routeName))
        ->assertRedirect();
});

/**
 * TwoFactorRecoveryPageQueryCompatTest
 *
 * Ensures the two-factor recovery page renders when a valid pending login challenge is provided via query param.
 */
it('renders the two-factor recovery page with a valid query challenge', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['two_factor_recovery'] ?? 'authkit.web.twofactor.recovery');

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: 'user-1',
        remember: true,
        ttlMinutes: 5,
        methods: ['totp']
    );

    $this->get(route($routeName, ['c' => $challenge]))
        ->assertOk()
        ->assertSee('Use a recovery code')
        ->assertSee('Recovery code')
        ->assertSee('Remember me')
        ->assertSee('Continue');
});