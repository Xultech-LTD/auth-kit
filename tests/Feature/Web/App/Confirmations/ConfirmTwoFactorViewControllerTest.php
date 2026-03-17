<?php

use Illuminate\Foundation\Auth\User as Authenticatable;

it('renders the confirm two-factor page for authenticated users', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['confirm_two_factor'] ?? 'authkit.web.confirm.two_factor');
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
        ->assertSee('Confirm two-factor authentication')
        ->assertSee('Enter your authentication code')
        ->assertSee('Authentication code')
        ->assertSee('Confirm')
        ->assertSee('form', false)
        ->assertSee('method="post"', false)
        ->assertSee('action="' . route((string) (config('authkit.route_names.api.confirm_two_factor') ?? 'authkit.api.confirm.two_factor')) . '"', false);
});

it('renders session status and error messages on the confirm two-factor page', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['confirm_two_factor'] ?? 'authkit.web.confirm.two_factor');
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
            'status' => 'Two-factor confirmation is required.',
            'error' => 'The provided authentication code is invalid.',
        ])
        ->get(route($routeName));

    $response
        ->assertOk()
        ->assertSee('Two-factor confirmation is required.')
        ->assertSee('The provided authentication code is invalid.');
});

it('adds the authkit ajax attribute when forms mode is ajax', function () {
    config()->set('authkit.forms.mode', 'ajax');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['confirm_two_factor'] ?? 'authkit.web.confirm.two_factor');
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

it('does not add the authkit ajax attribute when forms mode is http', function () {
    config()->set('authkit.forms.mode', 'http');

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['confirm_two_factor'] ?? 'authkit.web.confirm.two_factor');
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

it('redirects guests away from the confirm two-factor page', function () {
    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['confirm_two_factor'] ?? 'authkit.web.confirm.two_factor');

    $response = $this->get(route($routeName));

    $response->assertRedirect();
});