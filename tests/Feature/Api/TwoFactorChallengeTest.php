<?php
// file: tests/Feature/Api/TwoFactorChallengeTest.php

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
use Xul\AuthKit\Actions\Auth\LoginAction;
use Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
use Xul\AuthKit\Concerns\Model\HasAuthKitTwoFactor;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Events\AuthKitTwoFactorLoggedIn;
use Xul\AuthKit\Events\AuthKitTwoFactorRequired;
use Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorChallengeController;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Tests\Support\ArrayTokenRepository;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('database.default', 'testing');
    Config::set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamp('email_verified_at')->nullable();
        $table->rememberToken();
        $table->boolean('two_factor_enabled')->default(false);
        $table->text('two_factor_secret')->nullable();
        $table->json('two_factor_recovery_codes')->nullable();
        $table->json('two_factor_methods')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->string('last_challenge_token')->nullable();
        $table->string('last_two_factor_code')->nullable();
        $table->timestamps();
    });

    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => TwoFactorChallengeTestUser::class,
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

    app()->singleton(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());

    Route::post('/authkit/twofactor/challenge', TwoFactorChallengeController::class)
        ->name('authkit.api.twofactor.challenge');

    Route::get('/authkit/login', fn () => 'login')
        ->name('authkit.web.login');

    Route::get('/authkit/twofactor', fn () => 'twofactor')
        ->name('authkit.web.twofactor.challenge');

    Route::get('/dashboard', fn () => 'dashboard')
        ->name('dashboard');
});

