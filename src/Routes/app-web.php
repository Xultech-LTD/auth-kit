<?php
// file: src/Routes/app-web.php

use Illuminate\Support\Facades\Route;
use Xul\AuthKit\Http\Controllers\Web\App\Confirmations\ConfirmPasswordViewController;
use Xul\AuthKit\Http\Controllers\Web\App\Confirmations\ConfirmTwoFactorViewController;
use Xul\AuthKit\Http\Controllers\Web\App\DashboardViewController;
use Xul\AuthKit\Http\Controllers\Web\App\SecurityViewController;
use Xul\AuthKit\Http\Controllers\Web\App\SessionsViewController;
use Xul\AuthKit\Http\Controllers\Web\App\SettingsViewController;
use Xul\AuthKit\Http\Controllers\Web\App\TwoFactorSettingsViewController;
use Xul\AuthKit\Support\Resolvers\ControllerResolver;

/**
 * AuthKit Authenticated Web Routes
 *
 * These routes render the authenticated application pages that live inside
 * AuthKit's logged-in account/application shell.
 *
 * Conventions:
 * - Web routes are GET-only.
 * - These routes are distinct from guest authentication pages such as login,
 *   register, password reset, and login-time two-factor challenge pages.
 * - Route names are resolved from config so consuming applications may
 *   override naming conventions without editing package routes.
 * - Controllers are resolved through the controller resolver so consumers
 *   may replace packaged implementations through configuration.
 * - Per-page middleware is resolved from authkit.app.middleware.pages, with
 *   fallback to authkit.app.middleware.base.
 *
 * Notes:
 * - This file should contain only authenticated page-rendering routes.
 * - State-changing endpoints for these pages belong in app-api.php.
 * - Utility confirmation pages are intentionally included here even though
 *   they are not primary navigation destinations.
 */
Route::middleware(array_values(array_filter(array_merge(
    (array) config('authkit.routes.middleware', ['web']),
    (array) data_get(config('authkit.routes.groups', []), 'web.middleware', [])
))))
    ->prefix((string) config('authkit.routes.prefix', ''))
    ->group(function (): void {
        if (! (bool) config('authkit.app.enabled', true)) {
            return;
        }

        $webNames = (array) config('authkit.route_names.web', []);
        $pages = (array) data_get(config('authkit.app', []), 'pages', []);
        $pageMiddleware = (array) data_get(config('authkit.app', []), 'middleware.pages', []);
        $baseMiddleware = (array) data_get(config('authkit.app', []), 'middleware.base', []);

        /**
         * Resolve the effective middleware stack for an authenticated app page.
         *
         * The page-specific middleware takes precedence when configured;
         * otherwise the base authenticated app middleware is used.
         *
         * @param string $pageKey
         * @return array<int, string>
         */
        $resolvePageMiddleware = static function (string $pageKey) use ($pageMiddleware, $baseMiddleware): array {
            $stack = array_key_exists($pageKey, $pageMiddleware)
                ? (array) $pageMiddleware[$pageKey]
                : $baseMiddleware;

            return array_values(array_filter($stack));
        };

        /**
         * Dashboard page.
         */
        if ((bool) data_get($pages, 'dashboard_web.enabled', false)) {
            Route::get(
                '/dashboard',
                ControllerResolver::resolve('web', 'dashboard_web', DashboardViewController::class)
            )
                ->middleware($resolvePageMiddleware('dashboard_web'))
                ->name((string) ($webNames['dashboard_web'] ?? 'authkit.web.dashboard'));
        }

        /**
         * Settings page.
         */
        if ((bool) data_get($pages, 'settings.enabled', false)) {
            Route::get(
                '/settings',
                ControllerResolver::resolve('web', 'settings', SettingsViewController::class)
            )
                ->middleware($resolvePageMiddleware('settings'))
                ->name((string) ($webNames['settings'] ?? 'authkit.web.settings'));
        }

        /**
         * Security page.
         */
        if ((bool) data_get($pages, 'security.enabled', false)) {
            Route::get(
                '/settings/security',
                ControllerResolver::resolve('web', 'security', SecurityViewController::class)
            )
                ->middleware($resolvePageMiddleware('security'))
                ->name((string) ($webNames['security'] ?? 'authkit.web.settings.security'));
        }

        /**
         * Sessions page.
         */
        if ((bool) data_get($pages, 'sessions.enabled', false)) {
            Route::get(
                '/settings/sessions',
                ControllerResolver::resolve('web', 'sessions', SessionsViewController::class)
            )
                ->middleware($resolvePageMiddleware('sessions'))
                ->name((string) ($webNames['sessions'] ?? 'authkit.web.settings.sessions'));
        }

        /**
         * Two-factor settings page.
         */
        if ((bool) data_get($pages, 'two_factor_settings.enabled', false)) {
            Route::get(
                '/settings/two-factor',
                ControllerResolver::resolve('web', 'two_factor_settings', TwoFactorSettingsViewController::class)
            )
                ->middleware($resolvePageMiddleware('two_factor_settings'))
                ->name((string) ($webNames['two_factor_settings'] ?? 'authkit.web.settings.two_factor'));
        }

        /**
         * Password confirmation page.
         *
         * Important:
         * - This page should require an authenticated user.
         * - It must not include the middleware that enforces password confirmation
         *   itself, otherwise it would redirect to itself.
         */
        if ((bool) data_get($pages, 'confirm_password.enabled', false)) {
            Route::get(
                '/confirm-password',
                ControllerResolver::resolve('web', 'confirm_password', ConfirmPasswordViewController::class)
            )
                ->middleware($resolvePageMiddleware('confirm_password'))
                ->name((string) ($webNames['confirm_password'] ?? 'authkit.web.confirm.password'));
        }

        /**
         * Two-factor confirmation page.
         *
         * Important:
         * - This page should require an authenticated user.
         * - It must not include the middleware that enforces two-factor
         *   confirmation itself, otherwise it would redirect to itself.
         */
        if ((bool) data_get($pages, 'confirm_two_factor.enabled', false)) {
            Route::get(
                '/confirm-two-factor',
                ControllerResolver::resolve('web', 'confirm_two_factor', ConfirmTwoFactorViewController::class)
            )
                ->middleware($resolvePageMiddleware('confirm_two_factor'))
                ->name((string) ($webNames['confirm_two_factor'] ?? 'authkit.web.confirm.two_factor'));
        }
    });