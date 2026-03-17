<?php

use Illuminate\Foundation\Auth\User as Authenticatable;

it('renders the confirm password page for authenticated users', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $apiNames = (array) config('authkit.route_names.api', []);

    $routeName = (string) ($webNames['confirm_password'] ?? 'authkit.web.confirm.password');
    $confirmAction = (string) ($apiNames['confirm_password'] ?? 'authkit.api.confirm.password');
    $guard = (string) config('authkit.auth.guard', 'web');

    $user = new class extends Authenticatable {
        protected $attributes = [
            'name' => 'Michael',
            'email' => 'michael@example.com',
        ];
    };

    $response = $this->actingAs($user, $guard)
        ->get(route($routeName));

    $response
        ->assertOk()
        ->assertSee('Confirm your password')
        ->assertSee('For your security, please confirm your current password before continuing.')
        ->assertSee('Current password')
        ->assertSee('Confirm password')
        ->assertSee('form', false)
        ->assertSee('method="post"', false)
        ->assertSee('action="' . route($confirmAction) . '"', false);
});

it('renders session status and error messages on the confirm password page', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['confirm_password'] ?? 'authkit.web.confirm.password');
    $guard = (string) config('authkit.auth.guard', 'web');

    $user = new class extends Authenticatable {
        protected $attributes = [
            'name' => 'Michael',
            'email' => 'michael@example.com',
        ];
    };

    $response = $this
        ->actingAs($user, $guard)
        ->withSession([
            'status' => 'Password confirmation is required.',
            'error' => 'The provided password is incorrect.',
        ])
        ->get(route($routeName));

    $response
        ->assertOk()
        ->assertSee('Password confirmation is required.')
        ->assertSee('The provided password is incorrect.');
});

it('adds the authkit ajax attribute when confirm password form mode is ajax', function () {
    config()->set('authkit.forms.mode', 'ajax');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['confirm_password'] ?? 'authkit.web.confirm.password');
    $guard = (string) config('authkit.auth.guard', 'web');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');

    $user = new class extends Authenticatable {
        protected $attributes = [
            'name' => 'Michael',
            'email' => 'michael@example.com',
        ];
    };

    $response = $this->actingAs($user, $guard)
        ->get(route($routeName));

    $response
        ->assertOk()
        ->assertSee($ajaxAttr . '="1"', false);
});

it('does not add the authkit ajax attribute when confirm password form mode is http', function () {
    config()->set('authkit.forms.mode', 'http');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['confirm_password'] ?? 'authkit.web.confirm.password');
    $guard = (string) config('authkit.auth.guard', 'web');
    $ajaxAttr = (string) config('authkit.forms.ajax.attribute', 'data-authkit-ajax');

    $user = new class extends Authenticatable {
        protected $attributes = [
            'name' => 'Michael',
            'email' => 'michael@example.com',
        ];
    };

    $response = $this->actingAs($user, $guard)
        ->get(route($routeName));

    $response
        ->assertOk()
        ->assertDontSee($ajaxAttr . '="1"', false);
});

it('redirects guests away from the confirm password page', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['confirm_password'] ?? 'authkit.web.confirm.password');

    $response = $this->get(route($routeName));

    $response->assertRedirect();
});