<?php

use Xul\AuthKit\Contracts\TokenRepositoryContract;

/**
 * TokenRepositoryPeekTest
 *
 * Ensures peek does not consume tokens.
 */
it('can peek a token without consuming it', function () {

    $repo = app(TokenRepositoryContract::class);

    $token = $repo->create(
        type: 'peek_test',
        identifier: 'x',
        ttlMinutes: 5,
        payload: ['a' => 1]
    );

    $peek = $repo->peek(
        type: 'peek_test',
        identifier: 'x',
        token: $token
    );

    expect($peek)->toBe(['a' => 1]);

    $payload = $repo->validate(
        type: 'peek_test',
        identifier: 'x',
        token: $token
    );

    expect($payload)->toBe(['a' => 1]);
});