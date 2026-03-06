<?php

use Xul\AuthKit\Support\Resolvers\ControllerResolver;

/**
 * ControllerResolverTest
 *
 * Ensures controller resolution respects config overrides
 * and safely falls back to defaults.
 */
it('returns the default controller when no override is configured', function () {

    config()->set('authkit.controllers.api.login', null);

    $resolved = ControllerResolver::resolve(
        group: 'api',
        key: 'login',
        default: \Xul\AuthKit\Http\Controllers\Api\Auth\LoginController::class
    );

    expect($resolved)->toBe(
        \Xul\AuthKit\Http\Controllers\Api\Auth\LoginController::class
    );
});

it('returns the configured override controller when valid', function () {

    $fake = \Xul\AuthKit\Tests\Fixtures\FakeLoginController::class;

    config()->set('authkit.controllers.api.login', $fake);

    $resolved = ControllerResolver::resolve(
        group: 'api',
        key: 'login',
        default: \Xul\AuthKit\Http\Controllers\Api\Auth\LoginController::class
    );

    expect($resolved)->toBe($fake);
});

it('falls back to default when configured class does not exist', function () {

    config()->set('authkit.controllers.api.login', 'App\\DoesNotExist');

    $resolved = ControllerResolver::resolve(
        group: 'api',
        key: 'login',
        default: \Xul\AuthKit\Http\Controllers\Api\Auth\LoginController::class
    );

    expect($resolved)->toBe(
        \Xul\AuthKit\Http\Controllers\Api\Auth\LoginController::class
    );
});