<?php

use Illuminate\Foundation\Auth\User as Authenticatable;

it('renders the settings page for authenticated users', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['settings'] ?? 'authkit.web.settings');
    $guard = (string) config('authkit.auth.guard', 'web');

    $user = new class extends Authenticatable {
        protected $attributes = [
            'name' => 'Michael',
            'email' => 'michael@example.com',
        ];
    };

    $this->actingAs($user, $guard)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('Settings')
        ->assertSee('Settings overview')
        ->assertSee('Security')
        ->assertSee('Sessions');
});