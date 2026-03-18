<?php

namespace Xul\AuthKit\Tests\Feature\Api\PasswordReset;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Actions\PasswordReset\RequestPasswordResetAction;
use Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUrlGeneratorContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Events\AuthKitPasswordResetRequested;
use Xul\AuthKit\Http\Controllers\Api\PasswordReset\ForgotPasswordController;
use Xul\AuthKit\Support\CacheTokenRepository;
use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\PendingPasswordReset;

uses(RefreshDatabase::class);

final class ForgotPasswordControllerTest extends BaseUser
{
    use Notifiable;
    use HasAuthKitMappedPersistence;

    /**
     * @var string
     */
    protected $table = 'users';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'last_reset_request_email',
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
        $table->string('last_reset_request_email')->nullable();
        $table->timestamps();
    });

    Route::get('/forgot-password', fn () => 'forgot')->name('authkit.web.password.forgot');
    Route::get('/forgot-password/sent', fn () => 'sent')->name('authkit.web.password.forgot.sent');
    Route::get('/reset-password/token', fn () => 'token')->name('authkit.web.password.reset.token');

    Route::post('/forgot-password', ForgotPasswordController::class)
        ->middleware(['web'])
        ->name('authkit.api.password.forgot');

    config()->set('authkit.password_reset.driver', 'link');
    config()->set('authkit.password_reset.ttl_minutes', 30);
    config()->set('authkit.password_reset.privacy.hide_user_existence', true);
    config()->set(
        'authkit.password_reset.privacy.generic_message',
        'If an account exists for this email, password reset instructions have been sent.'
    );
    config()->set('authkit.route_names.web.password_forgot', 'authkit.web.password.forgot');
    config()->set('authkit.route_names.web.password_forgot_sent', 'authkit.web.password.forgot.sent');
    config()->set('authkit.route_names.api.password_send_reset', 'authkit.api.password.forgot');

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

    app()->instance(PasswordResetUrlGeneratorContract::class, new class implements PasswordResetUrlGeneratorContract {
        public function make(string $email, string $token): string
        {
            return 'https://example.test/reset?email=' . urlencode($email) . '&token=' . urlencode($token);
        }
    });
});

