<?php
// file: src/Routes/web.php

use Illuminate\Support\Facades\Route;
use Xul\AuthKit\Http\Controllers\Web\Auth\LoginViewController;
use Xul\AuthKit\Http\Controllers\Web\Auth\RegisterViewController;
use Xul\AuthKit\Http\Controllers\Web\Auth\TwoFactorChallengeViewController;
use Xul\AuthKit\Http\Controllers\Web\Auth\TwoFactorRecoveryViewController;
use Xul\AuthKit\Http\Controllers\Web\EmailVerification\EmailVerificationNoticeViewController;
use Xul\AuthKit\Http\Controllers\Web\EmailVerification\EmailVerificationSuccessViewController;
use Xul\AuthKit\Http\Controllers\Web\EmailVerification\EmailVerificationTokenViewController;
use Xul\AuthKit\Http\Controllers\Web\EmailVerification\VerifyEmailLinkController;
use Xul\AuthKit\Http\Controllers\Web\PasswordReset\ForgotPasswordSentViewController;
use Xul\AuthKit\Http\Controllers\Web\PasswordReset\ForgotPasswordViewController;
use Xul\AuthKit\Http\Controllers\Web\PasswordReset\PasswordResetTokenViewController;
use Xul\AuthKit\Http\Controllers\Web\PasswordReset\ResetPasswordSuccessViewController;
use Xul\AuthKit\Http\Controllers\Web\PasswordReset\ResetPasswordViewController;
use Xul\AuthKit\RateLimiting\RateLimitMiddlewareFactory;
use Xul\AuthKit\Support\Resolvers\ControllerResolver;

/**
 * AuthKit Web Routes
 *
 * Web routes are intended for page rendering and browser navigation.
 *
 * Conventions:
 * - Web routes are GET-only.
 * - Action endpoints (POST/PUT/PATCH/DELETE) are registered in api routes.
 * - Route names are read from config so consuming apps can override them.
 * - Middleware is configurable at a global level and per group (web).
 * - Controllers are resolved from config so consumers can override behavior without publishing routes.
 * - Throttling is resolved by limiter keys (authkit.rate_limiting.map.*) to avoid hardcoding limiter names.
 */
Route::middleware(array_values(array_filter(array_merge(
    (array) config('authkit.routes.middleware', ['web']),
    (array) data_get(config('authkit.routes.groups', []), 'web.middleware', [])
))))
    ->prefix((string) config('authkit.routes.prefix', ''))
    ->group(function (): void {
        $webNames = (array) config('authkit.route_names.web', []);

        /** @var RateLimitMiddlewareFactory $throttle */
        $throttle = app(RateLimitMiddlewareFactory::class);

        /**
         * Guest-only pages
         *
         * These pages are accessible only to unauthenticated users.
         */
        Route::middleware(['guest'])->group(function () use ($webNames): void {
            /**
             * Login page.
             */
            Route::get(
                '/login',
                ControllerResolver::resolve('web', 'login', LoginViewController::class)
            )->name((string) ($webNames['login'] ?? 'authkit.web.login'));

            /**
             * Two-factor authentication page.
             */
            Route::get(
                '/two-factor/challenge',
                ControllerResolver::resolve('web', 'two_factor_challenge', TwoFactorChallengeViewController::class)
            )->name((string) ($webNames['two_factor_challenge'] ?? 'authkit.web.twofactor.challenge'));

            /**
             * Two-factor recovery page.
             */
            Route::get(
                '/two-factor/recovery',
                ControllerResolver::resolve('web', 'two_factor_recovery', TwoFactorRecoveryViewController::class)
            )->name((string) ($webNames['two_factor_recovery'] ?? 'authkit.web.twofactor.recovery'));

            /**
             * Register page.
             */
            Route::get(
                '/register',
                ControllerResolver::resolve('web', 'register', RegisterViewController::class)
            )->name((string) ($webNames['register'] ?? 'authkit.web.register'));

            /**
             * Email verification success page.
             */
            Route::get(
                '/email/verify/success',
                ControllerResolver::resolve('web', 'email_verify_success', EmailVerificationSuccessViewController::class)
            )->name((string) ($webNames['verify_success'] ?? 'authkit.web.email.verify.success'));

            /**
             * Forgot password page (request reset link/token).
             */
            Route::get(
                '/forgot-password',
                ControllerResolver::resolve('web', 'password_forgot', ForgotPasswordViewController::class)
            )->name((string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot'));

            /**
             * Reset password success page.
             */
            Route::get(
                '/reset-password/success',
                ControllerResolver::resolve('web', 'password_reset_success', ResetPasswordSuccessViewController::class)
            )->name((string) ($webNames['password_reset_success'] ?? 'authkit.web.password.reset.success'));

            /**
             * Password reset pages that require a pending reset context.
             */
            Route::middleware((array) config('authkit.middlewares.password_reset_required', []))
                ->group(function () use ($webNames): void {
                    /**
                     * Forgot password "sent" confirmation page.
                     */
                    Route::get(
                        '/forgot-password/sent',
                        ControllerResolver::resolve('web', 'password_forgot_sent', ForgotPasswordSentViewController::class)
                    )->name((string) ($webNames['password_forgot_sent'] ?? 'authkit.web.password.forgot.sent'));

                    /**
                     * Reset password token entry page (token driver).
                     */
                    Route::get(
                        '/reset-password/token',
                        ControllerResolver::resolve('web', 'password_reset_token_page', PasswordResetTokenViewController::class)
                    )->name((string) ($webNames['password_reset_token_page'] ?? 'authkit.web.password.reset.token'));

                    /**
                     * Reset password page (link driver).
                     *
                     * The link itself should be signed to prevent tampering with URL parameters.
                     */
                    Route::get(
                        '/reset-password/{token}',
                        ControllerResolver::resolve('web', 'password_reset', ResetPasswordViewController::class)
                    )
                        ->middleware(['signed'])
                        ->name((string) ($webNames['password_reset'] ?? 'authkit.web.password.reset'));
                });
        });

        /**
         * Email verification via signed link.
         */
        Route::get(
            '/email/verify/link/{id}/{hash}',
            ControllerResolver::resolve('web', 'email_verify_link', VerifyEmailLinkController::class)
        )
            ->middleware(array_values(array_filter([
                'signed',
                $throttle->middlewareFor('email_send_verification'),
            ])))
            ->name((string) ($webNames['verify_link'] ?? 'authkit.web.email.verification.verify.link'));

        /**
         * Pending Email Verification pages
         */
        Route::middleware((array) config('authkit.middlewares.email_verification_required', []))
            ->group(function () use ($webNames): void {
                /**
                 * Email verification notice page.
                 */
                Route::get(
                    '/email/verify',
                    ControllerResolver::resolve('web', 'email_verify_notice', EmailVerificationNoticeViewController::class)
                )->name((string) ($webNames['verify_notice'] ?? 'authkit.web.email.verify.notice'));

                /**
                 * Email verification token entry page.
                 */
                Route::get(
                    '/email/verify/token',
                    ControllerResolver::resolve('web', 'email_verify_token_page', EmailVerificationTokenViewController::class)
                )->name((string) ($webNames['verify_token_page'] ?? 'authkit.web.email.verify.token'));
            });
    });