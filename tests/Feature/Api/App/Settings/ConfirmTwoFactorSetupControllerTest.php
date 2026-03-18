<?php
// file: tests/Feature/Api/App/Settings/ConfirmTwoFactorSetupControllerTest.php

namespace Xul\AuthKit\Tests\Feature\Api\App\Settings;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\App\Settings\ConfirmTwoFactorSetupAction;
use Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
use Xul\AuthKit\Concerns\Model\HasAuthKitTwoFactor;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;

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
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->boolean('two_factor_enabled')->default(false);
        $table->text('two_factor_secret')->nullable();
        $table->json('two_factor_recovery_codes')->nullable();
        $table->json('two_factor_methods')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->string('last_confirm_two_factor_setup_code')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });

    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => ConfirmTwoFactorSetupTestUser::class,
    ]);

    Config::set('authkit.auth.guard', 'web');

    Config::set('authkit.route_names.web.two_factor_settings', 'authkit.web.settings.two_factor');
    Config::set('authkit.route_names.api.two_factor_confirm', 'authkit.api.settings.two_factor.confirm');

    Config::set('authkit.schemas.two_factor_confirm', [
        'submit' => [
            'label' => 'Confirm setup',
        ],
        'fields' => [
            'code' => [
                'label' => 'Authentication code',
                'type' => 'otp',
                'required' => true,
            ],
        ],
    ]);

    Config::set('authkit.validation.providers.two_factor_confirm', null);

    Config::set('authkit.two_factor.enabled', true);
    Config::set('authkit.two_factor.driver', 'totp');
    Config::set('authkit.two_factor.methods', ['totp']);
    Config::set('authkit.two_factor.drivers', [
        'totp' => \Xul\AuthKit\Support\TwoFactor\Drivers\TotpTwoFactorDriver::class,
    ]);

    Config::set('authkit.two_factor.columns.enabled', 'two_factor_enabled');
    Config::set('authkit.two_factor.columns.secret', 'two_factor_secret');
    Config::set('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
    Config::set('authkit.two_factor.columns.methods', 'two_factor_methods');
    Config::set('authkit.two_factor.columns.confirmed_at', 'two_factor_confirmed_at');

    Config::set('authkit.two_factor.recovery_codes.flash_key', 'authkit.two_factor.recovery_codes');
    Config::set('authkit.two_factor.recovery_codes.response_key', 'recovery_codes');

    Route::middleware('web')->group(function (): void {
        Route::get('/authkit/settings/two-factor', fn () => 'two-factor-page')
            ->name('authkit.web.settings.two_factor');

        Route::post(
            '/authkit/settings/two-factor/confirm',
            \Xul\AuthKit\Http\Controllers\Api\App\Settings\ConfirmTwoFactorSetupController::class
        )
            ->middleware('auth:web')
            ->name('authkit.api.settings.two_factor.confirm');
    });

    $this->app->bind(\Xul\AuthKit\Support\TwoFactor\Drivers\TotpTwoFactorDriver::class, function () {
        return new class implements \Xul\AuthKit\Contracts\TwoFactorDriverContract {
            public function key(): string
            {
                return 'totp';
            }

            public function methods(object $user): array
            {
                return ['totp'];
            }

            public function enabled(object $user): bool
            {
                return (bool) data_get($user, 'two_factor_enabled', false);
            }

            public function verify(object $user, string $code): bool
            {
                return trim($code) === '123456';
            }

            public function generateRecoveryCodes(int $count = 8, int $length = 10): array
            {
                return [
                    'ABCDE-FGHIJ',
                    'KLMNO-PQRST',
                    'UVWXY-Z2345',
                    '6789A-BCDEF',
                    'GHIJK-LMNOP',
                    'QRSTU-VWXYZ',
                    '23456-789AB',
                    'CDEFG-HJKLM',
                ];
            }

            public function verifyRecoveryCode(object $user, string $recoveryCode): bool
            {
                return false;
            }

            public function consumeRecoveryCode(object $user, string $recoveryCode): bool
            {
                return false;
            }
        };
    });
});

