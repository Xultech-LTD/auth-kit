<?php
// file: tests/Feature/Api/App/Settings/UpdatePasswordControllerTest.php

namespace Xul\AuthKit\Tests\Feature\Api\App\Settings;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Xul\AuthKit\Concerns\Model\HasAuthKitTwoFactor;

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
        $table->boolean('two_factor_enabled')->default(false);
        $table->text('two_factor_secret')->nullable();
        $table->json('two_factor_recovery_codes')->nullable();
        $table->json('two_factor_methods')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('last_password_update_current_password')->nullable();
        $table->timestamps();
    });

    /**
     * Auth configuration used by the authenticated app routes.
     */
    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards.web', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    Config::set('auth.providers.users', [
        'driver' => 'eloquent',
        'model' => UpdatePasswordControllerTestUser::class,
    ]);

    Config::set('authkit.auth.guard', 'web');

    /**
     * Route names used by the controller.
     */
    Config::set('authkit.route_names.web.security', 'authkit.web.settings.security');
    Config::set('authkit.route_names.api.password_update', 'authkit.api.settings.password.update');

    /**
     * Password update schema used by the request.
     */
    Config::set('authkit.schemas.password_update', [
        'submit' => [
            'label' => 'Update password',
        ],
        'fields' => [
            'current_password' => [
                'label' => 'Current password',
                'type' => 'password',
                'required' => true,
                'autocomplete' => 'current-password',
                'attributes' => [],
                'wrapper' => [
                    'class' => 'authkit-field',
                ],
            ],
            'password' => [
                'label' => 'New password',
                'type' => 'password',
                'required' => true,
                'autocomplete' => 'new-password',
                'attributes' => [],
                'wrapper' => [
                    'class' => 'authkit-field',
                ],
            ],
            'password_confirmation' => [
                'label' => 'Confirm new password',
                'type' => 'password',
                'required' => true,
                'autocomplete' => 'new-password',
                'attributes' => [],
                'wrapper' => [
                    'class' => 'authkit-field',
                ],
            ],
            'logout_other_devices' => [
                'label' => 'Logout Other loggedin Devices',
                'type' => 'checkbox',
                'checked' => true,
                'attributes' => [],
                'wrapper' => [
                    'class' => 'authkit-field authkit-field--checkbox',
                ],
            ],
        ],
    ]);

    /**
     * Validation provider slots for this context.
     */
    Config::set('authkit.validation.providers.password_update', null);

    /**
     * Simple fallback routes referenced by redirect logic.
     */
    Route::middleware('web')->group(function (): void {
        Route::get('/authkit/settings/security', fn () => 'security-page')
            ->name('authkit.web.settings.security');

        Route::post(
            '/authkit/settings/password/update/process',
            \Xul\AuthKit\Http\Controllers\Api\App\Settings\UpdatePasswordController::class
        )
            ->middleware('auth:web')
            ->name('authkit.api.settings.password.update');
    });
});

it('updates the password and redirects back to the security page for a normal web request', function (): void {
    $user = UpdatePasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('old-password'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->from(route('authkit.web.settings.security'))
        ->post(route('authkit.api.settings.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password-123Me!',
            'password_confirmation' => 'new-password-123Me!',
            'logout_other_devices' => '1',
        ]);

    $response
        ->assertRedirect(route('authkit.web.settings.security'))
        ->assertSessionHas('status');

    $user->refresh();

    expect(Hash::check('new-password-123Me!', $user->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->password))->toBeFalse();
});

it('updates the password and returns a standardized json response for json requests', function (): void {
    $user = UpdatePasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('old-password'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password-123Me!',
            'password_confirmation' => 'new-password-123Me!',
            'logout_other_devices' => true,
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 200,
        ])
        ->assertJsonPath('flow.name', 'completed');

    $user->refresh();

    expect(Hash::check('new-password-123Me!', $user->password))->toBeTrue();
});

it('returns validation errors for missing fields on normal web requests', function (): void {
    $user = UpdatePasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('old-password'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->from(route('authkit.web.settings.security'))
        ->post(route('authkit.api.settings.password.update'), []);

    $response
        ->assertRedirect(route('authkit.web.settings.security'))
        ->assertSessionHasErrors([
            'current_password',
            'password',
        ]);
});

it('returns validation errors for password confirmation mismatch on normal web requests', function (): void {
    $user = UpdatePasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('old-password'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->from(route('authkit.web.settings.security'))
        ->post(route('authkit.api.settings.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'wrong-confirmation',
        ]);

    $response
        ->assertRedirect(route('authkit.web.settings.security'))
        ->assertSessionHasErrors([
            'password',
        ]);
});

it('returns standardized dto validation errors for json requests', function (): void {
    $user = UpdatePasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('old-password'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.password.update'), []);

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

    expect($response->json('payload.fields.current_password.0'))->toBeString()
        ->and($response->json('payload.fields.password.0'))->toBeString();
});

it('does not update the password when the current password is incorrect', function (): void {
    $user = UpdatePasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('old-password'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->from(route('authkit.web.settings.security'))
        ->post(route('authkit.api.settings.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

    expect(in_array($response->getStatusCode(), [302, 422], true))->toBeTrue();

    $user->refresh();

    expect(Hash::check('old-password', $user->password))->toBeTrue()
        ->and(Hash::check('new-password-123', $user->password))->toBeFalse();
});

it('persists mapper-approved password update attributes when the model supports mapped persistence', function (): void {
    Config::set('authkit.mappers.contexts.password_update.class', PersistingUpdatePasswordMapper::class);

    $user = UpdatePasswordControllerTestUser::query()->create([
        'name' => 'Michael',
        'email' => 'michael@example.com',
        'password' => Hash::make('old-password'),
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'web')
        ->postJson(route('authkit.api.settings.password.update'), [
            'current_password' => '  old-password  ',
            'password' => 'new-password-123Me!',
            'password_confirmation' => 'new-password-123Me!',
            'logout_other_devices' => true,
        ]);

    $response->assertOk();

    $user->refresh();

    expect($user->last_password_update_current_password)->toBe('old-password');
});
/**
 * UpdatePasswordControllerTestUser
 *
 * Minimal Eloquent user model used for authenticated password update tests.
 */
final class UpdatePasswordControllerTestUser extends BaseUser
{
    use HasAuthKitTwoFactor;
    use \Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;

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
        'two_factor_enabled' => 'bool',
        'two_factor_recovery_codes' => 'array',
        'two_factor_methods' => 'array',
    ];
}

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;
/**
 * PersistingUpdatePasswordMapper
 *
 * Test-only mapper that marks password-update attributes as persistable.
 */
final class PersistingUpdatePasswordMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'password_update';
    }

    /**
     * Merge this mapper with the package default password-update mapper.
     *
     * This allows the packaged default fields to remain intact while adding
     * a persistence-aware mapping for test verification.
     *
     * @return string
     */
    public function mode(): string
    {
        return self::MODE_MERGE;
    }

    /**
     * Return mapper definitions added on top of the package default mapper.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'current_password_persist' => [
                'source' => 'current_password',
                'target' => 'last_password_update_current_password',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'trim',
            ],
        ];
    }
}