<?php

namespace Xul\AuthKit\Tests\Feature\Api\PasswordReset;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\PasswordReset\VerifyPasswordResetTokenAction;
use Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordUpdaterContract;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Controllers\Api\PasswordReset\VerifyPasswordResetTokenController;
use Xul\AuthKit\Support\CacheTokenRepository;
use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\PendingPasswordReset;

uses(RefreshDatabase::class);

final class VerifyPasswordResetTokenTestUser extends BaseUser
{
    use HasAuthKitMappedPersistence;

    protected $table = 'users';

    protected $fillable = [
        'email',
        'password',
        'remember_token',
        'last_verified_reset_email',
        'last_verified_reset_token',
    ];
}

beforeEach(function () {
    Config::set('database.default', 'testing');
    Config::set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    Config::set('cache.default', 'array');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('password');
        $table->string('remember_token')->nullable();
        $table->string('last_verified_reset_email')->nullable();
        $table->string('last_verified_reset_token')->nullable();
        $table->timestamps();
    });

    config()->set('auth.defaults.guard', 'web');
    config()->set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    config()->set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => VerifyPasswordResetTokenTestUser::class,
    ]);

    config()->set('authkit.auth.guard', 'web');
    config()->set('authkit.password_reset.driver', 'token');
    config()->set('authkit.password_reset.ttl_minutes', 5);
    config()->set('authkit.password_reset.token.max_attempts', 5);
    config()->set('authkit.password_reset.token.decay_minutes', 1);

    config()->set('authkit.route_names.api.password_verify_token', 'authkit.api.password.reset.verify.token');
    config()->set('authkit.route_names.web.password_reset_token_page', 'authkit.web.password.reset.token');
    config()->set('authkit.route_names.web.password_reset_success', 'authkit.web.password.reset.success');
    config()->set('authkit.route_names.web.login', 'authkit.web.login');

    Route::post('/authkit/password/reset/verify-token', VerifyPasswordResetTokenController::class)
        ->middleware(['web'])
        ->name('authkit.api.password.reset.verify.token');

    Route::get('/password/reset/token', fn () => 'token-page')
        ->name('authkit.web.password.reset.token');

    Route::get('/password/reset/success', fn () => 'success-page')
        ->name('authkit.web.password.reset.success');

    Route::get('/login', fn () => 'login')
        ->name('authkit.web.login');

    app()->singleton(TokenRepositoryContract::class, function ($app) {
        return new CacheTokenRepository($app['cache']->store());
    });

    app()->singleton(PendingPasswordReset::class, function ($app) {
        return new PendingPasswordReset(
            $app->make(TokenRepositoryContract::class),
            $app['cache']->store()
        );
    });

    app()->instance(PasswordResetPolicyContract::class, new class implements PasswordResetPolicyContract {
        public function canRequest(string $email): bool
        {
            return true;
        }

        public function canReset(string $email): bool
        {
            return true;
        }
    });

    app()->instance(PasswordResetUserResolverContract::class, new class implements PasswordResetUserResolverContract {
        public function resolve(string $identityValue): ?Authenticatable
        {
            return VerifyPasswordResetTokenTestUser::query()
                ->where('email', $identityValue)
                ->first();
        }
    });

    app()->instance(PasswordUpdaterContract::class, new class implements PasswordUpdaterContract {
        public function update(Authenticatable $user, string $newPasswordRaw, bool $refreshRememberToken = true): void
        {
            $user->forceFill([
                'password' => Hash::make($newPasswordRaw),
            ]);

            if ($refreshRememberToken) {
                $user->setRememberToken(str()->random(60));
            }

            $user->save();
        }
    });
});

it('returns 200 and resets password when token is valid for token driver flow', function () {
    $user = VerifyPasswordResetTokenTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $routeName = (string) data_get(
        config('authkit.route_names.api', []),
        'password_verify_token',
        'authkit.api.password.reset.verify.token'
    );

    $pending = app(PendingPasswordReset::class);

    $token = $pending->createForEmail(
        email: 'michael@example.com',
        ttlMinutes: 5
    );

    $this->postJson(route($routeName), [
        'email' => 'michael@example.com',
        'token' => $token,
        'password' => 'New-password-123',
        'password_confirmation' => 'New-password-123',
    ])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Password reset successfully.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.email', 'michael@example.com')
        ->assertJsonPath('payload.password_reset', true);

    $user->refresh();

    expect(Hash::check('New-password-123', (string) $user->password))->toBeTrue()
        ->and($pending->hasPendingForEmail('michael@example.com'))->toBeFalse()
        ->and($pending->peekToken('michael@example.com', $token))->toBeNull();
});

it('returns 422 when token is invalid and does not update password', function () {
    $user = VerifyPasswordResetTokenTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $routeName = (string) data_get(
        config('authkit.route_names.api', []),
        'password_verify_token',
        'authkit.api.password.reset.verify.token'
    );

    $pending = app(PendingPasswordReset::class);

    $token = $pending->createForEmail(
        email: 'michael@example.com',
        ttlMinutes: 5
    );

    $this->postJson(route($routeName), [
        'email' => 'michael@example.com',
        'token' => '000000',
        'password' => 'New-password-123',
        'password_confirmation' => 'New-password-123',
    ])
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'Invalid reset token.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'invalid_reset_token');

    $user->refresh();

    expect(Hash::check('old-password', (string) $user->password))->toBeTrue()
        ->and($pending->hasPendingForEmail('michael@example.com'))->toBeTrue()
        ->and($pending->peekToken('michael@example.com', $token))->not->toBeNull();
});

