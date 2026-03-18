<?php
// file: tests/Feature/Api/App/Confirmations/ConfirmPasswordControllerTest.php

namespace Xul\AuthKit\Tests\Feature\Api\App\Confirmations;

use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\App\Confirmations\ConfirmPasswordAction;
use Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Controllers\Api\App\Confirmations\ConfirmPasswordController;
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
        $table->string('password');
        $table->rememberToken();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('last_confirmed_password_input')->nullable();
        $table->timestamps();
    });

    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => ConfirmPasswordControllerTestUser::class,
    ]);

    Config::set('authkit.auth.guard', 'web');

    Config::set('authkit.route_names.web.confirm_password', 'authkit.web.confirm.password');
    Config::set('authkit.route_names.web.dashboard_web', 'authkit.web.dashboard');
    Config::set('authkit.route_names.api.confirm_password', 'authkit.api.confirm.password');

    Config::set('authkit.confirmations.session.password_key', 'authkit.confirmed.password_at');
    Config::set('authkit.confirmations.session.intended_key', 'authkit.confirmation.intended');
    Config::set('authkit.confirmations.session.type_key', 'authkit.confirmation.type');
    Config::set('authkit.confirmations.routes.fallback', 'authkit.web.dashboard');

    Config::set('authkit.schemas.confirm_password', [
        'submit' => [
            'label' => 'Confirm password',
        ],
        'fields' => [
            'password' => [
                'label' => 'Current password',
                'type' => 'password',
                'required' => true,
                'autocomplete' => 'current-password',
                'attributes' => [],
                'wrapper' => [
                    'class' => 'authkit-field',
                ],
            ],
        ],
    ]);

    Config::set('authkit.validation.providers.confirm_password', null);
    Config::set('authkit.mappers.contexts.confirm_password.class', null);
    Config::set('authkit.mappers.contexts.confirm_password.schema', 'confirm_password');

    Route::middleware('web')->group(function (): void {
        Route::get('/authkit/confirm-password', fn () => 'confirm-password-page')
            ->name('authkit.web.confirm.password');

        Route::get('/dashboard', fn () => 'dashboard-page')
            ->name('authkit.web.dashboard');

        Route::get('/sensitive-area', fn () => 'sensitive-area')
            ->name('test.sensitive.area');

        Route::post('/authkit/confirm-password/process', ConfirmPasswordController::class)
            ->middleware('auth:web')
            ->name('authkit.api.confirm.password');
    });
});

it('confirms password, stores confirmation timestamp, clears transient session keys, and redirects to intended url for a normal web request', function (): void {
    $user = ConfirmPasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('secret-123'),
        'email_verified_at' => now(),
    ]);

    $this->withSession([
        'authkit.confirmation.intended' => route('test.sensitive.area'),
        'authkit.confirmation.type' => 'password',
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->from(route('authkit.web.confirm.password'))
        ->post(route('authkit.api.confirm.password'), [
            'password' => 'secret-123',
        ]);

    $response
        ->assertRedirect(route('test.sensitive.area'))
        ->assertSessionHas('status', 'Password confirmed successfully.');

    expect(session()->has('authkit.confirmed.password_at'))->toBeTrue()
        ->and(session('authkit.confirmed.password_at'))->toBeInt()
        ->and(session()->has('authkit.confirmation.intended'))->toBeFalse()
        ->and(session()->has('authkit.confirmation.type'))->toBeFalse();
});

