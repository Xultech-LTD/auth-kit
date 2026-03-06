<?php
//file: tests/Feature/Api/LoginActionTest

namespace Xul\AuthKit\Tests\Feature\Api;

use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\Auth\LoginAction;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Events\AuthKitTwoFactorRequired;
use Xul\AuthKit\Tests\Support\ArrayTokenRepository;
use Illuminate\Support\Facades\Route;

app()->bind(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Config::set('database.default', 'testing');
    Config::set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('email')->unique();
        $t->string('password');
        $t->rememberToken();
        $t->boolean('two_factor_enabled')->default(false);
        $t->text('two_factor_secret')->nullable();
        $t->json('two_factor_recovery_codes')->nullable();
        $t->json('two_factor_methods')->nullable();
        $t->timestamp('email_verified_at')->nullable();
        $t->timestamps();
    });

    Config::set('authkit.email_verification.enabled', true);
    Config::set('authkit.email_verification.driver', 'link');
    Config::set('authkit.email_verification.ttl_minutes', 30);
    Config::set('authkit.email_verification.columns.verified_at', 'email_verified_at');
    Config::set('authkit.route_names.web.verify_link', 'authkit.web.email.verification.verify.link');

    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => \Xul\AuthKit\Tests\Feature\Api\LoginActionTest::class,
    ]);

    Config::set('authkit.auth.guard', 'web');
    Config::set('authkit.identity.login.field', 'email');

    Config::set('authkit.tokens.types.pending_login', [
        'length' => 64,
        'alphabet' => 'alnum',
        'uppercase' => false,
    ]);

    Config::set('authkit.two_factor.enabled', true);
    Config::set('authkit.two_factor.driver', 'totp');
    Config::set('authkit.two_factor.methods', ['totp']);
    Config::set('authkit.two_factor.ttl_minutes', 10);
    Config::set('authkit.two_factor.columns.enabled', 'two_factor_enabled');
    Config::set('authkit.two_factor.columns.secret', 'two_factor_secret');
    Config::set('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
    Config::set('authkit.two_factor.columns.methods', 'two_factor_methods');

    Route::get('/authkit/email/verify/link/{id}/{hash}', fn () => 'verify-link')
        ->name('authkit.web.email.verification.verify.link');

    app()->bind(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());

});

/**
 * LoginActionNoTwoFactorTest
 *
 * Ensures a user is authenticated and AuthKitLoggedIn is dispatched
 * when two-factor is globally disabled.
 */
it('logs in and dispatches AuthKitLoggedIn when two-factor is globally disabled', function () {
    Event::fake();

    Config::set('authkit.two_factor.enabled', false);

    $user = \Xul\AuthKit\Tests\Feature\Api\LoginActionTest::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'email_verified_at' => now(),
    ]);

    /** @var LoginAction $action */
    $action = app()->make(LoginAction::class);

    $result = $action->handle([
        'email' => 'michael@example.com',
        'password' => 'secret123',
        'remember' => true,
    ]);

    expect($result)
        ->and((bool) ($result['ok'] ?? false))->toBeTrue()
        ->and((bool) ($result['two_factor_required'] ?? false))->toBeFalse()
        ->and((string) ($result['user_id'] ?? ''))->toBe((string) $user->getAuthIdentifier())
        ->and(array_key_exists('internal_challenge', $result))->toBeFalse()
        ->and(array_key_exists('challenge', $result))->toBeFalse();

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeTrue();

    Event::assertDispatched(AuthKitLoggedIn::class, function (AuthKitLoggedIn $e) use ($user) {
        return (string) $e->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $e->guard === 'web'
            && $e->remember === true;
    });

    Event::assertNotDispatched(AuthKitTwoFactorRequired::class);
});

/**
 * LoginActionRequiresTwoFactorTest
 *
 * Ensures a pending login challenge is created and AuthKitTwoFactorRequired is dispatched
 * when two-factor is enabled and the user has it enabled.
 */