it('returns 410 when reset request is missing or expired', function () {
    $routeName = (string) data_get(
        config('authkit.route_names.api', []),
        'password_verify_token',
        'authkit.api.password.reset.verify.token'
    );

    $this->postJson(route($routeName), [
        'email' => 'michael@example.com',
        'token' => '123456',
        'password' => 'New-password-123',
        'password_confirmation' => 'New-password-123',
    ])
        ->assertStatus(410)
        ->assertJson([
            'ok' => false,
            'status' => 410,
            'message' => 'Password reset request has expired.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'password_reset_request_expired');
});

it('returns a standardized action result for valid token reset flow', function () {
    $user = VerifyPasswordResetTokenTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $pending = app(PendingPasswordReset::class);

    $token = $pending->createForEmail(
        email: 'michael@example.com',
        ttlMinutes: 5
    );

    /** @var VerifyPasswordResetTokenAction $action */
    $action = app(VerifyPasswordResetTokenAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('password_reset_token', [
            'email' => 'michael@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('email'))->toBe('michael@example.com')
        ->and($result->payload?->get('password_reset'))->toBeTrue();

    $user->refresh();

    expect(Hash::check('new-password-123', (string) $user->password))->toBeTrue();
});

it('persists mapper-approved verify-token attributes when the model supports mapped persistence', function () {
    config()->set('authkit.mappers.contexts.password_reset_token.class', PersistingVerifyPasswordResetTokenMapper::class);

    $user = VerifyPasswordResetTokenTestUser::query()->create([
        'email' => 'michael@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $pending = app(PendingPasswordReset::class);

    $token = $pending->createForEmail(
        email: 'michael@example.com',
        ttlMinutes: 5
    );

    /** @var VerifyPasswordResetTokenAction $action */
    $action = app(VerifyPasswordResetTokenAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('password_reset_token', [
            'email' => '  MICHAEL@EXAMPLE.COM  ',
            'token' => '  ' . $token . '  ',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue();

    $user->refresh();

    expect($user->last_verified_reset_email)->toBe('michael@example.com')
        ->and($user->last_verified_reset_token)->toBe($token);
});

it('returns standardized DTO validation response for JSON verify password reset token requests', function () {
    $routeName = (string) data_get(
        config('authkit.route_names.api', []),
        'password_verify_token',
        'authkit.api.password.reset.verify.token'
    );

    $response = $this->postJson(route($routeName), []);

    $response->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The given data was invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('payload.fields.email.0', 'The E-mail field is required.')
        ->assertJsonPath('payload.fields.token.0', 'The Reset code field is required.')
        ->assertJsonPath('payload.fields.password.0', 'The New password field is required.')
        ->assertJsonPath('payload.fields.password_confirmation.0', 'The Confirm password field is required.');

    $errors = $response->json('errors');

    expect($errors)->toBeArray()
        ->and(count($errors))->toBe(4)
        ->and($errors[0])->toHaveKeys(['code', 'message', 'field', 'meta']);

    expect(collect($errors)->pluck('field')->all())
        ->toContain('email', 'token', 'password', 'password_confirmation');

    expect(collect($errors)->pluck('code')->unique()->values()->all())
        ->toBe(['validation_error']);
});

it('normalizes email before validation for JSON verify password reset token requests', function () {
    $routeName = (string) data_get(
        config('authkit.route_names.api', []),
        'password_verify_token',
        'authkit.api.password.reset.verify.token'
    );

    $response = $this->postJson(route($routeName), [
        'email' => '  NOT-AN-EMAIL  ',
        'token' => '',
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The given data was invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed');

    expect($response->json('payload.fields.email'))->toBeArray();
    expect($response->json('payload.fields.token'))->toBeArray();
    expect($response->json('payload.fields.password'))->toBeArray();
    expect($response->json('payload.fields.password_confirmation'))->toBeArray();

    expect(collect($response->json('errors'))->pluck('field')->all())
        ->toContain('email', 'token', 'password', 'password_confirmation');
});

/**
 * PersistingVerifyPasswordResetTokenMapper
 *
 * Test-only mapper that preserves the packaged defaults while adding
 * persistable mapped targets sourced from the same validated input.
 */
final class PersistingVerifyPasswordResetTokenMapper extends AbstractPayloadMapper
{
    public function context(): string
    {
        return 'password_reset_token';
    }

    public function mode(): string
    {
        return self::MODE_MERGE;
    }

    public function definitions(): array
    {
        return [
            'email_persist' => [
                'source' => 'email',
                'target' => 'last_verified_reset_email',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'lower_trim',
            ],

            'token_persist' => [
                'source' => 'token',
                'target' => 'last_verified_reset_token',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'trim',
            ],
        ];
    }
}