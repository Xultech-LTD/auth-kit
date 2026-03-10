<?php

namespace Xul\AuthKit\Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Events\AuthKitLoggedOut;
use Xul\AuthKit\Http\Controllers\Api\Auth\LogoutController;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Config::set('database.default', 'testing');
    Config::set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => \Xul\AuthKit\Tests\Feature\Api\LogoutTest::class,
    ]);

    Config::set('authkit.auth.guard', 'web');
    Config::set('authkit.route_names.web.login', 'authkit.web.login');

    Route::post('/authkit/logout', LogoutController::class)
        ->middleware(['web'])
        ->name('authkit.api.auth.logout');

    Route::get('/authkit/login', fn () => 'login')
        ->name('authkit.web.login');
});

it('logs out and returns json when request expects json', function () {
    Event::fake();

    $user = \Xul\AuthKit\Tests\Feature\Api\LogoutTest::query()->create([
        'email' => 'michael@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $this->actingAs($user, 'web');

    $response = $this->postJson(route('authkit.api.auth.logout'));

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Logged out.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('redirect.target', 'authkit.web.login')
        ->assertJsonPath('payload.guard', 'web');

    expect(auth('web')->check())->toBeFalse();

    Event::assertDispatched(AuthKitLoggedOut::class, function (AuthKitLoggedOut $event) use ($user) {
        return $event->guard === 'web'
            && $event->user !== null
            && (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier();
    });
});

it('logs out and redirects to login route for non-json requests', function () {
    Event::fake();

    $user = \Xul\AuthKit\Tests\Feature\Api\LogoutTest::query()->create([
        'email' => 'michael@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $this->actingAs($user, 'web');

    $response = $this->post(route('authkit.api.auth.logout'));

    $response->assertRedirect(route('authkit.web.login'))
        ->assertSessionHas('status', 'Logged out.');

    expect(auth('web')->check())->toBeFalse();

    Event::assertDispatched(AuthKitLoggedOut::class, function (AuthKitLoggedOut $event) use ($user) {
        return $event->guard === 'web'
            && $event->user !== null
            && (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier();
    });
});

it('returns 401 when logout is called without an authenticated user', function () {
    Event::fake();

    $response = $this->postJson(route('authkit.api.auth.logout'));

    $response->assertStatus(401)
        ->assertJson([
            'ok' => false,
            'status' => 401,
            'message' => 'Unauthenticated.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'unauthenticated');

    Event::assertNotDispatched(AuthKitLoggedOut::class);
});

/**
 * LogoutActionResultTest
 *
 * Ensures the standardized logout action result contract is returned.
 */
it('returns a standardized action result from logout action', function () {
    $user = \Xul\AuthKit\Tests\Feature\Api\LogoutTest::query()->create([
        'email' => 'michael@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $this->actingAs($user, 'web');

    $action = app(\Xul\AuthKit\Actions\Auth\LogoutAction::class);

    $result = $action->handle();

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->message)->toBe('Logged out.')
        ->and($result->flow?->is('completed'))->toBeTrue()
        ->and($result->redirect?->target)->toBe('authkit.web.login')
        ->and($result->payload?->get('guard'))->toBe('web');
});

it('redirects to login with error when logout is called without an authenticated user for non-json requests', function () {
    Event::fake();

    $response = $this->post(route('authkit.api.auth.logout'));

    $response->assertRedirect(route('authkit.web.login'))
        ->assertSessionHas('error', 'Unauthenticated.');

    Event::assertNotDispatched(AuthKitLoggedOut::class);
});

/**
 * TestUser
 *
 * Minimal user model for package tests.
 */
final class LogoutTest extends BaseUser
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
}