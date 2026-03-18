<?php
// file: tests/Feature/Api/TwoFactorResendTest.php

namespace Xul\AuthKit\Tests\Feature\Api;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\Contracts\TwoFactorDriverContract;
use Xul\AuthKit\Contracts\TwoFactorResendableContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Events\AuthKitTwoFactorResent;
use Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorResendController;
use Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\PendingLogin;
use Xul\AuthKit\Tests\Support\ArrayTokenRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('database.default', 'testing');
    Config::set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->boolean('two_factor_enabled')->default(false);
        $table->text('two_factor_secret')->nullable();
        $table->json('two_factor_recovery_codes')->nullable();
        $table->json('two_factor_methods')->nullable();
        $table->string('last_resend_email')->nullable();
        $table->timestamps();
    });

    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => TwoFactorResendTestUser::class,
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
    Config::set('authkit.route_names.web.login', 'authkit.web.login');
    Config::set('authkit.route_names.web.two_factor_challenge', 'authkit.web.twofactor.challenge');

    app()->singleton(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());

    Route::get('/login', fn () => 'login')->name('authkit.web.login');
    Route::get('/twofactor', fn () => 'twofactor')->name('authkit.web.twofactor.challenge');

    Route::post('/authkit/twofactor/resend', TwoFactorResendController::class)
        ->middleware(['web'])
        ->name('authkit.api.twofactor.resend');
});

it('returns 409 when resend is not supported by the active driver', function () {
    Event::fake();

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
            'status' => 409,
            'message' => 'Two-factor resend is not supported for the active driver.',
        ])
        ->assertJsonPath('payload.driver', 'totp');

    Event::assertNotDispatched(AuthKitTwoFactorResent::class);
});

it('returns 200 when resend is supported by the active driver', function () {
    Event::fake();

    config()->set('authkit.two_factor.driver', 'fake_resend');
    config()->set('authkit.two_factor.drivers.fake_resend', FakeResendTwoFactorDriver::class);
    config()->set('authkit.two_factor.methods', ['fake']);

    $apiNames = (array) config('authkit.route_names.api', []);
    $routeName = (string) ($apiNames['two_factor_resend'] ?? 'authkit.api.twofactor.resend');

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
            'status' => 200,
            'message' => 'Challenge resent.',
        ])
        ->assertJsonPath('flow.name', 'two_factor_required')
        ->assertJsonPath('payload.driver', 'fake_resend');

    Event::assertDispatched(AuthKitTwoFactorResent::class);
});

