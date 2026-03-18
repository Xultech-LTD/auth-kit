<?php

namespace Xul\AuthKit\Tests\Feature\Api\PasswordReset;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\PasswordReset\ResetPasswordAction;
use Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordUpdaterContract;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Http\Controllers\Api\PasswordReset\ResetPasswordController;
use Xul\AuthKit\Support\CacheTokenRepository;
use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\PendingPasswordReset;

uses(RefreshDatabase::class);

final class ResetPasswordControllerTest extends BaseUser
{
    use Notifiable;
    use HasAuthKitMappedPersistence;

    protected $table = 'users';

    protected $fillable = [
        'email',
        'password',
        'remember_token',
        'last_reset_email',
        'last_reset_token',
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
        $table->string('last_reset_email')->nullable();
        $table->string('last_reset_token')->nullable();
        $table->timestamps();
    });

    config()->set('auth.defaults.guard', 'web');
    config()->set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    config()->set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => ResetPasswordControllerTest::class,
    ]);

    config()->set('authkit.auth.guard', 'web');
    config()->set('authkit.password_reset.driver', 'link');
    config()->set('authkit.password_reset.ttl_minutes', 30);
    config()->set('authkit.route_names.api.password_reset', 'authkit.api.password.reset');
    config()->set('authkit.route_names.web.password_reset', 'authkit.web.password.reset');
    config()->set('authkit.route_names.web.password_reset_success', 'authkit.web.password.reset.success');
    config()->set('authkit.route_names.web.login', 'authkit.web.login');
    config()->set('authkit.login.dashboard_route', 'dashboard');

    Route::post('/authkit/password/reset', ResetPasswordController::class)
        ->middleware(['web'])
        ->name('authkit.api.password.reset');

    Route::get('/password/reset', fn () => 'reset-form')
        ->name('authkit.web.password.reset');

    Route::get('/password/reset/success', fn () => 'reset-success')
        ->name('authkit.web.password.reset.success');

    Route::get('/login', fn () => 'login')
        ->name('authkit.web.login');

    Route::get('/dashboard', fn () => 'dashboard')
        ->name('dashboard');

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
            return ResetPasswordControllerTest::query()->where('email', $identityValue)->first();
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

