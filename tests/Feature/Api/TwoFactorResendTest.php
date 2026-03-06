<?php

namespace Xul\AuthKit\Tests\Feature\Api;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Xul\AuthKit\Contracts\TwoFactorDriverContract;
use Xul\AuthKit\Contracts\TwoFactorResendableContract;
use Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorResendController;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\PendingLogin;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('database.default', 'testing');
    Config::set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name')->nullable();
        $t->string('email')->unique();
        $t->string('password');
        $t->rememberToken();
        $t->boolean('two_factor_enabled')->default(false);
        $t->text('two_factor_secret')->nullable();
        $t->json('two_factor_recovery_codes')->nullable();
        $t->json('two_factor_methods')->nullable();
        $t->timestamps();
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

    Config::set('authkit.identity.login.field', 'email');
    Config::set('authkit.identity.login.normalize', 'lower');

    Config::set('authkit.two_factor.enabled', true);
    Config::set('authkit.two_factor.driver', 'totp');
    Config::set('authkit.two_factor.methods', ['totp']);
    Config::set('authkit.two_factor.ttl_minutes', 10);

    Config::set('authkit.two_factor.columns.enabled', 'two_factor_enabled');
    Config::set('authkit.two_factor.columns.secret', 'two_factor_secret');
    Config::set('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
    Config::set('authkit.two_factor.columns.methods', 'two_factor_methods');

    Config::set('authkit.route_names.api.two_factor_resend', 'authkit.api.twofactor.resend');

    Route::post('/authkit/twofactor/resend', TwoFactorResendController::class)
        ->name('authkit.api.twofactor.resend');
});

it('returns 409 when resend is not supported by the active driver (totp)', function () {
    $apiNames = (array) config('authkit.route_names.api', []);
    $routeName = (string) ($apiNames['two_factor_resend'] ?? 'authkit.api.twofactor.resend');

    $user = createTwoFactorEnabledUser();

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: (string) $user->getAuthIdentifier(),
        remember: true,
        ttlMinutes: 5,
        methods: ['totp']
    );

    $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->postJson(route($routeName), [
            'email' => $user->email,
        ])
        ->assertStatus(409)
        ->assertJson([
            'ok' => false,
        ]);
});

it('returns 200 when resend is supported by the active driver', function () {
    $apiNames = (array) config('authkit.route_names.api', []);
    $routeName = (string) ($apiNames['two_factor_resend'] ?? 'authkit.api.twofactor.resend');

    config()->set('authkit.two_factor.driver', 'fake_resend');
    config()->set('authkit.two_factor.drivers.fake_resend', FakeResendTwoFactorDriver::class);
    config()->set('authkit.two_factor.methods', ['fake']);

    $user = createTwoFactorEnabledUser();

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: (string) $user->getAuthIdentifier(),
        remember: true,
        ttlMinutes: 5,
        methods: ['fake']
    );

    $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->postJson(route($routeName), [
            'email' => $user->email,
        ])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'driver' => 'fake_resend',
        ]);
});

/**
 * Create a minimal user record with two-factor enabled.
 *
 * @return TestUser
 */
function createTwoFactorEnabledUser(): TestUser
{
    $enabledCol = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');

    /** @var TestUser $user */
    $user = TestUser::query()->create([
        'name' => 'Test User',
        'email' => 'user' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        $enabledCol => true,
    ]);

    return $user;
}

/**
 * TestUser
 *
 * Minimal user model for this test file.
 */
final class TestUser extends BaseUser
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'two_factor_enabled' => 'bool',
        'two_factor_recovery_codes' => 'array',
        'two_factor_methods' => 'array',
    ];
}

/**
 * FakeResendTwoFactorDriver
 *
 * Test-only driver that supports resend for the pending login context.
 *
 * @final
 */
final class FakeResendTwoFactorDriver implements TwoFactorDriverContract, TwoFactorResendableContract
{
    public function key(): string
    {
        return 'fake_resend';
    }

    public function methods(object $user): array
    {
        return ['fake'];
    }

    public function enabled(object $user): bool
    {
        if (method_exists($user, 'hasTwoFactorEnabled')) {
            return (bool) $user->hasTwoFactorEnabled();
        }

        $col = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');

        return (bool) data_get($user, $col, false);
    }

    public function verify(object $user, string $code): bool
    {
        return false;
    }

    public function verifyRecoveryCode(object $user, string $recoveryCode): bool
    {
        return false;
    }

    public function consumeRecoveryCode(object $user, string $recoveryCode): bool
    {
        return false;
    }

    public function resend(Authenticatable $user, array $context = []): array
    {
        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Challenge resent.',
        ];
    }

    public function generateRecoveryCodes(int $count = 8, int $length = 10): array
    {
        $count = max(1, $count);
        $length = max(4, $length);

        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $out[] = Str::lower(Str::random($length));
        }

        return $out;
    }
}