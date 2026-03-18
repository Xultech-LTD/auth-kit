<?php

namespace Xul\AuthKit\Tests\Feature\Api\EmailVerification;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Xul\AuthKit\Actions\EmailVerification\SendEmailVerificationAction;
use Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
use Xul\AuthKit\Contracts\TokenRepositoryContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Events\AuthKitEmailVerificationRequired;
use Xul\AuthKit\Http\Controllers\Api\EmailVerification\SendEmailVerificationController;
use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
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
        $table->string('password')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('last_verification_email')->nullable();
        $table->timestamps();
    });

    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => SendEmailVerificationTestUser::class,
    ]);

    Config::set('authkit.auth.guard', 'web');
    Config::set('authkit.email_verification.driver', 'link');
    Config::set('authkit.email_verification.ttl_minutes', 30);
    Config::set('authkit.email_verification.columns.verified_at', 'email_verified_at');

    Config::set('authkit.route_names.api.send_verification', 'authkit.api.email.verification.send');
    Config::set('authkit.route_names.web.verify_notice', 'authkit.web.email.verify.notice');
    Config::set('authkit.route_names.web.verify_link', 'authkit.web.email.verification.verify.link');

    app()->singleton(TokenRepositoryContract::class, fn () => new ArrayTokenRepository());

    Route::middleware(['web'])->group(function (): void {
        Route::post('/authkit/email/verification/send', SendEmailVerificationController::class)
            ->name('authkit.api.email.verification.send');

        Route::get('/authkit/email/verify/notice', fn () => 'verify-notice')
            ->name('authkit.web.email.verify.notice');

        Route::get('/authkit/email/verify/link/{id}/{hash}', fn () => 'verify-link')
            ->name('authkit.web.email.verification.verify.link');
    });
});

