<?php

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Actions\EmailVerification\VerifyEmailLinkAction;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Events\AuthKitEmailVerified;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Support\PendingEmailVerification;
use Xul\AuthKit\Tests\Support\ArrayTokenRepository;

beforeEach(function (): void {
    app()->singleton(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());

    Route::get('/login', fn () => 'login')->name('authkit.web.login');
    Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
    Route::get('/email/verify/success', fn () => 'success')->name('authkit.web.email.verify.success');
});

it('returns a standardized action result for successful link verification', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class, AuthKitLoggedIn::class]);

    config()->set('authkit.email_verification.driver', 'link');
    config()->set('authkit.auth.guard', 'web');
    config()->set('authkit.email_verification.post_verify.mode', 'redirect');
    config()->set('authkit.email_verification.post_verify.redirect_route', 'authkit.web.login');
    config()->set('authkit.email_verification.post_verify.login_after_verify', false);
    config()->set('authkit.route_names.web.login', 'authkit.web.login');

    $user = new class extends AuthenticatableUser implements MustVerifyEmail {
        public $email = 'michael@example.com';
        public $email_verified_at = null;

        /**
         * @return string
         */
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        /**
         * @return mixed
         */
        public function getAuthIdentifier(): mixed
        {
            return 10;
        }

        /**
         * @return bool
         */
        public function hasVerifiedEmail(): bool
        {
            return $this->email_verified_at !== null;
        }

        /**
         * @return bool
         */
        public function markEmailAsVerified(): bool
        {
            $this->email_verified_at = now();

            return true;
        }

        /**
         * @return void
         */
        public function sendEmailVerificationNotification(): void {}

        /**
         * @return string
         */
        public function getEmailForVerification(): string
        {
            return (string) $this->email;
        }
    };

    $provider = new class($user) implements UserProvider {
        /**
         * @param Authenticatable $user
         */
        public function __construct(private Authenticatable $user) {}

        /**
         * @param mixed $identifier
         * @return Authenticatable|null
         */
        public function retrieveById($identifier)
        {
            return ((string) $identifier === '10') ? $this->user : null;
        }

        /**
         * @param mixed $identifier
         * @param string|null $token
         * @return Authenticatable|null
         */
        public function retrieveByToken($identifier, $token)
        {
            return null;
        }

        /**
         * @param Authenticatable $user
         * @param string $token
         * @return void
         */
        public function updateRememberToken(Authenticatable $user, $token): void {}

        /**
         * @param array<string, mixed> $credentials
         * @return Authenticatable|null
         */
        public function retrieveByCredentials(array $credentials)
        {
            return null;
        }

        /**
         * @param Authenticatable $user
         * @param array<string, mixed> $credentials
         * @return bool
         */
        public function validateCredentials(Authenticatable $user, array $credentials): bool
        {
            return false;
        }

        /**
         * @param Authenticatable $user
         * @param array<string, mixed> $credentials
         * @param bool $force
         * @return void
         */
        public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
    };

    $guard = new class($provider) implements Guard {
        /**
         * @param UserProvider $provider
         */
        public function __construct(private UserProvider $provider) {}

        /**
         * @return bool
         */
        public function check()
        {
            return false;
        }

        /**
         * @return bool
         */
        public function guest()
        {
            return true;
        }

        /**
         * @return Authenticatable|null
         */
        public function user()
        {
            return null;
        }

        /**
         * @return int|string|null
         */
        public function id()
        {
            return null;
        }

        /**
         * @param array<string, mixed> $credentials
         * @return bool
         */
        public function validate(array $credentials = [])
        {
            return false;
        }

        /**
         * @return bool
         */
        public function hasUser()
        {
            return false;
        }

        /**
         * @param Authenticatable $user
         * @return $this
         */
        public function setUser(Authenticatable $user)
        {
            return $this;
        }

        /**
         * @return UserProvider
         */
        public function getProvider()
        {
            return $this->provider;
        }
    };

    $auth = new class($guard) implements AuthFactory {
        /**
         * @param Guard $guard
         */
        public function __construct(private Guard $guard) {}

        /**
         * @param string|null $name
         * @return Guard
         */
        public function guard($name = null)
        {
            return $this->guard;
        }

        /**
         * @param string $name
         * @return void
         */
        public function shouldUse($name) {}

        /**
         * @param string $name
         * @return void
         */
        public function setDefaultDriver($name) {}

        /**
         * @return string
         */
        public function getDefaultDriver()
        {
            return 'web';
        }

        /**
         * @param string $driver
         * @param \Closure $callback
         * @return mixed
         */
        public function extend($driver, \Closure $callback) {}

        /**
         * @param string $name
         * @param \Closure $callback
         * @return mixed
         */
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
        payload: ['user_id' => '10', 'driver' => 'link']
    );

    $action = new VerifyEmailLinkAction($pending, $auth);

    $result = $action->handle('10', $token);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('email'))->toBe('michael@example.com')
        ->and($result->payload?->get('verified'))->toBeTrue()
        ->and($result->payload?->get('driver'))->toBe('link')
        ->and($result->redirect?->target)->toBe('authkit.web.login');

    Event::assertDispatched(Verified::class);
    Event::assertDispatched(AuthKitEmailVerified::class, function ($event) use ($user) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->driver === 'link';
    });
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('verifies email via signed link and redirects with status', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class]);

    config()->set('authkit.email_verification.driver', 'link');
    config()->set('authkit.auth.guard', 'web');
    config()->set('authkit.email_verification.post_verify.mode', 'redirect');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['verify_link'] ?? 'authkit.web.email.verification.verify.link');
    $loginName = (string) ($webNames['login'] ?? 'authkit.web.login');

    $user = new class extends AuthenticatableUser implements MustVerifyEmail {
        public $email = 'michael@example.com';
        public $email_verified_at = null;

        /**
         * @return string
         */
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        /**
         * @return mixed
         */
        public function getAuthIdentifier(): mixed
        {
            return 10;
        }

        /**
         * @return bool
         */
        public function hasVerifiedEmail(): bool
        {
            return $this->email_verified_at !== null;
        }

        /**
         * @return bool
         */
        public function markEmailAsVerified(): bool
        {
            $this->email_verified_at = now();

            return true;
        }

        /**
         * @return void
         */
        public function sendEmailVerificationNotification(): void {}

        /**
         * @return string
         */
        public function getEmailForVerification(): string
        {
            return (string) $this->email;
        }
    };

    $provider = new class($user) implements UserProvider {
        /**
         * @param Authenticatable $user
         */
        public function __construct(private Authenticatable $user) {}

        /**
         * @param mixed $identifier
         * @return Authenticatable|null
         */
        public function retrieveById($identifier)
        {
            return ((string) $identifier === '10') ? $this->user : null;
        }

        /**
         * @param mixed $identifier
         * @param string|null $token
         * @return Authenticatable|null
         */
        public function retrieveByToken($identifier, $token)
        {
            return null;
        }

        /**
         * @param Authenticatable $user
         * @param string $token
         * @return void
         */
        public function updateRememberToken(Authenticatable $user, $token): void {}

        /**
         * @param array<string, mixed> $credentials
         * @return Authenticatable|null
         */
        public function retrieveByCredentials(array $credentials)
        {
            return null;
        }

        /**
         * @param Authenticatable $user
         * @param array<string, mixed> $credentials
         * @return bool
         */
        public function validateCredentials(Authenticatable $user, array $credentials): bool
        {
            return false;
        }

        /**
         * @param Authenticatable $user
         * @param array<string, mixed> $credentials
         * @param bool $force
         * @return void
         */
        public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
    };

    $guard = new class($provider) implements Guard {
        /**
         * @param UserProvider $provider
         */
        public function __construct(private UserProvider $provider) {}

        /**
         * @return bool
         */
        public function check()
        {
            return false;
        }

        /**
         * @return bool
         */
        public function guest()
        {
            return true;
        }

        /**
         * @return Authenticatable|null
         */
        public function user()
        {
            return null;
        }

        /**
         * @return int|string|null
         */
        public function id()
        {
            return null;
        }

        /**
         * @param array<string, mixed> $credentials
         * @return bool
         */
        public function validate(array $credentials = [])
        {
            return false;
        }

        /**
         * @return bool
         */
        public function hasUser()
        {
            return false;
        }

        /**
         * @param Authenticatable $user
         * @return $this
         */
        public function setUser(Authenticatable $user)
        {
            return $this;
        }

        /**
         * @return UserProvider
         */
        public function getProvider()
        {
            return $this->provider;
        }
    };

    $auth = new class($guard) implements AuthFactory {
        /**
         * @param Guard $guard
         */
        public function __construct(private Guard $guard) {}

        /**
         * @param string|null $name
         * @return Guard
         */
        public function guard($name = null)
        {
            return $this->guard;
        }

        /**
         * @param string $name
         * @return void
         */
        public function shouldUse($name) {}

        /**
         * @param string $name
         * @return void
         */
        public function setDefaultDriver($name) {}

        /**
         * @return string
         */
        public function getDefaultDriver()
        {
            return 'web';
        }

        /**
         * @param string $driver
         * @param \Closure $callback
         * @return mixed
         */
        public function extend($driver, \Closure $callback) {}

        /**
         * @param string $name
         * @param \Closure $callback
         * @return mixed
         */
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
        payload: ['user_id' => '10', 'driver' => 'link']
    );

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
        ->assertSessionHas('status', 'Email verified successfully.');

    Event::assertDispatched(Verified::class);
    Event::assertDispatched(AuthKitEmailVerified::class);
});

