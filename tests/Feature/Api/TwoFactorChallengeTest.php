<?php
//file: tests/Feature/Api/TwoFactorChallengeTest

namespace Xul\AuthKit\Tests\Feature\Api;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Concerns\Model\HasAuthKitTwoFactor;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Events\AuthKitTwoFactorLoggedIn;
use Xul\AuthKit\Events\AuthKitTwoFactorRequired;
use Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorChallengeController;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Tests\Support\ArrayTokenRepository;

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
        $t->timestamp('email_verified_at')->nullable();
        $t->rememberToken();
        $t->boolean('two_factor_enabled')->default(false);
        $t->text('two_factor_secret')->nullable();
        $t->json('two_factor_recovery_codes')->nullable();
        $t->json('two_factor_methods')->nullable();
        $t->timestamp('two_factor_confirmed_at')->nullable();
        $t->timestamps();
    });

    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => \Xul\AuthKit\Tests\Feature\Api\TwoFactorChallengeTestUser::class,
    ]);

    Config::set('authkit.auth.guard', 'web');

    Config::set('authkit.identity.login.field', 'email');
    Config::set('authkit.identity.login.normalize', 'lower');

    Config::set('authkit.route_names.web.login', 'authkit.web.login');
    Config::set('authkit.route_names.web.two_factor_challenge', 'authkit.web.twofactor.challenge');
    Config::set('authkit.route_names.api.two_factor_challenge', 'authkit.api.twofactor.challenge');

    Config::set('authkit.login.redirect_route', null);
    Config::set('authkit.login.dashboard_route', 'dashboard');

    Config::set('authkit.tokens.types.pending_login', [
        'length' => 64,
        'alphabet' => 'alnum',
        'uppercase' => false,
    ]);

    Config::set('authkit.two_factor.enabled', true);
    Config::set('authkit.two_factor.driver', 'totp');
    Config::set('authkit.two_factor.methods', ['totp']);
    Config::set('authkit.two_factor.ttl_minutes', 10);
    Config::set('authkit.two_factor.challenge_strategy', 'peek');

    Config::set('authkit.two_factor.columns.enabled', 'two_factor_enabled');
    Config::set('authkit.two_factor.columns.secret', 'two_factor_secret');
    Config::set('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
    Config::set('authkit.two_factor.columns.methods', 'two_factor_methods');
    Config::set('authkit.two_factor.columns.confirmed_at', 'two_factor_confirmed_at');

    Config::set('authkit.two_factor.security.encrypt_secret', true);

    Config::set('authkit.two_factor.totp.digits', 6);
    Config::set('authkit.two_factor.totp.period', 30);
    Config::set('authkit.two_factor.totp.window', 1);
    Config::set('authkit.two_factor.totp.algo', 'sha1');

    // Bind token repo for pending_login
    app()->bind(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());

    Route::post('/authkit/twofactor/challenge', TwoFactorChallengeController::class)
        ->name('authkit.api.twofactor.challenge');

    Route::get('/authkit/login', fn () => 'login')->name('authkit.web.login');
    Route::get('/authkit/twofactor', fn () => 'twofactor')->name('authkit.web.twofactor.challenge');
    Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
});

it('in peek strategy, completes the challenge and forgets the session challenge', function () {
    Event::fake();
    Config::set('authkit.two_factor.challenge_strategy', 'peek');

    $user = TwoFactorChallengeTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'email_verified_at' => now(),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
    ]);

    $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');
    $user->save();

    $loginResult = app()->make(\Xul\AuthKit\Actions\Auth\LoginAction::class)->handle([
        'email' => 'michael@example.com',
        'password' => 'secret123',
        'remember' => true,
    ]);

    expect($loginResult['ok'])->toBeTrue()
        ->and($loginResult['two_factor_required'])->toBeTrue()
        ->and($loginResult['internal_challenge'])->toBeString();

    $challenge = (string) $loginResult['internal_challenge'];

    $res = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->postJson(route('authkit.api.twofactor.challenge'), [
            'code' => totp_code('JBSWY3DPEHPK3PXP'),
        ]);

    $res->assertOk()
        ->assertJson([
            'ok' => true,
            'two_factor_required' => false,
        ]);

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeTrue()
        ->and((string) $auth->guard('web')->id())->toBe((string) $user->getAuthIdentifier());

    $store = app('session.store');
    expect($store->has(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE))->toBeFalse();

    Event::assertDispatched(AuthKitTwoFactorRequired::class);
    Event::assertDispatched(AuthKitTwoFactorLoggedIn::class, function ($e) use ($user, $challenge) {
        return (string) $e->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $e->guard === 'web'
            && $e->challenge === $challenge
            && $e->remember === true;
    });
    Event::assertDispatched(AuthKitLoggedIn::class, function ($e) use ($user) {
        return (string) $e->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $e->guard === 'web'
            && $e->remember === true;
    });
});

