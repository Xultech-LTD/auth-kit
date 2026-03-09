<?php

return [
    /**
     * Authentication configuration.
     *
     * AuthKit does not assume the default guard.
     * Consumers may override this to match their application.
     */
    'auth' => [

        /**
         * Guard used for resolving providers and (optionally) session user context.
         */
        'guard' => 'web',
    ],

    /**
     * Identity configuration.
     *
     * AuthKit supports different "identity" fields across applications (email, username, phone, etc.).
     * This section defines which field is considered the primary login identifier and how it should
     * be treated across forms and validation defaults.
     *
     * Notes:
     * - This does not change your database schema automatically.
     * - If you change identity fields, ensure your user provider supports it (e.g. users table has username column).
     */
    'identity' => [

        /**
         * Primary login identity field used for authentication.
         *
         * Example values:
         * - email
         * - username
         * - phone
         */
        'login' => [

            /**
             * Input name used in login requests.
             */
            'field' => 'email',

            /**
             * Default label used in UI when AuthKit renders an identity field.
             * Consumers may override this per project.
             */
            'label' => 'Email',

            /**
             * Default HTML input type for the identity field.
             *
             * Example values:
             * - email
             * - text
             * - tel
             */
            'input_type' => 'email',

            /**
             * Default autocomplete attribute for the identity field.
             *
             * Example values:
             * - email
             * - username
             * - tel
             */
            'autocomplete' => 'email',

            /**
             * Optional normalization applied to identity input before validation/authentication.
             *
             * Allowed values:
             * - null
             * - lower
             * - trim
             *
             * Note:
             * Custom normalization can be implemented later via an action or hook.
             */
            'normalize' => 'lower',
        ],
    ],

    /**
     * Routing configuration for AuthKit.
     *
     * AuthKit separates routes into two layers:
     * - web: Page rendering and browser navigation (GET pages and signed links).
     * - api: Request handlers (POST/PUT/PATCH/DELETE and other action endpoints).
     *
     * The package will grow over time, so this structure is intended to remain stable
     * while new route groups and endpoints are added.
     */
    'routes' => [

        /**
         * Global prefix applied to all AuthKit routes.
         *
         * Example values:
         * - '' (no prefix)
         * - 'auth'
         * - 'dashboard/auth'
         */
        'prefix' => '',

        /**
         * Global middleware applied to all AuthKit routes.
         *
         * This is applied before group-level middleware.
         */
        'middleware' => ['web'],

        /**
         * Route groups allow consumers to control middleware per group and even introduce
         * their own middleware stacks as the package grows.
         *
         * Keys here are semantic group names used by the package (and may expand later).
         */
        'groups' => [

            /**
             * Web pages and browser navigation endpoints.
             *
             * Intended for:
             * - GET pages (login, register, verify notice, etc.)
             * - Signed verification links (still accessed via browser)
             */
            'web' => [
                'middleware' => [],
            ],

            /**
             * API/action endpoints that handle submitted requests.
             *
             * Intended for:
             * - POST/PUT/PATCH/DELETE actions (send verification, verify token, reset password, etc.)
             *
             * Consumers may attach custom middleware here (e.g., 'throttle:*', 'bindings', etc.).
             */
            'api' => [
                'middleware' => [],
            ],

        ],
    ],

    /**
     * Middleware configuration.
     *
     * These middleware stacks are used by AuthKit route groups.
     * Consumers may override these values to use their own middleware classes
     * or stacks without modifying package routes.
     */
    'middlewares' => [

        /**
         * Middleware applied to pages that require an authenticated user.
         */
        'authenticated' => ['auth'],

        /**
         * Middleware applied to pages that require an authenticated user
         * whose email address is not yet verified.
         */
        'email_verification_required' => [
            'web',
            \Xul\AuthKit\Http\Middleware\EnsurePendingEmailVerificationMiddleware::class,
        ],

        /**
         * Middleware applied to reset-password pages that must have a pending reset context.
         */
        'password_reset_required' => [
            'web',
            \Xul\AuthKit\Http\Middleware\EnsurePendingPasswordResetMiddleware::class,
        ],
    ],

    /**
     * Route names used internally by AuthKit.
     *
     * All routes are named and AuthKit references route names instead of hard-coded URLs.
     * Consumers may override these names to match their conventions.
     *
     * Note:
     * This list will expand as new modules are added.
     */
    'route_names' => [

        /**
         * Web (page) route names.
         *
         * These routes render pages and handle browser navigation.
         *
         * Conventions:
         * - Web routes are GET-only.
         * - State-changing actions are handled by API/action routes.
         * - AuthKit references route names so consuming apps can override naming without editing routes.
         *
         * Keys:
         * - login, register, two_factor_challenge
         * - verify_notice, verify_token_page, verify_link, verify_success
         * - password_forgot, password_forgot_sent, password_reset, password_reset_success
         */
        'web' => [
            'login' => 'authkit.web.login',
            'register' => 'authkit.web.register',
            'two_factor_challenge' => 'authkit.web.twofactor.challenge',
            'two_factor_recovery' => 'authkit.web.twofactor.recovery',

            'verify_notice' => 'authkit.web.email.verify.notice',
            'verify_token_page' => 'authkit.web.email.verify.token',
            'verify_link' => 'authkit.web.email.verification.verify.link',
            'verify_success' => 'authkit.web.email.verify.success',

            'password_forgot' => 'authkit.web.password.forgot',
            'password_forgot_sent' => 'authkit.web.password.forgot.sent',
            'password_reset' => 'authkit.web.password.reset',
            'password_reset_token_page' => 'authkit.web.password.reset.token',
            'password_reset_success' => 'authkit.web.password.reset.success',
        ],

        /**
         * API (action) route names.
         *
         * These routes handle submitted requests (state-changing actions).
         *
         * Conventions:
         * - API routes are POST/PUT/PATCH/DELETE.
         * - Pages (GET) are in web routes.
         * - AuthKit references route names so consuming apps can override naming without editing routes.
         *
         * Keys:
         * - auth: login, register, logout
         * - email verification: send_verification, verify_token
         * - password reset: password_send_reset, password_verify_token, password_reset
         * - two factor: two_factor_challenge, two_factor_resend, two_factor_recovery
         */
        'api' => [
            'login' => 'authkit.api.auth.login',
            'register' => 'authkit.api.auth.register',
            'logout' => 'authkit.api.auth.logout',

            'send_verification' => 'authkit.api.email.verification.send',
            'verify_token' => 'authkit.api.email.verification.verify.token',

            'password_send_reset' => 'authkit.api.password.reset.send',
            'password_verify_token' => 'authkit.api.password.reset.verify.token',
            'password_reset' => 'authkit.api.password.reset',

            'two_factor_challenge' => 'authkit.api.twofactor.challenge',
            'two_factor_resend' => 'authkit.api.twofactor.resend',
            'two_factor_recovery' => 'authkit.api.twofactor.recovery',
        ],
    ],

    /**
     * Controller override configuration.
     *
     * AuthKit keeps its routes internal, but allows consumers to override the controller
     * class used for any endpoint via config.
     *
     * How it works:
     * - Routes call a resolver to map a controller key (below) to a class-string.
     * - If no override is set, AuthKit uses the package default.
     *
     * Requirements:
     * - Values must be fully-qualified class names.
     * - Controllers should be invokable (single-action) controllers.
     *
     * Note:
     * This list may expand as new modules are added.
     */
    'controllers' => [

        /**
         * Web (page) controllers.
         */
        'web' => [
            'login' => \Xul\AuthKit\Http\Controllers\Web\Auth\LoginViewController::class,
            'register' => \Xul\AuthKit\Http\Controllers\Web\Auth\RegisterViewController::class,
            'two_factor_challenge' => \Xul\AuthKit\Http\Controllers\Web\Auth\TwoFactorChallengeViewController::class,
            'two_factor_recovery' => \Xul\AuthKit\Http\Controllers\Web\Auth\TwoFactorRecoveryViewController::class,

            'email_verify_notice' => \Xul\AuthKit\Http\Controllers\Web\EmailVerification\EmailVerificationNoticeViewController::class,
            'email_verify_token_page' => \Xul\AuthKit\Http\Controllers\Web\EmailVerification\EmailVerificationTokenViewController::class,
            'email_verify_success' => \Xul\AuthKit\Http\Controllers\Web\EmailVerification\EmailVerificationSuccessViewController::class,
            'email_verify_link' => \Xul\AuthKit\Http\Controllers\Web\EmailVerification\VerifyEmailLinkController::class,

            'password_forgot' => \Xul\AuthKit\Http\Controllers\Web\PasswordReset\ForgotPasswordViewController::class,
            'password_forgot_sent' => \Xul\AuthKit\Http\Controllers\Web\PasswordReset\ForgotPasswordSentViewController::class,
            'password_reset_token_page' => \Xul\AuthKit\Http\Controllers\Web\PasswordReset\PasswordResetTokenViewController::class,
            'password_reset' => \Xul\AuthKit\Http\Controllers\Web\PasswordReset\ResetPasswordViewController::class,
            'password_reset_success' => \Xul\AuthKit\Http\Controllers\Web\PasswordReset\ResetPasswordSuccessViewController::class,
        ],

        /**
         * API (action) controllers.
         */
        'api' => [
            'login' => \Xul\AuthKit\Http\Controllers\Api\Auth\LoginController::class,
            'register' => \Xul\AuthKit\Http\Controllers\Api\Auth\RegisterController::class,
            'logout' => \Xul\AuthKit\Http\Controllers\Api\Auth\LogoutController::class,

            'email_send_verification' => \Xul\AuthKit\Http\Controllers\Api\EmailVerification\SendEmailVerificationController::class,
            'email_verify_token' => \Xul\AuthKit\Http\Controllers\Api\EmailVerification\VerifyEmailTokenController::class,

            'password_forgot' => \Xul\AuthKit\Http\Controllers\Api\PasswordReset\ForgotPasswordController::class,
            'password_reset' => \Xul\AuthKit\Http\Controllers\Api\PasswordReset\ResetPasswordController::class,
            'password_verify_token' => \Xul\AuthKit\Http\Controllers\Api\PasswordReset\VerifyPasswordResetTokenController::class,

            'two_factor_challenge' => \Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorChallengeController::class,
            'two_factor_resend' => \Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorResendController::class,
            'two_factor_recovery' => \Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorRecoveryController::class,
        ],
    ],

    /**
     * Validation configuration.
     *
     * AuthKit supports customizable request validation without publishing FormRequests.
     * Consumers may provide rule providers for each form context (login, register, password reset, etc.).
     *
     * How it works:
     * - AuthKit builds sensible default rules from the form schema and flow defaults.
     * - If a provider is configured for a context, AuthKit calls it and uses its output.
     *
     * Notes:
     * - Provider values must be fully-qualified class names.
     * - Providers should implement the AuthKit rules provider contract.
     * - Providers are resolved via the container.
     */
    'validation' => [

        /**
         * Rules provider classes mapped by form context.
         *
         * Supported contexts (current):
         * - login
         * - register
         * - two_factor_challenge
         * - two_factor_recovery
         * - two_factor_resend
         * - email_verification_token
         * - email_verification_send
         * - password_forgot
         * - password_reset
         * - password_reset_token
         *
         * Contexts may expand over time as new AuthKit modules are added.
         */
        'providers' => [
            'login' => null,
            'register' => null,
            'two_factor_challenge' => null,
            'two_factor_recovery' => null,
            'two_factor_resend' => null,
            'email_verification_token' => null,
            'email_verification_send' => null,
            'password_forgot' => null,
            'password_reset' => null,
            'password_reset_token' => null,
        ],
    ],

    /**
     * Form schema configuration.
     *
     * AuthKit form schemas are the canonical definition of a form's fields.
     * Each field is now self-contained and carries its own rendering metadata.
     *
     * Design goals:
     * - Let consumers add, remove, reorder, or replace fields entirely from config.
     * - Support all common field types used by HTML forms and richer UI abstractions.
     * - Support static and dynamic option sources (arrays, enums, classes, models).
     * - Keep page structure in Blade, while keeping field structure in config.
     *
     * Important notes:
     * - These schemas are now the package standard; legacy split schemas are no longer supported.
     * - Validation still remains flow-aware; FormRequests may enforce required business constraints
     *   even when consumers customize UI metadata.
     * - Resolvers normalize these definitions before rendering or validation support consumes them.
     *
     * Top-level supported keys per form:
     * - submit: Button metadata for the primary submit action.
     * - fields: Ordered map of field-name => field definition.
     *
     * Recommended field definition keys:
     * - label
     * - type
     * - required
     * - placeholder
     * - help
     * - autocomplete
     * - inputmode
     * - value
     * - value_resolver
     * - checked
     * - multiple
     * - rows
     * - accept
     * - options
     * - attributes
     * - wrapper
     * - component
     * - render
     *
     * Supported field types include:
     * - Scalar inputs:
     *   text, email, password, hidden, number, tel, url, search, date,
     *   datetime-local, time, month, week, color, file
     * - Rich text:
     *   textarea
     * - Boolean / single-choice:
     *   checkbox, radio
     * - Multi-choice / grouped:
     *   select, multiselect, radio_group, checkbox_group
     * - Semantic / extensible:
     *   otp, custom
     *
     * Supported option sources for option-bearing fields:
     * - array : Inline items defined directly in config.
     * - enum  : PHP enum class cases normalized into [value, label] items.
     * - class : Custom provider class resolved from the container.
     * - model : Eloquent model-backed options for simple DB-driven choices.
     *
     * Option source shape examples:
     *
     * Array:
     * 'options' => [
     *     'source' => 'array',
     *     'items' => [
     *         ['value' => 'sms', 'label' => 'SMS'],
     *         ['value' => 'email', 'label' => 'Email'],
     *     ],
     * ]
     *
     * Enum:
     * 'options' => [
     *     'source' => 'enum',
     *     'class' => \App\Enums\AccountType::class,
     * ]
     *
     * Class:
     * 'options' => [
     *     'source' => 'class',
     *     'class' => \App\Support\Auth\CountryOptionsProvider::class,
     * ]
     *
     * Model:
     * 'options' => [
     *     'source' => 'model',
     *     'model' => \App\Models\Country::class,
     *     'label_by' => 'name',
     *     'value_by' => 'id',
     *     'order_by' => 'name',
     * ]
     *
     * Value precedence (recommended resolver behavior):
     * 1. old() input from the previous request
     * 2. runtime page/controller-supplied value
     * 3. value_resolver result
     * 4. static config value
     * 5. null
     */
    'schemas' => [

        /**
         * Login form schema.
         *
         * Default flow:
         * - identity field (email by default)
         * - password
         * - remember
         *
         * Notes:
         * - Consumers may replace "email" with another identity field if their application uses
         *   username, phone, or another credential, provided the backend flow is updated accordingly.
         * - The identity configuration above remains the canonical identity reference for auth logic.
         */
        'login' => [
            'submit' => [
                'label' => 'Continue',
            ],
            'fields' => [
                'email' => [
                    'label' => 'Email',
                    'type' => 'email',
                    'required' => true,
                    'placeholder' => 'Enter your email',
                    'autocomplete' => 'email',
                    'inputmode' => 'email',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-bottom:12px;',
                    ],
                ],
                'password' => [
                    'label' => 'Password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'current-password',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-bottom:12px;',
                    ],
                ],
                'remember' => [
                    'label' => 'Remember me',
                    'type' => 'checkbox',
                    'checked' => true,
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-bottom:16px;',
                    ],
                ],
            ],
        ],

        /**
         * Register form schema.
         *
         * Default flow:
         * - name
         * - email
         * - password
         * - password_confirmation
         *
         * Notes:
         * - Consumers may extend this form with additional fields such as role, account type,
         *   terms acceptance, phone number, country, etc.
         * - Where additional choice-based fields are added, options may be sourced from arrays,
         *   enums, provider classes, or simple Eloquent model lookups.
         */
        'register' => [
            'submit' => [
                'label' => 'Create account',
            ],
            'fields' => [
                'name' => [
                    'label' => 'Name',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'Enter your name',
                    'autocomplete' => 'name',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-bottom:12px;',
                    ],
                ],
                'email' => [
                    'label' => 'E-mail',
                    'type' => 'email',
                    'required' => true,
                    'placeholder' => 'Enter your email',
                    'autocomplete' => 'email',
                    'inputmode' => 'email',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-bottom:12px;',
                    ],
                ],
                'password' => [
                    'label' => 'Password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-bottom:12px;',
                    ],
                ],
                'password_confirmation' => [
                    'label' => 'Confirm password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-bottom:16px;',
                    ],
                ],
            ],
        ],

        /**
         * Two-factor challenge form schema.
         *
         * Default flow:
         * - challenge (usually hydrated implicitly from session or context)
         * - code
         *
         * Notes:
         * - The visible default input is "code".
         * - The challenge value is typically carried implicitly via session or runtime context,
         *   but remains part of the canonical request payload for validation and action handling.
         * - Consumers may later replace "code" with a richer OTP component via the "component" key.
         */
        'two_factor_challenge' => [
            'submit' => [
                'label' => 'Verify',
            ],
            'fields' => [
                'challenge' => [
                    'label' => 'Challenge',
                    'type' => 'hidden',
                    'render' => false,
                    'value' => null,
                    'attributes' => [],
                ],
                'code' => [
                    'label' => 'Authentication code',
                    'type' => 'otp',
                    'required' => true,
                    'placeholder' => 'Enter your authentication code',
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-bottom:12px;',
                    ],
                ],
            ],
        ],

        /**
         * Two-factor resend form schema.
         *
         * Default flow:
         * - email
         *
         * Notes:
         * - This is typically rendered as a hidden email field because the user is already in a
         *   known pending login flow.
         * - Consumers may switch this to another identity field if their resend flow is not email-based.
         */
        'two_factor_resend' => [
            'submit' => [
                'label' => 'Resend code',
            ],
            'fields' => [
                'email' => [
                    'label' => 'E-mail',
                    'type' => 'hidden',
                    'required' => true,
                    'autocomplete' => 'email',
                    'attributes' => [],
                ],
            ],
        ],

        /**
         * Two-factor recovery form schema.
         *
         * Default flow:
         * - challenge (implicit or hidden context)
         * - recovery_code
         * - remember
         *
         * Notes:
         * - Recovery codes are typically treated as manual one-time inputs and should be normalized
         *   before verification.
         */
        'two_factor_recovery' => [
            'submit' => [
                'label' => 'Continue',
            ],
            'fields' => [
                'challenge' => [
                    'label' => 'Challenge',
                    'type' => 'hidden',
                    'render' => false,
                    'value' => null,
                    'attributes' => [],
                ],
                'recovery_code' => [
                    'label' => 'Recovery code',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'Enter one of your saved recovery codes',
                    'autocomplete' => 'one-time-code',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-bottom:12px;',
                    ],
                ],
                'remember' => [
                    'label' => 'Remember me',
                    'type' => 'checkbox',
                    'checked' => false,
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-bottom:16px;',
                    ],
                ],
            ],
        ],

        /**
         * Email verification token form schema.
         *
         * Default flow:
         * - email
         * - token
         *
         * Notes:
         * - This applies when email verification is configured to use a token/code driver.
         * - The email is usually passed into the page context and submitted as a hidden field.
         */
        'email_verification_token' => [
            'submit' => [
                'label' => 'Verify email',
            ],
            'fields' => [
                'email' => [
                    'label' => 'E-mail',
                    'type' => 'hidden',
                    'required' => true,
                    'autocomplete' => 'email',
                    'attributes' => [],
                ],
                'token' => [
                    'label' => 'Verification code',
                    'type' => 'otp',
                    'required' => true,
                    'placeholder' => 'Enter the verification code',
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-top:12px;',
                    ],
                ],
            ],
        ],

        /**
         * Email verification resend schema.
         *
         * Default flow:
         * - email
         *
         * Notes:
         * - This form is typically rendered from the verification notice page and uses the already
         *   known pending verification email context.
         */
        'email_verification_send' => [
            'submit' => [
                'label' => 'Didn’t receive it? Resend.',
            ],
            'fields' => [
                'email' => [
                    'label' => 'E-mail',
                    'type' => 'hidden',
                    'required' => true,
                    'autocomplete' => 'email',
                    'attributes' => [],
                ],
            ],
        ],

        /**
         * Forgot password form schema.
         *
         * Default flow:
         * - email
         *
         * Notes:
         * - The default password reset request flow uses an email identity.
         * - Consumers with alternate identity-driven reset flows may replace this with a different
         *   field definition and corresponding backend logic.
         */
        'password_forgot' => [
            'submit' => [
                'label' => 'Send reset link',
            ],
            'fields' => [
                'email' => [
                    'label' => 'E-mail',
                    'type' => 'email',
                    'required' => true,
                    'placeholder' => 'Enter your email',
                    'autocomplete' => 'email',
                    'inputmode' => 'email',
                    'attributes' => [],
                ],
            ],
        ],

        /**
         * Forgot password resend schema.
         *
         * Default flow:
         * - email
         *
         * Notes:
         * - This schema is intended for the confirmation page where the email address
         *   is already known and should be resubmitted as hidden context.
         */
        'password_forgot_resend' => [
            'submit' => [
                'label' => 'Resend reset link',
            ],
            'fields' => [
                'email' => [
                    'label' => 'E-mail',
                    'type' => 'hidden',
                    'required' => true,
                    'autocomplete' => 'email',
                    'attributes' => [],
                ],
            ],
        ],

        /**
         * Reset password form schema (link driver).
         *
         * Default flow:
         * - email
         * - token
         * - password
         * - password_confirmation
         *
         * Notes:
         * - This schema is intended for reset-link flows where the reset token has already been
         *   delivered via URL and is carried into the form as a hidden value.
         */
        'password_reset' => [
            'submit' => [
                'label' => 'Reset password',
            ],
            'fields' => [
                'email' => [
                    'label' => 'E-mail',
                    'type' => 'hidden',
                    'required' => true,
                    'attributes' => [],
                ],
                'token' => [
                    'label' => 'Reset token',
                    'type' => 'hidden',
                    'required' => true,
                    'attributes' => [],
                ],
                'password' => [
                    'label' => 'New password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => null,
                    ],
                ],
                'password_confirmation' => [
                    'label' => 'Confirm password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-top:12px;',
                    ],
                ],
            ],
        ],

        /**
         * Reset password token entry schema (token driver).
         *
         * Default flow:
         * - email
         * - token
         * - password
         * - password_confirmation
         *
         * Notes:
         * - This schema is intended for token/code-based reset flows where the user must manually
         *   provide the reset code before choosing a new password.
         */
        'password_reset_token' => [
            'submit' => [
                'label' => 'Reset password',
            ],
            'fields' => [
                'email' => [
                    'label' => 'E-mail',
                    'type' => 'hidden',
                    'required' => true,
                    'attributes' => [],
                ],
                'token' => [
                    'label' => 'Reset code',
                    'type' => 'otp',
                    'required' => true,
                    'placeholder' => 'Enter the reset code',
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => null,
                    ],
                ],
                'password' => [
                    'label' => 'New password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-top:12px;',
                    ],
                ],
                'password_confirmation' => [
                    'label' => 'Confirm password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'style' => 'margin-top:12px;',
                    ],
                ],
            ],
        ],
    ],

    /**
     * Login UX configuration.
     *
     * Controls where users are redirected after a successful authentication flow
     * (including when two-factor is completed).
     */
    'login' => [

        /**
         * Named route to redirect to after login.
         *
         * - null: use dashboard_route
         * - string: treated as a named route
         */
        'redirect_route' => null,

        /**
         * Default dashboard route (named route) used when redirect_route is null.
         *
         * Consumers should set this to their app dashboard/home route.
         */
        'dashboard_route' => 'dashboard',
    ],

    /**
     * Asset configuration.
     *
     * Assets are published to the host application's public directory.
     * The base_path controls the public path used when referencing CSS/JS assets.
     */
    'assets' => [
        'base_path' => 'vendor/authkit',

        /**
         * Base assets (optional).
         *
         * These can be used by the layout to automatically include AuthKit CSS/JS.
         * Paths are relative to public/{assets.base_path}.
         *
         * Example resolved public paths:
         * - public/vendor/authkit/css/authkit.css
         * - public/vendor/authkit/js/authkit.js
         */
        'base' => [
            'css' => [
                // 'css/authkit.css',
            ],
            'js' => [
                // 'js/authkit.js',
            ],
        ],
    ],

    /**
     * Form submission configuration.
     *
     * AuthKit supports two UX modes for submitting forms:
     * - 'http': Standard form POST with full page navigation (SSR).
     * - 'ajax': Submit via JavaScript (fetch/XHR) and handle responses client-side.
     *
     * This is a package-wide default. Pages may later override this per-form if needed.
     */
    'forms' => [

        /**
         * Global submission mode.
         *
         * Allowed: 'http', 'ajax'
         */
        'mode' => 'http',

        /**
         * AJAX defaults (used when mode='ajax').
         *
         * These values are intentionally generic so consumers can plug any JS driver:
         * Alpine, htmx, Livewire, custom fetch, etc.
         */
        'ajax' => [

            /**
             * HTML attribute used to mark forms as AuthKit-AJAX enabled.
             * Your JS can query this attribute and attach submit handlers.
             *
             * Example:
             * <form data-authkit-ajax="1">...</form>
             */
            'attribute' => 'data-authkit-ajax',

            /**
             * Whether AuthKit should attempt to submit as JSON by default.
             * If true, JS should send:
             * - Accept: application/json
             * - Content-Type: application/json (or formdata if you prefer)
             *
             * If false, JS can submit as FormData and still set Accept: application/json.
             */
            'submit_json' => true,

            /**
             * Default behavior after a successful AJAX submission.
             *
             * - 'redirect': Redirect to a URL returned by server, or to fallback_redirect.
             * - 'none': Do nothing automatically; consumer JS handles it.
             */
            'success_behavior' => 'redirect',

            /**
             * Fallback redirect used when success_behavior='redirect'
             * and the server does not provide a redirect URL.
             *
             * If null, JS decides.
             */
            'fallback_redirect' => null,
        ],
    ],

    /**
     * Email verification configuration.
     *
     * driver:
     * - link: Signed URL verification.
     * - token: Token-based verification (token stored in cache).
     */
    'email_verification' => [
        /**
         * Whether AuthKit email verification features are enabled.
         *
         * When enabled, AuthKit may block login for unverified accounts
         * (depending on the LoginAction implementation) and will initiate
         * verification flows where applicable.
         */
        'enabled' => true,

        /**
         * Verification driver.
         *
         * Supported drivers:
         * - link  : Verification occurs via signed URL.
         * - token : Verification occurs by entering a verification code.
         */
        'driver' => 'link',

        /**
         * Lifetime of verification tokens or signed links in minutes.
         */
        'ttl_minutes' => 30,

        /**
         * Column mapping for email verification state.
         *
         * Consumers may rename columns in their schema and update these keys.
         *
         * verified_at:
         * - null   : email is not verified
         * - non-null : email is verified
         */
        'columns' => [
            'verified_at' => 'email_verified_at',
        ],

        /**
         * Delivery configuration.
         *
         * AuthKit emits the AuthKitEmailVerificationRequired event whenever
         * a verification flow begins (typically after registration).
         *
         * By default, AuthKit registers an internal listener which sends
         * the verification message using the configured notifier.
         *
         * Consumers may disable this listener and implement their own
         * event listeners to control delivery (email, SMS, queues, etc.).
         */
        'delivery' => [

            /**
             * Whether AuthKit should register its default delivery listener.
             *
             * When true:
             * - The package listener will send the verification message
             *   using the configured notifier.
             *
             * When false:
             * - The application must listen for the
             *   AuthKitEmailVerificationRequired event and handle delivery.
             */
            'use_listener' => true,

            /**
             * Delivery listener class registered for AuthKitEmailVerificationRequired.
             *
             * This listener is responsible for delegating verification delivery
             * to the configured notifier.
             *
             * The class must be a valid listener for:
             * Xul\AuthKit\Events\AuthKitEmailVerificationRequired
             */
            'listener' => \Xul\AuthKit\Listeners\SendEmailVerificationNotification::class,

            /**
             * Notifier implementation used by the default delivery listener.
             *
             * The class must implement:
             * Xul\AuthKit\Contracts\EmailVerificationNotifierContract
             *
             * Consumers may replace this with their own notifier
             * (for example: queue-based delivery, SMS delivery, etc.).
             */
            'notifier' => \Xul\AuthKit\Support\Notifiers\EmailVerificationNotifier::class,
        ],

        /**
         * Token verification security settings.
         *
         * These settings apply only when the driver is "token".
         */
        'token' => [

            /**
             * Maximum verification attempts allowed within the throttle window.
             *
             * This protects short numeric codes from brute-force attempts.
             */
            'max_attempts' => 5,

            /**
             * Throttle window duration in minutes.
             */
            'decay_minutes' => 1,
        ],

        /**
         * UX after a successful link verification.
         *
         * mode:
         * - 'redirect': redirect immediately after verification.
         * - 'success_page': redirect to AuthKit success page (which can then offer a "Continue" button).
         */
        'post_verify' => [
            'mode' => 'redirect',

            /**
             * Route name to redirect to after verification when mode=redirect.
             *
             * If null, AuthKit uses the login route name.
             */
            'redirect_route' => null,

            /**
             * Default login route name fallback.
             */
            'login_route' => 'authkit.web.login',

            /**
             * Web route name for the success page when mode=success_page.
             */
            'success_route' => 'authkit.web.email.verify.success',

            /**
             * Whether AuthKit should log the user in after a successful verification.
             *
             * This is helpful for stateless verification flows (e.g. token entry pages)
             * where the user is not already authenticated.
             *
             * When enabled, AuthKit will authenticate the verified user using the
             * configured guard (authkit.auth.guard).
             */
            'login_after_verify' => false,

            /**
             * Whether to "remember" the user when login_after_verify is enabled.
             *
             * This maps directly to Guard::login($user, $remember).
             */
            'remember' => true,
        ],
    ],

    /**
     * Password reset configuration.
     *
     * driver:
     * - link: email contains a reset link
     * - token: email contains a reset token/code
     *
     * This section intentionally mirrors the shape of email_verification configuration
     * where it makes sense, so consumers have a consistent customization experience.
     */
    'password_reset' => [

        /**
         * Reset driver.
         *
         * Supported drivers:
         * - link  : Reset occurs via a reset link that contains the token.
         * - token : Reset occurs by entering a reset code/token.
         */
        'driver' => 'link',

        /**
         * Lifetime of reset tokens or reset links in minutes.
         */
        'ttl_minutes' => 30,

        /**
         * Delivery configuration.
         *
         * AuthKit emits an event whenever a password reset flow begins.
         * By default, AuthKit registers an internal listener which sends
         * the reset token/link using the configured notifier.
         *
         * Consumers may disable this listener and implement their own
         * event listeners to control delivery (email, SMS, queues, etc.).
         */
        'delivery' => [

            /**
             * Whether AuthKit should register its default delivery listener.
             *
             * When false:
             * - The application must listen for the password reset event
             *   and handle delivery.
             */
            'use_listener' => true,

            /**
             * Delivery listener class registered for password reset events.
             *
             * This listener is responsible for delegating delivery
             * to the configured notifier.
             */
            'listener' => \Xul\AuthKit\Listeners\SendPasswordResetNotification::class,

            /**
             * Notifier implementation used by the default delivery listener.
             *
             * The class must implement:
             * Xul\AuthKit\Contracts\PasswordReset\PasswordResetNotifierContract
             *
             * Consumers may replace this with their own notifier
             * (for example: queue-based delivery, SMS delivery, etc.).
             */
            'notifier' => \Xul\AuthKit\Support\Notifiers\PasswordResetNotifier::class,
        ],

        /**
         * URL generator used for link-driver password reset flows.
         *
         * This is resolved via the container and must implement:
         * Xul\AuthKit\Contracts\PasswordReset\PasswordResetUrlGeneratorContract
         *
         * Defaults to AuthKit's internal generator which typically builds a signed/normal URL
         * to the configured reset page/route.
         */
        'url_generator' => \Xul\AuthKit\Support\PasswordReset\PasswordResetUrlGenerator::class,

        /**
         * Reset policy class used to enforce additional rules around password reset.
         *
         * This is resolved via the container and must implement:
         * Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract
         *
         * Defaults to a permissive policy (allows request/reset).
         */
        'policy' => \Xul\AuthKit\Support\PasswordReset\PermissivePasswordResetPolicy::class,

        /**
         * Token verification security settings.
         *
         * These settings apply only when the driver is "token".
         */
        'token' => [

            /**
             * Maximum verification attempts allowed within the throttle window.
             *
             * This protects short numeric reset codes from brute-force attempts.
             */
            'max_attempts' => 5,

            /**
             * Throttle window duration in minutes.
             */
            'decay_minutes' => 1,
        ],

        /**
         * UX after a successful password reset.
         *
         * mode:
         * - 'success_page': redirect to AuthKit success page.
         * - 'redirect'    : redirect immediately to a configured route.
         */
        'post_reset' => [

            /**
             * Post-reset UX mode.
             */
            'mode' => 'success_page',

            /**
             * Route name to redirect to after reset when mode=redirect.
             *
             * If null, AuthKit uses the login route name as a fallback.
             */
            'redirect_route' => null,

            /**
             * Default login route name fallback.
             */
            'login_route' => 'authkit.web.login',

            /**
             * Web route name for the success page when mode=success_page.
             */
            'success_route' => 'authkit.web.password.reset.success',

            /**
             * Whether AuthKit should log the user in automatically after a successful reset.
             *
             * When enabled, AuthKit will authenticate the user using the configured guard
             * (authkit.auth.guard). This is optional and defaults to false for safety.
             */
            'login_after_reset' => false,

            /**
             * Whether to "remember" the user when login_after_reset is enabled.
             *
             * This maps directly to Guard::login($user, $remember).
             */
            'remember' => true,
        ],

        /**
         * Post-request UX configuration.
         *
         * Controls where users are redirected after requesting a reset (send reset link/code).
         */
        'post_request' => [

            /**
             * Post-request UX mode.
             *
             * mode:
             * - 'sent_page' : redirect to the "check your email" confirmation page.
             * - 'token_page': redirect directly to the token entry page (token driver only).
             */
            'mode' => 'sent_page',

            /**
             * Web route name for the sent/confirmation page.
             */
            'sent_route' => 'authkit.web.password.forgot.sent',

            /**
             * Web route name for the token entry page (token driver).
             */
            'token_route' => 'authkit.web.password.reset.token',
        ],

        /**
         * User resolution configuration.
         *
         * Password reset flows must locate a user record for a given identity value.
         * Consumers may override this resolver to support custom identity fields,
         * multi-tenant applications, or non-standard user providers.
         */
        'user_resolver' => [

            /**
             * Resolver strategy used to locate users for password reset flows.
             *
             * Supported values:
             * - 'provider': use the configured guard's user provider (recommended default).
             * - 'custom'  : use a custom resolver class (resolver_class).
             */
            'strategy' => 'provider',

            /**
             * Custom resolver class used when strategy='custom'.
             *
             * The class must implement:
             * Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract
             */
            'resolver_class' => null,
        ],

        /**
         * Password update configuration.
         *
         * Consumers may override how passwords are persisted (audit trails,
         * password history, custom hashing strategies, etc.) by supplying a custom updater.
         */
        'password_updater' => [

            /**
             * Password updater class used to persist the new password.
             *
             * The class must implement:
             * Xul\AuthKit\Contracts\PasswordReset\PasswordUpdaterContract
             */
            'class' => null,

            /**
             * Whether AuthKit should refresh the remember token after a successful reset.
             *
             * This is recommended to invalidate existing "remember me" cookies.
             */
            'refresh_remember_token' => true,
        ],

        /**
         * Privacy configuration.
         *
         * Password reset flows are a common vector for user enumeration attacks,
         * where an attacker attempts to discover whether an email/account exists
         * in the system by observing different responses.
         *
         * When privacy protection is enabled, AuthKit will:
         * - Always return the same response message from the "forgot password" endpoint,
         *   regardless of whether a user exists.
         * - Only generate tokens and dispatch reset events when a real user exists,
         *   but never reveal that distinction to the client.
         *
         * This ensures attackers cannot determine which email addresses are registered.
         *
         * Recommended:
         * - Keep this enabled in production.
         */
        'privacy' => [

            /**
             * Whether AuthKit should hide whether a user exists during reset requests.
             *
             * When enabled:
             * - The controller/action will always return the same response message
             *   even if no account matches the provided email.
             * - Reset tokens and events are only generated if the user actually exists.
             *
             * When disabled:
             * - The response may indicate whether an account exists.
             * - This may be helpful during development but is not recommended for production.
             */
            'hide_user_existence' => true,

            /**
             * Generic response message returned after requesting a password reset.
             *
             * This message is used when `hide_user_existence` is enabled and must be
             * intentionally vague so that it does not reveal whether the account exists.
             *
             * Consumers may customize this message to match their product tone.
             */
            'generic_message' => 'If an account exists for this email, password reset instructions have been sent.',
        ],
    ],

    /**
     * Token generation configuration.
     *
     * AuthKit uses TokenRepository implementations to generate and validate
     * short-lived tokens for various flows (email verification, password reset, 2FA).
     *
     * This section controls token shape:
     * - length: number of characters (or digits).
     * - alphabet: token character set.
     * - uppercase: whether alpha/alnum tokens are uppercased for readability.
     *
     * Notes:
     * - Numeric tokens are easier to brute-force; action endpoints should be throttled.
     * - Link-based flows may prefer longer tokens when tokens are used in URLs.
     */
    'tokens' => [

        /**
         * Default token options used when a specific type is not configured.
         */
        'default' => [
            'length' => 64,

            /**
             * Allowed alphabets:
             * - digits: 0-9 only
             * - alpha: a-z only
             * - alnum: a-z + 0-9
             * - hex: 0-9 + a-f
             */
            'alphabet' => 'alnum',

            /**
             * Uppercase output for alpha/alnum tokens.
             */
            'uppercase' => false,
        ],

        /**
         * Per-token-type overrides.
         *
         * Keys here match the "type" values passed into TokenRepositoryContract::create().
         */
        'types' => [

            /**
             * Token-based email verification codes are typically short and user-friendly.
             */
            'email_verification' => [
                'length' => 6,
                'alphabet' => 'digits',
            ],

            /**
             * Token-based password reset codes are typically short and user-friendly.
             */
            'password_reset' => [
                'length' => 6,
                'alphabet' => 'digits',
            ],

            /**
             * Pending login challenges are not typically entered manually.
             */
            'pending_login' => [
                'length' => 64,
                'alphabet' => 'alnum',
            ],
        ],
    ],

    /**
     * Two-factor authentication configuration.
     *
     * For now we only ship a TOTP driver, but the driver is pluggable.
     * Consumers can add their own drivers via the 'drivers' map.
     */
    'two_factor' => [

        /**
         * Whether AuthKit 2FA features are enabled.
         */
        'enabled' => true,

        /**
         * Driver name used for verification.
         *
         * Supported (for now):
         * - 'totp'
         */
        'driver' => 'totp',

        /**
         * Allowed methods. For now: ['totp'] only.
         */
        'methods' => ['totp'],

        /**
         * Pending login challenge TTL when 2FA is required.
         */
        'ttl_minutes' => 10,

        /**
         * Pending login challenge consumption strategy.
         *
         * - 'peek': Best UX. Challenge is checked without consuming, and only invalidated after success.
         * - 'consume': Strict. Challenge is consumed immediately; invalid codes force restart login.
         */
        'challenge_strategy' => 'peek',

        /**
         * TOTP verification settings.
         */
        'totp' => [
            'digits' => 6,
            'period' => 30,
            'window' => 1,
            'algo' => 'sha1',
        ],

        /**
         * Users table name used by the publishable migration.
         */
        'table' => 'users',

        /**
         * Column mapping for the user model.
         *
         * Consumers can rename columns in their schema and just update these keys.
         */
        'columns' => [
            'enabled' => 'two_factor_enabled',
            'secret' => 'two_factor_secret',
            'recovery_codes' => 'two_factor_recovery_codes',
            'methods' => 'two_factor_methods',
            'confirmed_at' => 'two_factor_confirmed_at',
        ],

        /**
         * Driver map (driver name => class-string).
         * Consumers can override or add custom drivers here.
         */
        'drivers' => [
            'totp' => \Xul\AuthKit\Support\TwoFactor\Drivers\TotpTwoFactorDriver::class,
        ],

        'security' => [
            /**
             * Encrypt the stored two-factor secret at rest.
             */
            'encrypt_secret' => true,

            /**
             * Store recovery codes as hashes instead of plaintext.
             *
             * When enabled, "setTwoFactorRecoveryCodes" should receive raw codes,
             * but the model will store only hashes.
             */
            'hash_recovery_codes' => true,

            /**
             * Hash algorithm for recovery codes.
             * Allowed: bcrypt, argon2id, argon2i
             */
            'recovery_hash_driver' => 'bcrypt',
        ],
    ],

    /**
     * Rate limiting configuration.
     *
     * AuthKit ships named RateLimiters for sensitive authentication endpoints
     * (login, 2FA challenge, token verification, resend actions, etc.).
     *
     * Goals:
     * - Secure defaults (dual-bucket limiting by default).
     * - Extendable + overridable: consumers can remap which limiter a route uses,
     *   adjust limits/decay windows, change protection strategy, or replace implementations.
     *
     * How it works:
     * - AuthKit registers RateLimiter names during boot (e.g. "authkit.auth.login").
     * - Routes reference a limiter key (e.g. "login") and resolve the limiter name via this config.
     * - Each limiter can apply one or more buckets (per IP, per identity, per challenge).
     *
     * Notes:
     * - "Identity" refers to the configured primary login field (authkit.identity.login.field).
     * - Identity values should be normalized according to authkit.identity.login.normalize.
     */
    'rate_limiting' => [

        /**
         * Limiter mapping used by AuthKit routes.
         *
         * AuthKit routes should not hard-code limiter names.
         * Instead, they resolve the limiter name using these keys.
         *
         * Consumers may override mapped names to:
         * - point to their own RateLimiter names
         * - disable a limiter by setting the mapped value to null
         *
         * Example:
         * - 'login' => 'authkit.auth.login'
         * - 'login' => 'myapp.login.limiter'
         * - 'login' => null (no throttle middleware attached)
         */
        'map' => [
            'login' => 'authkit.auth.login',

            'two_factor_challenge' => 'authkit.two_factor.challenge',
            'two_factor_resend' => 'authkit.two_factor.resend',
            'two_factor_recovery' => 'authkit.two_factor.recovery',

            'password_forgot' => 'authkit.password.forgot',
            'password_verify_token' => 'authkit.password.verify_token',
            'password_reset' => 'authkit.password.reset',

            'email_send_verification' => 'authkit.email.send_verification',
            'email_verify_token' => 'authkit.email.verify_token',
        ],

        /**
         * Protection strategy per limiter.
         *
         * This controls which baseline buckets AuthKit applies when building a limiter.
         *
         * Supported values:
         * - 'dual'         : Apply both per-ip and per-identity buckets (default).
         * - 'per_ip'       : Apply per-ip bucket only.
         * - 'per_identity' : Apply per-identity bucket only.
         * - 'custom'       : Use a custom limiter implementation (see 'resolvers').
         *
         * Notes:
         * - Some limiters may additionally apply a per-challenge bucket internally
         *   where appropriate (e.g. two-factor challenge flows).
         */
        'strategy' => [
            'login' => 'dual',

            'two_factor_challenge' => 'dual',
            'two_factor_resend' => 'dual',
            'two_factor_recovery' => 'dual',

            'password_forgot' => 'dual',
            'password_verify_token' => 'dual',
            'password_reset' => 'dual',

            'email_send_verification' => 'dual',
            'email_verify_token' => 'dual',
        ],

        /**
         * Default limits and decay windows used by AuthKit RateLimiters.
         *
         * Buckets:
         * - per_ip       : Throttle based on the request client IP.
         * - per_identity : Throttle based on normalized identity (e.g. email).
         *
         * For 'dual' strategy, both buckets are applied.
         *
         * Shape:
         * - attempts      : Maximum attempts in the decay window.
         * - decay_minutes : Window size in minutes.
         */
        'limits' => [

            /**
             * Login attempts.
             *
             * Threat model:
             * - password brute-force / credential stuffing
             *
             * Rationale:
             * - per_ip: prevents high-volume attacks from a single origin.
             * - per_identity: protects a specific account from repeated attempts.
             */
            'login' => [
                'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
            ],

            /**
             * Two-factor challenge verification attempts.
             *
             * Threat model:
             * - brute forcing 2FA codes (TOTP or other drivers)
             *
             * Note:
             * - AuthKit may also include a per-challenge bucket internally when available.
             */
            'two_factor_challenge' => [
                'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
            ],

            /**
             * Two-factor resend attempts.
             *
             * Threat model:
             * - notification spam / abuse
             *
             * Defaults are intentionally stricter than code verification.
             */
            'two_factor_resend' => [
                'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 2, 'decay_minutes' => 1],
            ],

            /**
             * Two-factor recovery attempts.
             *
             * Threat model:
             * - brute forcing recovery codes
             */
            'two_factor_recovery' => [
                'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
            ],

            /**
             * Password reset request attempts (forgot password).
             *
             * Threat model:
             * - enumeration probing (mitigated by password_reset.privacy.hide_user_existence)
             * - notification spam / abuse
             */
            'password_forgot' => [
                'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 3, 'decay_minutes' => 1],
            ],

            /**
             * Password reset token/code verification attempts (token driver).
             *
             * Threat model:
             * - brute forcing short reset codes
             *
             * Defaults align with password_reset.token (max_attempts / decay_minutes).
             */
            'password_verify_token' => [
                'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
            ],

            /**
             * Password reset submission attempts (consume token + set new password).
             *
             * Threat model:
             * - repeated attempts against a reset token/link
             * - resource abuse
             */
            'password_reset' => [
                'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
            ],

            /**
             * Send email verification notification attempts.
             *
             * Threat model:
             * - notification spam / abuse
             */
            'email_send_verification' => [
                'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 2, 'decay_minutes' => 1],
            ],

            /**
             * Email verification token/code attempts (token driver).
             *
             * Threat model:
             * - brute forcing short verification codes
             *
             * Defaults align with email_verification.token (max_attempts / decay_minutes).
             */
            'email_verify_token' => [
                'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
            ],
        ],

        /**
         * Resolver overrides for advanced customization.
         *
         * Consumers may replace how throttle keys are derived (IP/identity/challenge)
         * or how limiters are built when strategy='custom'.
         *
         * Values:
         * - null        : use AuthKit defaults
         * - class-string: resolved from the container
         *
         * Intended uses:
         * - multi-tenant keying (include tenant/workspace id in the throttle key)
         * - alternative identity sources (e.g. username/phone)
         * - proxy/load-balancer IP resolution differences
         */
        'resolvers' => [

            /**
             * Identity resolver used for per-identity buckets.
             *
             * Expected behavior:
             * - read from the request using authkit.identity.login.field
             * - apply normalization per authkit.identity.login.normalize
             * - return a stable string key (or null when unavailable)
             */
            'identity' => null,

            /**
             * IP resolver used for per-ip buckets.
             *
             * Default uses the request client IP.
             * Consumers behind proxies may override this to ensure correct client IPs.
             */
            'ip' => null,

            /**
             * Challenge resolver used for per-challenge buckets where applicable.
             *
             * This is primarily used by 2FA flows to throttle attempts per challenge reference.
             */
            'challenge' => null,

            /**
             * Custom limiter builder/resolver used when strategy='custom'.
             *
             * This allows consumers to fully replace the limiter definition logic for
             * one or more limiter keys (e.g. use a different bucket model).
             */
            'limiter' => null,
        ],
    ],

    /**
     * UI component configuration.
     *
     * AuthKit pages are composed using Blade components.
     * Consumers may override these component views via vendor publishing
     * or point AuthKit to alternative component aliases.
     *
     * Notes:
     * - Values are Blade view component references (anonymous components).
     * - The package will add more components over time; this is a stable foundation.
     * - Additional components may be introduced internally for radio groups,
     *   checkbox groups, multiselects, OTP fields, or custom field dispatching.
     */
    'components' => [

        /**
         * Layout-level components.
         */
        'layout' => 'authkit::layout',
        'container' => 'authkit::container',
        'card' => 'authkit::card',
        'alert' => 'authkit::alert',
        'page' => 'authkit::page',

        /**
         * Auth page composition.
         */
        'auth_header' => 'authkit::auth.header',
        'auth_footer' => 'authkit::auth.footer',

        /**
         * Form primitives.
         *
         * These components are the base renderers used for normalized schema fields.
         * Field-to-component mapping is typically resolved by the field component resolver,
         * which may choose a component automatically based on the field type unless a field
         * explicitly overrides its component.
         *
         * Notes:
         * - input: General-purpose renderer for scalar input types such as text, email,
         *   password, hidden, number, tel, url, search, date, and similar controls.
         * - select: Used for select-like controls, including select and multiselect.
         * - textarea: Used for multiline text inputs.
         * - checkbox: Used for checkbox-style boolean inputs.
         * - otp: Dedicated renderer for one-time passcode / verification-code style inputs.
         *   This is intentionally separate from the generic input component so consumers
         *   can provide a specialized OTP UI without changing page templates or schemas.
         */
        'label' => 'authkit::form.label',
        'input' => 'authkit::form.input',
        'select' => 'authkit::form.select',
        'textarea' => 'authkit::form.textarea',
        'checkbox' => 'authkit::form.checkbox',
        'otp' => 'authkit::form.otp',

        /**
         * Form feedback.
         */
        'help' => 'authkit::form.help',
        'error' => 'authkit::form.error',
        'errors' => 'authkit::form.errors',

        /**
         * Actions and navigation.
         */
        'button' => 'authkit::button',
        'link' => 'authkit::link',
        'divider' => 'authkit::divider',
        'theme_toggle' => 'authkit::theme-toggle',

        /**
         * Schema-driven field rendering.
         *
         * These higher-level components are responsible for rendering one or more
         * normalized schema fields using the configured primitive components above.
         *
         * Typical responsibilities:
         * - field: Render a single resolved field, including wrapper, label, control,
         *   help text, and inline validation feedback where appropriate.
         * - fields: Render an ordered collection of resolved fields for a form context.
         *
         * This layer allows page templates to remain focused on page composition
         * while field-level rendering logic stays centralized and reusable.
         */
        'field' => 'authkit::form.field',
        'fields' => 'authkit::form.fields',
    ],

    /**
     * UI configuration.
     *
     * This section controls AuthKit's visual rendering system.
     *
     * AuthKit separates UI concerns into three independent parts:
     * - engine: the visual styling family (for example: tailwind-like or bootstrap-like)
     * - theme : the color/brand skin used within that engine
     * - mode  : the appearance mode (light, dark, or system)
     *
     * Design goals:
     * - Keep Blade components framework-agnostic.
     * - Allow consumers to switch visual systems without changing component markup.
     * - Support light/dark mode and system-preference detection.
     * - Support optional user-facing theme toggles.
     * - Make it easy for consuming applications to override or extend package styling.
     *
     * Notes:
     * - "tailwind" and "bootstrap" here describe AuthKit's packaged visual systems.
     *   They do not require the host application to compile Tailwind or include Bootstrap.
     * - AuthKit ships its own CSS files and applies them against semantic package classes
     *   such as authkit-card, authkit-input, authkit-btn, and related elements.
     */
    'ui' => [

        /**
         * Default visual engine.
         *
         * This selects the overall styling family used by AuthKit.
         *
         * Supported values (initial):
         * - tailwind
         * - bootstrap
         *
         * Examples:
         * - tailwind  : modern utility-inspired visual language
         * - bootstrap : traditional component-library visual language
         */
        'engine' => 'tailwind',

        /**
         * Default color theme/skin within the selected engine.
         *
         * This controls the visual palette and brand personality,
         * while the engine controls the broader component style language.
         *
         * Examples:
         * - forest
         * - red-beige
         *
         * The final stylesheet is typically resolved using:
         *   {engine}-{theme}.css
         *
         * Example:
         * - tailwind + forest => tailwind-forest.css
         * - bootstrap + red-beige => bootstrap-red-beige.css
         */
        'theme' => 'forest',

        /**
         * Default appearance mode.
         *
         * Supported values:
         * - light  : always render light mode
         * - dark   : always render dark mode
         * - system : follow the user's operating-system/browser preference
         *
         * When "system" is used, AuthKit JavaScript may resolve the active mode
         * using prefers-color-scheme and apply the final mode at runtime.
         */
        'mode' => 'system',

        /**
         * Whether AuthKit should emit data attributes for UI state.
         *
         * When enabled, the layout may render attributes such as:
         * - data-authkit-engine="tailwind"
         * - data-authkit-theme="forest"
         * - data-authkit-mode="dark"
         *
         * These attributes provide stable hooks for package CSS, JavaScript,
         * and consumer overrides.
         */
        'use_data_attributes' => true,

        /**
         * Whether AuthKit should include its packaged theme stylesheet automatically.
         *
         * When true:
         * - The layout resolves the configured engine/theme pair and loads the
         *   corresponding CSS file from the published assets directory.
         *
         * When false:
         * - Consumers are responsible for loading their own stylesheet(s).
         *
         * This is useful for applications that want to:
         * - fully replace AuthKit styling
         * - bundle AuthKit styles into their own asset pipeline
         * - ship custom themes not loaded by the package layout directly
         */
        'load_stylesheet' => true,

        /**
         * Whether AuthKit should include its packaged theme JavaScript automatically.
         *
         * When true:
         * - The layout may load AuthKit's base JavaScript for UI concerns such as:
         *   - resolving light/dark/system mode
         *   - persisting appearance preference
         *   - powering the optional theme-toggle component
         *
         * When false:
         * - Consumers are responsible for handling mode resolution/toggling themselves.
         */
        'load_script' => true,

        /**
         * Theme mode persistence settings.
         *
         * These options control whether AuthKit should remember the user's preferred
         * light/dark/system selection across page visits.
         */
        'persistence' => [

            /**
             * Whether AuthKit should persist UI mode preference in browser storage.
             *
             * When enabled, AuthKit JavaScript may store the user's chosen mode
             * (light, dark, or system) and restore it on future visits.
             */
            'enabled' => true,

            /**
             * Browser storage key used to persist the selected appearance mode.
             *
             * This key should remain stable once published to avoid breaking
             * existing saved preferences.
             */
            'storage_key' => 'authkit.ui.mode',
        ],

        /**
         * Theme toggle configuration.
         *
         * AuthKit may expose a reusable theme-toggle component that consumers
         * can place anywhere in their page layout.
         *
         * Examples:
         * - inside the auth card header
         * - at the top-right of the page shell
         * - in a custom application navbar
         *
         * This component is optional and is not required for theming to work.
         */
        'toggle' => [

            /**
             * Whether the packaged theme-toggle component is enabled for use.
             *
             * When false:
             * - Consumers may still build and use their own custom toggle UI.
             */
            'enabled' => true,

            /**
             * Default toggle presentation variant.
             *
             * Suggested values:
             * - auto
             * - dropdown
             * - buttons
             * - icon
             *
             * This controls only the packaged toggle component's default UI.
             * Consumers may still publish/override the component and render it differently.
             */
            'variant' => 'auto',

            /**
             * Whether the toggle should offer the "system" option in addition
             * to explicit light and dark modes.
             *
             * When true:
             * - users can choose light, dark, or system
             *
             * When false:
             * - packaged toggle UIs may expose only light and dark
             */
            'allow_system' => true,

            /**
             * Whether the packaged toggle component should show labels
             * alongside icons where applicable.
             *
             * This is purely a UI preference for the packaged component.
             */
            'show_labels' => true,

            /**
             * HTML attribute used to identify packaged theme-toggle elements.
             *
             * AuthKit JavaScript may use this attribute to discover toggle controls
             * and bind click/change behavior automatically.
             *
             * Example:
             * <button data-authkit-theme-toggle="dark">Dark</button>
             */
            'attribute' => 'data-authkit-theme-toggle',
        ],

        /**
         * Consumer extension hooks.
         *
         * These options make it easier for consuming applications to extend
         * or replace AuthKit styling without modifying package internals.
         */
        'extensions' => [

            /**
             * Whether AuthKit should expose stable root classes/hooks intended
             * for consumer CSS overrides.
             *
             * Example root hooks:
             * - .authkit
             * - [data-authkit-engine]
             * - [data-authkit-theme]
             * - [data-authkit-mode]
             *
             * In practice, this should usually remain enabled.
             */
            'enable_root_hooks' => true,

            /**
             * Optional additional stylesheet paths to load after the packaged
             * AuthKit stylesheet.
             *
             * These paths are relative to public/{assets.base_path} unless the
             * layout chooses to interpret them differently.
             *
             * Purpose:
             * - allow easy consumer overrides
             * - allow brand-specific additions without replacing the full theme
             *
             * Example:
             * - 'css/overrides/authkit-custom.css'
             */
            'extra_css' => [
                // 'css/authkit-overrides.css',
            ],

            /**
             * Optional additional script paths to load after AuthKit's base script.
             *
             * These may be used to extend toggle behavior, analytics hooks,
             * or custom UI interactions related to AuthKit pages.
             */
            'extra_js' => [
                // 'js/authkit-overrides.js',
            ],
        ],
    ],

    /**
     * Theme asset configuration.
     *
     * AuthKit ships packaged theme stylesheets using a flat naming convention:
     *
     *   {engine}-{theme}.css
     *
     * Examples:
     * - tailwind-forest.css
     * - tailwind-red-beige.css
     * - bootstrap-forest.css
     * - bootstrap-red-beige.css
     *
     * These files are expected under:
     *   public/{assets.base_path}/themes/
     *
     * Notes:
     * - The ui.engine and ui.theme values select which theme file is loaded by default.
     * - Consumers may add their own files to the published themes directory and
     *   then point AuthKit to them through configuration.
     */
    'themes' => [

        /**
         * Available packaged engines.
         *
         * These are the styling families supported by AuthKit's shipped theme files.
         */
        'engines' => [
            'tailwind',
            'bootstrap',
        ],

        /**
         * Available theme names by engine.
         *
         * This is primarily informational and may also be used by future validation,
         * tooling, UI controls, or documentation helpers.
         *
         * Consumers may extend these arrays with custom theme names
         * after publishing and adding their own theme files.
         */
        'available' => [
            'tailwind' => [
                'forest',
                'red-beige',
            ],
            'bootstrap' => [
                'forest',
                'red-beige',
            ],
        ],

        /**
         * Theme asset filename pattern.
         *
         * AuthKit resolves the final theme stylesheet using this pattern.
         *
         * Supported placeholders:
         * - {engine}
         * - {theme}
         *
         * Example:
         * - pattern: '{engine}-{theme}.css'
         * - engine : tailwind
         * - theme  : forest
         * - result : tailwind-forest.css
         */
        'file_pattern' => '{engine}-{theme}.css',
    ],

    /**
     * JavaScript runtime configuration.
     *
     * AuthKit ships a browser runtime centered around a single entry file:
     *   public/{assets.base_path}/js/authkit.js
     *
     * This file acts as the package client bootstrapper and may register or initialize
     * multiple internal modules such as:
     * - theme mode handling
     * - AJAX form submission
     * - page-specific behaviors (login, register, password reset, etc.)
     *
     * Design goals:
     * - Keep progressive enhancement optional: pages must still work without JavaScript.
     * - Allow AuthKit to organize page-specific behaviors into separate internal modules
     *   while keeping a single public entry file.
     * - Emit stable browser events so consuming applications can extend package behavior
     *   without modifying package source files.
     * - Allow consumers to enable, disable, or replace runtime pieces over time.
     *
     * Notes:
     * - Browser events configured here are dispatched from AuthKit's client runtime.
     * - Event names should remain stable once published to avoid breaking consumer code.
     * - "Modules" here refer to browser runtime modules booted by authkit.js.
     */
    'javascript' => [

        /**
         * Whether AuthKit should boot its packaged browser runtime.
         *
         * When false:
         * - AuthKit may still render its HTML, CSS, and server-side flows normally.
         * - Consumers become fully responsible for client-side enhancements such as:
         *   - theme toggling
         *   - AJAX form submission
         *   - page-level JavaScript behaviors
         */
        'enabled' => true,

        /**
         * Global browser runtime configuration.
         */
        'runtime' => [

            /**
             * Name of the global object AuthKit may expose on window.
             *
             * Example:
             * - window.AuthKit
             *
             * Consumers may use this public object to interact with the package runtime
             * when such an API is exposed.
             */
            'window_key' => 'AuthKit',

            /**
             * Whether AuthKit should dispatch browser events during runtime activity.
             *
             * When enabled:
             * - AuthKit JavaScript may emit CustomEvent instances on document or window
             *   for lifecycle hooks such as ready, theme change, form success, and page boot.
             */
            'dispatch_events' => true,

            /**
             * Event target used for runtime browser events.
             *
             * Supported values (recommended initial support):
             * - document
             * - window
             *
             * Recommendation:
             * - Use document for most DOM lifecycle and form-related events.
             */
            'event_target' => 'document',
        ],

        /**
         * Browser event names emitted by the packaged runtime.
         *
         * These names are intentionally configurable so consuming applications may
         * align them with project conventions if desired.
         *
         * Notes:
         * - Keep names unique and namespaced to avoid collisions.
         * - Consumers may listen for these events to plug in additional behaviors.
         */
        'events' => [

            /**
             * Fired when the AuthKit runtime has completed its initial boot.
             */
            'ready' => 'authkit:ready',

            /**
             * Fired after the theme module has initialized.
             */
            'theme_ready' => 'authkit:theme:ready',

            /**
             * Fired whenever the preferred/resolved appearance mode changes.
             */
            'theme_changed' => 'authkit:theme:changed',

            /**
             * Fired before an AuthKit AJAX-enabled form is submitted.
             */
            'form_before_submit' => 'authkit:form:before-submit',

            /**
             * Fired after an AuthKit AJAX form completes successfully.
             */
            'form_success' => 'authkit:form:success',

            /**
             * Fired when an AuthKit AJAX form returns validation or request errors.
             */
            'form_error' => 'authkit:form:error',

            /**
             * Fired when a page module is initialized.
             *
             * The specific page key is typically also included in the event payload.
             */
            'page_ready' => 'authkit:page:ready',
        ],

        /**
         * Core runtime modules.
         *
         * These modules are responsible for shared functionality that may be used
         * across multiple AuthKit pages.
         */
        'modules' => [

            /**
             * Theme/appearance runtime.
             *
             * Responsibilities may include:
             * - resolving light/dark/system mode
             * - persisting user preference
             * - syncing theme toggle controls
             * - responding to system color-scheme changes
             */
            'theme' => [
                'enabled' => true,
            ],

            /**
             * AJAX form runtime.
             *
             * Responsibilities may include:
             * - binding forms marked with the configured AJAX attribute
             * - serializing payloads as JSON or FormData
             * - dispatching lifecycle events
             * - handling redirects and structured responses
             * - surfacing validation or request errors
             */
            'forms' => [
                'enabled' => true,
            ],
        ],

        /**
         * Page runtime modules.
         *
         * These entries represent page-specific browser modules that may be booted by
         * the packaged authkit.js entry file.
         *
         * Expected examples over time:
         * - login
         * - register
         * - two_factor_challenge
         * - two_factor_recovery
         * - email_verification_notice
         * - email_verification_token
         * - password_forgot
         * - password_reset
         * - password_reset_token
         *
         * Notes:
         * - Module keys should align with package page concepts.
         * - The packaged runtime may decide which page module to boot based on DOM markers
         *   or page-level data attributes.
         */
        'pages' => [

            'login' => [
                'enabled' => true,

                /**
                 * Optional DOM marker used by the runtime to detect the page.
                 *
                 * Example:
                 * - data-authkit-page="login"
                 */
                'page_key' => 'login',
            ],

            'register' => [
                'enabled' => true,
                'page_key' => 'register',
            ],

            'two_factor_challenge' => [
                'enabled' => true,
                'page_key' => 'two_factor_challenge',
            ],

            'two_factor_recovery' => [
                'enabled' => true,
                'page_key' => 'two_factor_recovery',
            ],

            'email_verification_notice' => [
                'enabled' => true,
                'page_key' => 'email_verification_notice',
            ],

            'email_verification_token' => [
                'enabled' => true,
                'page_key' => 'email_verification_token',
            ],

            'password_forgot' => [
                'enabled' => true,
                'page_key' => 'password_forgot',
            ],

            'password_forgot_sent' => [
                'enabled' => true,
                'page_key' => 'password_forgot_sent',
            ],

            'password_reset' => [
                'enabled' => true,
                'page_key' => 'password_reset',
            ],

            'password_reset_token' => [
                'enabled' => true,
                'page_key' => 'password_reset_token',
            ],

            'password_reset_success' => [
                'enabled' => true,
                'page_key' => 'password_reset_success',
            ],

            'email_verification_success' => [
                'enabled' => true,
                'page_key' => 'email_verification_success',
            ],
        ],

        /**
         * Consumer JavaScript extension hooks.
         *
         * These options allow consumers to register additional JavaScript files
         * or runtime entrypoints that execute alongside the packaged AuthKit runtime.
         *
         * Notes:
         * - These do not replace the browser event system; they complement it.
         * - Consumers may use these hooks to attach analytics, custom UI handlers,
         *   alternate toast systems, or page-specific augmentations.
         */
        'extensions' => [

            /**
             * Additional packaged or consumer-provided browser scripts that should be loaded
             * after the main authkit.js runtime.
             *
             * Paths are relative to public/{assets.base_path}.
             *
             * Example:
             * - 'js/extensions/authkit-consumer.js'
             */
            'scripts' => [
                // 'js/extensions/authkit-consumer.js',
            ],
        ],
    ],
];