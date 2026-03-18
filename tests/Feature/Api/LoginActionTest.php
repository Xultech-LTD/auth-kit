<?php
// file: tests/Feature/Api/LoginActionTest.php

namespace Xul\AuthKit\Tests\Feature\Api;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\Auth\LoginAction;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Events\AuthKitTwoFactorRequired;
use Xul\AuthKit\Tests\Support\ArrayTokenRepository;

app()->instance(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Config::set('database.default', 'testing');
    Config::set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->boolean('two_factor_enabled')->default(false);
        $table->text('two_factor_secret')->nullable();
        $table->json('two_factor_recovery_codes')->nullable();
        $table->json('two_factor_methods')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });

    Config::set('authkit.email_verification.enabled', true);
    Config::set('authkit.email_verification.driver', 'link');
    Config::set('authkit.email_verification.ttl_minutes', 30);
    Config::set('authkit.email_verification.columns.verified_at', 'email_verified_at');
    Config::set('authkit.route_names.web.verify_link', 'authkit.web.email.verification.verify.link');
    Config::set('authkit.route_names.web.verify_notice', 'authkit.web.email.verify.notice');
    Config::set('authkit.route_names.web.two_factor_challenge', 'authkit.web.twofactor.challenge');
    Config::set('authkit.route_names.web.login', 'authkit.web.login');

    Config::set('authkit.login.redirect_route', null);
    Config::set('authkit.login.dashboard_route', 'dashboard');

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

    Config::set('authkit.route_names.web.login', 'authkit.web.login');
    Config::set('authkit.identity.login.field', 'email');
    Config::set('authkit.identity.login.normalize', 'lower');

    Route::post('/authkit/login', \Xul\AuthKit\Http\Controllers\Api\Auth\LoginController::class)
        ->name('authkit.api.login');

    Route::get('/authkit/login', fn () => 'login')
        ->name('authkit.web.login');


    Route::get('/authkit/email/verify/link/{id}/{hash}', fn () => 'verify-link')
        ->name('authkit.web.email.verification.verify.link');

    Route::get('/authkit/email/verify/notice', fn () => 'verify-notice')
        ->name('authkit.web.email.verify.notice');

    Route::get('/authkit/two-factor/challenge', fn () => 'two-factor-challenge')
        ->name('authkit.web.twofactor.challenge');

    Route::get('/authkit/login', fn () => 'login')
        ->name('authkit.web.login');

    Route::get('/dashboard', fn () => 'dashboard')
        ->name('dashboard');

    app()->singleton(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());
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
        'attributes' => [
            'email' => 'michael@example.com',
            'password' => 'secret123',
        ],
        'options' => [
            'remember' => true,
        ],
        'meta' => [],
    ]);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('user_id'))->toBe((string) $user->getAuthIdentifier())
        ->and($result->payload?->get('remember'))->toBeTrue()
        ->and($result->internal)->toBeNull();

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeTrue();

    Event::assertDispatched(AuthKitLoggedIn::class, function (AuthKitLoggedIn $event) use ($user) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->guard === 'web'
            && $event->remember === true;
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
        'attributes' => [
            'email' => 'michael@example.com',
            'password' => 'secret123',
        ],
        'options' => [
            'remember' => false,
        ],
        'meta' => [],
    ]);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('two_factor_required'))->toBeTrue()
        ->and($result->payload?->get('methods'))->toBe(['totp'])
        ->and($result->payload?->get('remember'))->toBeFalse()
        ->and($result->internal)->not->toBeNull();

    $internalChallenge = (string) $result->internal?->get('challenge', '');

    expect($internalChallenge)->not->toBeEmpty()
        ->and(strlen($internalChallenge))->toBeGreaterThan(10);

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertDispatched(AuthKitTwoFactorRequired::class, function (AuthKitTwoFactorRequired $event) use ($user, $internalChallenge) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->guard === 'web'
            && $event->challenge === $internalChallenge
            && $event->methods === ['totp']
            && $event->remember === false;
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
        'attributes' => [
            'email' => 'michael@example.com',
            'password' => 'wrong-password',
        ],
        'options' => [],
        'meta' => [],
    ]);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeFalse()
        ->and($result->status)->toBe(401)
        ->and($result->flow?->is('failed'))->toBeTrue()
        ->and($result->internal)->toBeNull()
        ->and($result->hasErrors())->toBeTrue()
        ->and($result->errors[0]->code)->toBe('invalid_credentials');

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
it('returns 201 and dispatches AuthKitEmailVerificationRequired when email verification is required', function () {
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
        'attributes' => [
            'email' => 'michael@example.com',
            'password' => 'secret123',
        ],
        'options' => [
            'remember' => true,
        ],
        'meta' => [],
    ]);
    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeFalse()
        ->and($result->status)->toBe(201)
        ->and($result->flow?->is('email_verification_required'))->toBeTrue()
        ->and($result->payload?->get('email'))->toBe('michael@example.com')
        ->and($result->payload?->get('driver'))->toBe('link')
        ->and($result->redirect)->not->toBeNull()
        ->and($result->redirect?->target)->toBe('authkit.web.email.verify.notice')
        ->and($result->redirect?->parameters)->toBe(['email' => 'michael@example.com'])
        ->and($result->internal)->toBeNull();

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertDispatched(AuthKitEmailVerificationRequired::class, function (AuthKitEmailVerificationRequired $event) use ($user) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->email === 'michael@example.com'
            && $event->driver === 'link'
            && $event->ttlMinutes === 30
            && is_string($event->token)
            && $event->token !== ''
            && is_string($event->url)
            && $event->url !== '';
    });

    Event::assertNotDispatched(AuthKitTwoFactorRequired::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('returns standardized DTO validation response for JSON login requests', function () {
    $response = $this
        ->postJson(route('authkit.api.login'), []);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The given data was invalid.',
            'flow' => [
                'name' => 'failed',
            ],
        ])
        ->assertJsonPath('payload.fields.email.0', 'The Email field is required.')
        ->assertJsonPath('payload.fields.password.0', 'The Password field is required.');

    $errors = $response->json('errors');

    expect($errors)->toBeArray()
        ->and(count($errors))->toBe(2)
        ->and($errors[0])->toHaveKeys(['code', 'message', 'field', 'meta'])
        ->and($errors[1])->toHaveKeys(['code', 'message', 'field', 'meta']);

    expect(collect($errors)->pluck('field')->all())
        ->toContain('email', 'password');

    expect(collect($errors)->pluck('code')->unique()->values()->all())
        ->toBe(['validation_error']);
});