it('privacy mode returns generic success when user does not exist and does not dispatch event', function () {
    Event::fake();

    app()->instance(PasswordResetUserResolverContract::class, new class implements PasswordResetUserResolverContract {
        public function resolve(string $identityValue): ?Authenticatable
        {
            return null;
        }
    });

    /** @var RequestPasswordResetAction $action */
    $action = app(RequestPasswordResetAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('password_forgot', [
            'email' => 'missing@example.com',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->message)->toBe('If an account exists for this email, password reset instructions have been sent.')
        ->and($result->payload?->get('driver'))->toBe('link')
        ->and($result->payload?->get('privacy_mode'))->toBeTrue();

    Event::assertNotDispatched(AuthKitPasswordResetRequested::class);

    expect(app(PendingPasswordReset::class)->hasPendingForEmail('missing@example.com'))->toBeFalse();
});

it('privacy mode returns generic success when user exists creates pending presence and dispatches event', function () {
    Event::fake();

    $user = ForgotPasswordControllerTest::query()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret'),
    ]);

    app()->instance(PasswordResetUserResolverContract::class, new class($user) implements PasswordResetUserResolverContract {
        public function __construct(private Authenticatable $user) {}

        public function resolve(string $identityValue): ?Authenticatable
        {
            return $this->user;
        }
    });

    /** @var RequestPasswordResetAction $action */
    $action = app(RequestPasswordResetAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('password_forgot', [
            'email' => 'jane@example.com',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->message)->toBe('If an account exists for this email, password reset instructions have been sent.')
        ->and($result->payload?->get('driver'))->toBe('link')
        ->and($result->payload?->get('privacy_mode'))->toBeTrue();

    expect(app(PendingPasswordReset::class)->hasPendingForEmail('jane@example.com'))->toBeTrue();

    Event::assertDispatched(AuthKitPasswordResetRequested::class, function (AuthKitPasswordResetRequested $event) use ($user) {
        expect($event->driver)->toBe('link')
            ->and($event->email)->toBe('jane@example.com')
            ->and($event->token)->toBeString()->not->toBe('')
            ->and($event->url)->toBeString()->not->toBe('')
            ->and($event->user)->not->toBeNull();

        expect($event->user?->getAuthIdentifier())->toBe($user->getAuthIdentifier());

        return true;
    });
});

it('non privacy mode returns explicit failure when user does not exist', function () {
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

    $result = $action->handle(
        MappedPayloadBuilder::build('password_forgot', [
            'email' => 'missing@example.com',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeFalse()
        ->and($result->status)->toBe(404)
        ->and($result->message)->toBe('We could not find an account with that email address.')
        ->and($result->errors[0]->code)->toBe('password_reset_user_not_found');

    Event::assertNotDispatched(AuthKitPasswordResetRequested::class);
    expect(app(PendingPasswordReset::class)->hasPendingForEmail('missing@example.com'))->toBeFalse();
});

it('non privacy mode returns explicit success and token page redirect when configured', function () {
    Event::fake();

    config()->set('authkit.password_reset.privacy.hide_user_existence', false);
    config()->set('authkit.password_reset.driver', 'token');
    config()->set('authkit.password_reset.post_request.mode', 'token_page');
    config()->set('authkit.password_reset.post_request.token_route', 'authkit.web.password.reset.token');

    $user = ForgotPasswordControllerTest::query()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret'),
    ]);

    app()->instance(PasswordResetUserResolverContract::class, new class($user) implements PasswordResetUserResolverContract {
        public function __construct(private Authenticatable $user) {}

        public function resolve(string $identityValue): ?Authenticatable
        {
            return $this->user;
        }
    });

    /** @var RequestPasswordResetAction $action */
    $action = app(RequestPasswordResetAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('password_forgot', [
            'email' => 'jane@example.com',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->message)->toBe('A password reset code has been sent.')
        ->and($result->payload?->get('driver'))->toBe('token')
        ->and($result->payload?->get('privacy_mode'))->toBeFalse()
        ->and($result->redirect?->target)->toBe('authkit.web.password.reset.token')
        ->and($result->redirect?->parameters)->toBe(['email' => 'jane@example.com']);

    Event::assertDispatched(AuthKitPasswordResetRequested::class);
});

it('persists mapper-approved forgot-password attributes when the resolved model supports mapped persistence', function () {
    Event::fake();

    config()->set('authkit.mappers.contexts.password_forgot.class', PersistingForgotPasswordMapper::class);

    $user = ForgotPasswordControllerTest::query()->create([
        'email' => 'persist-reset@example.com',
        'password' => bcrypt('secret'),
    ]);

    app()->instance(PasswordResetUserResolverContract::class, new class($user) implements PasswordResetUserResolverContract {
        public function __construct(private Authenticatable $user) {}

        public function resolve(string $identityValue): ?Authenticatable
        {
            return $this->user;
        }
    });

    /** @var RequestPasswordResetAction $action */
    $action = app(RequestPasswordResetAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('password_forgot', [
            'email' => '  PERSIST-RESET@EXAMPLE.COM  ',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue();

    $user->refresh();

    expect($user->last_reset_request_email)->toBe('persist-reset@example.com');
});

it('returns standardized DTO validation response for JSON forgot password requests', function () {
    $response = $this->postJson(route('authkit.api.password.forgot'), []);

    $response->assertStatus(422)
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

it('normalizes email before validation for JSON forgot password requests', function () {
    $response = $this->postJson(route('authkit.api.password.forgot'), [
        'email' => '  NOT-AN-EMAIL  ',
    ]);

    $response->assertStatus(422)
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

/**
 * PersistingForgotPasswordMapper
 *
 * Test-only mapper that keeps the package default forgot-password field while
 * adding a persistable target sourced from the same validated input.
 */
final class PersistingForgotPasswordMapper extends AbstractPayloadMapper
{
    public function context(): string
    {
        return 'password_forgot';
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
                'target' => 'last_reset_request_email',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'lower_trim',
            ],
        ];
    }
}