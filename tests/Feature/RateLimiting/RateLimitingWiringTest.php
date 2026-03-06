<?php
// file: tests/Feature/RateLimiting/RateLimitingWiringTest.php

namespace Xul\AuthKit\Tests\Feature\RateLimiting;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Xul\AuthKit\AuthKitServiceProvider;
use Xul\AuthKit\RateLimiting\AuthKitRateLimiterRegistrar;
use Xul\AuthKit\RateLimiting\RateLimitMiddlewareFactory;
use Xul\AuthKit\RateLimiting\RateLimiterBuilder;
use Xul\AuthKit\RateLimiting\ThrottleKeyFactory;
use Xul\AuthKit\RateLimiting\Contracts\CustomLimiterResolverContract;
use Xul\AuthKit\RateLimiting\Contracts\IdentityResolverContract;
use Xul\AuthKit\RateLimiting\Contracts\IpResolverContract;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('authkit.rate_limiting.map', [
        'login' => 'authkit.auth.login',
        'email_verify_token' => 'authkit.email.verify_token',
        'disabled' => null,
    ]);

    config()->set('authkit.rate_limiting.strategy', [
        'login' => 'dual',
        'email_verify_token' => 'per_identity',
        'unknown' => 'dual',
    ]);

    config()->set('authkit.rate_limiting.limits', [
        'login' => [
            'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
            'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
        ],
        'email_verify_token' => [
            'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
            'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
        ],
        'unknown' => [
            'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
            'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
        ],
    ]);
});

it('registers rate limiter names from config map', function (): void {
    /**
     * Ensure service provider is loaded (package-testbench usually does this, but keep explicit).
     */
    $this->app->register(AuthKitServiceProvider::class);

    /**
     * Registrar should register RateLimiter callbacks.
     */
    $this->app->make(AuthKitRateLimiterRegistrar::class)->register();

    $callback = RateLimiter::limiter('authkit.auth.login');
    expect($callback)->toBeCallable();

    $callback2 = RateLimiter::limiter('authkit.email.verify_token');
    expect($callback2)->toBeCallable();

    /**
     * Disabled mappings should not register a limiter.
     */
    $disabled = RateLimiter::limiter('disabled');
    expect($disabled)->toBeNull();
});

it('middleware factory resolves throttle middleware strings from limiter keys', function (): void {
    $factory = $this->app->make(RateLimitMiddlewareFactory::class);

    expect($factory->middlewareFor('login'))->toBe('throttle:authkit.auth.login');
    expect($factory->middlewareFor(' email_verify_token '))->toBe('throttle:authkit.email.verify_token');

    expect($factory->middlewareFor('disabled'))->toBeNull();
    expect($factory->middlewareFor(''))->toBeNull();
    expect($factory->middlewareFor('   '))->toBeNull();
});

it('builds dual strategy limits (per-ip + per-identity) when identity exists', function (): void {
    $this->app->bind(IpResolverContract::class, fn () => new class implements IpResolverContract {
        public function resolve(Request $request): string { return '203.0.113.9'; }
    });

    $this->app->bind(IdentityResolverContract::class, fn () => new class implements IdentityResolverContract {
        public function resolve(Request $request): ?string { return 'user@example.com'; }
    });

    $builder = new RateLimiterBuilder(
        keys: $this->app->make(ThrottleKeyFactory::class),
        ip: $this->app->make(IpResolverContract::class),
        identity: $this->app->make(IdentityResolverContract::class),
        challenge: $this->app->make(\Xul\AuthKit\RateLimiting\Contracts\ChallengeResolverContract::class),
    );

    $limits = $builder->build('login', Request::create('/login', 'POST', [
        'email' => 'user@example.com',
    ]));

    expect($limits)->toBeArray()->and($limits)->toHaveCount(2);
    expect($limits[0])->toBeInstanceOf(Limit::class);
    expect($limits[1])->toBeInstanceOf(Limit::class);

    RateLimiter::for('authkit.auth.login', fn () => $limits);

    $throttle = new ThrottleRequests($this->app->make(\Illuminate\Cache\RateLimiter::class));

    $r1 = Request::create('/login', 'POST', ['email' => 'user@example.com']);
    $r1->server->set('REMOTE_ADDR', '203.0.113.9');

    $response1 = $throttle->handle($r1, fn () => response('ok'), 'authkit.auth.login');
    expect($response1->getStatusCode())->toBe(200);

    /**
     * Change IP each time so the per-ip bucket never hits its limit (10/min),
     * while the per-identity bucket should hit its limit (5/min).
     *
     * ThrottleRequests throws ThrottleRequestsException when exceeded; the HTTP 429
     * is produced later by Laravel's exception handler (outside this direct call).
     */
    $attempts = 0;
    $throttledAt = null;

    for ($i = 0; $i < 10; $i++) {
        $attempts++;

        $req = Request::create('/login', 'POST', ['email' => 'user@example.com']);
        $req->server->set('REMOTE_ADDR', '198.51.100.' . ($i + 1));

        try {
            $res = $throttle->handle($req, fn () => response('ok'), 'authkit.auth.login');
            expect($res->getStatusCode())->toBe(200);
        } catch (ThrottleRequestsException $e) {
            $throttledAt = $attempts;
            break;
        }
    }

    /**
     * Per-identity attempts is 5, so throttling should occur on or before attempt 6.
     */
    expect($throttledAt)->not->toBeNull();
    expect($throttledAt)->toBeGreaterThanOrEqual(5);
    expect($throttledAt)->toBeLessThanOrEqual(6);
});