it('normalizes email before validation in JSON login requests', function () {
    $response = $this->postJson(route('authkit.api.login'), [
        'email' => '  MICHAEL@EXAMPLE.COM  ',
        'password' => '',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('payload.fields.password.0', 'The Password field is required.')
        ->assertJsonMissingPath('payload.fields.email');
});

it('remains successful when the login model supports mapped persistence even though default login fields are non-persistable', function () {
    Event::fake();

    Config::set('authkit.two_factor.enabled', false);

    $user = \Xul\AuthKit\Tests\Feature\Api\LoginActionTest::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'email_verified_at' => now(),
    ]);

    /** @var LoginAction $action */
    $action = app()->make(LoginAction::class);

    $result = $action->handle([
        'attributes' => [
            'email' => 'michael@example.com',
            'password' => 'secret123',
        ],
        'options' => [
            'remember' => false,
        ],
        'meta' => [],
    ]);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue();

    $user->refresh();

    expect($user->email)->toBe('michael@example.com');

    Event::assertDispatched(AuthKitLoggedIn::class);
});

/**
 * TestUser
 *
 * Minimal user model for package tests.
 */
final class LoginActionTest extends BaseUser
{
    use \Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
    /**
     * @var string
     */
    protected $table = 'users';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @var array<int, string>
     */
    protected $hidden = ['password'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'two_factor_enabled' => 'bool',
        'two_factor_recovery_codes' => 'array',
        'two_factor_methods' => 'array',
    ];
}