it('confirms password and redirects to configured fallback route when no intended url exists for a normal web request', function (): void {
    $user = ConfirmPasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('secret-123'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->from(route('authkit.web.confirm.password'))
        ->post(route('authkit.api.confirm.password'), [
            'password' => 'secret-123',
        ]);

    $response
        ->assertRedirect(route('authkit.web.dashboard'))
        ->assertSessionHas('status', 'Password confirmed successfully.');

    expect(session()->has('authkit.confirmed.password_at'))->toBeTrue();
});

it('confirms password and returns a standardized json response for json requests', function (): void {
    $user = ConfirmPasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('secret-123'),
        'email_verified_at' => now(),
    ]);

    $this->withSession([
        'authkit.confirmation.intended' => route('test.sensitive.area'),
        'authkit.confirmation.type' => 'password',
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.confirm.password'), [
            'password' => 'secret-123',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Password confirmed successfully.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.confirmed', true)
        ->assertJsonPath('payload.type', 'password')
        ->assertJsonPath('redirect.type', 'url')
        ->assertJsonPath('redirect.target', route('test.sensitive.area'));

    expect(session()->has('authkit.confirmed.password_at'))->toBeTrue()
        ->and(session()->has('authkit.confirmation.intended'))->toBeFalse()
        ->and(session()->has('authkit.confirmation.type'))->toBeFalse();
});

it('returns validation errors for missing password on normal web requests', function (): void {
    $user = ConfirmPasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('secret-123'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->from(route('authkit.web.confirm.password'))
        ->post(route('authkit.api.confirm.password'), []);

    $response
        ->assertRedirect(route('authkit.web.confirm.password'))
        ->assertSessionHasErrors(['password']);
});

it('returns standardized dto validation errors for json requests when password is missing', function (): void {
    $user = ConfirmPasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('secret-123'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.confirm.password'), []);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The given data was invalid.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('payload.fields.password.0', 'The Current password field is required.');

    $errors = $response->json('errors');

    expect($errors)->toBeArray()
        ->and(count($errors))->toBe(1)
        ->and($errors[0])->toHaveKeys(['code', 'message', 'field', 'meta'])
        ->and($errors[0]['field'])->toBe('password')
        ->and($errors[0]['code'])->toBe('validation_error');
});

it('does not confirm password when the provided password is incorrect for a normal web request', function (): void {
    $user = ConfirmPasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('secret-123'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->from(route('authkit.web.confirm.password'))
        ->post(route('authkit.api.confirm.password'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertRedirect(route('authkit.web.confirm.password'))
        ->assertSessionHas('error', 'The provided password is incorrect.');

    expect(session()->has('authkit.confirmed.password_at'))->toBeFalse();
});

it('does not confirm password when the provided password is incorrect for a json request', function (): void {
    $user = ConfirmPasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('secret-123'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.confirm.password'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'status' => 422,
            'message' => 'The provided password is incorrect.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.field', 'password')
        ->assertJsonPath('errors.0.code', 'invalid_password');

    expect(session()->has('authkit.confirmed.password_at'))->toBeFalse();
});

it('returns a standardized action result for valid password confirmation', function (): void {
    $user = ConfirmPasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('secret-123'),
        'email_verified_at' => now(),
    ]);

    /** @var ConfirmPasswordAction $action */
    $action = app(ConfirmPasswordAction::class);

    /** @var Session $session */
    $session = app('session.store');
    $session->put('authkit.confirmation.intended', route('test.sensitive.area'));
    $session->put('authkit.confirmation.type', 'password');

    $result = $action->handle(
        user: $user,
        data: MappedPayloadBuilder::build('confirm_password', [
            'password' => 'secret-123',
        ]),
        session: $session,
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->payload?->get('confirmed'))->toBeTrue()
        ->and($result->payload?->get('type'))->toBe('password')
        ->and($result->redirect?->type)->toBe('url')
        ->and($result->redirect?->target)->toBe(route('test.sensitive.area'));
});

it('persists mapper-approved confirmation attributes when the model supports mapped persistence', function (): void {
    Config::set('authkit.mappers.contexts.confirm_password.class', PersistingConfirmPasswordMapper::class);

    $user = ConfirmPasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('secret-123'),
        'email_verified_at' => now(),
    ]);

    /** @var ConfirmPasswordAction $action */
    $action = app(ConfirmPasswordAction::class);

    /** @var Session $session */
    $session = app('session.store');

    $result = $action->handle(
        user: $user,
        data: MappedPayloadBuilder::build('confirm_password', [
            'password' => '  secret-123  ',
        ]),
        session: $session,
    );

    expect($result->ok)->toBeTrue();

    $user->refresh();

    expect($user->last_confirmed_password_input)->toBe('secret-123');
});

/**
 * ConfirmPasswordControllerTestUser
 *
 * Minimal Eloquent user model used for authenticated password confirmation tests.
 */
final class ConfirmPasswordControllerTestUser extends BaseUser
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
    protected $hidden = ['password'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}

/**
 * PersistingConfirmPasswordMapper
 *
 * Test-only mapper that marks confirm-password attributes as persistable.
 */
final class PersistingConfirmPasswordMapper extends AbstractPayloadMapper
{
    public function context(): string
    {
        return 'confirm_password';
    }

    public function mode(): string
    {
        return self::MODE_MERGE;
    }

    public function definitions(): array
    {
        return [
            'password_persist' => [
                'source' => 'password',
                'target' => 'last_confirmed_password_input',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'trim',
            ],
        ];
    }
}