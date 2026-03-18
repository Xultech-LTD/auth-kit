<?php
// file: tests/Feature/Api/App/Settings/DisableTwoFactorControllerTest.php

namespace Xul\AuthKit\Tests\Feature\Api\App\Settings;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\App\Settings\DisableTwoFactorAction;
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
        $table->string('last_disabled_two_factor_code')->nullable();
        $table->timestamps();
    });

    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => DisableTwoFactorTestUser::class,
    ]);

    Config::set('authkit.auth.guard', 'web');

    Config::set('authkit.route_names.web.two_factor_settings', 'authkit.web.settings.two_factor');
    Config::set('authkit.route_names.api.two_factor_disable', 'authkit.api.settings.two_factor.disable');

    Config::set('authkit.schemas.two_factor_disable', [
        'submit' => [
            'label' => 'Disable two-factor authentication',
        ],
        'fields' => [
            'code' => [
                'label' => 'Authentication code',
                'type' => 'otp',
                'required' => false,
            ],
            'recovery_code' => [
                'label' => 'Recovery code',
                'type' => 'text',
                'required' => false,
            ],
        ],
    ]);

    Config::set('authkit.validation.providers.two_factor_disable', null);

    Config::set('authkit.two_factor.enabled', true);
    Config::set('authkit.two_factor.driver', 'totp');
    Config::set('authkit.two_factor.drivers', [
        'totp' => \Xul\AuthKit\Support\TwoFactor\Drivers\TotpTwoFactorDriver::class,
    ]);

    Config::set('authkit.two_factor.columns.enabled', 'two_factor_enabled');
    Config::set('authkit.two_factor.columns.secret', 'two_factor_secret');
    Config::set('authkit.two_factor.columns.recovery_codes', 'two_factor_recovery_codes');
    Config::set('authkit.two_factor.columns.methods', 'two_factor_methods');
    Config::set('authkit.two_factor.columns.confirmed_at', 'two_factor_confirmed_at');

    Route::middleware('web')->group(function (): void {
        Route::get('/authkit/settings/two-factor', fn () => 'two-factor-page')
            ->name('authkit.web.settings.two_factor');

        Route::post(
            '/authkit/settings/two-factor/disable',
            \Xul\AuthKit\Http\Controllers\Api\App\Settings\DisableTwoFactorController::class
        )
            ->middleware('auth:web')
            ->name('authkit.api.settings.two_factor.disable');
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
                    'AAAAA-BBBBB',
                    'CCCCC-DDDDD',
                ];
            }

            public function verifyRecoveryCode(object $user, string $recoveryCode): bool
            {
                $codes = (array) data_get($user, 'two_factor_recovery_codes', []);

                foreach ($codes as $stored) {
                    if ((string) $stored === trim($recoveryCode)) {
                        return true;
                    }
                }

                return false;
            }

            public function consumeRecoveryCode(object $user, string $recoveryCode): bool
            {
                $codes = array_values((array) data_get($user, 'two_factor_recovery_codes', []));
                $needle = trim($recoveryCode);

                $remaining = array_values(array_filter($codes, fn ($code) => (string) $code !== $needle));

                if (count($remaining) === count($codes)) {
                    return false;
                }

                data_set($user, 'two_factor_recovery_codes', $remaining);

                return true;
            }
        };
    });
});

it('disables two-factor with a valid authenticator code for normal web requests', function (): void {
    $user = DisableTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
        'two_factor_methods' => ['totp'],
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->from(route('authkit.web.settings.two_factor'))
        ->post(route('authkit.api.settings.two_factor.disable'), [
            'code' => '123456',
        ]);

    $response
        ->assertRedirect(route('authkit.web.settings.two_factor'))
        ->assertSessionHas('status', 'Two-factor authentication has been disabled successfully.');

    $user->refresh();

    expect($user->two_factor_enabled)->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_methods)->toBe([])
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

it('disables two-factor with a valid recovery code for json requests', function (): void {
    $user = DisableTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
        'two_factor_methods' => ['totp'],
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.disable'), [
            'recovery_code' => 'AAAAA-BBBBB',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Two-factor authentication has been disabled successfully.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.two_factor_disabled', true)
        ->assertJsonPath('payload.used_recovery_code', true);

    $user->refresh();

    expect($user->two_factor_enabled)->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_methods)->toBe([]);
});

it('returns standardized action result for valid disable-two-factor flow', function (): void {
    $user = DisableTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_recovery_codes' => ['AAAAA-BBBBB'],
        'two_factor_methods' => ['totp'],
        'two_factor_confirmed_at' => now(),
    ]);

    /** @var DisableTwoFactorAction $action */
    $action = app(DisableTwoFactorAction::class);

    $result = $action->handle(
        user: $user,
        data: MappedPayloadBuilder::build('two_factor_disable', [
            'code' => '123456',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('two_factor_disabled'))->toBeTrue()
        ->and($result->payload?->get('used_recovery_code'))->toBeFalse();

    $user->refresh();

    expect($user->two_factor_enabled)->toBeFalse();
});

it('returns validation failure when neither code nor recovery code is provided', function (): void {
    $user = DisableTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.disable'), []);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'Provide either an authentication code or a recovery code.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'two_factor_disable_credential_required');
});

it('returns validation failure when authenticator code is invalid', function (): void {
    $user = DisableTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
        'two_factor_recovery_codes' => ['AAAAA-BBBBB'],
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.disable'), [
            'code' => '000000',
        ]);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The authentication code you entered is invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.field', 'code')
        ->assertJsonPath('errors.0.code', 'invalid_two_factor_code');
});

it('returns validation failure when recovery code is invalid', function (): void {
    $user = DisableTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_methods' => ['totp'],
        'two_factor_recovery_codes' => ['AAAAA-BBBBB'],
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.disable'), [
            'recovery_code' => 'WRONG-CODE',
        ]);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The recovery code you entered is invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.field', 'recovery_code')
        ->assertJsonPath('errors.0.code', 'invalid_two_factor_recovery_code');
});

it('fails when two-factor is not enabled', function (): void {
    $user = DisableTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => false,
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.disable'), [
            'code' => '123456',
        ]);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'Two-factor authentication is not enabled for this account.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'two_factor_not_enabled');
});

it('persists mapper-approved disable attributes when the model supports mapped persistence', function (): void {
    Config::set('authkit.mappers.contexts.two_factor_disable.class', PersistingDisableTwoFactorMapper::class);

    $user = DisableTwoFactorTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('secret123'),
        'two_factor_enabled' => true,
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_recovery_codes' => ['AAAAA-BBBBB'],
        'two_factor_methods' => ['totp'],
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.disable'), [
            'code' => ' 123456 ',
        ]);

    $response->assertOk();

    $user->refresh();

    expect($user->last_disabled_two_factor_code)->toBe('123456');
});

/**
 * DisableTwoFactorTestUser
 *
 * Minimal user model used for authenticated disable-two-factor tests.
 */
final class DisableTwoFactorTestUser extends BaseUser
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
    ];
}

/**
 * PersistingDisableTwoFactorMapper
 *
 * Test-only mapper that marks disable-two-factor attributes as persistable.
 */
final class PersistingDisableTwoFactorMapper extends AbstractPayloadMapper
{
    public function context(): string
    {
        return 'two_factor_disable';
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
                'target' => 'last_disabled_two_factor_code',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'trim',
            ],
        ];
    }
}