it('creates a pending login challenge and dispatches AuthKitTwoFactorRequired when two-factor is enabled and user has it enabled', function () {
    Event::fake();

    $user = \Xul\AuthKit\Tests\Feature\Api\LoginActionTest::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'email_verified_at' => now(),
        'two_factor_enabled' => true,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_methods' => ['totp'],
    ]);

    /** @var LoginAction $action */
    $action = app()->make(LoginAction::class);

    $result = $action->handle([
        'email' => 'michael@example.com',
        'password' => 'secret123',
        'remember' => false,
    ]);

    expect($result)
        ->and((bool) ($result['ok'] ?? false))->toBeTrue()
        ->and((bool) ($result['two_factor_required'] ?? false))->toBeTrue()
        ->and($result['methods'] ?? null)->toBe(['totp'])
        ->and(array_key_exists('challenge', $result))->toBeFalse()
        ->and((string) ($result['internal_challenge'] ?? ''))->toBeString()->not->toBeEmpty();

    $internalChallenge = (string) ($result['internal_challenge'] ?? '');

    expect(strlen($internalChallenge))->toBeGreaterThan(10);

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertDispatched(AuthKitTwoFactorRequired::class, function (AuthKitTwoFactorRequired $e) use ($user, $internalChallenge) {
        return (string) $e->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $e->guard === 'web'
            && $e->challenge === $internalChallenge
            && $e->methods === ['totp']
            && $e->remember === false;
    });

    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

/**
 * LoginActionInvalidCredentialsTest
 *
 * Ensures invalid credentials return 401 and no events are dispatched.
 */
it('returns 401 when credentials are invalid', function () {
    Event::fake();

    \Xul\AuthKit\Tests\Feature\Api\LoginActionTest::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => false,
        'email_verified_at' => now(),
    ]);

    /** @var LoginAction $action */
    $action = app()->make(LoginAction::class);

    $result = $action->handle([
        'email' => 'michael@example.com',
        'password' => 'wrong-password',
    ]);

    expect($result)
        ->and((bool) ($result['ok'] ?? true))->toBeFalse()
        ->and((int) ($result['status'] ?? 0))->toBe(401)
        ->and(array_key_exists('internal_challenge', $result))->toBeFalse()
        ->and(array_key_exists('challenge', $result))->toBeFalse();

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertNotDispatched(AuthKitLoggedIn::class);
    Event::assertNotDispatched(AuthKitTwoFactorRequired::class);
});

/**
 * LoginActionEmailVerificationRequiredTest
 *
 * Ensures an unverified user is not logged in, does not enter two-factor flow,
 * and dispatches AuthKitEmailVerificationRequired.
 */
it('returns 403 and dispatches AuthKitEmailVerificationRequired when email verification is required', function () {
    Event::fake();

    Config::set('authkit.two_factor.enabled', true);
    Config::set('authkit.email_verification.enabled', true);
    Config::set('authkit.email_verification.driver', 'link');

    $user = \Xul\AuthKit\Tests\Feature\Api\LoginActionTest::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'email_verified_at' => null,
        'two_factor_enabled' => true,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_methods' => ['totp'],
    ]);

    /** @var LoginAction $action */
    $action = app()->make(LoginAction::class);

    $result = $action->handle([
        'email' => 'michael@example.com',
        'password' => 'secret123',
        'remember' => true,
    ]);

    expect($result)
        ->and((bool) ($result['ok'] ?? true))->toBeFalse()
        ->and((int) ($result['status'] ?? 0))->toBe(403)
        ->and((bool) ($result['email_verification_required'] ?? false))->toBeTrue()
        ->and($result['redirect_params'] ?? [])->toBe(['email' => 'michael@example.com'])
        ->and(array_key_exists('two_factor_required', $result))->toBeFalse()
        ->and(array_key_exists('internal_challenge', $result))->toBeFalse()
        ->and(array_key_exists('challenge', $result))->toBeFalse();

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertDispatched(AuthKitEmailVerificationRequired::class, function (AuthKitEmailVerificationRequired $e) use ($user) {
        return (string) $e->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $e->email === 'michael@example.com'
            && $e->driver === 'link'
            && $e->ttlMinutes === 30
            && is_string($e->token)
            && $e->token !== ''
            && is_string($e->url)
            && $e->url !== '';
    });

    Event::assertNotDispatched(AuthKitTwoFactorRequired::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

/**
 * TestUser
 *
 * Minimal user model for package tests.
 */
final class LoginActionTest extends BaseUser
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = [
        'two_factor_enabled' => 'bool',
        'two_factor_recovery_codes' => 'array',
        'two_factor_methods' => 'array',
    ];
}