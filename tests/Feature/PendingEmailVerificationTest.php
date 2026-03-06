<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\Support\PendingEmailVerification;

/**
 * PendingEmailVerificationTest
 *
 * Validates pending email verification behavior for both token and link drivers.
 */
it('tracks pending verification presence for token driver and clears it on consume', function () {

    config()->set('authkit.email_verification.driver', 'token');

    $pending = app(PendingEmailVerification::class);

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

it('validates link context against a user record by peeking token existence', function () {

    config()->set('authkit.email_verification.driver', 'link');
    config()->set('authkit.auth.guard', 'web');

    $user = new class extends AuthenticatableUser {
        public $email = 'michael@example.com';
        public function getAuthIdentifierName(): string { return 'id'; }
        public function getAuthIdentifier(): mixed { return 10; }
    };

    $provider = new class($user) implements UserProvider {
        public function __construct(private Authenticatable $user) {}

        public function retrieveById($identifier)
        {
            return ((string) $identifier === '10') ? $this->user : null;
        }

        public function retrieveByToken($identifier, $token) { return null; }
        public function updateRememberToken(Authenticatable $user, $token): void {}
        public function retrieveByCredentials(array $credentials) { return null; }
        public function validateCredentials(Authenticatable $user, array $credentials): bool { return false; }
        public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
    };

    $guard = new class($provider) implements Guard {
        public function __construct(private UserProvider $provider) {}
        public function check() { return false; }
        public function guest() { return true; }
        public function user() { return null; }
        public function id() { return null; }
        public function validate(array $credentials = []) { return false; }
        public function hasUser() { return false; }
        public function setUser(Authenticatable $user) { return $this; }
        public function getProvider() { return $this->provider; }
    };

    $auth = new class($guard) implements AuthFactory {
        public function __construct(private Guard $guard) {}
        public function guard($name = null) { return $this->guard; }
        public function shouldUse($name) {}
        public function setDefaultDriver($name) {}
        public function getDefaultDriver() { return 'web'; }
        public function extend($driver, \Closure $callback) {}
        public function provider($name, \Closure $callback) {}
    };

    $pending = new PendingEmailVerification(
        tokens: app(TokenRepositoryContract::class),
        cache: app('cache')->store(),
        auth: $auth
    );

    $email = 'michael@example.com';

    $token = $pending->createForEmail(
        email: $email,
        ttlMinutes: 5,
        payload: ['purpose' => 'link-test']
    );

    expect($pending->isLinkContextValid('10', $token))->toBeTrue()
        ->and($pending->isLinkContextValid('10', 'wrong-token'))->toBeFalse();

    $pending->consumeToken($email, $token);

    expect($pending->isLinkContextValid('10', $token))->toBeFalse();
});