it('rejects invalid token and redirects to login with error', function () {
    config()->set('authkit.email_verification.driver', 'link');
    config()->set('authkit.auth.guard', 'web');
    config()->set('authkit.email_verification.post_verify.mode', 'redirect');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['verify_link'] ?? 'authkit.web.email.verification.verify.link');
    $loginName = (string) ($webNames['login'] ?? 'authkit.web.login');

    config()->set('authkit.email_verification.post_verify.redirect_route', $loginName);

    $provider = new class implements UserProvider {
        /**
         * @param mixed $identifier
         * @return Authenticatable|null
         */
        public function retrieveById($identifier)
        {
            return null;
        }

        /**
         * @param mixed $identifier
         * @param string|null $token
         * @return Authenticatable|null
         */
        public function retrieveByToken($identifier, $token)
        {
            return null;
        }

        /**
         * @param Authenticatable $user
         * @param string $token
         * @return void
         */
        public function updateRememberToken(Authenticatable $user, $token): void {}

        /**
         * @param array<string, mixed> $credentials
         * @return Authenticatable|null
         */
        public function retrieveByCredentials(array $credentials)
        {
            return null;
        }

        /**
         * @param Authenticatable $user
         * @param array<string, mixed> $credentials
         * @return bool
         */
        public function validateCredentials(Authenticatable $user, array $credentials): bool
        {
            return false;
        }

        /**
         * @param Authenticatable $user
         * @param array<string, mixed> $credentials
         * @param bool $force
         * @return void
         */
        public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
    };

    $guard = new class($provider) implements Guard {
        /**
         * @param UserProvider $provider
         */
        public function __construct(private UserProvider $provider) {}

        /**
         * @return bool
         */
        public function check()
        {
            return false;
        }

        /**
         * @return bool
         */
        public function guest()
        {
            return true;
        }

        /**
         * @return Authenticatable|null
         */
        public function user()
        {
            return null;
        }

        /**
         * @return int|string|null
         */
        public function id()
        {
            return null;
        }

        /**
         * @param array<string, mixed> $credentials
         * @return bool
         */
        public function validate(array $credentials = [])
        {
            return false;
        }

        /**
         * @return bool
         */
        public function hasUser()
        {
            return false;
        }

        /**
         * @param Authenticatable $user
         * @return $this
         */
        public function setUser(Authenticatable $user)
        {
            return $this;
        }

        /**
         * @return UserProvider
         */
        public function getProvider()
        {
            return $this->provider;
        }
    };

    $auth = new class($guard) implements AuthFactory {
        /**
         * @param Guard $guard
         */
        public function __construct(private Guard $guard) {}

        /**
         * @param string|null $name
         * @return Guard
         */
        public function guard($name = null)
        {
            return $this->guard;
        }

        /**
         * @param string $name
         * @return void
         */
        public function shouldUse($name) {}

        /**
         * @param string $name
         * @return void
         */
        public function setDefaultDriver($name) {}

        /**
         * @return string
         */
        public function getDefaultDriver()
        {
            return 'web';
        }

        /**
         * @param string $driver
         * @param \Closure $callback
         * @return mixed
         */
        public function extend($driver, \Closure $callback) {}

        /**
         * @param string $name
         * @param \Closure $callback
         * @return mixed
         */
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
        ->assertSessionHas('error', 'Invalid verification link.');
});

