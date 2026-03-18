<?php

// config/authkit.php

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

        /**
         * Middleware applied to authenticated "app" pages rendered by AuthKit.
         *
         * Intended for:
         * - dashboard
         * - settings
         * - security
         * - sessions
         * - two-factor settings/setup
         * - confirmation pages for sensitive actions
         *
         * Notes:
         * - This is the baseline stack for authenticated AuthKit pages.
         * - Consumers may replace or extend this stack to add project-specific middleware
         *   such as tenant resolution, verified-email enforcement, role checks, or locale handling.
         */
        'authenticated_app' => ['web', 'auth'],

        /**
         * Middleware applied to pages or routes that require a recent password confirmation.
         *
         * Intended for:
         * - sensitive settings pages
         * - dangerous account actions
         * - password-protected management areas
         *
         * Behavior:
         * - The middleware should check whether a fresh password confirmation marker
         *   exists in session.
         * - If confirmation is missing or expired, the user should be redirected to the
         *   configured password confirmation page.
         *
         * Notes:
         * - This is separate from password reset flows.
         * - This is also separate from the "update password" settings action.
         */
        'password_confirmation_required' => [
            'web',
            'auth',
            \Xul\AuthKit\Http\Middleware\RequirePasswordConfirmationMiddleware::class,
        ],

        /**
         * Middleware applied to pages or routes that require a recent two-factor confirmation.
         *
         * Intended for:
         * - highly sensitive settings pages
         * - recovery code viewing/regeneration
         * - two-factor management actions
         * - other step-up security checkpoints
         *
         * Behavior:
         * - The middleware should check whether a fresh two-factor confirmation marker
         *   exists in session.
         * - If confirmation is missing or expired, the user should be redirected to the
         *   configured two-factor confirmation page.
         *
         * Notes:
         * - This is separate from the login-time two-factor challenge flow.
         * - Login-time two-factor verifies a pending login.
         * - This middleware protects already-authenticated users who are trying to access
         *   a sensitive page or perform a sensitive action.
         */
        'two_factor_confirmation_required' => [
            'web',
            'auth',
            \Xul\AuthKit\Http\Middleware\RequireTwoFactorConfirmationMiddleware::class,
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

            /**
             * Authenticated app/account pages.
             *
             * These routes render the logged-in AuthKit application shell and its
             * account/security management pages.
             *
             * Notes:
             * - These are distinct from guest authentication pages such as login/register.
             * - These are also distinct from one-off success pages in reset/verification flows.
             * - Consumers may disable individual pages through the app configuration section
             *   while still overriding route names here if needed.
             */
            'dashboard_web' => 'authkit.web.dashboard',
            'settings' => 'authkit.web.settings',
            'security' => 'authkit.web.settings.security',
            'sessions' => 'authkit.web.settings.sessions',
            'two_factor_settings' => 'authkit.web.settings.two_factor',

            /**
             * Authenticated confirmation pages.
             *
             * These pages are used when an already-authenticated user attempts to access
             * a page or action that requires step-up confirmation.
             *
             * Important distinction:
             * - confirm_password / confirm_two_factor:
             *   Used for confirming access to a sensitive page/action.
             * - two_factor_challenge / two_factor_recovery:
             *   Used during the login flow before the user becomes fully authenticated.
             */
            'confirm_password' => 'authkit.web.confirm.password',
            'confirm_two_factor' => 'authkit.web.confirm.two_factor',
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

            /**
             * Authenticated settings/account management actions.
             *
             * These routes handle state-changing actions initiated from authenticated
             * account/security pages rendered inside the AuthKit app shell.
             *
             * Examples:
             * - updating the current password
             * - enabling or disabling two-factor authentication
             * - confirming two-factor setup
             * - regenerating recovery codes
             * - revoking/logging out other sessions
             *
             * Important distinction:
             * - two_factor_confirm:
             *   Confirms/setup-enables two-factor from settings.
             * - confirm_two_factor:
             *   Confirms access to a sensitive page/action for an already-authenticated user.
             */
            'password_update' => 'authkit.api.settings.password.update',

            'two_factor_confirm' => 'authkit.api.settings.two_factor.confirm',
            'two_factor_disable' => 'authkit.api.settings.two_factor.disable',
            'two_factor_recovery_regenerate' => 'authkit.api.settings.two_factor.recovery.regenerate',

            'sessions_logout_other' => 'authkit.api.settings.sessions.logout_other',

            /**
             * Authenticated confirmation actions.
             *
             * These endpoints are used by step-up confirmation pages when a user must
             * re-confirm their password or two-factor code before proceeding to a
             * sensitive page or action.
             *
             * Notes:
             * - These routes should typically write a fresh confirmation timestamp to session.
             * - After success, they should redirect back to the stored intended URL or to a
             *   configured fallback route.
             */
            'confirm_password' => 'authkit.api.confirm.password',
            'confirm_two_factor' => 'authkit.api.confirm.two_factor',
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

            /**
             * Authenticated app/account page controllers.
             *
             * These controllers render pages inside AuthKit's authenticated application shell.
             * They are responsible for resolving page-level data, navigation context, and
             * any view-specific information required by dashboard/settings/security pages.
             */
            'dashboard_web' => \Xul\AuthKit\Http\Controllers\Web\App\DashboardViewController::class,
            'settings' => \Xul\AuthKit\Http\Controllers\Web\App\SettingsViewController::class,
            'security' => \Xul\AuthKit\Http\Controllers\Web\App\SecurityViewController::class,
            'sessions' => \Xul\AuthKit\Http\Controllers\Web\App\SessionsViewController::class,
            'two_factor_settings' => \Xul\AuthKit\Http\Controllers\Web\App\TwoFactorSettingsViewController::class,

            /**
             * Authenticated confirmation page controllers.
             *
             * These controllers render step-up confirmation pages for users who are already
             * signed in but must confirm their identity again before proceeding.
             *
             * Important distinction:
             * - These are not login pages.
             * - These are not password reset pages.
             * - These are not two-factor setup pages.
             */
            'confirm_password' => \Xul\AuthKit\Http\Controllers\Web\App\Confirmations\ConfirmPasswordViewController::class,
            'confirm_two_factor' => \Xul\AuthKit\Http\Controllers\Web\App\Confirmations\ConfirmTwoFactorViewController::class,
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

            /**
             * Authenticated settings/account action controllers.
             *
             * These controllers handle state-changing actions initiated from logged-in
             * AuthKit pages such as security settings, two-factor setup, and session management.
             */
            'password_update' => \Xul\AuthKit\Http\Controllers\Api\App\Settings\UpdatePasswordController::class,

            'two_factor_confirm' => \Xul\AuthKit\Http\Controllers\Api\App\Settings\ConfirmTwoFactorSetupController::class,
            'two_factor_disable' => \Xul\AuthKit\Http\Controllers\Api\App\Settings\DisableTwoFactorController::class,
            'two_factor_recovery_regenerate' => \Xul\AuthKit\Http\Controllers\Api\App\Settings\RegenerateTwoFactorRecoveryCodesController::class,

            'sessions_logout_other' => \Xul\AuthKit\Http\Controllers\Api\App\Settings\LogoutOtherSessionsController::class,

            /**
             * Authenticated confirmation action controllers.
             *
             * These controllers handle form submissions from confirmation pages that
             * protect sensitive pages or actions.
             *
             * Expected responsibilities:
             * - validate the submitted password or two-factor code
             * - write a fresh confirmation marker into session
             * - redirect back to the intended destination or configured fallback route
             */
            'confirm_password' => \Xul\AuthKit\Http\Controllers\Api\App\Confirmations\ConfirmPasswordController::class,
            'confirm_two_factor' => \Xul\AuthKit\Http\Controllers\Api\App\Confirmations\ConfirmTwoFactorController::class,
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

            /**
             * Authenticated confirmation form contexts.
             *
             * These contexts support step-up confirmation flows for sensitive pages/actions.
             */
            'confirm_password' => null,
            'confirm_two_factor' => null,

            /**
             * Authenticated settings/action form contexts.
             *
             * These contexts are used by forms rendered inside the authenticated AuthKit
             * application area such as password update, two-factor setup, and session actions.
             *
             * Notes:
             * - Some actions may remain controller-driven and may not require a visible schema.
             * - These provider slots exist so consumers can fully customize validation behavior
             *   without editing package code when forms are introduced or expanded.
             */
            'password_update' => null,
            'two_factor_enable' => null,
            'two_factor_confirm' => null,
            'two_factor_disable' => null,
            'two_factor_recovery_regenerate' => null,
            'sessions_logout_other' => null,
        ],
    ],

        /**
     * Payload mapper configuration.
     *
     * AuthKit payload mappers translate validated request input into the
     * normalized payload structure consumed by package actions.
     *
     * How it works:
     * - Each mapper context maps to a schema context.
     * - If no custom mapper class is configured, AuthKit uses its internal default mapper.
     * - If a custom mapper class is configured, AuthKit resolves it via the container.
     *
     * Notes:
     * - Mapper keys intentionally mirror AuthKit's schema and validation contexts.
     * - Custom mapper classes must implement the AuthKit payload mapper contract.
     * - Mappers work with validated data and schema field keys.
     */
    'mappers' => [

        /**
         * Mapper definitions keyed by action/form context.
         *
         * Supported values:
         * - class  : custom mapper class-string or null to use package default
         * - schema : schema context key used by the mapper
         */
        'contexts' => [
            'login' => [
                'class' => null,
                'schema' => 'login',
            ],
            'register' => [
                'class' => null,
                'schema' => 'register',
            ],
            'two_factor_challenge' => [
                'class' => null,
                'schema' => 'two_factor_challenge',
            ],
            'two_factor_recovery' => [
                'class' => null,
                'schema' => 'two_factor_recovery',
            ],
            'two_factor_resend' => [
                'class' => null,
                'schema' => 'two_factor_resend',
            ],
            'email_verification_token' => [
                'class' => null,
                'schema' => 'email_verification_token',
            ],
            'email_verification_send' => [
                'class' => null,
                'schema' => 'email_verification_send',
            ],
            'password_forgot' => [
                'class' => null,
                'schema' => 'password_forgot',
            ],
            'password_reset' => [
                'class' => null,
                'schema' => 'password_reset',
            ],
            'password_reset_token' => [
                'class' => null,
                'schema' => 'password_reset_token',
            ],
            'confirm_password' => [
                'class' => null,
                'schema' => 'confirm_password',
            ],
            'confirm_two_factor' => [
                'class' => null,
                'schema' => 'confirm_two_factor',
            ],
            'password_update' => [
                'class' => null,
                'schema' => 'password_update',
            ],
            'two_factor_enable' => [
                'class' => null,
                'schema' => null,
            ],
            'two_factor_confirm' => [
                'class' => null,
                'schema' => 'two_factor_confirm',
            ],
            'two_factor_disable' => [
                'class' => null,
                'schema' => 'two_factor_disable',
            ],
            'two_factor_disable_recovery' => [
                'class' => null,
                'schema' => 'two_factor_disable_recovery',
            ],
            'two_factor_recovery_regenerate' => [
                'class' => null,
                'schema' => 'two_factor_recovery_regenerate',
            ],
            'sessions_logout_other' => [
                'class' => null,
                'schema' => null,
            ],
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
                        'class' => 'authkit-field',
                    ],
                ],
                'password' => [
                    'label' => 'Password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'current-password',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],
                'remember' => [
                    'label' => 'Remember me',
                    'type' => 'checkbox',
                    'checked' => true,
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field authkit-field--checkbox',
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
                        'class' => 'authkit-field',
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
                        'class' => 'authkit-field',
                    ],
                ],
                'password' => [
                    'label' => 'Password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],
                'password_confirmation' => [
                    'label' => 'Confirm password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
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
                        'class' => 'authkit-field',
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
                        'class' => 'authkit-field',
                    ],
                ],
                'remember' => [
                    'label' => 'Remember me',
                    'type' => 'checkbox',
                    'checked' => false,
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field authkit-field--checkbox',
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
                        'class' => 'authkit-field authkit-field--offset',
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
                        'class' => 'authkit-field',
                    ],
                ],
                'password_confirmation' => [
                    'label' => 'Confirm password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
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
                        'class' => 'authkit-field',
                    ],
                ],
                'password' => [
                    'label' => 'New password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],
                'password_confirmation' => [
                    'label' => 'Confirm password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],
            ],
        ],

        /**
         * Password confirmation form schema.
         *
         * Purpose:
         * - Used when an already-authenticated user must re-enter their current password
         *   before accessing a sensitive page or performing a sensitive action.
         *
         * Important distinction:
         * - This is not the forgot-password flow.
         * - This is not the password reset flow.
         * - This is not the password update form in security settings.
         *
         * Typical usage:
         * - middleware detects that no fresh password confirmation exists in session
         * - user is redirected to the confirm-password page
         * - successful submission stores a fresh confirmation timestamp in session
         * - user is redirected back to the intended page/action
         */
        'confirm_password' => [
            'submit' => [
                'label' => 'Confirm password',
            ],
            'fields' => [
                'password' => [
                    'label' => 'Current password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'current-password',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],
            ],
        ],

        /**
         * Two-factor confirmation form schema.
         *
         * Purpose:
         * - Used when an already-authenticated user must re-confirm two-factor authentication
         *   before accessing a sensitive page or performing a sensitive action.
         *
         * Important distinction:
         * - This is not the login-time two-factor challenge.
         * - This is not the two-factor settings/setup page.
         *
         * Typical usage:
         * - middleware detects that no fresh two-factor confirmation exists in session
         * - user is redirected to the confirm-two-factor page
         * - successful submission stores a fresh confirmation timestamp in session
         * - user is redirected back to the intended page/action
         *
         * Notes:
         * - Consumers may later provide a recovery-code alternative using a separate page,
         *   a secondary form, or custom page logic.
         */
        'confirm_two_factor' => [
            'submit' => [
                'label' => 'Confirm',
            ],
            'fields' => [
                'code' => [
                    'label' => 'Authentication code',
                    'type' => 'otp',
                    'required' => true,
                    'placeholder' => 'Enter your authentication code',
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],
            ],
        ],

        /**
         * Password update form schema.
         *
         * Purpose:
         * - Used from the authenticated security/settings area when a signed-in user
         *   wants to change their current password.
         *
         * Important distinction:
         * - This is not the forgot-password flow.
         * - This is not the reset-password flow initiated from email/token.
         * - This is not the password confirmation form used for step-up access.
         *
         * Default flow:
         * - current_password
         * - password
         * - password_confirmation
         */
        'password_update' => [
            'submit' => [
                'label' => 'Update password',
            ],
            'fields' => [
                'current_password' => [
                    'label' => 'Current password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'current-password',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],
                'password' => [
                    'label' => 'New password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],
                'password_confirmation' => [
                    'label' => 'Confirm new password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],
                'logout_other_devices' => [
                    'label' => 'Logout Other loggedin Devices',
                    'type' => 'checkbox',
                    'checked' => true,
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field authkit-field--checkbox',
                    ],
                ],
            ],
        ],

        /**
         * Two-factor setup confirmation form schema.
         *
         * This schema is used when a user has started enabling two-factor
         * authentication but must confirm the setup by submitting a valid
         * verification code from their authenticator application.
         *
         * Responsibilities:
         * - Render the confirmation form fields used during the setup flow.
         * - Allow the consumer to customize labels, input types, and attributes.
         * - Provide a schema-driven structure that integrates with the
         *   AuthKit form rendering system.
         *
         * Typical flow:
         * 1. User enables two-factor authentication.
         * 2. A secret and QR code are generated.
         * 3. The user enters the verification code from their authenticator.
         * 4. This schema handles the confirmation form used in that step.
         *
         * Notes:
         * - The `otp` input type is recommended for one-time passcodes.
         * - Consumers may replace this schema entirely to support
         *   different UX patterns or additional verification inputs.
         */
        'two_factor_confirm' => [
            'submit' => [
                'label' => 'Confirm setup',
            ],
            'fields' => [
                'code' => [
                    'label' => 'Authentication code',
                    'type' => 'otp',
                    'required' => true,
                    'placeholder' => 'Enter your authentication code',
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],

            ],
        ],

        /**
         * Two-factor disable form schema.
         *
         * Purpose:
         * - Used when an authenticated user wants to disable two-factor authentication
         *   and still has access to their authenticator application.
         *
         * Verification method:
         * - Authenticator-generated one-time code (OTP/TOTP)
         *
         * Typical usage:
         * - Rendered as the default disable form on the two-factor settings page.
         * - Submitted to the shared two-factor disable action endpoint.
         *
         * Notes:
         * - This schema represents the primary and preferred disable path.
         * - Business validation and actual disable logic remain the responsibility of
         *   the request/action layer.
         * - Consumers may customize labels, attributes, and presentation without
         *   changing the underlying action endpoint.
         */
        'two_factor_disable' => [
            'submit' => [
                'label' => 'Disable two-factor authentication',
            ],
            'fields' => [
                'code' => [
                    'label' => 'Authentication code',
                    'type' => 'otp',
                    'required' => true,
                    'placeholder' => 'Enter your authentication code',
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],
            ],
        ],

        /**
         * Two-factor disable recovery form schema.
         *
         * Purpose:
         * - Used when an authenticated user wants to disable two-factor authentication
         *   but no longer has access to their authenticator application.
         *
         * Verification method:
         * - Saved recovery code
         *
         * Typical usage:
         * - Rendered only after the user explicitly chooses the fallback recovery-code
         *   path from the two-factor settings page.
         * - Submitted to the same shared two-factor disable action endpoint as the
         *   OTP-based disable form.
         *
         * UI expectation:
         * - This schema is usually hidden initially in the page UI.
         * - It is revealed only when the user clicks something like:
         *   "Use a recovery code instead".
         *
         * Notes:
         * - This schema exists separately from `two_factor_disable` so schema
         *   resolution stays unambiguous and the field renderer only receives the
         *   exact fields intended for the active form.
         * - Keeping this as a separate schema avoids hidden-field hacks and keeps page
         *   composition cleaner.
         */
        'two_factor_disable_recovery' => [
            'submit' => [
                'label' => 'Disable two-factor authentication',
            ],
            'fields' => [
                'recovery_code' => [
                    'label' => 'Recovery code',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'Enter one of your saved recovery codes',
                    'autocomplete' => 'one-time-code',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
                    ],
                ],
            ],
        ],

        /**
         * Recovery code regeneration form schema.
         *
         * Purpose:
         * - Used from the authenticated two-factor settings page when a user wants
         *   to replace their existing recovery codes with a newly generated set.
         *
         * Security rationale:
         * - Regenerating recovery codes is a highly sensitive action because it
         *   replaces the account's backup access credentials.
         * - For that reason, this form requires a fresh authenticator code.
         * - Recovery-code-based regeneration is intentionally not the default,
         *   because a fallback credential should not be used to mint a fresh
         *   long-term fallback credential set.
         *
         * Recommended flow:
         * - User enters a valid OTP from their authenticator app.
         * - AuthKit verifies the code using the active driver resolved through
         *   the TwoFactorManager.
         * - Existing recovery codes are replaced with a newly generated set.
         * - Newly generated plaintext recovery codes are shown once via:
         *   - session flash for SSR flows
         *   - JSON payload for AJAX flows
         */
        'two_factor_recovery_regenerate' => [
            'submit' => [
                'label' => 'Regenerate recovery codes',
            ],
            'fields' => [
                'code' => [
                    'label' => 'Authentication code',
                    'type' => 'otp',
                    'required' => true,
                    'placeholder' => 'Enter your authentication code',
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'attributes' => [],
                    'wrapper' => [
                        'class' => 'authkit-field',
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
        'dashboard_route' => 'authkit.web.dashboard',
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
         * Base built assets (optional).
         *
         * These paths are relative to public/{assets.base_path}.
         *
         * Example resolved public paths:
         * - public/vendor/authkit/js/authkit.js
         * - public/vendor/authkit/css/themes/tailwind-forest.css
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

    /*
    |--------------------------------------------------------------------------
    | Form Submission UI
    |--------------------------------------------------------------------------
    |
    | These options control the client-side loading/busy state applied when an
    | AuthKit-managed form is submitted.
    |
    | The loading system is intended to work for both:
    | - normal HTTP submissions
    | - AJAX submissions handled by the AuthKit browser runtime
    |
    | Design goals:
    | - prevent duplicate submissions
    | - provide visible feedback while a request is in progress
    | - remain configurable without hard-coding one loading style
    | - allow future extension for custom HTML, components, or external libraries
    |
    */
    'forms' => [

        /*
        |--------------------------------------------------------------------------
        | Submission Mode
        |--------------------------------------------------------------------------
        |
        | Controls the default form transport mode used by AuthKit pages.
        |
        | Supported values:
        | - http : regular browser form submission
        | - ajax : JavaScript-driven submission through the AuthKit runtime
        |
        */
        'mode' => 'http',

        /*
        |--------------------------------------------------------------------------
        | AJAX Form Settings
        |--------------------------------------------------------------------------
        |
        | These options are used when forms are submitted through the AuthKit
        | JavaScript runtime.
        |
        */
        'ajax' => [

            /*
            |--------------------------------------------------------------------------
            | AJAX Marker Attribute
            |--------------------------------------------------------------------------
            |
            | Forms containing this attribute are treated as AuthKit AJAX forms by
            | the browser runtime.
            |
            */
            'attribute' => 'data-authkit-ajax',

            /*
            |--------------------------------------------------------------------------
            | JSON Submission
            |--------------------------------------------------------------------------
            |
            | When true, AJAX submissions are sent as JSON payloads by default.
            | When false, FormData is used.
            |
            */
            'submit_json' => true,

            /*
            |--------------------------------------------------------------------------
            | Success Behavior
            |--------------------------------------------------------------------------
            |
            | Controls what the runtime should do after a successful AJAX form
            | submission.
            |
            | Supported values:
            | - redirect : follow redirect intent from the response when available
            | - none     : do not redirect automatically
            |
            */
            'success_behavior' => 'redirect',

            /*
            |--------------------------------------------------------------------------
            | Fallback Redirect
            |--------------------------------------------------------------------------
            |
            | Optional URL used when success behavior is "redirect" but the server
            | response does not provide its own redirect target.
            |
            */
            'fallback_redirect' => null,
        ],

        /*
        |--------------------------------------------------------------------------
        | Loading State
        |--------------------------------------------------------------------------
        |
        | Controls the client-side busy/loading state applied to submit actions
        | while a form request is being processed.
        |
        | This system is configuration-driven so consumers may later customize:
        | - loading text
        | - spinner-only behavior
        | - spinner + text behavior
        | - custom HTML loaders
        |
        | Future versions may also support custom Blade components or external
        | library integrations without changing the overall config shape.
        |
        */
        'loading' => [

            /*
            |--------------------------------------------------------------------------
            | Enable Loading State
            |--------------------------------------------------------------------------
            |
            | When true, AuthKit applies a temporary busy state to the submit
            | button while a submission is in progress.
            |
            */
            'enabled' => true,

            /*
            |--------------------------------------------------------------------------
            | Prevent Double Submission
            |--------------------------------------------------------------------------
            |
            | When true, AuthKit ignores repeated submit attempts while the current
            | form is already submitting.
            |
            */
            'prevent_double_submit' => true,

            /*
            |--------------------------------------------------------------------------
            | Disable Submit Control
            |--------------------------------------------------------------------------
            |
            | When true, submit buttons are disabled during the active submission
            | window.
            |
            */
            'disable_submit' => true,

            /*
            |--------------------------------------------------------------------------
            | Set ARIA Busy
            |--------------------------------------------------------------------------
            |
            | When true, AuthKit adds aria-busy="true" to the form during
            | submission to improve accessibility and machine-readable state.
            |
            */
            'set_aria_busy' => true,

            /*
            |--------------------------------------------------------------------------
            | Loading Presentation Type
            |--------------------------------------------------------------------------
            |
            | Controls the built-in visual style applied to the submit button while
            | submitting.
            |
            | Supported values:
            | - text         : replace the label with loading text only
            | - spinner      : show spinner only
            | - spinner_text : show spinner and loading text
            | - custom_html  : render configured HTML markup
            |
            */
            'type' => 'spinner_text',

            /*
            |--------------------------------------------------------------------------
            | Loading Text
            |--------------------------------------------------------------------------
            |
            | Default text displayed while a form submission is in progress.
            |
            | This may later be overridden per form from resolved schema submit
            | configuration.
            |
            */
            'text' => 'Processing...',

            /*
            |--------------------------------------------------------------------------
            | Show Text
            |--------------------------------------------------------------------------
            |
            | When false, AuthKit may hide loading text even for loading types that
            | normally support text output.
            |
            */
            'show_text' => true,

            /*
            |--------------------------------------------------------------------------
            | Custom Loading HTML
            |--------------------------------------------------------------------------
            |
            | Optional HTML markup used when the loading type is set to
            | "custom_html".
            |
            | This should be a small inline-safe snippet such as:
            | - <span class="my-loader" aria-hidden="true"></span>
            | - <svg ...></svg>
            |
            | Leave null to use only built-in loading behavior.
            |
            */
            'html' => null,

            /*
            |--------------------------------------------------------------------------
            | Loading State Class
            |--------------------------------------------------------------------------
            |
            | CSS class applied to the submit control while loading is active.
            |
            */
            'class' => 'authkit-btn--loading',
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
             * - The package listener will handle delivery using the configured
             *   notifier and delivery execution mode.
             *
             * When false:
             * - The application must listen for the
             *   AuthKitEmailVerificationRequired event and handle delivery itself.
             */
            'use_listener' => true,

            /**
             * Delivery listener class registered for AuthKitEmailVerificationRequired.
             *
             * This listener is responsible for orchestrating delivery using the
             * configured notifier and execution mode.
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
             * Consumers may replace this with their own notifier to support
             * alternate delivery channels or custom notification logic.
             */
            'notifier' => \Xul\AuthKit\Support\Notifiers\EmailVerificationNotifier::class,

            /**
             * Delivery execution mode used by the default listener.
             *
             * Supported values:
             * - sync           : send immediately during the current request
             * - queue          : dispatch delivery to the queue
             * - after_response : defer delivery until after the HTTP response is sent
             *
             * Notes:
             * - This option affects only the package's default listener flow.
             * - If use_listener=false, the application becomes fully responsible
             *   for deciding how delivery is executed.
             */
            'mode' => 'sync',

            /**
             * Queue connection used when delivery mode is "queue".
             *
             * Examples:
             * - null      : use the application's default queue connection
             * - 'database'
             * - 'redis'
             * - 'sqs'
             */
            'queue_connection' => null,

            /**
             * Queue name used when delivery mode is "queue".
             *
             * Examples:
             * - null
             * - 'default'
             * - 'mail'
             * - 'notifications'
             */
            'queue' => null,

            /**
             * Optional delivery delay in seconds.
             *
             * When greater than zero and delivery mode is "queue", AuthKit will
             * delay job execution by the configured number of seconds.
             *
             * This is ignored for "sync" mode and may be ignored for
             * "after_response" mode depending on the implementation.
             */
            'delay' => 0,
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
            'redirect_route' => 'authkit.web.dashboard',

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
             * When true:
             * - The package listener will handle password reset delivery using the
             *   configured notifier and execution mode.
             *
             * When false:
             * - The application must listen for the password reset event
             *   and handle delivery itself.
             */
            'use_listener' => true,

            /**
             * Delivery listener class registered for password reset events.
             *
             * This listener is responsible for orchestrating delivery using the
             * configured notifier and execution mode.
             */
            'listener' => \Xul\AuthKit\Listeners\SendPasswordResetNotification::class,

            /**
             * Notifier implementation used by the default delivery listener.
             *
             * The class must implement:
             * Xul\AuthKit\Contracts\PasswordReset\PasswordResetNotifierContract
             *
             * Consumers may replace this with their own notifier to support
             * alternate delivery channels or custom notification logic.
             */
            'notifier' => \Xul\AuthKit\Support\Notifiers\PasswordResetNotifier::class,

            /**
             * Delivery execution mode used by the default listener.
             *
             * Supported values:
             * - sync           : send immediately during the current request
             * - queue          : dispatch delivery to the queue
             * - after_response : defer delivery until after the HTTP response is sent
             *
             * Notes:
             * - This option affects only the package's default listener flow.
             * - If use_listener=false, the application becomes fully responsible
             *   for deciding how delivery is executed.
             */
            'mode' => 'sync',

            /**
             * Queue connection used when delivery mode is "queue".
             *
             * Examples:
             * - null      : use the application's default queue connection
             * - 'database'
             * - 'redis'
             * - 'sqs'
             */
            'queue_connection' => null,

            /**
             * Queue name used when delivery mode is "queue".
             *
             * Examples:
             * - null
             * - 'default'
             * - 'mail'
             * - 'notifications'
             */
            'queue' => null,

            /**
             * Optional delivery delay in seconds.
             *
             * When greater than zero and delivery mode is "queue", AuthKit will
             * delay job execution by the configured number of seconds.
             *
             * This is ignored for "sync" mode and may be ignored for
             * "after_response" mode depending on the implementation.
             */
            'delay' => 0,
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

        /**
         * Recovery code display and transport configuration.
         *
         * This section defines the canonical keys used when AuthKit needs to expose
         * freshly generated plaintext recovery codes to the user immediately after:
         * - confirming two-factor setup
         * - regenerating recovery codes
         *
         * Why this exists:
         * - Recovery codes are intentionally shown only once in plaintext.
         * - Redirect-based web flows need a session flash key so the destination page
         *   can render the codes server-side on the next request.
         * - AJAX flows need a stable response payload key so client-side page modules
         *   can discover and render the codes without hard-coding response field names.
         *
         * Important distinction:
         * - This section controls temporary plaintext presentation only.
         * - It does not control how recovery codes are stored on the user model.
         * - Persistent storage and hashing behavior remain controlled by:
         *   authkit.two_factor.security.hash_recovery_codes
         *   authkit.two_factor.security.recovery_hash_driver
         *
         * Design goals:
         * - Keep Blade templates free from hard-coded flash session keys.
         * - Keep page JavaScript free from hard-coded payload keys.
         * - Allow consumers to rename these keys if needed without editing package code.
         */
        'recovery_codes' => [

            /**
             * Session flash key used for redirect-based web flows.
             *
             * Expected usage:
             * - Actions flash newly generated plaintext recovery codes to this key.
             * - The next rendered page reads this same key from session and displays
             *   the codes once for secure download or storage.
             *
             * Example flashed value:
             * [
             *     'ABCD-EFGH',
             *     'IJKL-MNOP',
             * ]
             */
            'flash_key' => 'authkit.two_factor.recovery_codes',

            /**
             * Public response payload key used for JSON/AJAX success responses.
             *
             * Expected usage:
             * - Actions return newly generated plaintext recovery codes in the public
             *   payload using this key.
             * - Client-side page modules read this key from successful AJAX responses
             *   and render the recovery-code section dynamically.
             *
             * Example response payload:
             * [
             *     'confirmed' => true,
             *     'methods' => ['totp'],
             *     'recovery_codes' => ['ABCD-EFGH', 'IJKL-MNOP'],
             * ]
             */
            'response_key' => 'recovery_codes',

            /**
             * Whether the recovery-code presentation section should remain hidden by
             * default until codes are actually available.
             *
             * Intended behavior:
             * - Redirect/SSR flow:
             *   The section becomes visible only when the configured flash key contains
             *   codes for the current request.
             * - AJAX flow:
             *   The section starts hidden and is revealed by page JavaScript when a
             *   successful response contains recovery codes under the configured
             *   response_key.
             *
             * This keeps the page clean when no new recovery codes are being shown.
             */
            'hide_when_empty' => true,
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

            /**
             * Authenticated confirmation actions.
             *
             * These limiters protect step-up confirmation endpoints used when an
             * already-authenticated user must re-confirm their identity before
             * accessing a sensitive page or action.
             */
            'confirm_password' => 'authkit.confirm.password',
            'confirm_two_factor' => 'authkit.confirm.two_factor',

            /**
             * Authenticated settings/account actions.
             *
             * These limiters protect sensitive account-management endpoints rendered
             * from AuthKit's authenticated application area.
             */
            'password_update' => 'authkit.settings.password.update',
            'two_factor_enable' => 'authkit.settings.two_factor.enable',
            'two_factor_confirm' => 'authkit.settings.two_factor.confirm',
            'two_factor_disable' => 'authkit.settings.two_factor.disable',
            'two_factor_recovery_regenerate' => 'authkit.settings.two_factor.recovery.regenerate',
            'sessions_logout_other' => 'authkit.settings.sessions.logout_other',
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

            /**
             * Authenticated confirmation actions.
             *
             * These endpoints should be protected because they validate sensitive
             * secrets for already-authenticated users.
             */
            'confirm_password' => 'dual',
            'confirm_two_factor' => 'dual',

            /**
             * Authenticated settings/account actions.
             *
             * These endpoints are state-changing and security-sensitive, so the
             * default dual-bucket strategy remains appropriate.
             */
            'password_update' => 'dual',
            'two_factor_enable' => 'dual',
            'two_factor_confirm' => 'dual',
            'two_factor_disable' => 'dual',
            'two_factor_recovery_regenerate' => 'dual',
            'sessions_logout_other' => 'dual',
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

            /**
             * Password confirmation attempts.
             *
             * Threat model:
             * - repeated guessing of the current password for step-up confirmation
             *
             * Notes:
             * - This protects the confirmation endpoint used before allowing
             *   access to a sensitive page or action.
             * - Defaults mirror other sensitive credential checks.
             */
            'confirm_password' => [
                'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
            ],

            /**
             * Two-factor confirmation attempts.
             *
             * Threat model:
             * - brute forcing TOTP codes during step-up confirmation
             *
             * Notes:
             * - This is distinct from the login-time two-factor challenge flow.
             * - It protects already-authenticated users performing sensitive actions.
             */
            'confirm_two_factor' => [
                'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
            ],

            /**
             * Password update attempts.
             *
             * Threat model:
             * - repeated abuse of the password change endpoint
             * - brute forcing current-password confirmation as part of password change
             */
            'password_update' => [
                'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
            ],

            /**
             * Two-factor enable attempts.
             *
             * Threat model:
             * - repeated setup abuse
             * - resource abuse against setup endpoints
             *
             * Notes:
             * - This endpoint is usually lower risk than code verification,
             *   but should still be throttled.
             */
            'two_factor_enable' => [
                'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 3, 'decay_minutes' => 1],
            ],

            /**
             * Two-factor setup confirmation attempts.
             *
             * Threat model:
             * - brute forcing setup-confirmation codes
             *
             * Notes:
             * - This applies when a user is confirming/enabling two-factor
             *   from the authenticated settings area.
             */
            'two_factor_confirm' => [
                'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
            ],

            /**
             * Two-factor disable attempts.
             *
             * Threat model:
             * - repeated abuse of the disable endpoint
             * - attempts to weaken account security through repeated requests
             */
            'two_factor_disable' => [
                'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 3, 'decay_minutes' => 1],
            ],

            /**
             * Recovery code regeneration attempts.
             *
             * Threat model:
             * - repeated regeneration abuse
             * - unnecessary secret rotation or spam-like usage
             *
             * Defaults are intentionally stricter because regeneration is a
             * high-sensitivity action and should not be called repeatedly.
             */
            'two_factor_recovery_regenerate' => [
                'per_ip' => ['attempts' => 4, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 2, 'decay_minutes' => 1],
            ],

            /**
             * Logout-other-sessions attempts.
             *
             * Threat model:
             * - repeated state-changing abuse against session management
             *
             * Notes:
             * - This endpoint is not usually brute-force sensitive, but throttling
             *   still helps reduce unnecessary abuse and repeated session churn.
             */
            'sessions_logout_other' => [
                'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
                'per_identity' => ['attempts' => 3, 'decay_minutes' => 1],
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
     * Authenticated application area configuration.
     *
     * This section controls AuthKit's logged-in "app" experience, including:
     * - dashboard and account/settings pages
     * - authenticated layout/shell selection
     * - sidebar navigation structure
     * - page enablement and view mapping
     * - per-page middleware stacks
     *
     * Design goals:
     * - Keep authenticated account/security pages configurable in the same spirit
     *   as the rest of AuthKit.
     * - Allow consumers to enable only the pages they want.
     * - Allow consumers to rename, reorder, or hide navigation items.
     * - Keep actual page markup in Blade/views while leaving page structure,
     *   routing, and navigation metadata configurable.
     *
     * Important notes:
     * - This section does not replace the existing UI/theme system.
     * - AuthKit's authenticated area should continue using the same ui/theme/mode
     *   configuration already defined elsewhere in this file.
     * - These pages are distinct from guest auth pages such as login/register/reset.
     */
    'app' => [

        /**
         * Whether AuthKit's authenticated application pages are enabled.
         *
         * When false:
         * - AuthKit may still provide guest auth flows (login/register/reset/etc.)
         * - Consumers become responsible for their own post-login dashboard/settings area.
         */
        'enabled' => true,

        /**
         * Authenticated application brand configuration.
         *
         * This controls the small branding block rendered in the app sidebar.
         *
         * Supported types:
         * - letter : render a short text mark such as "AK"
         * - image  : render an image logo
         *
         * Notes:
         * - `image` should be a public path resolvable by asset().
         * - When type=image but no image path is provided, AuthKit falls back to letter mode.
         */
        'brand' => [
            'title' => env('APP_NAME', 'AuthKit'),
            'subtitle' => 'Application Console',

            /**
             * letter|image
             */
            'type' => 'letter',

            /**
             * Short textual mark used when type=letter.
             */
            'letter' => 'AK',

            /**
             * Public asset path used when type=image.
             *
             * Example:
             * - 'vendor/authkit/images/logo.svg'
             * - 'images/brand/authkit-logo.png'
             */
            'image' => '',

            /**
             * Alt text for the logo image.
             */
            'image_alt' => env('APP_NAME', 'AuthKit'),

            /**
             * Whether to show the subtitle under the brand title.
             */
            'show_subtitle' => true,
        ],

        'shell' => [
            'sidebar' => [
                'allow_collapse' => true,
                'collapsed' => false,
                'mobile_drawer' => true,
                'storage_key' => 'authkit.app.sidebar.collapsed',
                'mobile_breakpoint' => 1024,
            ],
        ],

        /**
         * Available authenticated layout variants.
         *
         * Values are Blade view/component references used by authenticated pages.
         * Per-page layout selection may reference one of these keys.
         *
         * Notes:
         * - "default" should usually be the main authenticated shell.
         * - Additional variants may be added later for compact/minimal page presentations.
         */
        'layouts' => [
            'default' => 'authkit::app.layout',
        ],

        /**
         * Authenticated page definitions.
         *
         * This section describes the built-in pages that make up AuthKit's
         * logged-in application area.
         *
         * Each page entry allows consumers to control things such as:
         * - whether the page is available
         * - the browser/page title and visible heading
         * - the named route used to reach the page
         * - the layout variant used to render it
         * - the Blade view responsible for the page body
         * - whether the page should appear in sidebar navigation
         *
         * In practice, this gives consumers a simple way to keep only the
         * pages they need, rename them to fit their product language, or
         * point AuthKit to published/customized views without editing the
         * package internals.
         *
         * Notes:
         * - Destination pages such as dashboard, settings, security, and sessions
         *   will usually appear in navigation.
         * - Utility pages such as password/two-factor confirmation pages usually
         *   should not appear in sidebar navigation.
         */
        'pages' => [

            'dashboard_web' => [
                'enabled' => true,
                'title' => 'Dashboard',
                'heading' => 'Account overview',
                'route' => 'authkit.web.dashboard',
                'layout' => 'default',
                'view' => 'authkit::pages.app.dashboard',
                'nav_label' => 'Dashboard',
                'show_in_sidebar' => true,
            ],

            'settings' => [
                'enabled' => true,
                'title' => 'Settings',
                'heading' => 'Account settings',
                'route' => 'authkit.web.settings',
                'layout' => 'default',
                'view' => 'authkit::pages.app.settings',
                'nav_label' => 'Settings',
                'show_in_sidebar' => true,
            ],

            'security' => [
                'enabled' => true,
                'title' => 'Security',
                'heading' => 'Security settings',
                'route' => 'authkit.web.settings.security',
                'layout' => 'default',
                'view' => 'authkit::pages.app.security',
                'nav_label' => 'Security',
                'show_in_sidebar' => true,

                /**
                 * Built-in section visibility for the security page.
                 *
                 * These toggles allow consumers to keep the security page enabled
                 * while hiding individual packaged sections they do not want to
                 * expose in their application.
                 *
                 * Example uses:
                 * - hide password change while keeping two-factor management
                 * - hide recovery-code tools in a custom implementation
                 * - keep the page shell but replace some sections with custom content
                 */
                'sections' => [
                    'password_update' => true,
                    'two_factor' => true,
                    'sessions_summary' => true,
                ],
            ],

            'sessions' => [
                'enabled' => true,
                'title' => 'Sessions',
                'heading' => 'Active sessions',
                'route' => 'authkit.web.settings.sessions',
                'layout' => 'default',
                'view' => 'authkit::pages.app.sessions',
                'nav_label' => 'Sessions',
                'show_in_sidebar' => true,
            ],

            'two_factor_settings' => [
                'enabled' => true,
                'title' => 'Two-factor authentication',
                'heading' => 'Manage two-factor authentication',
                'route' => 'authkit.web.settings.two_factor',
                'layout' => 'default',
                'view' => 'authkit::pages.app.two-factor',
                'nav_label' => 'Two-factor',
                'show_in_sidebar' => false,
            ],

            /**
             * Sensitive-action confirmation pages.
             *
             * These pages are shown when a signed-in user tries to open a page or
             * perform an action that requires fresh confirmation.
             *
             * Typical examples:
             * - confirming the current password before a protected change
             * - confirming a fresh two-factor code before viewing or regenerating
             *   recovery codes
             *
             * Notes:
             * - These pages are part of the authenticated AuthKit experience,
             *   but they are utility pages rather than main navigation destinations.
             * - For that reason, they are hidden from the sidebar by default.
             */
            'confirm_password' => [
                'enabled' => true,
                'title' => 'Confirm password',
                'heading' => 'Confirm your password',
                'route' => 'authkit.web.confirm.password',
                'layout' => 'default',
                'view' => 'authkit::pages.app.confirm-password',
                'nav_label' => 'Confirm password',
                'show_in_sidebar' => false,
            ],

            'confirm_two_factor' => [
                'enabled' => true,
                'title' => 'Confirm two-factor authentication',
                'heading' => 'Confirm two-factor authentication',
                'route' => 'authkit.web.confirm.two_factor',
                'layout' => 'default',
                'view' => 'authkit::pages.app.confirm-two-factor',
                'nav_label' => 'Confirm two-factor',
                'show_in_sidebar' => false,
            ],
        ],

        /**
         * Authenticated navigation configuration.
         *
         * This section defines the default sidebar navigation rendered by
         * AuthKit's authenticated shell.
         *
         * Each item points to a page key from app.pages, which means consumers
         * can keep navigation and page configuration aligned in one place.
         *
         * Consumers may:
         * - reorder items
         * - remove items they do not need
         * - add their own items later if their resolver supports it
         *
         * Utility pages such as confirmation screens are intentionally left out
         * of the sidebar by default because users are usually redirected to them
         * only when needed.
         */
        'navigation' => [
            'sidebar' => [
                [
                    'page' => 'dashboard_web',
                    'route' => 'authkit.web.dashboard',
                    'icon' => 'home',
                ],

                [
                    'page' => 'settings',
                    'route' => '#',
                    'icon' => 'settings',
                    'children' => [
                        [
                            'page' => 'security',
                            'route' => 'authkit.web.settings.security',
                            'icon' => 'shield',
                        ],
                        [
                            'page' => 'settings',
                            'route' => 'authkit.web.settings',
                            'icon' => 'settings',
                        ],
                        [
                            'page' => 'sessions',
                            'route' => 'authkit.web.settings.sessions',
                            'icon' => 'devices',
                        ],
                    ],
                ],

                [
                    'page' => 'two_factor_settings',
                    'route' => 'authkit.web.settings.two_factor',
                    'icon' => 'key',
                ],
            ],
        ],

        /**
         * Authenticated middleware configuration.
         *
         * This section controls which middleware should protect AuthKit's
         * built-in authenticated pages.
         *
         * The base middleware is used as the default protection for the logged-in
         * application area, while the per-page map allows consumers to make some
         * pages stricter when needed.
         *
         * Why class names are used here:
         * - it keeps the configuration explicit
         * - it avoids relying on middleware aliases being registered elsewhere
         * - it makes package behavior easier to understand by simply reading config
         *
         * Notes:
         * - The default uses Laravel's built-in Authenticate middleware.
         * - Consumers may replace any entry with their own middleware class if
         *   they need tenant-aware auth, role-aware access checks, or project-
         *   specific security rules.
         */
        'middleware' => [

            /**
             * Baseline middleware applied to AuthKit's authenticated application pages.
             *
             * This stack represents the default protection layer for all pages rendered
             * inside AuthKit's logged-in application shell.
             *
             * By default this includes Laravel's standard authentication middleware,
             * which ensures that only authenticated users can access the application
             * dashboard and account management pages.
             *
             * Consumers may replace or extend this stack if their application requires
             * additional checks such as:
             * - tenant resolution
             * - verified email enforcement
             * - role/permission guards
             * - locale middleware
             * - application-specific access policies
             *
             * Example customization:
             *
             * [
             *     \Xul\AuthKit\Http\Middleware\Authenticate::class,
             *     \App\Http\Middleware\EnsureTenantIsResolved::class,
             * ]
             */
            'base' => [
                \Xul\AuthKit\Http\Middleware\Authenticate::class,
            ],

            /**
             * Per-page middleware overrides.
             *
             * Each key corresponds to a page defined in `app.pages`. The middleware
             * defined here will be applied when registering the route for that page.
             *
             * If a page is not listed here, the resolver may fall back to the `base`
             * middleware stack.
             *
             * This structure allows consumers to apply stricter protection to specific
             * pages without affecting the rest of the authenticated application area.
             *
             * Typical examples:
             * - requiring password confirmation before accessing security settings
             * - requiring two-factor confirmation before viewing recovery codes
             * - applying custom permission middleware to administrative pages
             *
             * Since middleware is defined as an array, consumers can easily extend
             * these stacks with additional middleware as needed.
             */
            'pages' => [

                'dashboard_web' => [
                    \Xul\AuthKit\Http\Middleware\Authenticate::class,
                ],

                'settings' => [
                    \Xul\AuthKit\Http\Middleware\Authenticate::class,
                ],

                'security' => [
                    \Xul\AuthKit\Http\Middleware\Authenticate::class,
                ],

                'sessions' => [
                    \Xul\AuthKit\Http\Middleware\Authenticate::class,
                ],

                'two_factor_settings' => [
                    \Xul\AuthKit\Http\Middleware\Authenticate::class,
                    \Xul\AuthKit\Http\Middleware\RequirePasswordConfirmationMiddleware::class,
                ],

                /**
                 * Password confirmation page.
                 *
                 * This page itself only requires the user to be authenticated.
                 * The confirmation middleware should NOT be applied here,
                 * otherwise the page would redirect to itself.
                 */
                'confirm_password' => [
                    \Xul\AuthKit\Http\Middleware\Authenticate::class,
                ],

                /**
                 * Two-factor confirmation page.
                 *
                 * Similar to the password confirmation page, this screen only
                 * requires authentication and should not include the middleware
                 * that enforces the confirmation itself.
                 */
                'confirm_two_factor' => [
                    \Xul\AuthKit\Http\Middleware\Authenticate::class,
                ],
            ],
        ],
    ],

    /**
     * Sensitive action confirmation configuration.
     *
     * This section controls "step-up" confirmation flows used when an already-authenticated
     * user attempts to access a sensitive page or perform a sensitive action.
     *
     * Supported confirmation types (current):
     * - password
     * - two_factor
     *
     * Examples:
     * - viewing or regenerating recovery codes
     * - accessing a highly sensitive settings page
     * - performing account-destructive operations
     * - confirming identity again before a protected action
     *
     * Design goals:
     * - keep confirmation freshness session-based and configurable
     * - separate confirmation flows from login-time authentication flows
     * - allow different confirmation types to have different lifetimes
     * - allow middleware to redirect users to configurable routes
     *
     * Important distinction:
     * - These confirmations do not replace login or two-factor login challenge flows.
     * - They are used after the user is already authenticated.
     */
    'confirmations' => [

        /**
         * Whether step-up confirmation features are enabled.
         *
         * When false:
         * - confirmation middleware should ideally behave as pass-through
         *   or remain unused by the consuming application.
         */
        'enabled' => true,

        /**
         * Session key configuration.
         *
         * These keys are used to store confirmation freshness timestamps and
         * redirect metadata for confirmation flows.
         *
         * Recommended behavior:
         * - password_key: store the time at which password confirmation succeeded
         * - two_factor_key: store the time at which two-factor confirmation succeeded
         * - intended_key: store the intended destination URL before redirecting
         * - type_key: optionally store the confirmation type being requested
         */
        'session' => [
            'password_key' => 'authkit.confirmed.password_at',
            'two_factor_key' => 'authkit.confirmed.two_factor_at',
            'intended_key' => 'authkit.confirmation.intended',
            'type_key' => 'authkit.confirmation.type',
        ],

        /**
         * Confirmation freshness lifetime in minutes.
         *
         * These values determine how long a successful confirmation remains valid
         * before the user must confirm again.
         *
         * Notes:
         * - Shorter lifetimes provide stronger security.
         * - Longer lifetimes reduce friction for users navigating settings pages.
         */
        'ttl_minutes' => [
            'password' => 15,
            'two_factor' => 10,
        ],

        /**
         * Route configuration for confirmation redirects.
         *
         * These routes are used by confirmation middleware when a required confirmation
         * is missing or stale.
         *
         * Notes:
         * - password: route name for the password confirmation page
         * - two_factor: route name for the two-factor confirmation page
         * - fallback: route name used when no intended URL is available after success
         */
        'routes' => [
            'password' => 'authkit.web.confirm.password',
            'two_factor' => 'authkit.web.confirm.two_factor',
            'fallback' => 'authkit.web.dashboard',
        ],

        /**
         * Password confirmation settings.
         */
        'password' => [

            /**
             * Whether password confirmations are enabled.
             */
            'enabled' => true,
        ],

        /**
         * Two-factor confirmation settings.
         */
        'two_factor' => [

            /**
             * Whether two-factor confirmations are enabled.
             */
            'enabled' => true,
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
        'option_items' => 'authkit::form.option-items',

        /**
         * Authenticated application shell components.
         *
         * These components render the logged-in AuthKit "app" experience, including
         * sidebar navigation, topbar actions, page headers, and shared account layouts.
         *
         * Notes:
         * - These are distinct from guest auth page components such as auth_header/auth_footer.
         * - Consumers may override these components to fully customize the authenticated shell
         *   without changing package route/controller structure.
         */
        'app_layout' => 'authkit::app.layout',
        'app_shell' => 'authkit::app.shell',
        'app_sidebar' => 'authkit::app.sidebar',
        'app_topbar' => 'authkit::app.topbar',
        'app_nav' => 'authkit::app.nav',
        'app_nav_item' => 'authkit::app.nav-item',
        'app_page_header' => 'authkit::app.page-header',
        'app_user_menu' => 'authkit::app.user-menu',

        /**
         * Account/settings page support components.
         *
         * These components are intended for reusable page sections rendered within
         * dashboard/settings/security/session pages.
         */
        'settings_section' => 'authkit::app.settings.section',
        'session_list' => 'authkit::app.sessions.list',
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
        'theme' => 'slate-gold',

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
        'mode' => 'light',

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
            'variant' => 'icon',

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
            'allow_system' => false,

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
     *   public/{assets.base_path}/css/themes/
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
                'amber-silk',
                'aurora',
                'forest',
                'imperial-gold',
                'ivory-gold',
                'midnight-blue',
                'neutral',
                'noir-grid',
                'ocean-mist',
                'paper-ink',
                'red-beige',
                'rose-ash',
                'slate-gold',
            ],
            'bootstrap' => [
                'amber-silk',
                'aurora',
                'forest',
                'imperial-gold',
                'ivory-gold',
                'midnight-blue',
                'neutral',
                'noir-grid',
                'ocean-mist',
                'paper-ink',
                'red-beige',
                'rose-ash',
                'slate-gold',
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

            /**
             * Authenticated app/account pages.
             *
             * These page modules support the logged-in AuthKit shell and its
             * account/security management screens.
             */
            'dashboard_web' => [
                'enabled' => true,
                'page_key' => 'dashboard_web',
            ],

            'settings' => [
                'enabled' => true,
                'page_key' => 'settings',
            ],

            'security' => [
                'enabled' => true,
                'page_key' => 'security',
            ],

            'sessions' => [
                'enabled' => true,
                'page_key' => 'sessions',
            ],

            'two_factor_settings' => [
                'enabled' => true,
                'page_key' => 'two_factor_settings',
            ],

            /**
             * Authenticated confirmation pages.
             *
             * These page modules support step-up confirmation screens that are shown
             * when a sensitive page or action requires fresh verification.
             */
            'confirm_password' => [
                'enabled' => true,
                'page_key' => 'confirm_password',
            ],

            'confirm_two_factor' => [
                'enabled' => true,
                'page_key' => 'confirm_two_factor',
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