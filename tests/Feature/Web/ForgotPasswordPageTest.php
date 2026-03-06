<?php

/**
 * ForgotPasswordPageTest
 *
 * Ensures the forgot password page renders for guests.
 */
it('renders the forgot password page for guests', function () {

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');

    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('Forgot password');
});