it('in peek strategy, completes the challenge and forgets the session challenge', function (): void {
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

    /** @var LoginAction $loginAction */
    $loginAction = app(LoginAction::class);

    $loginResult = $loginAction->handle(
        MappedPayloadBuilder::build('login', [
            'email' => 'michael@example.com',
            'password' => 'secret123',
            'remember' => true,
        ])
    );

    expect($loginResult)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($loginResult->ok)->toBeTrue()
        ->and($loginResult->flow?->is('two_factor_required'))->toBeTrue()
        ->and($loginResult->internal?->get('challenge'))->toBeString();

    $challenge = (string) $loginResult->internal?->get('challenge', '');

    $response = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->postJson(route('authkit.api.twofactor.challenge'), [
            'code' => totp_code('JBSWY3DPEHPK3PXP'),
        ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Two-factor verified.',
        ])
        ->assertJsonPath('flow.name', 'completed');

    /** @var AuthFactory $auth */
    $auth = app(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeTrue()
        ->and((string) $auth->guard('web')->id())->toBe((string) $user->getAuthIdentifier());

    expect(session()->has(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE))->toBeFalse();

    Event::assertDispatched(AuthKitTwoFactorRequired::class);
    Event::assertDispatched(AuthKitTwoFactorLoggedIn::class, function (AuthKitTwoFactorLoggedIn $event) use ($user, $challenge): bool {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->guard === 'web'
            && $event->challenge === $challenge
            && $event->remember === true;
    });
    Event::assertDispatched(AuthKitLoggedIn::class, function (AuthKitLoggedIn $event) use ($user): bool {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->guard === 'web'
            && $event->remember === true;
    });
});

it('in peek strategy, invalid code keeps the session challenge and returns 401', function (): void {
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

    /** @var LoginAction $loginAction */
    $loginAction = app(LoginAction::class);

    $loginResult = $loginAction->handle(
        MappedPayloadBuilder::build('login', [
            'email' => 'michael@example.com',
            'password' => 'secret123',
            'remember' => true,
        ])
    );

    $challenge = (string) $loginResult->internal?->get('challenge', '');

    expect($challenge)->not->toBe('');

    $response = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->postJson(route('authkit.api.twofactor.challenge'), [
            'code' => '000000',
        ]);

    $response->assertStatus(401)
        ->assertJson([
            'ok' => false,
            'status' => 401,
            'message' => 'Invalid authentication code.',
        ])
        ->assertJsonPath('flow.name', 'two_factor_required')
        ->assertJsonPath('payload.challenge', $challenge);

    expect(session(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE))->toBe($challenge);

    /** @var AuthFactory $auth */
    $auth = app(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertNotDispatched(AuthKitTwoFactorLoggedIn::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('in consume strategy, invalid code forgets the session challenge and returns 401', function (): void {
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

    /** @var LoginAction $loginAction */
    $loginAction = app(LoginAction::class);

    $loginResult = $loginAction->handle(
        MappedPayloadBuilder::build('login', [
            'email' => 'michael@example.com',
            'password' => 'secret123',
            'remember' => true,
        ])
    );

    $challenge = (string) $loginResult->internal?->get('challenge', '');

    expect($challenge)->not->toBe('');

    $response = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->postJson(route('authkit.api.twofactor.challenge'), [
            'code' => '000000',
        ]);

    $response->assertStatus(401)
        ->assertJson([
            'ok' => false,
            'status' => 401,
            'message' => 'Invalid authentication code.',
        ])
        ->assertJsonPath('flow.name', 'failed');

    expect(session()->has(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE))->toBeFalse();

    /** @var AuthFactory $auth */
    $auth = app(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertNotDispatched(AuthKitTwoFactorLoggedIn::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('returns 410 and forgets the session challenge when the challenge is missing or invalid', function (): void {
    Event::fake();

    $response = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => 'invalid-challenge-token'])
        ->postJson(route('authkit.api.twofactor.challenge'), [
            'code' => '123456',
        ]);

    $response->assertStatus(410)
        ->assertJson([
            'ok' => false,
            'status' => 410,
            'message' => 'Expired or invalid two-factor challenge.',
        ])
        ->assertJsonPath('flow.name', 'failed');

    expect(session()->has(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE))->toBeFalse();

    /** @var AuthFactory $auth */
    $auth = app(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeFalse();

    Event::assertNotDispatched(AuthKitTwoFactorLoggedIn::class);
    Event::assertNotDispatched(AuthKitLoggedIn::class);
});

it('verifies against an encrypted secret stored on the model', function (): void {
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

    /** @var LoginAction $loginAction */
    $loginAction = app(LoginAction::class);

    $loginResult = $loginAction->handle(
        MappedPayloadBuilder::build('login', [
            'email' => 'michael@example.com',
            'password' => 'secret123',
            'remember' => true,
        ])
    );

    $challenge = (string) $loginResult->internal?->get('challenge', '');

    expect($challenge)->not->toBe('');

    $response = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->postJson(route('authkit.api.twofactor.challenge'), [
            'code' => totp_code($plain),
        ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Two-factor verified.',
        ])
        ->assertJsonPath('flow.name', 'completed');

    /** @var AuthFactory $auth */
    $auth = app(AuthFactory::class);

    expect($auth->guard('web')->check())->toBeTrue();
    expect(session()->has(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE))->toBeFalse();

    Event::assertDispatched(AuthKitTwoFactorLoggedIn::class);
    Event::assertDispatched(AuthKitLoggedIn::class);
});

it('persists mapper-approved challenge attributes when the model supports mapped persistence', function (): void {
    Event::fake();

    Config::set('authkit.mappers.contexts.two_factor_challenge.class', PersistingTwoFactorChallengeMapper::class);

    $user = TwoFactorChallengeTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'email_verified_at' => now(),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
    ]);

    $plain = 'JBSWY3DPEHPK3PXP';

    $user->setTwoFactorSecret($plain);
    $user->save();

    /** @var LoginAction $loginAction */
    $loginAction = app(LoginAction::class);

    $loginResult = $loginAction->handle(
        MappedPayloadBuilder::build('login', [
            'email' => 'michael@example.com',
            'password' => 'secret123',
            'remember' => true,
        ])
    );

    $challenge = (string) $loginResult->internal?->get('challenge', '');
    $code = totp_code($plain);

    $response = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => $challenge])
        ->postJson(route('authkit.api.twofactor.challenge'), [
            'code' => $code,
        ]);

    $response->assertOk();

    $user->refresh();

    expect($user->last_challenge_token)->toBe($challenge)
        ->and($user->last_two_factor_code)->toBe($code);
});

it('returns standardized DTO validation response for JSON two-factor challenge requests', function (): void {
    $response = $this->postJson(route('authkit.api.twofactor.challenge'), []);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The given data was invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('payload.fields.challenge.0', 'The Challenge field is required.')
        ->assertJsonPath('payload.fields.code.0', 'The Authentication code field is required.');

    $errors = $response->json('errors');

    expect($errors)->toBeArray()
        ->and(count($errors))->toBe(2)
        ->and($errors[0])->toHaveKeys(['code', 'message', 'field', 'meta'])
        ->and($errors[1])->toHaveKeys(['code', 'message', 'field', 'meta']);

    expect(collect($errors)->pluck('field')->all())
        ->toContain('challenge', 'code');

    expect(collect($errors)->pluck('code')->unique()->values()->all())
        ->toBe(['validation_error']);
});

it('hydrates challenge from session before validation for JSON two-factor challenge requests', function (): void {
    $response = $this
        ->withSession([AuthKitSessionKeys::TWO_FACTOR_CHALLENGE => 'session-challenge-token'])
        ->postJson(route('authkit.api.twofactor.challenge'), []);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The given data was invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonMissingPath('payload.fields.challenge')
        ->assertJsonPath('payload.fields.code.0', 'The Authentication code field is required.');

    $errors = collect($response->json('errors'));

    expect($errors->pluck('field')->all())->toBe(['code']);
});

/**
 * Generate a TOTP code for a Base32-encoded secret.
 *
 * @param string $base32Secret
 * @return string
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
 *
 * @param string $value
 * @return string
 */
function base32_decode_bytes(string $value): string
{
    $value = strtoupper((string) preg_replace('/[^A-Z2-7]/', '', $value));

    if ($value === '') {
        return '';
    }

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bits = 0;
    $output = '';

    $length = strlen($value);

    for ($index = 0; $index < $length; $index++) {
        $charValue = strpos($alphabet, $value[$index]);

        if ($charValue === false) {
            return '';
        }

        $buffer = ($buffer << 5) | $charValue;
        $bits += 5;

        while ($bits >= 8) {
            $bits -= 8;
            $output .= chr(($buffer >> $bits) & 0xFF);
        }
    }

    return $output;
}

/**
 * TwoFactorChallengeTestUser
 *
 * Minimal user model for package tests.
 */
final class TwoFactorChallengeTestUser extends BaseUser
{
    use HasAuthKitTwoFactor;
    use HasAuthKitMappedPersistence;

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

/**
 * PersistingTwoFactorChallengeMapper
 *
 * Test-only mapper that marks challenge flow attributes as persistable.
 */
final class PersistingTwoFactorChallengeMapper extends AbstractPayloadMapper
{
    public function context(): string
    {
        return 'two_factor_challenge';
    }

    public function mode(): string
    {
        return self::MODE_MERGE;
    }

    public function definitions(): array
    {
        return [
            'challenge_persist' => [
                'source' => 'challenge',
                'target' => 'last_challenge_token',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'trim',
            ],

            'code_persist' => [
                'source' => 'code',
                'target' => 'last_two_factor_code',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'trim',
            ],
        ];
    }
}