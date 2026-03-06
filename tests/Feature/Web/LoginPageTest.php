<?php

/**
 * LoginPageTest
 *
 * Ensures the login page is accessible to guests and renders expected content.
 */
it('renders the login page for guests', function () {

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['login'] ?? 'authkit.web.login');

    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('Welcome back');
});