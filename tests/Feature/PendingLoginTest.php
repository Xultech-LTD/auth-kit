<?php

use Xul\AuthKit\Support\PendingLogin;

/**
 * PendingLoginTest
 *
 * Ensures pending login challenges can be created, peeked, and consumed.
 */
it('creates, peeks and consumes a pending login challenge', function () {

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: 'user-1',
        remember: true,
        ttlMinutes: 5,
        methods: ['totp']
    );

    expect($challenge)->not->toBeEmpty();

    $peek = $pending->peek($challenge);

    expect($peek)->toMatchArray([
        'user_id' => 'user-1',
        'remember' => true,
    ]);

    $consumed = $pending->consume($challenge);

    expect($consumed)->toMatchArray([
        'user_id' => 'user-1',
        'remember' => true,
    ]);

    $again = $pending->consume($challenge);

    expect($again)->toBeNull();
});