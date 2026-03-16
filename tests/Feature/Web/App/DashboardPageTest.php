<?php

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * DashboardPageTest
 *
 * Ensures the authenticated dashboard page is protected and renders
 * the configured default packaged dashboard content.
 */

if (! class_exists(TestAuthKitUser::class, false)) {
    class TestAuthKitUser extends Authenticatable
    {
        protected $table = 'users';

        protected $guarded = [];
    }
}

it('redirects guests away from the dashboard page', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['dashboard_web'] ?? 'authkit.web.dashboard');

    $this->get(route($routeName))
        ->assertRedirect();
});

it('renders the dashboard page for authenticated users', function () {
    $user = new TestAuthKitUser();
    $user->id = 1;
    $user->name = 'Michael';
    $user->email = 'michael@example.com';

    $appPages = (array) config('authkit.app.pages', []);
    $page = (array) ($appPages['dashboard_web'] ?? []);

    $expectedHeading = (string) ($page['heading'] ?? 'Account overview');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['dashboard_web'] ?? 'authkit.web.dashboard');

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee($expectedHeading)
        ->assertSee('Overview')
        ->assertSee('Quick links')
        ->assertSee('You are signed in successfully')
        ->assertSee('Welcome back, Michael');
});