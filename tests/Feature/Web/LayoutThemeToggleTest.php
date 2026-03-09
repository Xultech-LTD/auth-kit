<?php

/**
 * LayoutThemeToggleTest
 *
 * Ensures the packaged theme toggle is rendered by the root AuthKit layout
 * when toggle support is enabled.
 */
it('renders the packaged theme toggle on auth pages', function () {
    config()->set('authkit.ui.toggle.enabled', true);

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['login'] ?? 'authkit.web.login');

    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('data-authkit-layout-toggle="1"', false)
        ->assertSee('data-authkit-theme-toggle-cycle="1"', false);
});

/**
 * LayoutThemeToggleTest
 *
 * Ensures the packaged theme toggle can be disabled through configuration.
 */
it('does not render the packaged theme toggle when disabled', function () {
    config()->set('authkit.ui.toggle.enabled', false);

    $webNames = (array) config('authkit.route_names.web', []);
    $routeName = (string) ($webNames['login'] ?? 'authkit.web.login');

    $this->get(route($routeName))
        ->assertOk()
        ->assertDontSee('data-authkit-layout-toggle="1"', false)
        ->assertDontSee('data-authkit-theme-toggle-cycle="1"', false);
});