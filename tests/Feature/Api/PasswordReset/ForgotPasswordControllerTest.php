<?php

namespace Xul\AuthKit\Tests\Feature\Api\PasswordReset;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\PasswordReset\PasswordResetRequestResult;
use Xul\AuthKit\Actions\PasswordReset\RequestPasswordResetAction;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUrlGeneratorContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\Events\AuthKitPasswordResetRequested;
use Xul\AuthKit\Support\CacheTokenRepository;
use Xul\AuthKit\Support\PendingPasswordReset;

uses(RefreshDatabase::class);

final class ForgotPasswordControllerTest extends BaseUser
{
    use Notifiable;

    protected $table = 'users';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
    ];
}

beforeEach(function () {
    /**
     * Use sqlite in-memory database for fast, isolated tests.
     */
    Config::set('database.default', 'testing');
    Config::set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    /**
     * Use array cache store so cache assertions are deterministic.
     */
    Config::set('cache.default', 'array');

    /**
     * Minimal users table for password reset resolution.
     */
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('email')->unique();
        $t->string('password');
        $t->timestamps();
    });

    /**
     * Minimal password reset configuration needed by the action.
     */
    config()->set('authkit.password_reset.driver', 'link');
    config()->set('authkit.password_reset.ttl_minutes', 30);

    /**
     * Privacy defaults:
     * - Hide user existence by default.
     * - Response message must be configurable.
     */
    config()->set('authkit.password_reset.privacy.hide_user_existence', true);
    config()->set(
        'authkit.password_reset.privacy.generic_message',
        'If an account exists for this email, password reset instructions have been sent.'
    );

    /**
     * Bind the token repository and pending reset helper exactly as AuthKit does.
     */
    app()->singleton(TokenRepositoryContract::class, function ($app) {
        return new CacheTokenRepository($app['cache']->store());
    });

    app()->singleton(PendingPasswordReset::class, function ($app) {
        return new PendingPasswordReset(
            $app->make(TokenRepositoryContract::class),
            $app['cache']->store()
        );
    });

    /**
     * Bind a permissive policy for these tests.
     */
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

    /**
     * Bind a deterministic URL generator so the test does not depend on routes.
     */
    app()->instance(PasswordResetUrlGeneratorContract::class, new class implements PasswordResetUrlGeneratorContract {
        public function make(string $email, string $token): string
        {
            return 'https://example.test/reset?email=' . urlencode($email) . '&token=' . urlencode($token);
        }
    });
});

it('privacy mode: returns generic success when user does not exist and does not dispatch event', function () {
    Event::fake();

    /**
     * Resolver returns null: simulates "user not found".
     */
    app()->instance(PasswordResetUserResolverContract::class, new class implements PasswordResetUserResolverContract {
        public function resolve(string $identityValue): ?Authenticatable
        {
            return null;
        }
    });

    /** @var RequestPasswordResetAction $action */
    $action = app(RequestPasswordResetAction::class);

    $result = $action->execute('missing@example.com');

    expect($result)->toBeInstanceOf(PasswordResetRequestResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->message)->toBe('If an account exists for this email, password reset instructions have been sent.')
        ->and($result->driver)->toBe('link');

    /**
     * No delivery event must be dispatched for unknown identities.
     * This prevents token/link delivery for non-existent users.
     */
    Event::assertNotDispatched(AuthKitPasswordResetRequested::class);

    /**
     * No pending reset presence should be created when user does not exist.
     */
    expect(app(PendingPasswordReset::class)->hasPendingForEmail('missing@example.com'))->toBeFalse();
});

it('privacy mode: returns generic success when user exists, creates pending presence, and dispatches event', function () {
    Event::fake();

    $user = ForgotPasswordControllerTest::query()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret'),
    ]);

    /**
     * Resolver returns the real user: simulates "user exists".
     */
    app()->instance(PasswordResetUserResolverContract::class, new class($user) implements PasswordResetUserResolverContract {
        public function __construct(private Authenticatable $user) {}

        public function resolve(string $identityValue): ?Authenticatable
        {
            return $this->user;
        }
    });

    /** @var RequestPasswordResetAction $action */
    $action = app(RequestPasswordResetAction::class);

    $result = $action->execute('jane@example.com');

    expect($result)->toBeInstanceOf(PasswordResetRequestResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->message)->toBe('If an account exists for this email, password reset instructions have been sent.')
        ->and($result->driver)->toBe('link');

    /**
     * Presence key should exist so UI/middleware can allow reset pages.
     */
    expect(app(PendingPasswordReset::class)->hasPendingForEmail('jane@example.com'))->toBeTrue();

    /**
     * Delivery event must be dispatched for an existing user.
     */
    Event::assertDispatched(AuthKitPasswordResetRequested::class, function (AuthKitPasswordResetRequested $event) use ($user) {
        expect($event->driver)->toBe('link')
            ->and($event->email)->toBe('jane@example.com')
            ->and($event->token)->toBeString()->not->toBe('')
            ->and($event->url)->toBeString()->not->toBe('')
            ->and($event->user)->not->toBeNull();

        // Ensure the event points to the same user instance (or equivalent).
        expect($event->user?->getAuthIdentifier())->toBe($user->getAuthIdentifier());

        return true;
    });
});

it('non-privacy mode: returns explicit failure when user does not exist', function () {
    Event::fake();

    config()->set('authkit.password_reset.privacy.hide_user_existence', false);

    app()->instance(PasswordResetUserResolverContract::class, new class implements PasswordResetUserResolverContract {
        public function resolve(string $identityValue): ?Authenticatable
        {
            return null;
        }
    });

    /** @var RequestPasswordResetAction $action */
    $action = app(RequestPasswordResetAction::class);

    $result = $action->execute('missing@example.com');

    expect($result)->toBeInstanceOf(PasswordResetRequestResult::class)
        ->and($result->ok)->toBeFalse()
        ->and($result->driver)->toBe('link')
        ->and($result->message)->toBe('We could not find an account with that email address.');

    Event::assertNotDispatched(AuthKitPasswordResetRequested::class);
    expect(app(PendingPasswordReset::class)->hasPendingForEmail('missing@example.com'))->toBeFalse();
});