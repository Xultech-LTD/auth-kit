<?php
// file: tests/Feature/Api/App/Confirmations/ConfirmTwoFactorControllerTest.php

namespace Xul\AuthKit\Tests\Feature\Api\App\Confirmations;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\App\Confirmations\ConfirmTwoFactorAction;
use Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
use Xul\AuthKit\Concerns\Model\HasAuthKitTwoFactor;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Controllers\Api\App\Confirmations\ConfirmTwoFactorController;
use Xul\AuthKit\Http\Middleware\Authenticate;
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
        $table->rememberToken();
        $table->boolean('two_factor_enabled')->default(false);
        $table->text('two_factor_secret')->nullable();
        $table->json('two_factor_recovery_codes')->nullable();
        $table->json('two_factor_methods')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->string('last_confirm_two_factor_code')->nullable();
        $table->timestamps();
    });

    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => ConfirmTwoFactorTestUser::class,
    ]);

    Config::set('authkit.auth.guard', 'web');

    Config::set('authkit.confirmations.enabled', true);
    Config::set('authkit.confirmations.two_factor.enabled', true);
    Config::set('authkit.confirmations.session.two_factor_key', 'authkit.confirmed.two_factor_at');
    Config::set('authkit.confirmations.session.intended_key', 'authkit.confirmation.intended');
    Config::set('authkit.confirmations.session.type_key', 'authkit.confirmation.type');
    Config::set('authkit.confirmations.routes.fallback', 'authkit.web.dashboard');

    Config::set('authkit.route_names.web.confirm_two_factor', 'authkit.web.confirm.two_factor');
    Config::set('authkit.route_names.web.two_factor_settings', 'authkit.web.settings.two_factor');
    Config::set('authkit.route_names.web.dashboard_web', 'authkit.web.dashboard');
    Config::set('authkit.route_names.api.confirm_two_factor', 'authkit.api.confirm.two_factor');

    Config::set('authkit.two_factor.enabled', true);
    Config::set('authkit.two_factor.driver', 'totp');
    Config::set('authkit.two_factor.methods', ['totp']);
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

    Route::middleware(['web', Authenticate::class])->group(function (): void {
        Route::post('/authkit/confirm/two-factor', ConfirmTwoFactorController::class)
            ->name('authkit.api.confirm.two_factor');
    });

    Route::get('/authkit/confirm/two-factor', fn () => 'confirm-two-factor')
        ->middleware(['web'])
        ->name('authkit.web.confirm.two_factor');

    Route::get('/authkit/settings/two-factor', fn () => 'two-factor-settings')
        ->middleware(['web'])
        ->name('authkit.web.settings.two_factor');

    Route::get('/dashboard', fn () => 'dashboard')
        ->middleware(['web'])
        ->name('authkit.web.dashboard');
});

it('confirms two-factor successfully and stores a fresh confirmation timestamp', function (): void {
    $user = ConfirmTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
    ]);

    $plain = 'JBSWY3DPEHPK3PXP';
    $user->setTwoFactorSecret($plain);
    $user->save();

    $response = $this
        ->actingAs($user, 'web')
        ->withSession([
            'authkit.confirmation.intended' => route('authkit.web.dashboard'),
            'authkit.confirmation.type' => 'two_factor',
        ])
        ->postJson(route('authkit.api.confirm.two_factor'), [
            'code' => totp_code($plain),
        ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Two-factor authentication confirmed successfully.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.confirmed', true)
        ->assertJsonPath('payload.type', 'two_factor')
        ->assertJsonPath('payload.driver', 'totp');

    expect(session('authkit.confirmed.two_factor_at'))->toBeInt()
        ->and(session()->has('authkit.confirmation.intended'))->toBeFalse()
        ->and(session()->has('authkit.confirmation.type'))->toBeFalse();
});

it('returns 422 when the provided two-factor code is invalid', function (): void {
    $user = ConfirmTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
    ]);

    $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');
    $user->save();

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.confirm.two_factor'), [
            'code' => '000000',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The provided authentication code is invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'invalid_two_factor_code');

    expect(session()->has('authkit.confirmed.two_factor_at'))->toBeFalse();
});