it('resets password with a valid token and consumes the token', function () {
    $user = ResetPasswordControllerTest::query()->create([
        'email' => 'jane@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $pending = app(PendingPasswordReset::class);

    $token = $pending->createForEmail(
        email: 'jane@example.com',
        ttlMinutes: 30,
        payload: ['user_id' => $user->getAuthIdentifier()]
    );

    $this->postJson(route('authkit.api.password.reset'), [
        'email' => 'jane@example.com',
        'token' => $token,
        'password' => 'New-password-123',
        'password_confirmation' => 'New-password-123',
    ])
        ->assertStatus(200)
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Password reset successfully.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.email', 'jane@example.com')
        ->assertJsonPath('payload.password_reset', true);

    $user->refresh();

    expect(Hash::check('New-password-123', (string) $user->password))->toBeTrue()
        ->and($pending->hasPendingForEmail('jane@example.com'))->toBeFalse()
        ->and($pending->peekToken('jane@example.com', $token))->toBeNull();
});

it('rejects reset when token is invalid and does not update password', function () {
    $user = ResetPasswordControllerTest::query()->create([
        'email' => 'jane@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $pending = app(PendingPasswordReset::class);

    $token = $pending->createForEmail(
        email: 'jane@example.com',
        ttlMinutes: 30,
        payload: ['user_id' => $user->getAuthIdentifier()]
    );

    $this->postJson(route('authkit.api.password.reset'), [
        'email' => 'jane@example.com',
        'token' => 'bad-token',
        'password' => 'New-password-123',
        'password_confirmation' => 'New-password-123',
    ])
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'Invalid or expired reset token.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'invalid_or_expired_reset_token');

    $user->refresh();

    expect(Hash::check('old-password', (string) $user->password))->toBeTrue()
        ->and($pending->hasPendingForEmail('jane@example.com'))->toBeTrue()
        ->and($pending->peekToken('jane@example.com', $token))->not->toBeNull();
});

it('returns a standardized action result from reset password action', function () {
    $user = ResetPasswordControllerTest::query()->create([
        'email' => 'jane@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $pending = app(PendingPasswordReset::class);

    $token = $pending->createForEmail(
        email: 'jane@example.com',
        ttlMinutes: 30,
        payload: ['user_id' => $user->getAuthIdentifier()]
    );

    /** @var ResetPasswordAction $action */
    $action = app(ResetPasswordAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('password_reset', [
            'email' => 'jane@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('email'))->toBe('jane@example.com')
        ->and($result->payload?->get('password_reset'))->toBeTrue();
});

it('logs the user in after reset when configured', function () {
    Event::fake([AuthKitLoggedIn::class]);

    config()->set('authkit.password_reset.post_reset.login_after_reset', true);
    config()->set('authkit.password_reset.post_reset.remember', true);

    $user = ResetPasswordControllerTest::query()->create([
        'email' => 'jane@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $pending = app(PendingPasswordReset::class);

    $token = $pending->createForEmail(
        email: 'jane@example.com',
        ttlMinutes: 30,
        payload: ['user_id' => $user->getAuthIdentifier()]
    );

    $response = $this->post(route('authkit.api.password.reset'), [
        'email' => 'jane@example.com',
        'token' => $token,
        'password' => 'New-password-123',
        'password_confirmation' => 'New-password-123',
    ]);

    $response->assertRedirect(route('dashboard'))
        ->assertSessionHas('status', 'Password reset successfully.');

    $this->assertAuthenticated('web');
    $this->assertAuthenticatedAs($user, 'web');

    Event::assertDispatched(AuthKitLoggedIn::class, function (AuthKitLoggedIn $event) use ($user) {
        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->guard === 'web'
            && $event->remember === true;
    });
});

it('persists mapper-approved reset-password attributes when the model supports mapped persistence', function () {
    config()->set('authkit.mappers.contexts.password_reset.class', PersistingResetPasswordMapper::class);

    $user = ResetPasswordControllerTest::query()->create([
        'email' => 'jane@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $pending = app(PendingPasswordReset::class);

    $token = $pending->createForEmail(
        email: 'jane@example.com',
        ttlMinutes: 30,
        payload: ['user_id' => $user->getAuthIdentifier()]
    );

    /** @var ResetPasswordAction $action */
    $action = app(ResetPasswordAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('password_reset', [
            'email' => '  JANE@EXAMPLE.COM  ',
            'token' => '  ' . $token . '  ',
            'password' => 'New-password-123',
            'password_confirmation' => 'New-password-123',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue();

    $user->refresh();

    expect($user->last_reset_email)->toBe('jane@example.com')
        ->and($user->last_reset_token)->toBe($token);
});

it('returns standardized DTO validation response for JSON reset password requests', function () {
    $response = $this->postJson(route('authkit.api.password.reset'), []);

    $response->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The given data was invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('payload.fields.email.0', 'The E-mail field is required.')
        ->assertJsonPath('payload.fields.token.0', 'The Reset token field is required.')
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

it('normalizes email before validation for JSON reset password requests', function () {
    $response = $this->postJson(route('authkit.api.password.reset'), [
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
 * PersistingResetPasswordMapper
 *
 * Test-only mapper that preserves the package defaults while adding persistable
 * mapped targets sourced from the same validated reset input.
 */
final class PersistingResetPasswordMapper extends AbstractPayloadMapper
{
    public function context(): string
    {
        return 'password_reset';
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
                'target' => 'last_reset_email',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'lower_trim',
            ],

            'token_persist' => [
                'source' => 'token',
                'target' => 'last_reset_token',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'trim',
            ],
        ];
    }
}