<?php

/**
 * RegisterPageTest
 *
 * Ensures the register page is accessible to guests and renders expected content.
 */
it('renders the register page for guests', function () {

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['register'] ?? 'authkit.web.register');

    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('Create account');
});