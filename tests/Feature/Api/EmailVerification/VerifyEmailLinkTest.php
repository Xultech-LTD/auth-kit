<?php

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\Support\PendingEmailVerification;
use Xul\AuthKit\Actions\EmailVerification\VerifyEmailLinkAction;

/**
 * VerifyEmailLinkTest
 *
 * Ensures signed verification link triggers verification logic and dispatches event.
 */
it('verifies email via signed link and redirects with status', function () {

    Event::fake([Verified::class]);

    config()->set('authkit.email_verification.driver', 'link');
    config()->set('authkit.auth.guard', 'web');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['verify_link'] ?? 'authkit.web.email.verification.verify.link');
    $loginName = (string) ($webNames['login'] ?? 'authkit.web.login');

    $user = new class extends AuthenticatableUser implements MustVerifyEmail {
        public $email = 'michael@example.com';
        public $email_verified_at = null;

        public function getAuthIdentifierName(): string { return 'id'; }
        public function getAuthIdentifier(): mixed { return 10; }

        public function hasVerifiedEmail(): bool
        {
            return $this->email_verified_at !== null;
        }

        public function markEmailAsVerified(): bool
        {
            $this->email_verified_at = now();

            return true;
        }

        public function sendEmailVerificationNotification(): void {}
        public function getEmailForVerification(): string { return (string) $this->email; }
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

    $token = $pending->createForEmail(
        email: 'michael@example.com',
        ttlMinutes: 10,
        payload: ['user_id' => '10']
    );

    config()->set('authkit.email_verification.post_verify.mode', 'redirect');
    config()->set('authkit.email_verification.post_verify.redirect_route', $loginName);

    $this->app->singleton(VerifyEmailLinkAction::class, function () use ($pending, $auth) {
        return new VerifyEmailLinkAction($pending, $auth);
    });

    $url = URL::temporarySignedRoute(
        $routeName,
        now()->addMinutes(10),
        [
            'id' => '10',
            'hash' => $token,
        ]
    );

    $this->get($url)
        ->assertRedirect(route($loginName))
        ->assertSessionHas('status');

    Event::assertDispatched(Verified::class);
});

it('rejects invalid token and redirects to login with error', function () {

    config()->set('authkit.email_verification.driver', 'link');
    config()->set('authkit.auth.guard', 'web');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['verify_link'] ?? 'authkit.web.email.verification.verify.link');
    $loginName = (string) ($webNames['login'] ?? 'authkit.web.login');

    config()->set('authkit.email_verification.post_verify.mode', 'redirect');
    config()->set('authkit.email_verification.post_verify.redirect_route', $loginName);

    $provider = new class implements UserProvider {
        public function retrieveById($identifier) { return null; }
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

    $this->app->singleton(VerifyEmailLinkAction::class, function () use ($pending, $auth) {
        return new VerifyEmailLinkAction($pending, $auth);
    });

    $url = URL::temporarySignedRoute(
        $routeName,
        now()->addMinutes(10),
        [
            'id' => '999',
            'hash' => 'wrong-token',
        ]
    );

    $this->get($url)
        ->assertRedirect(route($loginName))
        ->assertSessionHas('error');
});