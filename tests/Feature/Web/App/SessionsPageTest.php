<?php

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * SessionsPageTest
 *
 * Ensures the authenticated sessions page is protected and renders
 * the configured default packaged sessions content.
 */

if (! class_exists(TestAuthKitSessionsUser::class, false)) {
    class TestAuthKitSessionsUser extends Authenticatable
    {
        protected $table = 'users';

        protected $guarded = [];
    }
}

it('redirects guests away from the sessions page', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['sessions'] ?? 'authkit.web.settings.sessions');

    $this->get(route($routeName))
        ->assertRedirect();
});

it('renders the sessions page for authenticated users', function () {
    $user = new TestAuthKitSessionsUser();
    $user->id = 1;
    $user->name = 'Michael';
    $user->email = 'michael@example.com';

    $appPages = (array) config('authkit.app.pages', []);
    $page = (array) ($appPages['sessions'] ?? []);

    $expectedHeading = (string) ($page['heading'] ?? 'Active sessions');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['sessions'] ?? 'authkit.web.settings.sessions');

    $this->actingAs($user)
        ->withServerVariables([
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
            'REMOTE_ADDR' => '127.0.0.1',
        ])
        ->get(route($routeName))
        ->assertOk()
        ->assertSee($expectedHeading)
        ->assertSee('Review devices and browser sessions associated with your account.')
        ->assertSee('Session overview')
        ->assertSee('Current and recent sessions')
        ->assertSee('Current session')
        ->assertSee('Current session', false)
        ->assertSee('This device')
        ->assertSee('Google Chrome')
        ->assertSee('Windows')
        ->assertSee('127.0.0.1')
        ->assertSee('Michael')
        ->assertSee('Security');
});