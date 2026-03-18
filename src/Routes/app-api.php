<?php
// file: src/Routes/app-api.php

use Illuminate\Support\Facades\Route;
use Xul\AuthKit\Http\Controllers\Api\App\Confirmations\ConfirmPasswordController;
use Xul\AuthKit\Http\Controllers\Api\App\Confirmations\ConfirmTwoFactorController;
use Xul\AuthKit\Http\Controllers\Api\App\Settings\ConfirmTwoFactorSetupController;
use Xul\AuthKit\Http\Controllers\Api\App\Settings\DisableTwoFactorController;
use Xul\AuthKit\Http\Controllers\Api\App\Settings\LogoutOtherSessionsController;
use Xul\AuthKit\Http\Controllers\Api\App\Settings\RegenerateTwoFactorRecoveryCodesController;
use Xul\AuthKit\Http\Controllers\Api\App\Settings\UpdatePasswordController;
use Xul\AuthKit\RateLimiting\RateLimitMiddlewareFactory;
use Xul\AuthKit\Support\Resolvers\ControllerResolver;

/**
 * AuthKit Authenticated API Routes
 *
 * These routes handle state-changing actions initiated from AuthKit's
 * authenticated application/account area.
 *
 * Conventions:
 * - API routes are POST/PUT/PATCH/DELETE-oriented action endpoints.
 * - These routes are distinct from guest authentication actions such as
 *   login, register, forgot-password, and login-time two-factor challenge.
 * - Route names are resolved from config so consuming applications may
 *   override them without publishing package routes.
 * - Controllers are resolved through the controller resolver so packaged
 *   implementations remain replaceable through configuration.
 * - Throttling is resolved by limiter keys from authkit.rate_limiting.map.*
 *   to avoid hard-coded middleware strings in route definitions.
 *
 * Notes:
 * - All routes in this file require an authenticated user session.
 * - Confirmation endpoints are included here because they support step-up
 *   verification for already-authenticated users.
 */
Route::middleware(array_values(array_filter(array_merge(
    (array) config('authkit.routes.middleware', ['web']),
    (array) data_get(config('authkit.routes.groups', []), 'api.middleware', [])
))))
    ->prefix((string) config('authkit.routes.prefix', ''))
    ->group(function (): void {
        if (! (bool) config('authkit.app.enabled', true)) {
            return;
        }

        $apiNames = (array) config('authkit.route_names.api', []);
        $guard = (string) config('authkit.auth.guard', 'web');

        /** @var RateLimitMiddlewareFactory $throttle */
        $throttle = app(RateLimitMiddlewareFactory::class);

        /**
         * Authenticated application actions.
         *
         * These endpoints require an authenticated user session before they
         * may be executed.
         */
        Route::middleware(["auth:{$guard}"])->group(function () use ($apiNames, $throttle): void {
            /**
             * Update password action.
             *
             * Allows an authenticated user to change their current password
             * from the account/security area.
             */
            Route::post(
                '/settings/password/update/process',
                ControllerResolver::resolve('api', 'password_update', UpdatePasswordController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('password_update'),
                ])))
                ->name((string) ($apiNames['password_update'] ?? 'authkit.api.settings.password.update'));

            /**
             * Confirm two-factor setup action.
             *
             * Verifies the provided code and finalizes two-factor setup for
             * the authenticated user.
             */
            Route::post(
                '/settings/two-factor/confirm/process',
                ControllerResolver::resolve('api', 'two_factor_confirm', ConfirmTwoFactorSetupController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('two_factor_confirm'),
                ])))
                ->name((string) ($apiNames['two_factor_confirm'] ?? 'authkit.api.settings.two_factor.confirm'));

            /**
             * Disable two-factor authentication action.
             *
             * Disables two-factor protection for the authenticated user.
             */
            Route::post(
                '/settings/two-factor/disable/process',
                ControllerResolver::resolve('api', 'two_factor_disable', DisableTwoFactorController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('two_factor_disable'),
                ])))
                ->name((string) ($apiNames['two_factor_disable'] ?? 'authkit.api.settings.two_factor.disable'));

            /**
             * Regenerate two-factor recovery codes action.
             *
             * Replaces the user's current recovery codes with a newly generated set.
             */
            Route::post(
                '/settings/two-factor/recovery-codes/regenerate/process',
                ControllerResolver::resolve('api', 'two_factor_recovery_regenerate', RegenerateTwoFactorRecoveryCodesController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('two_factor_recovery_regenerate'),
                ])))
                ->name((string) ($apiNames['two_factor_recovery_regenerate'] ?? 'authkit.api.settings.two_factor.recovery.regenerate'));

            /**
             * Password confirmation action.
             *
             * Confirms the current authenticated user's password as part of a
             * step-up confirmation flow for sensitive pages or actions.
             */
            Route::post(
                '/confirm-password/process',
                ControllerResolver::resolve('api', 'confirm_password', ConfirmPasswordController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('confirm_password'),
                ])))
                ->name((string) ($apiNames['confirm_password'] ?? 'authkit.api.confirm.password'));

            /**
             * Two-factor confirmation action.
             *
             * Confirms the current authenticated user's two-factor code as part
             * of a step-up confirmation flow for sensitive pages or actions.
             */
            Route::post(
                '/confirm-two-factor/process',
                ControllerResolver::resolve('api', 'confirm_two_factor', ConfirmTwoFactorController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('confirm_two_factor'),
                ])))
                ->name((string) ($apiNames['confirm_two_factor'] ?? 'authkit.api.confirm.two_factor'));
        });
    });