<?php

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * SettingsPageTest
 *
 * Ensures the authenticated settings page is protected and renders
 * the configured default packaged settings content.
 */

if (! class_exists(TestAuthKitSettingsUser::class, false)) {
    class TestAuthKitSettingsUser extends Authenticatable
    {
        protected $table = 'users';

        protected $guarded = [];
    }
}

it('redirects guests away from the settings page', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['settings'] ?? 'authkit.web.settings');

    $this->get(route($routeName))
        ->assertRedirect();
});

it('renders the settings page for authenticated users', function () {
    $user = new TestAuthKitSettingsUser();
    $user->id = 1;
    $user->name = 'Michael';
    $user->email = 'michael@example.com';

    $appPages = (array) config('authkit.app.pages', []);
    $page = (array) ($appPages['settings'] ?? []);

    $expectedHeading = (string) ($page['heading'] ?? 'Account settings');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['settings'] ?? 'authkit.web.settings');

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee($expectedHeading)
        ->assertSee('Manage your account preferences and review the available account areas.')
        ->assertSee('Account profile')
        ->assertSee('Michael')
        ->assertSee('michael@example.com')
        ->assertSee('Security')
        ->assertSee('Two-factor authentication')
        ->assertSee('Sessions');
});