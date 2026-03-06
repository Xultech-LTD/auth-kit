<?php

namespace Xul\AuthKit\Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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

    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('email')->unique();
        $t->string('password');
        $t->rememberToken();
        $t->timestamps();
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
        ->middleware(['web', 'auth'])
        ->name('authkit.api.auth.logout');

    Route::get('/authkit/login', fn() => 'login')->name('authkit.web.login');
});

it('logs out and returns json when request expects json', function () {
    Event::fake();

    $user = \Xul\AuthKit\Tests\Feature\Api\LogoutTest::query()->create([
        'email' => 'michael@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $this->actingAs($user, 'web');

    $res = $this->postJson(route('authkit.api.auth.logout'));

    $res->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
        ]);

    expect(auth('web')->check())->toBeFalse();

    Event::assertDispatched(AuthKitLoggedOut::class, function ($e) use ($user) {
        return $e->guard === 'web'
            && $e->user !== null
            && (string)$e->user->getAuthIdentifier() === (string)$user->getAuthIdentifier();
    });
});

it('logs out and redirects to login route for non-json requests', function () {
    Event::fake();

    $user = \Xul\AuthKit\Tests\Feature\Api\LogoutTest::query()->create([
        'email' => 'michael@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $this->actingAs($user, 'web');

    $res = $this->post(route('authkit.api.auth.logout'));

    $res->assertRedirect(route('authkit.web.login'));

    expect(auth('web')->check())->toBeFalse();

    Event::assertDispatched(AuthKitLoggedOut::class, function ($e) use ($user) {
        return $e->guard === 'web'
            && $e->user !== null
            && (string)$e->user->getAuthIdentifier() === (string)$user->getAuthIdentifier();
    });
});

it('returns 401 when logout is called without an authenticated user', function () {
    Event::fake();

    $res = $this->postJson(route('authkit.api.auth.logout'));

    $res->assertStatus(401);

    Event::assertNotDispatched(AuthKitLoggedOut::class);
});

/**
 * TestUser
 *
 * Minimal user model for package tests.
 */
final class LogoutTest extends BaseUser
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];
}