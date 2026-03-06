<?php

it('renders the reset password success page', function () {

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['password_reset_success'] ?? 'authkit.web.password.reset.success');

    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('Password reset successful');
});