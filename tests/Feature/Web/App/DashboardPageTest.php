<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;


it('renders the dashboard page for authenticated users', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['dashboard_web'] ?? 'authkit.web.dashboard');
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
        ->assertSee('Dashboard')
        ->assertSee('Welcome')
        ->assertSee('Settings')
        ->assertSee('Security');
});