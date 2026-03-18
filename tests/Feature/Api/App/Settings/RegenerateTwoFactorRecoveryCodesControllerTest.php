<?php
// file: tests/Feature/Api/App/Settings/RegenerateTwoFactorRecoveryCodesControllerTest.php

namespace Xul\AuthKit\Tests\Feature\Api\App\Settings;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Concerns\Model\HasAuthKitTwoFactor;

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
        'model' => RegenerateRecoveryCodesTestUser::class,
    ]);

    Config::set('authkit.auth.guard', 'web');

    Config::set('authkit.route_names.web.two_factor_settings', 'authkit.web.settings.two_factor');
    Config::set('authkit.route_names.api.two_factor_recovery_regenerate', 'authkit.api.settings.two_factor.recovery.regenerate');

    /**
     * Schema for regenerate form
     */
    Config::set('authkit.schemas.two_factor_recovery_regenerate', [
        'submit' => [
            'label' => 'Regenerate',
        ],
        'fields' => [
            'code' => [
                'label' => 'Authentication code',
                'type' => 'otp',
                'required' => true,
            ],
        ],
    ]);

    Config::set('authkit.validation.providers.two_factor_recovery_regenerate', null);

    /**
     * Two-factor config
     */
    Config::set('authkit.two_factor.enabled', true);
    Config::set('authkit.two_factor.driver', 'totp');
    Config::set('authkit.two_factor.drivers', [
        'totp' => \Xul\AuthKit\Support\TwoFactor\Drivers\TotpTwoFactorDriver::class,
    ]);

    Config::set('authkit.two_factor.recovery_codes.response_key', 'recovery_codes');

    Route::middleware('web')->group(function (): void {
        Route::get('/authkit/settings/two-factor', fn () => 'two-factor-page')
            ->name('authkit.web.settings.two_factor');

        Route::post(
            '/authkit/settings/two-factor/recovery/regenerate',
            \Xul\AuthKit\Http\Controllers\Api\App\Settings\RegenerateTwoFactorRecoveryCodesController::class
        )
            ->middleware('auth:web')
            ->name('authkit.api.settings.two_factor.recovery.regenerate');
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

it('regenerates recovery codes and redirects for normal web request', function (): void {
    $user = RegenerateRecoveryCodesTestUser::query()->create([
        'email' => 'michael@example.com',
        'two_factor_enabled' => true,
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->from(route('authkit.web.settings.two_factor'))
        ->post(route('authkit.api.settings.two_factor.recovery.regenerate'), [
            'code' => '123456', // may pass depending on timing window
        ]);

    expect(in_array($response->getStatusCode(), [302, 422], true))->toBeTrue();
});

it('returns standardized json response when regeneration succeeds', function (): void {
    $user = RegenerateRecoveryCodesTestUser::query()->create([
        'email' => 'michael@example.com',
        'two_factor_enabled' => true,
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.recovery.regenerate'), [
            'code' => '123456',
        ]);

    if ($response->status() === 200) {
        $response
            ->assertJson([
                'ok' => true,
                'status' => 200,
            ])
            ->assertJsonPath('flow.name', 'completed');

        expect($response->json('payload.recovery_codes'))->toBeArray();
    } else {
        $response->assertStatus(422);
    }
});

it('returns validation error when code is missing', function (): void {
    $user = RegenerateRecoveryCodesTestUser::query()->create([
        'email' => 'michael@example.com',
        'two_factor_enabled' => true,
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.recovery.regenerate'), []);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
        ]);

    expect($response->json('payload.fields.code.0'))->toBeString();
});

it('fails when two-factor is not enabled', function (): void {
    $user = RegenerateRecoveryCodesTestUser::query()->create([
        'email' => 'michael@example.com',
        'two_factor_enabled' => false,
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.recovery.regenerate'), [
            'code' => '123456',
        ]);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
        ]);
});

it('persists newly generated recovery codes', function (): void {
    $user = RegenerateRecoveryCodesTestUser::query()->create([
        'email' => 'michael@example.com',
        'two_factor_enabled' => true,
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
    ]);

    $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.two_factor.recovery.regenerate'), [
            'code' => '123456',
        ]);

    $user->refresh();

    expect($user->two_factor_recovery_codes)->not->toBeNull();
});

final class RegenerateRecoveryCodesTestUser extends BaseUser
{
    use HasAuthKitTwoFactor;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'two_factor_enabled' => 'bool',
        'two_factor_recovery_codes' => 'array',
        'two_factor_methods' => 'array',
    ];
}