it('returns 409 when two-factor is not enabled for the current user', function (): void {
    $user = ConfirmTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => false,
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.confirm.two_factor'), [
            'code' => '123456',
        ]);

    $response->assertStatus(409)
        ->assertJson([
            'ok' => false,
            'status' => 409,
            'message' => 'Two-factor authentication is not enabled for this account.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'two_factor_not_enabled')
        ->assertJsonPath('redirect.target', 'authkit.web.settings.two_factor');
});

it('returns a standardized action result for valid two-factor confirmation', function (): void {
    $user = ConfirmTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
    ]);

    $plain = 'JBSWY3DPEHPK3PXP';
    $user->setTwoFactorSecret($plain);
    $user->save();

    /** @var ConfirmTwoFactorAction $action */
    $action = app(ConfirmTwoFactorAction::class);

    $result = $action->handle(
        user: $user,
        data: MappedPayloadBuilder::build('confirm_two_factor', [
            'code' => totp_code($plain),
        ]),
        session: app('session.store'),
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('confirmed'))->toBeTrue()
        ->and($result->payload?->get('type'))->toBe('two_factor')
        ->and($result->payload?->get('driver'))->toBe('totp');
});

it('persists mapper-approved confirmation attributes when the model supports mapped persistence', function (): void {
    Config::set('authkit.mappers.contexts.confirm_two_factor.class', PersistingConfirmTwoFactorMapper::class);

    $user = ConfirmTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
    ]);

    $plain = 'JBSWY3DPEHPK3PXP';
    $code = totp_code($plain);

    $user->setTwoFactorSecret($plain);
    $user->save();

    /** @var ConfirmTwoFactorAction $action */
    $action = app(ConfirmTwoFactorAction::class);

    $result = $action->handle(
        user: $user,
        data: MappedPayloadBuilder::build('confirm_two_factor', [
            'code' => '  ' . $code . '  ',
        ]),
        session: app('session.store'),
    );

    expect($result->ok)->toBeTrue();

    $user->refresh();

    expect($user->last_confirm_two_factor_code)->toBe($code);
});

it('returns standardized DTO validation response for JSON confirm two-factor requests', function (): void {
    $user = ConfirmTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.confirm.two_factor'), []);

    $response->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The given data was invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('payload.fields.code.0', 'The Authentication code field is required.');

    $errors = $response->json('errors');

    expect($errors)->toBeArray()
        ->and(count($errors))->toBe(1)
        ->and($errors[0])->toHaveKeys(['code', 'message', 'field', 'meta'])
        ->and($errors[0]['field'])->toBe('code')
        ->and($errors[0]['code'])->toBe('validation_error');
});

it('redirects to intended url for web confirmation flow after success', function (): void {
    $user = ConfirmTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
    ]);

    $plain = 'JBSWY3DPEHPK3PXP';

    $user->setTwoFactorSecret($plain);
    $user->save();

    $response = $this
        ->actingAs($user, 'web')
        ->withSession([
            'authkit.confirmation.intended' => route('authkit.web.dashboard'),
            'authkit.confirmation.type' => 'two_factor',
        ])
        ->post(route('authkit.api.confirm.two_factor'), [
            'code' => totp_code($plain),
        ]);

    $response->assertRedirect(route('authkit.web.dashboard'))
        ->assertSessionHas('status', 'Two-factor authentication confirmed successfully.');
});

/**
 * Generate a TOTP code for a Base32-encoded secret.
 *
 * @param  string  $base32Secret
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
 * @param  string  $value
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
 * ConfirmTwoFactorTestUser
 *
 * Minimal user model for package tests.
 */
final class ConfirmTwoFactorTestUser extends BaseUser
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
    protected $hidden = ['password', 'remember_token'];

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
 * PersistingConfirmTwoFactorMapper
 *
 * Test-only mapper that marks confirm-two-factor attributes as persistable.
 */
final class PersistingConfirmTwoFactorMapper extends AbstractPayloadMapper
{
    public function context(): string
    {
        return 'confirm_two_factor';
    }

    public function mode(): string
    {
        return self::MODE_MERGE;
    }

    public function definitions(): array
    {
        return [
            'code_persist' => [
                'source' => 'code',
                'target' => 'last_confirm_two_factor_code',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'trim',
            ],
        ];
    }
}