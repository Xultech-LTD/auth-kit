<?php

use Xul\AuthKit\Support\PendingPasswordReset;

it('tracks pending password reset presence for token driver and clears it on consume', function () {

    $pending = app(PendingPasswordReset::class);

    $email = 'michael@example.com';

    expect($pending->hasPendingForEmail($email))->toBeFalse();

    $token = $pending->createForEmail(
        email: $email,
        ttlMinutes: 5,
        payload: ['purpose' => 'test']
    );

    expect($token)->not->toBeEmpty()
        ->and($pending->hasPendingForEmail($email))->toBeTrue();

    $payload = $pending->consumeToken(
        email: $email,
        token: $token
    );

    expect($payload)->toMatchArray([
        'email' => mb_strtolower($email),
        'purpose' => 'test',
    ])->and($pending->hasPendingForEmail($email))->toBeFalse();
});

it('can mark pending password reset presence without creating a token', function () {

    $pending = app(PendingPasswordReset::class);

    $email = 'michael@example.com';

    expect($pending->hasPendingForEmail($email))->toBeFalse();

    $pending->markPendingForEmail(
        email: $email,
        ttlMinutes: 5
    );

    expect($pending->hasPendingForEmail($email))->toBeTrue();
});