it('resends verification successfully using token driver for json requests', function (): void {
    Event::fake([AuthKitEmailVerificationRequired::class]);

    config()->set('authkit.email_verification.driver', 'token');
    config()->set('authkit.email_verification.ttl_minutes', 5);

    $email = 'meritinfos@gmail.com';

    $user = SendEmailVerificationTestUser::query()->create([
        'name' => 'Michael',
        'email' => $email,
        'email_verified_at' => null,
    ]);

    $response = $this->postJson(route('authkit.api.email.verification.send'), [
        'email' => $email,
    ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Verification message sent.',
        ])
        ->assertJsonPath('flow.name', 'email_verification_notice')
        ->assertJsonPath('payload.email', mb_strtolower($email))
        ->assertJsonPath('payload.driver', 'token')
        ->assertJsonPath('redirect.target', 'authkit.web.email.verify.notice')
        ->assertJsonMissingPath('token')
        ->assertJsonMissingPath('verify_url');

    $issuedToken = null;

    Event::assertDispatched(AuthKitEmailVerificationRequired::class, function (AuthKitEmailVerificationRequired $event) use ($user, $email, &$issuedToken): bool {
        $issuedToken = $event->token;

        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->email === mb_strtolower($email)
            && $event->driver === 'token'
            && $event->ttlMinutes === 5
            && is_string($event->token)
            && $event->token !== ''
            && $event->url === null;
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

it('resends verification successfully using link driver for json requests', function (): void {
    Event::fake([AuthKitEmailVerificationRequired::class]);

    config()->set('authkit.email_verification.driver', 'link');
    config()->set('authkit.email_verification.ttl_minutes', 5);

    $email = 'meritinfos@gmail.com';

    $user = SendEmailVerificationTestUser::query()->create([
        'name' => 'Michael',
        'email' => $email,
        'email_verified_at' => null,
    ]);

    $response = $this->postJson(route('authkit.api.email.verification.send'), [
        'email' => $email,
    ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Verification message sent.',
        ])
        ->assertJsonPath('flow.name', 'email_verification_notice')
        ->assertJsonPath('payload.email', mb_strtolower($email))
        ->assertJsonPath('payload.driver', 'link')
        ->assertJsonPath('redirect.target', 'authkit.web.email.verify.notice')
        ->assertJsonMissingPath('token')
        ->assertJsonMissingPath('verify_url');

    $issuedUrl = null;
    $issuedToken = null;

    Event::assertDispatched(AuthKitEmailVerificationRequired::class, function (AuthKitEmailVerificationRequired $event) use ($user, $email, &$issuedUrl, &$issuedToken): bool {
        $issuedUrl = $event->url;
        $issuedToken = $event->token;

        return (string) $event->user->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            && $event->email === mb_strtolower($email)
            && $event->driver === 'link'
            && $event->ttlMinutes === 5
            && is_string($event->token)
            && $event->token !== ''
            && is_string($event->url)
            && $event->url !== '';
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

it('returns a standardized action result from send email verification action', function (): void {
    Event::fake([AuthKitEmailVerificationRequired::class]);

    config()->set('authkit.email_verification.driver', 'token');

    SendEmailVerificationTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'meritinfos@gmail.com',
        'email_verified_at' => null,
    ]);

    /** @var SendEmailVerificationAction $action */
    $action = app(SendEmailVerificationAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('email_verification_send', [
            'email' => 'meritinfos@gmail.com',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue()
        ->and($result->status)->toBe(200)
        ->and($result->flow?->is('email_verification_notice'))->toBeTrue()
        ->and($result->payload?->get('email'))->toBe('meritinfos@gmail.com')
        ->and($result->payload?->get('driver'))->toBe('token')
        ->and($result->redirect?->target)->toBe('authkit.web.email.verify.notice');
});

it('returns success when the email is already verified', function (): void {
    Event::fake([AuthKitEmailVerificationRequired::class]);

    SendEmailVerificationTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'verified@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->postJson(route('authkit.api.email.verification.send'), [
        'email' => 'verified@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
            'message' => 'Your email is already verified.',
        ])
        ->assertJsonPath('flow.name', 'completed')
        ->assertJsonPath('payload.email', 'verified@example.com')
        ->assertJsonPath('payload.already_verified', true);

    Event::assertNotDispatched(AuthKitEmailVerificationRequired::class);
});

it('returns 403 when authenticated user attempts resend for another email', function (): void {
    Event::fake([AuthKitEmailVerificationRequired::class]);

    $sessionUser = SendEmailVerificationTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'owner@example.com',
        'email_verified_at' => null,
    ]);

    SendEmailVerificationTestUser::query()->create([
        'name' => 'Other',
        'email' => 'other@example.com',
        'email_verified_at' => null,
    ]);

    $this->actingAs($sessionUser, 'web');

    $response = $this->postJson(route('authkit.api.email.verification.send'), [
        'email' => 'other@example.com',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'ok' => false,
            'status' => 403,
            'message' => 'Invalid email verification context.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'invalid_email_verification_context');

    Event::assertNotDispatched(AuthKitEmailVerificationRequired::class);
});

it('returns 404 when the email does not belong to any user', function (): void {
    Event::fake([AuthKitEmailVerificationRequired::class]);

    $response = $this->postJson(route('authkit.api.email.verification.send'), [
        'email' => 'missing@example.com',
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'ok' => false,
            'status' => 404,
            'message' => 'We could not find a user with that email address.',
        ])
        ->assertJsonPath('flow.name', 'failed')
        ->assertJsonPath('errors.0.code', 'email_verification_user_not_found');

    Event::assertNotDispatched(AuthKitEmailVerificationRequired::class);
});

it('persists mapper-approved resend attributes when the model supports mapped persistence', function (): void {
    Event::fake([AuthKitEmailVerificationRequired::class]);

    config()->set(
        'authkit.mappers.contexts.email_verification_send.class',
        PersistingSendEmailVerificationMapper::class
    );

    $user = SendEmailVerificationTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'meritinfos@gmail.com',
        'email_verified_at' => null,
    ]);

    /** @var SendEmailVerificationAction $action */
    $action = app(SendEmailVerificationAction::class);

    $result = $action->handle(
        MappedPayloadBuilder::build('email_verification_send', [
            'email' => '  MERITINFOS@GMAIL.COM  ',
        ])
    );

    expect($result)->toBeInstanceOf(AuthKitActionResult::class)
        ->and($result->ok)->toBeTrue();

    $user->refresh();

    expect($user->last_verification_email)->toBe('meritinfos@gmail.com');
});

it('returns standardized DTO validation response for JSON send email verification requests', function (): void {
    $response = $this->postJson(route('authkit.api.email.verification.send'), []);

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

it('normalizes email before validation for JSON send email verification requests', function (): void {
    $response = $this->postJson(route('authkit.api.email.verification.send'), [
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

it('redirects back with errors for invalid SSR send email verification requests', function (): void {
    $response = $this->from(route('authkit.web.email.verify.notice'))
        ->post(route('authkit.api.email.verification.send'), []);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['email']);
});

/**
 * SendEmailVerificationTestUser
 *
 * Minimal user model for package tests.
 */
final class SendEmailVerificationTestUser extends BaseUser
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
}

/**
 * PersistingSendEmailVerificationMapper
 *
 * Test-only mapper that keeps the package default resend email mapping while
 * adding an extra persistable target sourced from the same validated email.
 */
final class PersistingSendEmailVerificationMapper extends AbstractPayloadMapper
{
    public function context(): string
    {
        return 'email_verification_send';
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
                'target' => 'last_verification_email',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'lower_trim',
            ],
        ];
    }
}