it('falls back to per-ip when strategy is per_identity and identity is missing', function (): void {
    $this->app->bind(IpResolverContract::class, fn () => new class implements IpResolverContract {
        public function resolve(Request $request): string { return '203.0.113.10'; }
    });

    $this->app->bind(IdentityResolverContract::class, fn () => new class implements IdentityResolverContract {
        public function resolve(Request $request): ?string { return null; }
    });

    $builder = new RateLimiterBuilder(
        keys: $this->app->make(ThrottleKeyFactory::class),
        ip: $this->app->make(IpResolverContract::class),
        identity: $this->app->make(IdentityResolverContract::class),
        challenge: $this->app->make(\Xul\AuthKit\RateLimiting\Contracts\ChallengeResolverContract::class),
    );

    $limits = $builder->build('email_verify_token', Request::create('/email/verify/token', 'POST', []));
    expect($limits)->toBeArray()->and($limits)->toHaveCount(1);
    expect($limits[0])->toBeInstanceOf(Limit::class);

    RateLimiter::for('authkit.email.verify_token', fn () => $limits);

    $throttle = new ThrottleRequests($this->app->make(\Illuminate\Cache\RateLimiter::class));

    /**
     * Per-ip attempts is 10/min, so requests 1..10 pass and request 11 throttles (throws).
     */
    for ($i = 1; $i <= 10; $i++) {
        $req = Request::create('/email/verify/token', 'POST', []);
        $req->server->set('REMOTE_ADDR', '203.0.113.10');

        $res = $throttle->handle($req, fn () => response('ok'), 'authkit.email.verify_token');
        expect($res->getStatusCode())->toBe(200);
    }

    $req = Request::create('/email/verify/token', 'POST', []);
    $req->server->set('REMOTE_ADDR', '203.0.113.10');

    expect(function () use ($throttle, $req): void {
        $throttle->handle($req, fn () => response('ok'), 'authkit.email.verify_token');
    })->toThrow(ThrottleRequestsException::class);
});

it('uses custom resolver when strategy is custom and normalizes its return', function (): void {
    config()->set('authkit.rate_limiting.strategy.login', 'custom');

    /**
     * Register a custom limiter resolver via config.
     */
    $this->app->bind(CustomLimiterResolverContract::class, fn () => new class implements CustomLimiterResolverContract {
        public function resolve(string $limiterKey, Request $request): Limit|array
        {
            /**
             * Return a single Limit (valid).
             */
            return Limit::perMinute(1)->by('custom|bucket');
        }
    });

    config()->set('authkit.rate_limiting.resolvers.limiter', CustomLimiterResolverContract::class);

    $builder = $this->app->make(RateLimiterBuilder::class);

    $limits = $builder->build('login', Request::create('/login', 'POST', []));
    expect($limits)->toBeArray()->and($limits)->toHaveCount(1);
    expect($limits[0])->toBeInstanceOf(Limit::class);
});

it('falls back to safe per-ip bucket when custom resolver returns invalid data', function (): void {
    config()->set('authkit.rate_limiting.strategy.login', 'custom');

    $this->app->bind(CustomLimiterResolverContract::class, fn () => new class implements CustomLimiterResolverContract {
        public function resolve(string $limiterKey, Request $request): Limit|array
        {
            /**
             * Invalid return (array of non-Limit values).
             */
            return ['nope'];
        }
    });

    config()->set('authkit.rate_limiting.resolvers.limiter', CustomLimiterResolverContract::class);

    $builder = $this->app->make(RateLimiterBuilder::class);

    $limits = $builder->build('login', Request::create('/login', 'POST', []));
    expect($limits)->toBeArray()->and($limits)->toHaveCount(1);
    expect($limits[0])->toBeInstanceOf(Limit::class);
});

it('throttle key factory never emits empty segments', function (): void {
    $keys = new ThrottleKeyFactory();

    expect($keys->make('', '', ''))->toBe('authkit|unknown|unknown|unknown');
    expect($keys->make('login', 'ip', ''))->toBe('authkit|login|ip|unknown');
});