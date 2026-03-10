<?php

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\Auth\TwoFactorRecoveryAction;
use Xul\AuthKit\Concerns\Model\HasAuthKitTwoFactor;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Events\AuthKitTwoFactorRecovered;
use Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorRecoveryController;
use Xul\AuthKit\Support\PendingLogin;
use Xul\AuthKit\Tests\Support\ArrayTokenRepository;

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
        $table->timestamps();
    });

    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => TestUser::class,
    ]);

    Config::set('authkit.auth.guard', 'web');

    Config::set('authkit.two_factor.enabled', true);
    Config::set('authkit.two_factor.driver', 'totp');
    Config::set('authkit.two_factor.methods', ['totp']);
    Config::set('authkit.two_factor.ttl_minutes', 10);

    Config::set('authkit.two_factor.columns.enabled', 'two_factor_enabled');
    Config::set('authkit.two_factor.columns.secret', 'two_factor_secret');
    Config::set('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
    Config::set('authkit.two_factor.columns.methods', 'two_factor_methods');

    Config::set('authkit.two_factor.security.encrypt_secret', true);
    Config::set('authkit.two_factor.security.hash_recovery_codes', true);
    Config::set('authkit.two_factor.security.recovery_hash_driver', 'bcrypt');

    Config::set('authkit.tokens.types.pending_login', [
        'length' => 64,
        'alphabet' => 'alnum',
        'uppercase' => false,
    ]);

    Config::set('authkit.route_names.web.login', 'authkit.web.login');
    Config::set('authkit.route_names.web.two_factor_challenge', 'authkit.web.twofactor.challenge');
    Config::set('authkit.route_names.api.two_factor_recovery', 'authkit.api.twofactor.recovery');

    Config::set('authkit.login.dashboard_route', 'dashboard');
    Config::set('authkit.login.redirect_route', null);

    app()->singleton(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());

    Route::middleware(['web'])->group(function () {
        Route::get('/login', fn () => 'login')->name('authkit.web.login');
        Route::get('/two-factor/challenge', fn () => 'Two-factor verification')->name('authkit.web.twofactor.challenge');
        Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');

        Route::post('/api/two-factor/recovery', TwoFactorRecoveryController::class)
            ->name('authkit.api.twofactor.recovery');
    });
});

it('action recovers and logs in, dispatching AuthKitTwoFactorRecovered and AuthKitLoggedIn', function () {
    Event::fake();

    $user = TestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $user->enableTwoFactor();
    $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');
    $user->setTwoFactorMethods(['totp']);

    $rawCodes = ['AAAAA-BBBBB', 'CCCCC-DDDDD'];
    $user->setTwoFactorRecoveryCodes($rawCodes);
    $user->save();

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: (string) $user->getAuthIdentifier(),
        remember: true,
        ttlMinutes: 5,
        methods: ['totp']
    );

    /** @var TwoFactorRecoveryAction $action */
    $action = app()->make(TwoFactorRecoveryAction::class);

    $result = $action->handle([
        'challenge' => $challenge,
        'recovery_code' => $rawCodes[0],
    ]);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('two_factor_recovered'))->toBeTrue();

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);
    expect($auth->guard('web')->check())->toBeTrue();

    Event::assertDispatched(AuthKitTwoFactorRecovered::class, function ($event) use ($user) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->guard === 'web';
    });

    Event::assertDispatched(AuthKitLoggedIn::class, function ($event) use ($user) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->guard === 'web'
            && $event->remember === true;
    });
});

it('action returns 422 for invalid recovery code and does not log in', function () {
    Event::fake();

    $user = TestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $user->enableTwoFactor();
    $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');
    $user->setTwoFactorMethods(['totp']);
    $user->setTwoFactorRecoveryCodes(['AAAAA-BBBBB']);
    $user->save();

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: (string) $user->getAuthIdentifier(),
        remember: false,
        ttlMinutes: 5,
        methods: ['totp']
    );

    /** @var TwoFactorRecoveryAction $action */
    $action = app()->make(TwoFactorRecoveryAction::class);

    $result = $action->handle([
        'challenge' => $challenge,
        'recovery_code' => 'WRONG-CODE',
    ]);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeFalse()
        ->and($result->status)->toBe(422)
        ->and($result->flow?->is('failed'))->toBeTrue()
        ->and($result->errors[0]->code)->toBe('invalid_recovery_code');

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);
    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertNotDispatched(AuthKitTwoFactorRecovered::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('action returns 410 for expired or invalid challenge', function () {
    Event::fake();

    /** @var TwoFactorRecoveryAction $action */
    $action = app()->make(TwoFactorRecoveryAction::class);

    $result = $action->handle([
        'challenge' => 'invalid-challenge-token',
        'recovery_code' => 'AAAAA-BBBBB',
    ]);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeFalse()
        ->and($result->status)->toBe(410)
        ->and($result->flow?->is('failed'))->toBeTrue()
        ->and($result->errors[0]->code)->toBe('invalid_or_expired_two_factor_challenge');

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);
    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertNotDispatched(AuthKitTwoFactorRecovered::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('controller returns JSON 200 on successful recovery', function () {
    Event::fake();

    $user = TestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $user->enableTwoFactor();
    $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');
    $user->setTwoFactorMethods(['totp']);

    $rawCodes = ['AAAAA-BBBBB'];
    $user->setTwoFactorRecoveryCodes($rawCodes);
    $user->save();

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: (string) $user->getAuthIdentifier(),
        remember: true,
        ttlMinutes: 5,
        methods: ['totp']
    );

    $apiNames = (array) config('authkit.route_names.api', []);
    $routeName = (string) ($apiNames['two_factor_recovery'] ?? 'authkit.api.twofactor.recovery');

    $this->postJson(route($routeName), [
        'challenge' => $challenge,
        'recovery_code' => $rawCodes[0],
    ])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Recovered and logged in.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.two_factor_recovered', true);
});

it('controller redirects to dashboard on successful SSR recovery', function () {
    Event::fake();

    $user = TestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $user->enableTwoFactor();
    $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');
    $user->setTwoFactorMethods(['totp']);

    $rawCodes = ['AAAAA-BBBBB'];
    $user->setTwoFactorRecoveryCodes($rawCodes);
    $user->save();

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: (string) $user->getAuthIdentifier(),
        remember: true,
        ttlMinutes: 5,
        methods: ['totp']
    );

    $apiNames = (array) config('authkit.route_names.api', []);
    $routeName = (string) ($apiNames['two_factor_recovery'] ?? 'authkit.api.twofactor.recovery');

    $this->post(route($routeName), [
        'challenge' => $challenge,
        'recovery_code' => $rawCodes[0],
    ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('status', 'Recovered and logged in.');
});

/**
 * TestUser
 *
 * Minimal user model for package tests.
 */
final class TestUser extends BaseUser
{
    use HasAuthKitTwoFactor;

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