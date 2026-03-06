<?php

namespace Xul\AuthKit\Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;
use Xul\AuthKit\Events\AuthKitRegistered;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }
});

/**
 * RegisterFlowTest (API)
 *
 * Covers:
 * - Controller JSON response
 * - RegisterAction behavior
 * - Event dispatch (AuthKitRegistered, AuthKitEmailVerificationRequired)
 * - Token persistence
 * - Security: response must not include raw token or verification URL
 */
it('registers successfully using token driver (API JSON)', function (): void {
    Event::fake([
        AuthKitRegistered::class,
        AuthKitEmailVerificationRequired::class,
    ]);

    config()->set('authkit.email_verification.driver', 'token');
    config()->set('authkit.email_verification.ttl_minutes', 5);

    $route = (string) config('authkit.route_names.api.register', 'authkit.api.auth.register');

    $email = 'meritinfos@gmail.com';

    $response = $this->postJson(route($route), [
        'name' => 'Michael API',
        'email' => $email,
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ]);

    $response->assertStatus(201);

    $data = $response->json();

    expect($data)
        ->and(data_get($data, 'ok'))->toBeTrue()
        ->and(data_get($data, 'email'))->toBe(mb_strtolower($email))
        ->and(data_get($data, 'token'))->toBeNull()
        ->and(data_get($data, 'verify_url'))->toBeNull();

    Event::assertDispatched(AuthKitRegistered::class);

    $issuedToken = null;

    Event::assertDispatched(AuthKitEmailVerificationRequired::class, function (AuthKitEmailVerificationRequired $e) use ($email, &$issuedToken): bool {
        $issuedToken = $e->token;

        return $e->driver === 'token'
            && $e->email === mb_strtolower($email)
            && $e->url === null
            && is_string($e->token) && $e->token !== '';
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

    $email = 'meritinfos@gmail.com';

    $response = $this->postJson(route($route), [
        'name' => 'Michael API Link',
        'email' => $email,
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ]);

    $response->assertStatus(201);

    $data = $response->json();

    expect($data)
        ->and(data_get($data, 'ok'))->toBeTrue()
        ->and(data_get($data, 'email'))->toBe(mb_strtolower($email))
        ->and(data_get($data, 'token'))->toBeNull()
        ->and(data_get($data, 'verify_url'))->toBeNull();

    Event::assertDispatched(AuthKitRegistered::class);

    $issuedUrl = null;
    $issuedToken = null;

    Event::assertDispatched(AuthKitEmailVerificationRequired::class, function (AuthKitEmailVerificationRequired $e) use ($email, &$issuedUrl, &$issuedToken): bool {
        $issuedUrl = $e->url;
        $issuedToken = $e->token;

        return $e->driver === 'link'
            && $e->email === mb_strtolower($email)
            && is_string($e->url) && $e->url !== ''
            && is_string($e->token) && $e->token !== '';
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