it('returns a standardized action result for supported resend flow', function () {
    Event::fake();

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

    session()->put(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, $challenge);

    $action = app(\Xul\AuthKit\Actions\Auth\TwoFactorResendAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('two_factor_resend', [
            'email' => $user->email,
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('two_factor_required'))->toBeTrue()
        ->and($result->payload?->get('driver'))->toBe('fake_resend');
});

it('persists mapper-approved resend attributes when the model supports mapped persistence', function () {
    Event::fake();

    config()->set('authkit.two_factor.driver', 'fake_resend');
    config()->set('authkit.two_factor.drivers.fake_resend', FakeResendTwoFactorDriver::class);
    config()->set('authkit.two_factor.methods', ['fake']);
    config()->set('authkit.mappers.contexts.two_factor_resend.class', PersistingTwoFactorResendMapper::class);

    $user = createTwoFactorEnabledUser();

    $pending = app(PendingLogin::class);

    $challenge = $pending->create(
        userId: (string) $user->getAuthIdentifier(),
        remember: true,
        ttlMinutes: 5,
        methods: ['fake']
    );

    session()->put(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, $challenge);

    $action = app(\Xul\AuthKit\Actions\Auth\TwoFactorResendAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('two_factor_resend', [
            'email' => '  ' . strtoupper($user->email) . '  ',
        ])
    );

    expect($result->ok)->toBeTrue();

    $user->refresh();

    expect($user->last_resend_email)->toBe(mb_strtolower($user->email));
});

it('returns standardized DTO validation response for JSON two-factor resend requests', function () {
    $apiNames = (array) config('authkit.route_names.api', []);
    $routeName = (string) ($apiNames['two_factor_resend'] ?? 'authkit.api.twofactor.resend');

    $response = $this->postJson(route($routeName), []);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The given data was invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('payload.fields.email.0', 'The E-mail field is required.');

    $errors = $response->json('errors');

    expect($errors)->toBeArray()
        ->and(count($errors))->toBe(1)
        ->and($errors[0])->toHaveKeys(['code', 'message', 'field', 'meta'])
        ->and($errors[0]['field'])->toBe('email')
        ->and($errors[0]['code'])->toBe('validation_error');
});

it('normalizes email before validation for JSON two-factor resend requests', function () {
    $apiNames = (array) config('authkit.route_names.api', []);
    $routeName = (string) ($apiNames['two_factor_resend'] ?? 'authkit.api.twofactor.resend');

    $response = $this->postJson(route($routeName), [
        'email' => '  NOT-AN-EMAIL  ',
    ]);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The given data was invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed');

    expect($response->json('payload.fields.email'))->toBeArray();
    expect(collect($response->json('errors'))->pluck('field')->all())
        ->toBe(['email']);
});

it('redirects to login when resend is requested without a pending challenge for non-json requests', function () {
    Event::fake();

    $apiNames = (array) config('authkit.route_names.api', []);
    $routeName = (string) ($apiNames['two_factor_resend'] ?? 'authkit.api.twofactor.resend');

    $this->post(route($routeName), [
        'email' => 'user@example.com',
    ])
        ->assertRedirect(route('authkit.web.login'))
        ->assertSessionHas('error', 'Missing two-factor challenge.');

    Event::assertNotDispatched(AuthKitTwoFactorResent::class);
});

/**
 * Create a minimal user record with two-factor enabled.
 *
 * @return TwoFactorResendTestUser
 */
function createTwoFactorEnabledUser(): TwoFactorResendTestUser
{
    $enabledColumn = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');

    /** @var TwoFactorResendTestUser $user */
    $user = TwoFactorResendTestUser::query()->create([
        'name' => 'Test User',
        'email' => 'user' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        $enabledColumn => true,
    ]);

    return $user;
}

/**
 * TwoFactorResendTestUser
 *
 * Minimal user model for this test file.
 */
final class TwoFactorResendTestUser extends BaseUser
{
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
 * FakeResendTwoFactorDriver
 *
 * Test-only driver that supports resend for the pending login context.
 */
final class FakeResendTwoFactorDriver implements TwoFactorDriverContract, TwoFactorResendableContract
{
    /**
     * @return string
     */
    public function key(): string
    {
        return 'fake_resend';
    }

    /**
     * @param object $user
     * @return array<int, string>
     */
    public function methods(object $user): array
    {
        return ['fake'];
    }

    /**
     * @param object $user
     * @return bool
     */
    public function enabled(object $user): bool
    {
        if (method_exists($user, 'hasTwoFactorEnabled')) {
            return (bool) $user->hasTwoFactorEnabled();
        }

        $column = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');

        return (bool) data_get($user, $column, false);
    }

    /**
     * @param object $user
     * @param string $code
     * @return bool
     */
    public function verify(object $user, string $code): bool
    {
        return false;
    }

    /**
     * @param object $user
     * @param string $recoveryCode
     * @return bool
     */
    public function verifyRecoveryCode(object $user, string $recoveryCode): bool
    {
        return false;
    }

    /**
     * @param object $user
     * @param string $recoveryCode
     * @return bool
     */
    public function consumeRecoveryCode(object $user, string $recoveryCode): bool
    {
        return false;
    }

    /**
     * @param Authenticatable $user
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function resend(Authenticatable $user, array $context = []): array
    {
        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Challenge resent.',
        ];
    }

    /**
     * @param int $count
     * @param int $length
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8, int $length = 10): array
    {
        $count = max(1, $count);
        $length = max(4, $length);

        $codes = [];

        for ($index = 0; $index < $count; $index++) {
            $codes[] = Str::lower(Str::random($length));
        }

        return $codes;
    }
}

/**
 * PersistingTwoFactorResendMapper
 *
 * Test-only mapper that marks resend flow attributes as persistable.
 */
final class PersistingTwoFactorResendMapper extends AbstractPayloadMapper
{
    public function context(): string
    {
        return 'two_factor_resend';
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
                'target' => 'last_resend_email',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'lower_trim',
            ],
        ];
    }
}