it('confirms two-factor setup and redirects for normal web requests', function (): void {
    $user = ConfirmTwoFactorSetupTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => false,
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_methods' => [],
        'two_factor_recovery_codes' => [],
        'two_factor_confirmed_at' => null,
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->from(route('authkit.web.settings.two_factor'))
        ->post(route('authkit.api.settings.two_factor.confirm'), [
            'code' => '123456',
        ]);

    $response
        ->assertRedirect(route('authkit.web.settings.two_factor'))
        ->assertSessionHas('status', 'Two-factor authentication has been enabled. Save your recovery codes in a secure location.')
        ->assertSessionHas('authkit.two_factor.recovery_codes');

    $user->refresh();

    expect($user->two_factor_enabled)->toBeTrue()
        ->and($user->two_factor_methods)->toBe(['totp'])
        ->and($user->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeArray();
});

it('returns standardized json response when setup confirmation succeeds', function (): void {
    $user = ConfirmTwoFactorSetupTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => false,
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_methods' => [],
        'two_factor_recovery_codes' => [],
        'two_factor_confirmed_at' => null,
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.confirm'), [
            'code' => '123456',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Two-factor authentication has been enabled. Save your recovery codes in a secure location.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.confirmed', true)
        ->assertJsonPath('payload.methods.0', 'totp');

    expect($response->json('payload.recovery_codes'))->toBeArray();

    $user->refresh();

    expect($user->two_factor_enabled)->toBeTrue()
        ->and($user->two_factor_methods)->toBe(['totp'])
        ->and($user->two_factor_confirmed_at)->not->toBeNull();
});

it('returns standardized action result for valid confirm-two-factor-setup flow', function (): void {
    $user = ConfirmTwoFactorSetupTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => false,
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_methods' => [],
        'two_factor_recovery_codes' => [],
        'two_factor_confirmed_at' => null,
    ]);

    /** @var ConfirmTwoFactorSetupAction $action */
    $action = app(ConfirmTwoFactorSetupAction::class);

    $result = $action->handle(
        user: $user,
        data: MappedPayloadBuilder::build('two_factor_confirm', [
            'code' => '123456',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('confirmed'))->toBeTrue()
        ->and($result->payload?->get('methods'))->toBe(['totp'])
        ->and($result->payload?->get('recovery_codes'))->toBeArray();

    $user->refresh();

    expect($user->two_factor_enabled)->toBeTrue();
});

it('returns validation failure when code is missing', function (): void {
    $user = ConfirmTwoFactorSetupTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_secret' => 'encrypted-secret',
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.confirm'), []);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The given data was invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed');

    expect($response->json('payload.fields.code.0'))->toBeString();
});

it('returns failure when authentication code is invalid', function (): void {
    $user = ConfirmTwoFactorSetupTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_secret' => 'encrypted-secret',
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.confirm'), [
            'code' => '000000',
        ]);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The provided authentication code is invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.field', 'code')
        ->assertJsonPath('errors.0.code', 'invalid_two_factor_code');
});

it('persists mapper-approved setup-confirmation attributes when the model supports mapped persistence', function (): void {
    Config::set('authkit.mappers.contexts.two_factor_confirm.class', PersistingConfirmTwoFactorSetupMapper::class);

    $user = ConfirmTwoFactorSetupTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => false,
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_methods' => [],
        'two_factor_recovery_codes' => [],
        'two_factor_confirmed_at' => null,
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.confirm'), [
            'code' => ' 123456 ',
        ]);

    $response->assertOk();

    $user->refresh();

    expect($user->last_confirm_two_factor_setup_code)->toBe('123456');
});

/**
 * ConfirmTwoFactorSetupTestUser
 *
 * Minimal user model used for authenticated two-factor setup confirmation tests.
 */
final class ConfirmTwoFactorSetupTestUser extends BaseUser
{
    use HasAuthKitTwoFactor;
    use HasAuthKitMappedPersistence;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'two_factor_enabled' => 'bool',
        'two_factor_recovery_codes' => 'array',
        'two_factor_methods' => 'array',
        'two_factor_confirmed_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];
}

/**
 * PersistingConfirmTwoFactorSetupMapper
 *
 * Test-only mapper that marks confirm-two-factor-setup attributes as persistable.
 */
final class PersistingConfirmTwoFactorSetupMapper extends AbstractPayloadMapper
{
    public function context(): string
    {
        return 'two_factor_confirm';
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
                'target' => 'last_confirm_two_factor_setup_code',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'trim',
            ],
        ];
    }
}