it('returns completed result when email is already verified', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class, AuthKitLoggedIn::class]);

    config()->set('authkit.email_verification.driver', 'link');
    config()->set('authkit.auth.guard', 'web');
    config()->set('authkit.email_verification.post_verify.mode', 'redirect');
    config()->set('authkit.email_verification.post_verify.redirect_route', 'authkit.web.login');
    config()->set('authkit.email_verification.post_verify.login_after_verify', false);
    config()->set('authkit.route_names.web.login', 'authkit.web.login');

    $user = new class extends AuthenticatableUser implements MustVerifyEmail {
        public $email = 'michael@example.com';
        public $email_verified_at;

        public function __construct()
        {
            $this->email_verified_at = now();
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 10;
        }

        public function hasVerifiedEmail(): bool
        {
            return true;
        }

        public function markEmailAsVerified(): bool
        {
            return true;
        }

        public function sendEmailVerificationNotification(): void {}

        public function getEmailForVerification(): string
        {
            return (string) $this->email;
        }
    };

    $provider = new class($user) implements UserProvider {
        public function __construct(private Authenticatable $user) {}

        public function retrieveById($identifier)
        {
            return ((string) $identifier === '10') ? $this->user : null;
        }

        public function retrieveByToken($identifier, $token)
        {
            return null;
        }

        public function updateRememberToken(Authenticatable $user, $token): void {}

        public function retrieveByCredentials(array $credentials)
        {
            return null;
        }

        public function validateCredentials(Authenticatable $user, array $credentials): bool
        {
            return false;
        }

        public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
    };

    $guard = new class($provider) implements Guard {
        public function __construct(private UserProvider $provider) {}

        public function check()
        {
            return false;
        }

        public function guest()
        {
            return true;
        }

        public function user()
        {
            return null;
        }

        public function id()
        {
            return null;
        }

        public function validate(array $credentials = [])
        {
            return false;
        }

        public function hasUser()
        {
            return false;
        }

        public function setUser(Authenticatable $user)
        {
            return $this;
        }

        public function getProvider()
        {
            return $this->provider;
        }
    };

    $auth = new class($guard) implements AuthFactory {
        public function __construct(private Guard $guard) {}

        public function guard($name = null)
        {
            return $this->guard;
        }

        public function shouldUse($name) {}

        public function setDefaultDriver($name) {}

        public function getDefaultDriver()
        {
            return 'web';
        }

        public function extend($driver, \Closure $callback) {}

        public function provider($name, \Closure $callback) {}
    };

    $pending = new PendingEmailVerification(
        tokens: app(TokenRepositoryContract::class),
        cache: app('cache')->store(),
        auth: $auth
    );

    $action = new VerifyEmailLinkAction($pending, $auth);

    $result = $action->handle('10', 'any-token');

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->message)->toBe('Your email is already verified.')
        ->and($result->payload?->get('email'))->toBe('michael@example.com')
        ->and($result->payload?->get('already_verified'))->toBeTrue()
        ->and($result->payload?->get('driver'))->toBe('link')
        ->and($result->redirect?->target)->toBe('authkit.web.login');

    Event::assertNotDispatched(Verified::class);
    Event::assertNotDispatched(AuthKitEmailVerified::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('logs the user in after successful verification when configured', function () {
    Event::fake([Verified::class, AuthKitEmailVerified::class, AuthKitLoggedIn::class]);

    config()->set('authkit.email_verification.driver', 'link');
    config()->set('authkit.auth.guard', 'web');
    config()->set('authkit.email_verification.post_verify.mode', 'redirect');
    config()->set('authkit.email_verification.post_verify.redirect_route', null);
    config()->set('authkit.email_verification.post_verify.login_after_verify', true);
    config()->set('authkit.email_verification.post_verify.remember', true);
    config()->set('authkit.login.dashboard_route', 'dashboard');
    config()->set('authkit.login.redirect_route', null);
    config()->set('authkit.route_names.web.login', 'authkit.web.login');

    $user = new class extends AuthenticatableUser implements MustVerifyEmail {
        public $email = 'michael@example.com';
        public $email_verified_at = null;

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 10;
        }

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

        public function getEmailForVerification(): string
        {
            return (string) $this->email;
        }
    };

    $provider = new class($user) implements UserProvider {
        public function __construct(private Authenticatable $user) {}

        public function retrieveById($identifier)
        {
            return ((string) $identifier === '10') ? $this->user : null;
        }

        public function retrieveByToken($identifier, $token)
        {
            return null;
        }

        public function updateRememberToken(Authenticatable $user, $token): void {}

        public function retrieveByCredentials(array $credentials)
        {
            return null;
        }

        public function validateCredentials(Authenticatable $user, array $credentials): bool
        {
            return false;
        }

        public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
    };

    $guard = new class($provider) implements \Illuminate\Contracts\Auth\StatefulGuard {
        public array $loggedIn = [];

        public function __construct(private UserProvider $provider) {}

        public function check()
        {
            return false;
        }

        public function guest()
        {
            return true;
        }

        public function user()
        {
            return null;
        }

        public function id()
        {
            return null;
        }

        public function validate(array $credentials = [])
        {
            return false;
        }

        public function hasUser()
        {
            return false;
        }

        public function setUser(Authenticatable $user)
        {
            return $this;
        }

        public function getProvider()
        {
            return $this->provider;
        }

        public function attempt(array $credentials = [], $remember = false)
        {
            return false;
        }

        public function once(array $credentials = [])
        {
            return false;
        }

        public function login(Authenticatable $user, $remember = false)
        {
            $this->loggedIn[] = [
                'id' => (string) $user->getAuthIdentifier(),
                'remember' => (bool) $remember,
            ];
        }

        public function loginUsingId($id, $remember = false)
        {
            return null;
        }

        public function onceUsingId($id)
        {
            return false;
        }

        public function viaRemember()
        {
            return false;
        }

        public function logout() {}

        public function logoutCurrentDevice() {}

        public function attemptWhen(array $credentials = [], $callbacks = null, $remember = false)
        {
            return false;
        }
    };

    $auth = new class($guard) implements AuthFactory {
        public function __construct(private \Illuminate\Contracts\Auth\StatefulGuard $guard) {}

        public function guard($name = null)
        {
            return $this->guard;
        }

        public function shouldUse($name) {}

        public function setDefaultDriver($name) {}

        public function getDefaultDriver()
        {
            return 'web';
        }

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
        payload: ['user_id' => '10', 'driver' => 'link']
    );

    $action = new VerifyEmailLinkAction($pending, $auth);

    $result = $action->handle('10', $token);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('email'))->toBe('michael@example.com')
        ->and($result->payload?->get('verified'))->toBeTrue()
        ->and($result->payload?->get('logged_in'))->toBeTrue()
        ->and($result->payload?->get('driver'))->toBe('link')
        ->and($result->redirect?->target)->toBe('dashboard');

    Event::assertDispatched(Verified::class);
    Event::assertDispatched(AuthKitEmailVerified::class, function ($event) use ($user) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->driver === 'link';
    });
    Event::assertDispatched(AuthKitLoggedIn::class, function ($event) use ($user) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->guard === 'web'
            && $event->remember === true;
    });
});