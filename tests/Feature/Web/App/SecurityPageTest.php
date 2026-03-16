<?php

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * SecurityPageTest
 *
 * Ensures the authenticated security page is protected and renders
 * the configured default packaged security content.
 */

if (! class_exists(TestAuthKitUser::class, false)) {
    class TestAuthKitUser extends Authenticatable
    {
        protected $table = 'users';

        protected $guarded = [];
    }
}

it('redirects guests away from the security page', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['security'] ?? 'authkit.web.settings.security');

    $this->get(route($routeName))
        ->assertRedirect();
});

it('renders the security page for authenticated users', function () {
    $user = new TestAuthKitUser();
    $user->id = 1;
    $user->name = 'Michael';
    $user->email = 'michael@example.com';

    $appPages = (array) config('authkit.app.pages', []);
    $page = (array) ($appPages['security'] ?? []);
    $sections = (array) ($page['sections'] ?? []);

    $expectedHeading = (string) ($page['heading'] ?? 'Security settings');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['security'] ?? 'authkit.web.settings.security');

    $response = $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee($expectedHeading)
        ->assertSee('Security overview')
        ->assertSee('Welcome, Michael');

    if ((bool) ($sections['password_update'] ?? true)) {
        $response->assertSee('Password');
    }

    if ((bool) ($sections['two_factor'] ?? true)) {
        $response->assertSee('Two-factor authentication');
    }

    if ((bool) ($sections['recovery_codes'] ?? true)) {
        $response->assertSee('Recovery codes');
    }
});