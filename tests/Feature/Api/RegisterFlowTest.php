<?php

namespace Xul\AuthKit\Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Actions\Auth\RegisterAction;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;
use Xul\AuthKit\Events\AuthKitRegistered;
use Xul\AuthKit\Http\Controllers\Api\Auth\RegisterController;
use Xul\AuthKit\Tests\Support\ArrayTokenRepository;

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
        'model' => \Xul\AuthKit\Tests\Feature\Api\RegisterTest::class,
    ]);

    Config::set('authkit.auth.guard', 'web');
    Config::set('authkit.email_verification.driver', 'link');
    Config::set('authkit.email_verification.ttl_minutes', 5);
    Config::set('authkit.route_names.api.register', 'authkit.api.auth.register');
    Config::set('authkit.route_names.web.verify_notice', 'authkit.web.email.verify.notice');
    Config::set('authkit.route_names.web.verify_link', 'authkit.web.email.verification.verify.link');

    app()->singleton(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());

    Route::post('/authkit/register', RegisterController::class)
        ->middleware(['web'])
        ->name('authkit.api.auth.register');

    Route::get('/authkit/email/verify/notice', fn () => 'verify-notice')
        ->name('authkit.web.email.verify.notice');

    Route::get('/authkit/email/verify/link/{id}/{hash}', fn () => 'verify-link')
        ->name('authkit.web.email.verification.verify.link');
});

/**
 * RegisterFlowTest
 *
 * Covers:
 * - Controller JSON response
 * - RegisterAction behavior
 * - Event dispatch
 * - Token persistence
 * - Security guarantees for API response payload
 */
it('registers successfully using token driver (API JSON)', function (): void {
    Event::fake([
        AuthKitRegistered::class,
        AuthKitEmailVerificationRequired::class,
    ]);

    config()->set('authkit.email_verification.driver', 'token');
    config()->set('authkit.email_verification.ttl_minutes', 5);

    $route = (string) config('authkit.route_names.api.register', 'authkit.api.auth.register');
    $email = 'michael@examples.com';

    $response = $this->postJson(route($route), [
        'name' => 'Michael API',
        'email' => $email,
        'password' => 'Password1234!',
        'password_confirmation' => 'Password1234!',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'ok' => true,
            'status' => 201,
            'message' => 'Account created. Please verify your email.',
        ])
        ->assertJsonPath('flow.name', 'email_verification_notice')
        ->assertJsonPath('payload.email', mb_strtolower($email))
        ->assertJsonPath('payload.driver', 'token');

    $data = $response->json();

    expect(data_get($data, 'token'))->toBeNull()
        ->and(data_get($data, 'verify_url'))->toBeNull();

    Event::assertDispatched(AuthKitRegistered::class);

    $issuedToken = null;

    Event::assertDispatched(AuthKitEmailVerificationRequired::class, function (AuthKitEmailVerificationRequired $event) use ($email, &$issuedToken): bool {
        $issuedToken = $event->token;

        return $event->driver === 'token'
            && $event->email === mb_strtolower($email)
            && $event->url === null
            && is_string($event->token)
            && $event->token !== '';
    });

    expect($issuedToken)->toBeString()->not->toBeEmpty();

    $tokens = app(TokenRepositoryContract::class);

    $peek = $tokens->peek(
        type: 'email_verification',
        identifier: mb_strtolower($email),
        token: (string) $issuedToken
    );

    expect($peek)->toBeArray();
});

it('registers successfully using link driver (API JSON)', function (): void {
    Event::fake([
        AuthKitRegistered::class,
        AuthKitEmailVerificationRequired::class,
    ]);

    config()->set('authkit.email_verification.driver', 'link');
    config()->set('authkit.email_verification.ttl_minutes', 5);

    $route = (string) config('authkit.route_names.api.register', 'authkit.api.auth.register');
    $email = 'michael@examples.com';

    $response = $this->postJson(route($route), [
        'name' => 'Michael API Link',
        'email' => $email,
        'password' => 'Password1234!',
        'password_confirmation' => 'Password1234!',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'ok' => true,
            'status' => 201,
            'message' => 'Account created. Please verify your email.',
        ])
        ->assertJsonPath('flow.name', 'email_verification_notice')
        ->assertJsonPath('payload.email', mb_strtolower($email))
        ->assertJsonPath('payload.driver', 'link');

    $data = $response->json();

    expect(data_get($data, 'token'))->toBeNull()
        ->and(data_get($data, 'verify_url'))->toBeNull();

    Event::assertDispatched(AuthKitRegistered::class);

    $issuedUrl = null;
    $issuedToken = null;

    Event::assertDispatched(AuthKitEmailVerificationRequired::class, function (AuthKitEmailVerificationRequired $event) use ($email, &$issuedUrl, &$issuedToken): bool {
        $issuedUrl = $event->url;
        $issuedToken = $event->token;

        return $event->driver === 'link'
            && $event->email === mb_strtolower($email)
            && is_string($event->url)
            && $event->url !== ''
            && is_string($event->token)
            && $event->token !== '';
    });

    expect($issuedUrl)->toBeString()->not->toBeEmpty();
    expect(URL::hasValidSignature(request()->create((string) $issuedUrl)))->toBeTrue();

    $path = parse_url((string) $issuedUrl, PHP_URL_PATH);
    $segments = array_values(array_filter(explode('/', (string) $path)));
    $hash = (string) end($segments);

    expect($hash)->toBeString()->not->toBeEmpty();
    expect($issuedToken)->toBe($hash);

    $tokens = app(TokenRepositoryContract::class);

    $peek = $tokens->peek(
        type: 'email_verification',
        identifier: mb_strtolower($email),
        token: $hash
    );

    expect($peek)->toBeArray();
});

it('returns a standardized action result from register action', function (): void {
    Event::fake([
        AuthKitRegistered::class,
        AuthKitEmailVerificationRequired::class,
    ]);

    config()->set('authkit.email_verification.driver', 'token');

    /** @var RegisterAction $action */
    $action = app(RegisterAction::class);

    $result = $action->handle([
        'name' => 'Michael DTO',
        'email' => 'michael@examples.com',
        'password' => 'Password1234!',
    ]);

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(201)
        ->and($result->flow?->is('email_verification_notice'))->toBeTrue()
        ->and($result->payload?->get('email'))->toBe('michael@examples.com')
        ->and($result->payload?->get('driver'))->toBe('token')
        ->and($result->redirect?->target)->toBe('authkit.web.email.verify.notice');
});

/**
 * TestUser
 *
 * Minimal user model for package tests.
 */
final class RegisterTest extends BaseUser
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