it('in peek strategy, invalid code keeps the session challenge and returns 401', function () {
    Event::fake();
    Config::set('authkit.two_factor.challenge_strategy', 'peek');

    $user = TwoFactorChallengeTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'email_verified_at' => now(),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
    ]);

    $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');
    $user->save();

    $loginResult = app()->make(\Xul\AuthKit\Actions\Auth\LoginAction::class)->handle([
        'email' => 'michael@example.com',
        'password' => 'secret123',
        'remember' => false,
    ]);

    $challenge = (string) ($loginResult['internal_challenge'] ?? '');
    expect($challenge)->not->toBe('');

    $res = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->postJson(route('authkit.api.twofactor.challenge'), [
            'code' => '000000',
        ]);

    $res->assertStatus(401)
        ->assertJson([
            'ok' => false,
            'two_factor_required' => true,
        ]);

    $store = app('session.store');
    expect($store->get(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE))->toBe($challenge);

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);
    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertNotDispatched(AuthKitTwoFactorLoggedIn::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('in consume strategy, invalid code forgets the session challenge and returns 401', function () {
    Event::fake();
    Config::set('authkit.two_factor.challenge_strategy', 'consume');

    $user = TwoFactorChallengeTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'email_verified_at' => now(),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
    ]);

    $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');
    $user->save();

    $loginResult = app()->make(\Xul\AuthKit\Actions\Auth\LoginAction::class)->handle([
        'email' => 'michael@example.com',
        'password' => 'secret123',
        'remember' => false,
    ]);

    $challenge = (string) ($loginResult['internal_challenge'] ?? '');
    expect($challenge)->not->toBe('');

    $res = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->postJson(route('authkit.api.twofactor.challenge'), [
            'code' => '000000',
        ]);

    $res->assertStatus(401)->assertJson(['ok' => false]);

    $store = app('session.store');
    expect($store->has(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE))->toBeFalse();

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);
    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertNotDispatched(AuthKitTwoFactorLoggedIn::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('returns 410 and forgets the session challenge when the challenge is missing/invalid', function () {
    Event::fake();

    $res = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => 'invalid-challenge-token'])
        ->postJson(route('authkit.api.twofactor.challenge'), [
            'code' => '123456',
        ]);

    $res->assertStatus(410)->assertJson(['ok' => false]);

    $store = app('session.store');
    expect($store->has(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE))->toBeFalse();

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);
    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertNotDispatched(AuthKitTwoFactorLoggedIn::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('verifies against an encrypted secret stored on the model', function () {
    Event::fake();

    Config::set('authkit.two_factor.challenge_strategy', 'peek');
    Config::set('authkit.two_factor.security.encrypt_secret', true);

    $plain = 'JBSWY3DPEHPK3PXP';

    $user = TwoFactorChallengeTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'email_verified_at' => now(),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
        'two_factor_secret' => Crypt::encryptString($plain),
    ]);

    $loginResult = app()->make(\Xul\AuthKit\Actions\Auth\LoginAction::class)->handle([
        'email' => 'michael@example.com',
        'password' => 'secret123',
        'remember' => false,
    ]);

    $challenge = (string) ($loginResult['internal_challenge'] ?? '');
    expect($challenge)->not->toBe('');

    $res = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->postJson(route('authkit.api.twofactor.challenge'), [
            'code' => totp_code($plain),
        ]);

    $res->assertOk()->assertJson(['ok' => true]);

    /** @var AuthFactory $auth */
    $auth = app()->make(AuthFactory::class);
    expect($auth->guard('web')->check())->toBeTrue();

    $store = app('session.store');
    expect($store->has(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE))->toBeFalse();

    Event::assertDispatched(AuthKitTwoFactorLoggedIn::class);
    Event::assertDispatched(AuthKitLoggedIn::class);
});

/**
 * Generate a TOTP code for a Base32-encoded secret.
 */
function totp_code(string $base32Secret): string
{
    $digits = (int) config('authkit.two_factor.totp.digits', 6);
    $period = (int) config('authkit.two_factor.totp.period', 30);
    $algo = (string) config('authkit.two_factor.totp.algo', 'sha1');

    $period = max(1, $period);
    $digits = max(1, $digits);

    $step = (int) floor(time() / $period);

    $secret = base32_decode_bytes($base32Secret);

    $binCounter = pack('N*', 0) . pack('N*', $step);

    $hash = hash_hmac($algo, $binCounter, $secret, true);

    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

    $part = substr($hash, $offset, 4);
    $value = unpack('N', $part)[1] & 0x7FFFFFFF;

    $mod = 10 ** $digits;

    return str_pad((string) ($value % $mod), $digits, '0', STR_PAD_LEFT);
}

/**
 * Decode RFC 4648 Base32 secret into raw bytes.
 */
function base32_decode_bytes(string $s): string
{
    $s = strtoupper((string) preg_replace('/[^A-Z2-7]/', '', $s));

    if ($s === '') {
        return '';
    }

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bits = 0;
    $out = '';

    $len = strlen($s);

    for ($i = 0; $i < $len; $i++) {
        $val = strpos($alphabet, $s[$i]);

        if ($val === false) {
            return '';
        }

        $buffer = ($buffer << 5) | $val;
        $bits += 5;

        while ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($buffer >> $bits) & 0xFF);
        }
    }

    return $out;
}

/**
 * TestUser
 *
 * Minimal user model for package tests.
 */
final class TwoFactorChallengeTestUser extends BaseUser
{
    use HasAuthKitTwoFactor;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = [
        'two_factor_enabled' => 'bool',
        'two_factor_recovery_codes' => 'array',
        'two_factor_methods' => 'array',
    ];
}