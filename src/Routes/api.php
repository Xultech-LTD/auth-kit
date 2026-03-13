<?php
// file: src/Routes/api.php

use Illuminate\Support\Facades\Route;
use Xul\AuthKit\Http\Controllers\Api\Auth\LoginController;
use Xul\AuthKit\Http\Controllers\Api\Auth\LogoutController;
use Xul\AuthKit\Http\Controllers\Api\Auth\RegisterController;
use Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorChallengeController;
use Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorRecoveryController;
use Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorResendController;
use Xul\AuthKit\Http\Controllers\Api\EmailVerification\SendEmailVerificationController;
use Xul\AuthKit\Http\Controllers\Api\EmailVerification\VerifyEmailTokenController;
use Xul\AuthKit\Http\Controllers\Api\PasswordReset\ForgotPasswordController;
use Xul\AuthKit\Http\Controllers\Api\PasswordReset\ResetPasswordController;
use Xul\AuthKit\Http\Controllers\Api\PasswordReset\VerifyPasswordResetTokenController;
use Xul\AuthKit\RateLimiting\RateLimitMiddlewareFactory;
use Xul\AuthKit\Support\Resolvers\ControllerResolver;

/**
 * AuthKit API Routes
 *
 * API routes are intended for handling submitted requests (actions).
 *
 * Conventions:
 * - API routes are POST/PUT/PATCH/DELETE.
 * - Pages and browser navigation endpoints (GET pages) live in web routes.
 * - Route names are read from config so consuming apps can override them.
 * - Middleware is configurable at a global level and per group (api).
 * - Controllers are resolved from config so consumers can override behavior without publishing routes.
 * - Throttling is resolved by limiter keys (authkit.rate_limiting.map.*) to avoid hardcoding limiter names.
 */
Route::middleware(array_values(array_filter(array_merge(
    (array) config('authkit.routes.middleware', ['web']),
    (array) data_get(config('authkit.routes.groups', []), 'api.middleware', [])
))))
    ->prefix((string) config('authkit.routes.prefix', ''))
    ->group(function (): void {
        $apiNames = (array) config('authkit.route_names.api', []);
        $guard = (string) config('authkit.auth.guard', 'web');

        /** @var RateLimitMiddlewareFactory $throttle */
        $throttle = app(RateLimitMiddlewareFactory::class);

        /* --------------------------------------------------------------------------
         | Guest-only actions
         | --------------------------------------------------------------------------
         | These endpoints are accessible only to unauthenticated users.
         */
        Route::middleware(['guest'])->group(function () use ($apiNames, $throttle): void {
            /**
             * Register action.
             */
            Route::post(
                '/register/process',
                ControllerResolver::resolve('api', 'register', RegisterController::class)
            )->name((string) ($apiNames['register'] ?? 'authkit.api.auth.register'));

            /**
             * Login action.
             */
            Route::post(
                '/login/process',
                ControllerResolver::resolve('api', 'login', LoginController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('login'),
                ])))
                ->name((string) ($apiNames['login'] ?? 'authkit.api.auth.login'));

            /**
             * Forgot password action (send reset link/token).
             */
            Route::post(
                '/forgot-password/process',
                ControllerResolver::resolve('api', 'password_forgot', ForgotPasswordController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('password_forgot'),
                ])))
                ->name((string) ($apiNames['password_send_reset'] ?? 'authkit.api.password.reset.send'));

            /**
             * Password reset token/code verification (token driver).
             *
             * This endpoint supports a token-driver UX where the user enters a code before resetting.
             * The verification is "peek-only" and must not consume the token.
             */
            Route::post(
                '/reset-password/verify-token/process',
                ControllerResolver::resolve('api', 'password_verify_token', VerifyPasswordResetTokenController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('password_verify_token'),
                ])))
                ->name((string) ($apiNames['password_verify_token'] ?? 'authkit.api.password.reset.verify.token'));

            /**
             * Reset password action (consume token + set new password).
             */
            Route::post(
                '/reset-password/process',
                ControllerResolver::resolve('api', 'password_reset', ResetPasswordController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('password_reset'),
                ])))
                ->name((string) ($apiNames['password_reset'] ?? 'authkit.api.password.reset'));

            /**
             * Two-factor challenge verify action.
             *
             * Used during login flow when a user must satisfy a pending 2FA challenge.
             */
            Route::post(
                '/two-factor/challenge/process',
                ControllerResolver::resolve('api', 'two_factor_challenge', TwoFactorChallengeController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('two_factor_challenge'),
                ])))
                ->name((string) ($apiNames['two_factor_challenge'] ?? 'authkit.api.twofactor.challenge'));

            /**
             * Two-factor resend action.
             *
             * Resends a challenge delivery for the current pending login context.
             */
            Route::post(
                '/two-factor/resend/process',
                ControllerResolver::resolve('api', 'two_factor_resend', TwoFactorResendController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('two_factor_resend'),
                ])))
                ->name((string) ($apiNames['two_factor_resend'] ?? 'authkit.api.twofactor.resend'));

            /**
             * Two-factor recovery action.
             *
             * Verifies a recovery code for the current pending login context.
             */
            Route::post(
                '/two-factor/recovery/process',
                ControllerResolver::resolve('api', 'two_factor_recovery', TwoFactorRecoveryController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('two_factor_recovery'),
                ])))
                ->name((string) ($apiNames['two_factor_recovery'] ?? 'authkit.api.twofactor.recovery'));

            /**
             * Verify email using token (token driver).
             */
            Route::post(
                '/email/verify/token/process',
                ControllerResolver::resolve('api', 'email_verify_token', VerifyEmailTokenController::class)
            )
                ->middleware(array_values(array_filter([
                    $throttle->middlewareFor('email_verify_token'),
                ])))
                ->name((string) ($apiNames['verify_token'] ?? 'authkit.api.email.verification.verify.token'));
        });

        /* --------------------------------------------------------------------------
         | Session-aware actions
         | --------------------------------------------------------------------------
         | These endpoints may be called with or without an authenticated session.
         | The action itself determines the correct standardized response.
         */

        /**
         * Send email verification notification (link or token depending on config).
         */
        Route::post(
            '/email/verification-notification/process',
            ControllerResolver::resolve('api', 'email_send_verification', SendEmailVerificationController::class)
        )
            ->middleware(array_values(array_filter([
                $throttle->middlewareFor('email_send_verification'),
            ])))
            ->name((string) ($apiNames['send_verification'] ?? 'authkit.api.email.verification.send'));

        /**
         * Logout action.
         */
        Route::post(
            '/logout/process',
            ControllerResolver::resolve('api', 'logout', LogoutController::class)
        )->name((string) ($apiNames['logout'] ?? 'authkit.api.auth.logout'));

        /* --------------------------------------------------------------------------
          | Authenticated actions
          | --------------------------------------------------------------------------
          | These endpoints require an authenticated user session.
          */
        Route::middleware(["auth:{$guard}"])->group(function () use ($apiNames): void {
            // other authenticated-only routes here
        });
    });