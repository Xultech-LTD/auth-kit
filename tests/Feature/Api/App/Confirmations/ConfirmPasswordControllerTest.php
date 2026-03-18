<?php
// file: tests/Feature/Api/App/Confirmations/ConfirmPasswordControllerTest.php

namespace Xul\AuthKit\Tests\Feature\Api\App\Confirmations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

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
        $table->timestamps();
    });

    /**
     * Auth configuration used by the authenticated confirmation routes.
     */
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

    /**
     * Route names used by the controller and action.
     */
    Config::set('authkit.route_names.web.confirm_password', 'authkit.web.confirm.password');
    Config::set('authkit.route_names.web.dashboard_web', 'authkit.web.dashboard');
    Config::set('authkit.route_names.api.confirm_password', 'authkit.api.confirm.password');

    /**
     * Confirmation session keys and redirect fallback configuration.
     */
    Config::set('authkit.confirmations.session.password_key', 'authkit.confirmed.password_at');
    Config::set('authkit.confirmations.session.intended_key', 'authkit.confirmation.intended');
    Config::set('authkit.confirmations.session.type_key', 'authkit.confirmation.type');
    Config::set('authkit.confirmations.routes.fallback', 'authkit.web.dashboard');

    /**
     * Password confirmation schema used by the request.
     */
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

    /**
     * Validation provider slot for this context.
     */
    Config::set('authkit.validation.providers.confirm_password', null);

    /**
     * Simple fallback routes referenced by redirect logic.
     */
    Route::middleware('web')->group(function (): void {
        Route::get('/authkit/confirm-password', fn () => 'confirm-password-page')
            ->name('authkit.web.confirm.password');

        Route::get('/dashboard', fn () => 'dashboard-page')
            ->name('authkit.web.dashboard');

        Route::get('/sensitive-area', fn () => 'sensitive-area')
            ->name('test.sensitive.area');

        Route::post(
            '/authkit/confirm-password/process',
            \Xul\AuthKit\Http\Controllers\Api\App\Confirmations\ConfirmPasswordController::class
        )
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
        ->assertSessionHas('status');

    $session = session();

    expect($session->has('authkit.confirmed.password_at'))->toBeTrue()
        ->and($session->get('authkit.confirmed.password_at'))->toBeInt()
        ->and($session->has('authkit.confirmation.intended'))->toBeFalse()
        ->and($session->has('authkit.confirmation.type'))->toBeFalse();
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
        ->assertSessionHas('status');

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
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.confirmed', true)
        ->assertJsonPath('payload.type', 'password')
        ->assertJsonPath('redirect.type', 'url')
        ->assertJsonPath('redirect.target', route('test.sensitive.area'));

    $session = session();

    expect($session->has('authkit.confirmed.password_at'))->toBeTrue()
        ->and($session->has('authkit.confirmation.intended'))->toBeFalse()
        ->and($session->has('authkit.confirmation.type'))->toBeFalse();
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
        ->assertSessionHasErrors([
            'password',
        ]);
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
            'flow' => [
                'name' => 'failed',
            ],
        ]);

    expect($response->json('payload.fields.password.0'))->toBeString();
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
        ->assertSessionHas('error');

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
            'flow' => [
                'name' => 'failed',
            ],
        ])
        ->assertJsonPath('errors.0.field', 'password')
        ->assertJsonPath('errors.0.code', 'invalid_password');

    expect(session()->has('authkit.confirmed.password_at'))->toBeFalse();
});

/**
 * ConfirmPasswordControllerTestUser
 *
 * Minimal Eloquent user model used for authenticated password confirmation tests.
 */
final class ConfirmPasswordControllerTestUser extends BaseUser
{
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