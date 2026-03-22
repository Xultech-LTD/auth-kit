# Configuration

## Overview

AuthKit is designed to be a configuration-driven authentication system.

Instead of forcing fixed behavior, the package exposes its core functionality through a centralized configuration file. This allows you to control how authentication flows behave, how data is handled, how UI is rendered, and how security features are enforced — all without modifying the package source code.

The configuration file acts as the control layer for:

- authentication behavior
- route structure and naming
- middleware and guards
- validation rules and payload mapping
- form schemas and UI behavior
- notification delivery
- security flows such as email verification, password reset, and two-factor authentication
- frontend behavior including themes and JavaScript runtime

For most applications, the default configuration is sufficient to get started. As your application grows, you can progressively customize specific areas without rewriting core logic.


## Configuration Philosophy

AuthKit follows a few key principles in how configuration is structured and used.

### Configuration over hardcoding

Most behavior in AuthKit is not hardcoded. Instead, it is defined in configuration and resolved at runtime.

This means you can:

- change flow behavior without editing controllers or actions
- adjust routes, middleware, and naming conventions
- swap implementations such as notifiers or mappers
- control UI rendering through schema definitions

### Explicit control of data flow

AuthKit avoids implicit assumptions about how data should be handled.

Instead, it uses:

- mappers to define how input becomes normalized payloads
- schemas to define how forms are rendered
- DTOs to define how results are returned

This makes the system predictable and easier to extend safely.

### Separation of concerns

Configuration is organized to reflect the internal architecture of the package.

Different sections control different layers:

- HTTP layer (routes, middleware, controllers)
- action layer (validation, mappers)
- support layer (notifications, tokens, flows)
- UI layer (schemas, components, themes, JavaScript)

This separation makes it easier to understand where to make changes.

### Safe extensibility

Instead of modifying package files, you extend AuthKit by:

- overriding configuration
- replacing classes via config
- publishing and customizing resources

This ensures your customizations remain stable across updates.


## Publishing the Configuration File

To customize AuthKit, you need to publish its configuration file into your application.

Run the following command:

```bash
php artisan vendor:publish --tag=authkit-config
```
This will publish the configuration file into your application so you can edit it.

Publishing is required if you want to:

- change route behavior or prefixes
- modify authentication flows
- enable or disable features
- customize validation, mapping, or schemas
- adjust notification delivery
- configure UI behavior and themes

If you do not publish the configuration file, AuthKit will continue using its internal default configuration.

## Configuration File Location

After publishing, the configuration file will be available at:

- `config/authkit.php`

This file contains all configurable options for AuthKit.

You can open and edit it directly to customize behavior across the package.

## Important Note

If you make changes to the configuration and they do not appear to take effect, clear Laravel’s cached configuration:

```bash
php artisan optimize:clear
```
This ensures that your latest configuration values are loaded.

## Recommended Approach

When starting out:

- review the configuration file once to understand its structure
- keep defaults where possible
- only change what you need for your application

As your application grows, you can revisit specific sections to refine behavior and extend AuthKit further.

## Default Configuration Structure

The AuthKit configuration file is organized into logical sections that map directly to the internal architecture of the package.

Each section controls a specific part of the authentication system, allowing you to customize behavior without modifying package code.

At a high level, the configuration includes:

- authentication and guard settings
- identity (login field) configuration
- route structure and naming
- middleware stacks
- controller overrides
- validation providers
- payload mappers
- form schemas
- security flows (email verification, password reset, two-factor)
- rate limiting
- authenticated app configuration
- UI, themes, and JavaScript runtime

The structure is intentionally modular, so you can focus only on the parts relevant to your application.


## Authentication Configuration

The authentication configuration defines which Laravel guard AuthKit should use when resolving and managing authenticated users.

Example:

```php
'auth' => [
    'guard' => 'web',
],
```
### Key Option

**guard**: The authentication guard used by AuthKit

This value should match one of the guards defined in `config/auth.php`.

AuthKit uses this guard for:

- login and logout
- resolving the current authenticated user
- password confirmation flows
- email verification state
- authenticated app pages and actions

### When to Change This

You may need to change the guard if:

- your application uses a custom guard (e.g., `admin`, `tenant`)
- you separate authentication contexts across different parts of your system

If the guard is misconfigured, authentication flows may fail or resolve the wrong user model.

## Identity Configuration

The identity configuration defines the primary login identifier used by AuthKit and how that identifier should be treated across forms and validation defaults.

AuthKit does not assume that every application uses email for login. You can configure the package to work with other identity fields such as username or phone, as long as your user provider and database schema support them.

The identity section is structured like this:

```php
'identity' => [
    'login' => [
        'field' => 'email',
        'label' => 'Email',
        'input_type' => 'email',
        'autocomplete' => 'email',
        'normalize' => 'lower',
    ],
],
```
### Key options

#### `identity.login.field`

This is the **primary identity field used for authentication at the database level**.

It represents the column AuthKit will use when resolving a user during login.

Examples:

- `email`
- `username`
- `phone`

This value is used internally by authentication logic and is **not responsible for rendering form inputs**.

---

#### `identity.login.label`

This is the **default label associated with the identity field**.

It may be used as a fallback in UI contexts, but actual form rendering is driven by the **schema system**, not this configuration.

Example values:

- `Email`
- `Username`
- `Phone number`

---

#### `identity.login.input_type`

This defines the **default HTML input type associated with the identity field**.

Like `label`, this is a fallback/default and does not override schema-driven form rendering.

Examples:

- `email`
- `text`
- `tel`

---

#### `identity.login.autocomplete`

This sets the default browser autocomplete attribute associated with the identity field.

Examples:

- `email`
- `username`
- `tel`

---

#### `identity.login.normalize`

This controls optional normalization applied to the identity input **before validation and authentication**.

Allowed values:

- `null`
- `lower`
- `trim`

This ensures consistent handling of identity values at the authentication layer.

For example:

- `lower` is commonly used for email-based login
- `trim` removes accidental whitespace from input

Custom normalization logic can be introduced later through actions or extension points.


### What this affects

This section influences:

- how AuthKit resolves users during authentication
- which database field is used as the login identifier
- how identity input is normalized before authentication logic runs
- default identity metadata used across the system

### What this does NOT affect

This configuration **does not control form rendering directly**.

AuthKit uses the **schema system** to define:

- which fields appear in forms
- field order and structure
- labels and UI presentation
- validation rules and input behavior

If you want to change how the login field appears in the UI, you should modify the relevant schema configuration instead.

### Important note

Changing the identity configuration does **not** modify your database schema automatically.

If you switch from `email` to another field such as `username`, you must ensure:

- your users table contains the corresponding column
- your user provider can resolve users using that field
- your authentication flow is aligned with the chosen identity

### Example: username-based authentication

```php
'identity' => [
    'login' => [
        'field' => 'username',
        'label' => 'Username',
        'input_type' => 'text',
        'autocomplete' => 'username',
        'normalize' => 'trim',
    ],
],
```
### Example: phone login
```php
'identity' => [
    'login' => [
        'field' => 'phone',
        'label' => 'Phone',
        'input_type' => 'tel',
        'autocomplete' => 'tel',
        'normalize' => 'trim',
    ],
],
```
## Registration Configuration
The `registration` section controls package-level behavior related to account creation.

At the moment, this section is primarily responsible for AuthKit’s default identity uniqueness behavior during registration.

This allows AuthKit to provide a safe default for common registration flows while still giving consumers full control when they need custom validation behavior.

#### Overview
````php
'registration' => [
    'enforce_unique_identity' => true,
    'unique_identity' => [
        'table' => null,
        'column' => null,
    ],
],
````
### Why this section exists
In most applications, the primary registration identity should be unique.
For example:

- an email address should usually not be reused across multiple accounts
- a username should usually be unique
- a phone-based identity may also need uniqueness depending on the application

AuthKit therefore adds a default unique validation rule for the configured identity field during registration.
However, because not every application has the same schema or requirements, this behavior is configurable.

#### `registration.enforce_unique_identity`

```php
'enforce_unique_identity' => true,
```
Controls whether AuthKit should automatically apply a default unique rule to the configured registration identity field.

**Behavior**

When set to true:

- AuthKit checks the configured identity field from authkit.identity.login.field
- if that field exists in the resolved register schema
- AuthKit adds a default unique validation rule for that field

When set to false:

- AuthKit does not apply its default identity uniqueness rule
- consumers may still enforce uniqueness through:
- a custom validation provider
- database constraints
- custom controller or action logic

**Recommended usage**

For most applications, this should remain enabled.

Disabling it is mainly useful when:

- your application handles uniqueness in a custom validation provider
- your registration flow needs non-standard identity behavior
- you are integrating AuthKit into a legacy schema or workflow

#### `registration.unique_identity.table`
```php
'table' => null,
```

Defines the database table AuthKit should use when building the default unique rule for the registration identity.

**Resolution behavior**

When set to `null`:

-AuthKit attempts to resolve the table automatically from the configured auth provider model

When set to a `string`:

- AuthKit uses that table name directly

**Example**

```php
'registration' => [
    'unique_identity' => [
        'table' => 'users',
    ],
],
```

This is useful when:

- your user data lives in a custom table
- you want to avoid relying on automatic provider-based resolution
- your application uses a legacy schema

#### `registration.unique_identity.column`
```php
'column' => null,
```

Defines the database column AuthKit should use for the default registration identity uniqueness rule.

**Resolution behavior**

When set to `null`:

- AuthKit uses the configured identity field from authkit.identity.login.field

When set to a `string`:

- AuthKit uses that column name directly

**Example**
```php

'registration' => [
    'unique_identity' => [
        'column' => 'email_address',
    ],
],
```
This is useful when:

- your registration identity field maps to a differently named database column
- your application uses a legacy schema
- your UI field naming and persistence column naming are intentionally different

### How this works with identity configuration

The registration uniqueness logic builds on top of the identity configuration.

For example, if you set:

```php
'identity' => [
    'login' => [
        'field' => 'username',
    ],
],
````
then AuthKit will treat username as the canonical identity field for registration uniqueness, unless you explicitly override the column in:

`registration.unique_identity.column`
This means: `identity.login.field` defines the canonical identity key registration controls whether and how uniqueness is enforced for that identity during registration

## Important note

This configuration affects only AuthKit’s built-in default registration validation behavior.

It does not prevent you from replacing or extending registration validation through a custom validation provider.

If a custom provider is configured for the register context, you remain fully in control of the final validation rules used by your application.

**Example: default email-based uniqueness**

```php
'identity' => [
    'login' => [
        'field' => 'email',
    ],
],

'registration' => [
    'enforce_unique_identity' => true,
    'unique_identity' => [
        'table' => null,
        'column' => null,
    ],
],
```
With this configuration:

- AuthKit uses email as the primary identity field
- AuthKit attempts to resolve the user table automatically
- AuthKit applies a default unique rule to the email field during registration

**Example: username-based registration**

```php
'identity' => [
    'login' => [
        'field' => 'username',
        'label' => 'Username',
        'input_type' => 'text',
        'autocomplete' => 'username',
        'normalize' => 'trim',
    ],
],

'registration' => [
    'enforce_unique_identity' => true,
    'unique_identity' => [
        'table' => 'users',
        'column' => 'username',
    ],
],
```
With this configuration:

- AuthKit uses username as the canonical identity
- registration applies uniqueness against users.username

**Example: disable default uniqueness**

```php
'registration' => [
    'enforce_unique_identity' => false,
],
```
With this configuration:

- AuthKit does not apply its built-in unique rule
- you are responsible for uniqueness behavior elsewhere

This can be appropriate when uniqueness is enforced through a custom register validation provider or another application-specific rule layer.

### Best practice

- keep identity uniqueness enabled in most production applications
- override the table or column only when your schema requires it
- use a custom validation provider when your registration rules go beyond AuthKit defaults
- keep database-level unique indexes aligned with your validation behavior

## Routes Configuration

The routes configuration controls the top-level structure used when AuthKit registers its routes.

AuthKit separates its routes into two major layers:

- **web**: browser-facing pages and navigation endpoints
- **api**: submitted action endpoints such as login, registration, reset, token verification, and other state-changing requests

The routes section is structured like this:
```php
'routes' => [
    'prefix' => '',
    'middleware' => ['web'],
    'groups' => [
        'web' => [
            'middleware' => [],
        ],
        'api' => [
            'middleware' => [],
        ],
    ],
],
```
### Key Options

#### `routes.prefix`

This is the global prefix applied to all AuthKit routes.

Examples:

- `''` for no prefix
- `'auth'`
- `'dashboard/auth'`

If you set:

```php
'prefix' => 'auth',
```
then routes such as `/login` and `/register` would become `/auth/login` and `/auth/register`.

#### `routes.middleware`

This is the global middleware stack applied to all AuthKit routes before group-level middleware is applied.

Default:

```php
'middleware' => ['web'],
``` 
This ensures that AuthKit routes run inside Laravel’s standard web middleware stack unless you change it.

#### `routes.groups.web.middleware`

This defines additional middleware applied specifically to AuthKit’s web routes.

These are typically used for:

- login page
- register page
- verify notice pages
- password reset pages
- signed verification links accessed through the browser

#### `routes.groups.api.middleware`

This defines additional middleware applied specifically to AuthKit’s action endpoints.

These are typically used for:

- login submission
- register submission
- password reset requests
- token verification requests
- two-factor challenge submissions

Consumers can add middleware here for project-specific behavior such as:

- throttle rules
- bindings
- tenant resolution
- custom security layers

### Why This Structure Matters

This design keeps route registration flexible without forcing you to edit package routes directly.

It allows you to:

- change the URL base for AuthKit
- apply middleware globally or per group
- keep browser pages and state-changing endpoints clearly separated

## Middleware Configuration

The `middlewares` section defines reusable middleware stacks used by different AuthKit flows and pages.

This is separate from the top-level `routes` section.

The `routes` section controls how route groups are registered, while the `middlewares` section provides the actual named stacks AuthKit can use internally for specific purposes.

The section is structured like this:
```php
'middlewares' => [
    'authenticated' => ['auth'],
    'email_verification_required' => [
        'web',
        \Xul\AuthKit\Http\Middleware\EnsurePendingEmailVerificationMiddleware::class,
    ],
    'password_reset_required' => [
        'web',
        \Xul\AuthKit\Http\Middleware\EnsurePendingPasswordResetMiddleware::class,
    ],
    'authenticated_app' => ['web', 'auth'],
    'password_confirmation_required' => [
        'web',
        'auth',
        \Xul\AuthKit\Http\Middleware\RequirePasswordConfirmationMiddleware::class,
    ],
    'two_factor_confirmation_required' => [
        'web',
        'auth',
        \Xul\AuthKit\Http\Middleware\RequireTwoFactorConfirmationMiddleware::class,
    ],
],
```
### Available Middleware Stacks

#### `middlewares.authenticated`

Middleware applied to pages that require an authenticated user.

Default:

```php
['auth']
```
#### `middlewares.email_verification_required`

Middleware applied to pages that require an authenticated user with a pending email verification context.

Default:

```php
[
    'web',
    \Xul\AuthKit\Http\Middleware\EnsurePendingEmailVerificationMiddleware::class,
]
```
This is used for flows where AuthKit expects the user to still be inside an email verification process.

#### `middlewares.password_reset_required`

Middleware applied to reset-password pages that require a valid pending password reset context.

Default:

```php
[
    'web',
    \Xul\AuthKit\Http\Middleware\EnsurePendingPasswordResetMiddleware::class,
]
```

This prevents direct access to reset pages without the required reset state.

#### `middlewares.authenticated_app`

Baseline middleware for AuthKit’s authenticated application area.

Default:
```php
['web', 'auth']
```
This is typically used for:

- dashboard
- settings
- security
- sessions
- two-factor settings
- authenticated confirmation pages

#### `middlewares.password_confirmation_required`

Middleware for routes or pages that require a fresh password confirmation.

Default:

```php
[
    'web',
    'auth',
    \Xul\AuthKit\Http\Middleware\RequirePasswordConfirmationMiddleware::class,
]
```
This is intended for sensitive actions such as:

- security settings access
- dangerous account actions
- password-protected management areas

#### `middlewares.two_factor_confirmation_required`

Middleware for routes or pages that require a fresh two-factor confirmation.
```php
[
    'web',
    'auth',
    \Xul\AuthKit\Http\Middleware\RequireTwoFactorConfirmationMiddleware::class,
]
```
This is intended for highly sensitive areas such as:

- recovery code access
- two-factor management actions
- step-up security checkpoints

### Why This Section Exists

This configuration gives you explicit control over the middleware used by AuthKit’s flows without editing package internals.

You can replace or extend these stacks to support things such as:

- tenant-aware authentication
- verified email requirements
- locale middleware
- role checks
- custom project-level guards  

## Route Names Configuration

AuthKit names all of its routes and relies on those names internally instead of hard-coded URLs.

This allows consumers to override route names to match application conventions while preserving package behavior.

The `route_names` section is divided into:

- **web**: page routes and browser navigation
- **api**: submitted action routes

### Web Route Names

These are GET routes used to render pages or handle browser navigation.

The structure is:

```php
'route_names' => [
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
        
        'dashboard_web' => 'authkit.web.dashboard',
        'settings' => 'authkit.web.settings',
        'security' => 'authkit.web.settings.security',
        'sessions' => 'authkit.web.settings.sessions',
        'two_factor_settings' => 'authkit.web.settings.two_factor',
        'confirm_password' => 'authkit.web.confirm.password',
        'confirm_two_factor' => 'authkit.web.confirm.two_factor',
    ],
],
```

These route names cover:

- guest auth pages
- email verification pages
- password reset pages
- authenticated app pages
- authenticated step-up confirmation pages

### Important Distinction

Some names represent login-time two-factor flow pages:

- `two_factor_challenge`
- `two_factor_recovery`

Others represent authenticated confirmation pages used after login:

- `confirm_password`
- `confirm_two_factor`

These serve different purposes and should not be confused.

### API Route Names

These are state-changing action routes such as POST, PUT, PATCH, or DELETE endpoints.

The structure is:
```php
'route_names' => [
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
        
        'password_update' => 'authkit.api.settings.password.update',
        'two_factor_confirm' => 'authkit.api.settings.two_factor.confirm',
        'two_factor_disable' => 'authkit.api.settings.two_factor.disable',
        'two_factor_recovery_regenerate' => 'authkit.api.settings.two_factor.recovery.regenerate',
        'confirm_password' => 'authkit.api.confirm.password',
        'confirm_two_factor' => 'authkit.api.confirm.two_factor',
    ],
],
```
These route names cover:

- guest authentication submissions
- verification and reset actions
- login-time two-factor challenge actions
- authenticated settings actions
- authenticated step-up confirmation actions

### Why Route Names Matter

AuthKit uses route names internally for:

- redirects
- page links
- success flow routing
- post-action navigation
- authenticated app navigation
- verification and reset flow transitions

Because of this, route names should be changed carefully.

If you override them, make sure:

- the new names actually exist
- they point to the intended routes
- they still align with the relevant AuthKit flow

Incorrect route naming can break redirects, navigation, or flow transitions.

### Best Practice

Only change route names when:

- you need AuthKit to match an existing application naming convention
- you are integrating it into a larger route architecture
- you clearly understand which flows depend on which route names

Otherwise, keeping the defaults is usually the safest approach.

## Controller Overrides

AuthKit keeps its route definitions internal, but it allows you to override the controller class used for any supported endpoint through configuration.

This gives you a way to customize request handling while keeping AuthKit’s route structure and flow wiring intact.

The controller override section is structured like this:

```php
'controllers' => [
    'web' => [
        'login' => \Xul\AuthKit\Http\Controllers\Web\Auth\LoginViewController::class,
        'register' => \Xul\AuthKit\Http\Controllers\Web\Auth\RegisterViewController::class,
        // ...
    ],
    'api' => [
        'login' => \Xul\AuthKit\Http\Controllers\Api\Auth\LoginController::class,
        'register' => \Xul\AuthKit\Http\Controllers\Api\Auth\RegisterController::class,
        // ...
    ],
],
```
### How It Works

AuthKit routes use internal controller keys rather than hard-coding controller classes directly into route definitions.

At runtime, AuthKit resolves each controller key to the configured class.

- If you do not override a controller, AuthKit uses the package default
- If you do override one, your configured class will be used instead
> Your controller definition must be an invokable.
```php
<?php

namespace App\Http\Controllers;

class CustomRegisterController extends Controller
{
    public function __invoke()
    {
        
    }
}

```

### Structure

The configuration is divided into two groups:

#### `controllers.web`

These are controllers responsible for rendering pages and handling browser navigation.

Examples include:

- login page
- register page
- two-factor challenge page
- email verification pages
- password reset pages
- authenticated app pages
- authenticated confirmation pages

#### `controllers.api`

These are controllers responsible for handling submitted requests and other state-changing actions.

Examples include:

- login submission
- register submission
- logout
- password reset actions
- token verification actions
- two-factor challenge actions
- authenticated settings actions
- step-up confirmation actions

### Requirements

Any controller class you provide must:

- be a fully qualified class name
- be resolvable through the Laravel container
- be invokable as a single-action controller

### Example: Override the Login Page Controller

```php
'controllers' => [
    'web' => [
        'login' => \App\Http\Controllers\Auth\CustomLoginViewController::class,
    ],
],
```
### Example: Override the Register Action Controller
```php
'controllers' => [
    'api' => [
        'register' => \App\Http\Controllers\Auth\CustomRegisterController::class,
    ],
],
```
### When to Use Controller Overrides

Controller overrides are useful when you want to:

- keep AuthKit’s routes but change page data or rendering behavior
- plug in your own request handling logic
- customize redirects or response behavior at the controller layer
- integrate AuthKit into an existing application controller structure

### Best Practice

Use controller overrides only when controller-level customization is the right tool.

In many cases, AuthKit already provides more focused extension points through:

- validation providers
- payload mappers
- schemas
- notifiers
- configuration flags

If your goal is only to change validation, payload shape, or rendering, use those extension points first before replacing controllers.

## Validation Providers

AuthKit supports configurable validation providers for each supported flow context.

This allows you to customize request validation without publishing or editing package FormRequest classes.

The `validation` section is structured like this:

```php
'validation' => [
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
        'confirm_password' => null,
        'confirm_two_factor' => null,
        'password_update' => null,
        'two_factor_confirm' => null,
        'two_factor_disable' => null,
        'two_factor_recovery_regenerate' => null,
    ],
],
```
### How It Works

For each form or action context, AuthKit can build sensible default validation rules based on:

- the active flow
- the configured schema
- package defaults

If a custom provider class is configured for that context, AuthKit resolves it from the container and uses its output instead of the default rules.

### Provider Contract (Required)

Any custom validation provider must implement the following contract:

`Xul\AuthKit\Contracts\Validation\RulesProviderContract`

This contract defines three methods:

- `rules()`
- `messages()`
- `attributes()`

Each method receives:

- the current request
- the resolved schema
- the default values provided by AuthKit

This allows you to either:

- fully override validation behavior, or
- extend the default rules, messages, and attributes

#### Contract structure
```php
use Illuminate\Http\Request;
use Xul\AuthKit\Contracts\Validation\RulesProviderContract;

class RegisterRulesProvider implements RulesProviderContract
{
    public function rules(Request $request, array $schema, array $defaults): array
    {
        return array_merge($defaults, [
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
        ]);
    }

    public function messages(Request $request, array $schema, array $defaults): array
    {
        return $defaults;
    }

    public function attributes(Request $request, array $schema, array $defaults): array
    {
        return $defaults;
    }
}
```


### Supported Contexts

Validation providers can be configured for:

#### Guest Auth Flows

- `login`
- `register`

#### Two-Factor Login Flows

- `two_factor_challenge`
- `two_factor_recovery`
- `two_factor_resend`

#### Email Verification Flows

- `email_verification_token`
- `email_verification_send`

#### Password Reset Flows

- `password_forgot`
- `password_reset`
- `password_reset_token`

#### Authenticated Confirmation Flows

- `confirm_password`
- `confirm_two_factor`

#### Authenticated Settings Flows

- `password_update`
- `two_factor_confirm`
- `two_factor_disable`
- `two_factor_recovery_regenerate`

### Example: Custom Register Validation Provider

```php
'validation' => [
    'providers' => [
        'register' => \App\AuthKit\Validation\RegisterRulesProvider::class,
    ],
],
```
### What a Provider Should Do

A validation provider should return the rules AuthKit should use for the configured context.

It should be designed specifically for that flow.

For example, a custom register provider may add:

- username requirements
- stricter password rules
- required terms acceptance
- project-specific domain restrictions

### When to Use Validation Providers

Use a validation provider when you need to:

- change validation rules for a specific flow
- add project-specific requirements
- enforce business rules beyond defaults
- modify validation messages or attribute names
- extend schema-derived validation safely

### Best Practice

- always implement the contract fully
- start from `$defaults` when possible instead of rewriting everything
- keep providers scoped to a single context
- do not mix validation logic with transformation logic (use mappers for that)

Validation providers should remain focused on validation only, while payload transformation should be handled by mappers.

## Payload Mappers

Payload mappers are one of the core extension points in AuthKit.

They are responsible for translating validated request input into the normalized payload structure consumed by AuthKit actions.

This means mappers sit between request validation and action execution.

The `mappers` section is structured like this:

```php
'mappers' => [
    'contexts' => [
        'login' => [
            'class' => null,
            'schema' => 'login',
        ],
        'register' => [
            'class' => null,
            'schema' => 'register',
        ],
        // ...
    ],
],
```
### How It Works

Each mapper context defines two things:

- **class**: the custom mapper class to use, or `null` to use the package default
- **schema**: the schema context associated with that mapper

When a request is processed:

- AuthKit validates the incoming data
- AuthKit resolves the configured mapper for that context
- The mapper transforms validated input into a normalized payload
- The resulting payload is passed into the relevant action

### Why Mappers Exist

Mappers make AuthKit’s flow handling more explicit and extensible.

Instead of assuming that validated request data should be passed directly into actions, AuthKit normalizes it first.

This helps with:

- clean separation between form structure and action input
- controlled persistence of only allowed fields
- transformation of incoming data into action-friendly payloads
- keeping actions stable even when forms evolve

### Supported Mapper Contexts

AuthKit supports mapper contexts for flows such as:

- `login`
- `register`
- `two_factor_challenge`
- `two_factor_recovery`
- `two_factor_resend`
- `email_verification_token`
- `email_verification_send`
- `password_forgot`
- `password_reset`
- `password_reset_token`
- `confirm_password`
- `confirm_two_factor`
- `password_update`
- `two_factor_confirm`
- `two_factor_disable`
- `two_factor_disable_recovery`
- `two_factor_recovery_regenerate`

### Example: Custom Register Mapper

```php
'mappers' => [
    'contexts' => [
        'register' => [
            'class' => \App\AuthKit\Mappers\RegisterPayloadMapper::class,
            'schema' => 'register',
        ],
    ],
],
```
### Relationship to Schemas

Mapper contexts intentionally mirror schema contexts.

This means AuthKit can keep the UI layer and the action layer aligned:

- schemas define the canonical form structure
- validation checks submitted data
- mappers normalize validated input for actions

This is especially important in flows like registration, where you may want to:

- add or remove fields in the schema
- validate extra data
- persist only specific mapped fields
- transform raw input before it reaches the action

### When to Use a Custom Mapper

Use a custom mapper when you need to:

- reshape validated input before action execution
- support custom registration fields
- map UI field names into internal action payload keys
- filter or normalize data beyond the default mapping behavior
- align form submissions with custom persistence logic

### Best Practice

Treat mappers as transformation layers, not validation layers.

Validation should remain in validation providers or request validation.

Mappers should focus on producing a clean, normalized payload that actions can trust.
## Form Schemas

Form schemas are the canonical definition of AuthKit forms.

They define which fields a form contains, how those fields should be rendered, and the metadata associated with them. In AuthKit, schemas are the primary configuration layer for form structure.

This means form rendering is not hardcoded into page templates. Instead:

- page templates define overall page composition
- schemas define the fields rendered inside those pages

The schema section is structured like this:

```php
'schemas' => [
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
        ],
    ],
],
```
### Design Goals

The schema system is designed to:

- let consumers add, remove, reorder, or replace fields from configuration
- support a wide range of field types
- keep page templates clean
- centralize form field metadata
- support extensible option sources and custom components

### Top-Level Schema Structure

Each form schema supports two main top-level keys:

#### `submit`

Defines metadata for the primary submit action.

Example:

```php
'submit' => [
    'label' => 'Continue',
],
```
#### `fields`

Defines an ordered map of field names to field definitions.

Example:

```php
'fields' => [
    'email' => [
        'label' => 'Email',
        'type' => 'email',
    ],
],
```
### Common field definition keys

AuthKit normalizes field definitions through its schema resolver layer.

A field definition may include keys such as:

- `label`
- `type`
- `required`
- `placeholder`
- `help`
- `autocomplete`
- `inputmode`
- `value`
- `value_resolver`
- `checked`
- `multiple`
- `rows`
- `accept`
- `options`
- `attributes`
- `wrapper`
- `component`
- `render`

Not every field uses every key. The resolver normalizes these keys, but their practical use depends on the field type and the rendering component used.

For example:

- `checked` is mainly relevant for checkbox-like fields
- `rows` is mainly relevant for textarea fields
- `accept` is mainly relevant for file inputs
- `options` is only meaningful for option-bearing fields
- `component` can be used to override the default component resolution for a field
- `render` controls whether the field should be rendered at all

### Supported field types

AuthKit’s field definition resolver normalizes the following field types:

#### Scalar inputs

- `text`
- `email`
- `password`
- `hidden`
- `number`
- `tel`
- `url`
- `search`
- `date`
- `datetime-local`
- `time`
- `month`
- `week`
- `color`
- `file`

#### Rich text

- `textarea`

#### Boolean and single-choice

- `checkbox`
- `radio`

#### Multi-choice and grouped inputs

- `select`
- `multiselect`
- `radio_group`
- `checkbox_group`

#### Semantic and extensible types

- `otp`
- `custom`

If an unsupported type is provided, AuthKit normalizes it to `text`.

### Important note about component resolution

Although the schema resolver recognizes the field types above, the default field component resolver maps them like this:

- `textarea` → textarea component
- `select`, `multiselect` → select component
- `checkbox` → checkbox component
- `otp` → otp component
- everything else → input component by default

This means some normalized field types may still render through the generic input component unless you provide a custom component override or extend the component resolver.

### Supported option sources

AuthKit supports option resolution for option-bearing fields through the field options resolver.

Option resolution applies to these field types:

- `select`
- `multiselect`
- `radio_group`
- `checkbox_group`
- `radio`

Supported option sources are:

- `array`
- `enum`
- `class`
- `model`

#### Array source

```php
'options' => [
    'source' => 'array',
    'items' => [
        ['value' => 'sms', 'label' => 'SMS'],
        ['value' => 'email', 'label' => 'Email'],
    ],
],
```
#### Enum source
```php
'options' => [
    'source' => 'enum',
    'class' => \App\Enums\AccountType::class,
],
```
#### Class source
```php
'options' => [
    'source' => 'class',
    'class' => \App\Support\Auth\CountryOptionsProvider::class,
],
```
> For class option sources, the provider should implement: `Xul\AuthKit\Contracts\Forms\FieldOptionsProviderContract`

**Example**
```php
<?php

namespace App\Support\Auth;

use Xul\AuthKit\Contracts\Forms\FieldOptionsProviderContract;

class CountryOptionsProvider implements FieldOptionsProviderContract
{
    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $field, array $context = []): array
    {
        return [
            [
                'label' => 'Africa',
                'attributes' => [
                    'data-region' => 'africa',
                ],
                'options' => [
                    [
                        'value' => 'ng',
                        'label' => 'Nigeria',
                        'attributes' => [
                            'data-country-code' => 'ng',
                        ],
                    ],
                    [
                        'value' => 'gh',
                        'label' => 'Ghana',
                        'disabled' => true,
                    ],
                ],
            ],
            [
                'label' => 'Europe',
                'options' => [
                    ['value' => 'gb', 'label' => 'United Kingdom'],
                    ['value' => 'de', 'label' => 'Germany'],
                ],
            ],
            [
                'label' => 'North America',
                'disabled' => false,
                'options' => [
                    ['value' => 'us', 'label' => 'United States'],
                    ['value' => 'ca', 'label' => 'Canada'],
                ],
            ],
        ];
    }
}
```
#### Model source
```php
'options' => [
    'source' => 'model',
    'model' => \App\Models\Country::class,
    'label_by' => 'name',
    'value_by' => 'id',
    'order_by' => 'name',
],
```
### Value Precedence

When resolving a field’s effective value, AuthKit uses this precedence:

- old input from the previous request
- runtime values passed into schema resolution
- configured `value_resolver`
- static schema value
- `null`

For checkbox-like fields, checked-state resolution follows a similar precedence:

- old input
- runtime values
- configured checked value

If a custom `value_resolver` is used, the resolver class should implement:`Xul\AuthKit\Contracts\Forms\FieldValueProviderContract`

### Important Note About Identity Configuration

The schema system controls form rendering.

The identity configuration does not directly determine which fields appear in forms. It defines the canonical authentication identity used by AuthKit internally.

If you want to change the visual login field, you should update the appropriate schema.

### Available Built-In Schema Contexts

AuthKit ships with schema definitions for flows such as:

- `login`
- `register`
- `two_factor_challenge`
- `two_factor_resend`
- `two_factor_recovery`
- `email_verification_token`
- `email_verification_send`
- `password_forgot`
- `password_forgot_resend`
- `password_reset`
- `password_reset_token`
- `confirm_password`
- `confirm_two_factor`
- `password_update`
- `two_factor_confirm`
- `two_factor_disable`
- `two_factor_disable_recovery`
- `two_factor_recovery_regenerate`

**Example: Adding a Custom Field to Registration**

```php
'schemas' => [
    'register' => [
        'submit' => [
            'label' => 'Create account',
        ],
        'fields' => [
            'name' => [
                'label' => 'Name',
                'type' => 'text',
                'required' => true,
            ],
            'email' => [
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
            ],
            'username' => [
                'label' => 'Username',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Choose a username',
            ],
            'password' => [
                'label' => 'Password',
                'type' => 'password',
                'required' => true,
            ],
            'password_confirmation' => [
                'label' => 'Confirm password',
                'type' => 'password',
                'required' => true,
            ],
        ],
    ],
],
```
If you add fields like this, make sure the rest of your flow is aligned:

- validation providers
- payload mappers
- persistence behavior
- database schema

### Best Practice

Treat schemas as the source of truth for form structure, not business logic.

Use:

- schemas for rendering and field metadata
- validation providers for validation behavior
- mappers for transforming validated input
- actions for business logic  

## Login Redirect Configuration

The login configuration controls where users are redirected after a successful authentication flow.

This includes the standard login flow and, where applicable, the point after two-factor authentication is completed.

The section is structured like this:
```php
'login' => [
    'redirect_route' => null,
    'dashboard_route' => 'authkit.web.dashboard',
],
```
### Key Options

#### `login.redirect_route`

This is the preferred named route AuthKit should redirect to after a successful login.

**Behavior:**

- if this is `null`, AuthKit falls back to `dashboard_route`
- if this is a string, it is treated as a named route

**Example:**

```php
'redirect_route' => 'app.home',
```
#### `login.dashboard_route`
This is the default named route used when redirect_route is null.

Default:
```php
'dashboard_route' => 'authkit.web.dashboard',
```
This is useful when you want a stable default destination for authenticated users.

**Example: Custom Post-Login Destination**
```php
'login' => [
    'redirect_route' => 'dashboard',
    'dashboard_route' => 'dashboard',
],
```
### Best Practice

If your application uses AuthKit’s authenticated app area, keeping the default dashboard route is usually fine.

If your application has its own post-login landing page, set `redirect_route` explicitly to your preferred named route.

Make sure any route name you configure actually exists.

## Asset Configuration

The asset configuration defines the **public base path** AuthKit uses when referencing published frontend assets.

AuthKit publishes its built frontend files from the package `dist` directory into your application's public directory through the `authkit-assets` publish tag.

The asset section is structured like this:

```php
'assets' => [
    'base_path' => 'vendor/authkit',
    'base' => [
        'css' => [
            // 'css/authkit.css',
        ],
        'js' => [
            // 'js/authkit.js',
        ],
    ],
],
```
### How AuthKit Uses This Configuration

AuthKit layouts resolve asset URLs using `authkit.assets.base_path`.

For example, in the packaged layouts, the base path is used to build URLs like:

```php
asset($basePath . '/css/themes/' . $themeFile)

asset($basePath . '/' . ltrim($path, '/'))
```
This means the asset configuration is not just informational. It directly affects how AuthKit loads:

- base CSS assets
- base JavaScript assets
- theme stylesheets
- extra CSS configured through UI extensions
- extra JavaScript configured through UI extensions

### Key Options

#### `assets.base_path`

This defines the public base directory under which AuthKit assets are expected to live.

**Default:**
```php
'base_path' => 'vendor/authkit',
```
With the default setup, published assets are expected under paths such as:

- `public/vendor/authkit/js/authkit.js`
- `public/vendor/authkit/css/themes/tailwind-forest.css`

This aligns with the package publishable defined in the service provider:

```php
$this->publishes([
    __DIR__ . '/../dist' => public_path('vendor/authkit'),
], 'authkit-assets');
```
So by default:
- package assets are published into `public/vendor/authkit`
- layouts resolve them from that same base path

#### `assets.base.css`

This allows you to define additional base CSS assets relative to `public/{assets.base_path}`.

**Example:**

```php
'assets' => [
    'base' => [
        'css' => [
            'css/authkit.css',
        ],
    ],
],
```
These files are loaded before the resolved theme stylesheet.

#### `assets.base.js`

This allows you to define base JavaScript assets relative to `public/{assets.base_path}`.

**Example:**

```php
'assets' => [
    'base' => [
        'js' => [
            'js/authkit.js',
        ],
    ],
],
```
If no base JavaScript assets are defined and script loading is enabled, the packaged layouts fall back to:

```php
['js/authkit.js']
```
This means AuthKit expects `js/authkit.js` to exist under the configured base path unless you explicitly override the base JS list.

### What This Affects

This section affects how AuthKit resolves and loads frontend assets in its layouts, including:

- base CSS files
- base JS files
- theme stylesheets
- configured extension CSS files
- configured extension JS files

It works closely with other configuration sections, especially:

- `ui`
- `themes`
- `javascript`

### Relationship to UI and Theme Loading

The asset configuration provides the base path, while the UI and theme configuration determine what gets loaded.

For example:

- `authkit.ui.load_stylesheet` controls whether the theme stylesheet is loaded
- `authkit.themes.file_pattern` controls the generated theme filename
- `authkit.ui.extensions.extra_css` and `authkit.ui.extensions.extra_js` add more files under the same base path
- `authkit.javascript.enabled` and `authkit.ui.load_script` influence JavaScript loading behavior

So the asset path configuration should always stay aligned with your published asset structure and your UI configuration.

### Typical Usage

In most applications, you will keep the default base path and publish assets using:

```bash
php artisan vendor:publish --tag=authkit-assets
```
That will place the package’s built assets where AuthKit already expects them by default.

**Example: Custom Asset Base Path**

If your application prefers a different public asset structure, you can change the base path:

```php
'assets' => [
    'base_path' => 'assets/authkit',
],
```
That will place the package’s built assets where AuthKit already expects them by default.

**Example: Custom Asset Base Path**

If your application prefers a different public asset structure, you can change the base path:

```php
'assets' => [
    'base_path' => 'assets/authkit',
],
```

## Form Submission and Loading State

The `forms` configuration controls how AuthKit forms behave on the frontend.

This includes:

- whether forms submit through normal HTTP requests or AJAX
- how AJAX requests are serialized
- what happens after a successful AJAX submission
- how loading and busy states are shown to users

The section is structured like this:

```php
'forms' => [
    'mode' => 'http',
    'ajax' => [
        'attribute' => 'data-authkit-ajax',
        'submit_json' => true,
        'success_behavior' => 'redirect',
        'fallback_redirect' => null,
    ],
    'loading' => [
        'enabled' => true,
        'prevent_double_submit' => true,
        'disable_submit' => true,
        'set_aria_busy' => true,
        'type' => 'spinner_text',
        'text' => 'Processing...',
        'show_text' => true,
        'html' => null,
        'class' => 'authkit-btn--loading',
    ],
],
```
### Submission Mode

#### `forms.mode`

Controls the default transport mode for AuthKit forms.

**Supported values:**

- `http`
- `ajax`

- `http` means normal browser form submission
- `ajax` means submission is handled by the AuthKit JavaScript runtime

**Example:**

```php
'forms' => [
    'mode' => 'ajax',
],
```

### AJAX Settings

#### `forms.ajax.attribute`

Forms containing this attribute are treated as AuthKit AJAX forms.

**Default:**

```php
'attribute' => 'data-authkit-ajax',
```
> If you intend to change this, you must wire your fronted js and runtime. AuthKit currently uses 
> `data-authkit-ajax`

#### `forms.ajax.submit_json`

Controls whether AJAX forms are submitted as JSON.

- `true`: send JSON payloads by default
- `false`: use `FormData`

#### `forms.ajax.success_behavior`

Controls what happens after a successful AJAX submission.

**Supported values:**

- `redirect`
- `none`

If set to `redirect`, AuthKit will follow redirect intent from the response when available.

#### `forms.ajax.fallback_redirect`

Optional fallback URL used when success behavior is `redirect` but the server response does not provide a redirect target.

### Loading State Settings

#### `forms.loading.enabled`

Enables the loading state system.

#### `forms.loading.prevent_double_submit`

Prevents multiple submissions while a request is already in progress.

#### `forms.loading.disable_submit`

Disables the submit button while a submission is active.

#### `forms.loading.set_aria_busy`

Adds `aria-busy="true"` during submission for improved accessibility.

#### `forms.loading.type`

Controls the visual loading style.

**Supported values:**

- `text`
- `spinner`
- `spinner_text`
- `custom_html`

#### `forms.loading.text`

Default loading text shown while submitting.

#### `forms.loading.show_text`

Controls whether loading text should be displayed when the chosen type supports it.

#### `forms.loading.html`

Optional custom HTML used when type is `custom_html`.

**Example:**

```php
'html' => '<span class="my-loader" aria-hidden="true"></span>',
```
#### `forms.loading.class`

CSS class applied to the submit control while loading is active.

**Default:**

```php
'class' => 'authkit-btn--loading',
```

**Example: Enable AJAX Mode**

```php
'forms' => [
    'mode' => 'ajax',
    'ajax' => [
        'attribute' => 'data-authkit-ajax',
        'submit_json' => true,
        'success_behavior' => 'redirect',
        'fallback_redirect' => null,
    ],
],
```

**Example: Custom Loading Behavior**

```php
'forms' => [
    'loading' => [
        'enabled' => true,
        'type' => 'custom_html',
        'html' => '<span class="spinner" aria-hidden="true"></span>',
        'show_text' => false,
        'class' => 'is-loading',
    ],
],
```
### Best Practice

- use `http` mode first if you want the simplest and most robust baseline setup
- use `ajax` mode when you want enhanced interactivity and your frontend is ready to work with AuthKit’s runtime behavior
- keep loading feedback enabled in both cases so users receive clear submission state feedback  

## Email Verification Configuration

AuthKit provides a flexible, event-driven email verification system that supports both **link-based** and **token-based** verification flows.

This section controls:

- whether verification is required
- how verification is performed (link vs token)
- how messages are delivered
- how tokens are secured
- what happens after verification

### Key concepts

AuthKit does not tightly couple verification to delivery. Instead, it uses:

- **Events** → `\Xul\AuthKit\Events\AuthKitEmailVerificationRequired`
- **Listeners** → configurable (default provided)
- **Notifiers** → swappable delivery implementations

This makes verification fully customizable without modifying core actions.


### Driver

```php
'driver' => 'link', // or 'token'
```
### Delivery System (Important)

AuthKit emits:
```php
\Xul\AuthKit\Events\AuthKitEmailVerificationRequired
```
By default, it registers a listener:

```php
\Xul\AuthKit\Listeners\SendEmailVerificationNotification::class
```
This listener:

- resolves the configured notifier
- executes delivery (`sync` / `queue` / `after_response`)

### Notifier Contract

If you replace the notifier, it must implement:
```php
\Xul\AuthKit\Contracts\EmailVerificationNotifierContract
```
```php
public function send(
    Authenticatable $user,
    string $driver,
    string $email,
    string $token,
    ?string $url = null
): void;
```

### Customization Options

You can customize delivery at multiple levels:

#### 1. Replace Notifier (Recommended)

```php
'notifier' => App\Notifications\CustomEmailVerificationNotifier::class,
```
#### 2. Replace Listener

```php
'listener' => App\Listeners\CustomVerificationListener::class,
```
#### 3. Disable Listener Completely

```php
'use_listener' => false,
```
Then handle the event yourself:
```php
\Xul\AuthKit\Event\AuthKitEmailVerificationRequired
```
### Delivery Modes

```php
'mode' => 'sync' | 'queue' | 'after_response'
```
- `sync` → immediate send
- `queue` → dispatched as job
- `after_response` → deferred until response completes

### Token Security (Token Driver Only)

```php
'token' => [
    'max_attempts' => 5,
    'decay_minutes' => 1,
],
```
Prevents brute-force attacks on short verification codes.

### Post Verification Behavior

```php
'post_verify' => [
    'mode' => 'redirect' | 'success_page',
],
```
Options include:

- redirect to dashboard/login
- show success page
- optionally auto-login user

### What This Affects

- registration flow
- login blocking (for unverified users)
- verification UX (link vs code)
- notification delivery
- post-verification navigation

## Password Reset Configuration

AuthKit password reset is designed with:

- privacy protection
- event-driven delivery
- pluggable policies
- multiple drivers (`link` / `token`)

### Driver

```php
'driver' => 'link' // or 'token'
```
- `link` → reset via URL
- `token` → reset via code entry

### Delivery System

AuthKit emits:
```php
\Xul\AuthKit\Events\AuthKitPasswordResetRequested
```
Handled by default listener:

```php
\Xul\AuthKit\Listeners\SendPasswordResetNotification::class
```
### Notifier Contract (Required)

Custom notifiers must implement:

`Xul\AuthKit\Contracts\PasswordReset\PasswordResetNotifierContract`

```php
public function send(
    string $driver,
    string $email,
    string $token,
    ?string $url = null
): void;
```
### Important Design Detail

Unlike email verification:

- password reset does not require a user instance
- supports privacy-safe flows (user may be `null`)

### Customization Points

You can override:

- notifier
- listener
- delivery mode
- URL generator
- reset policy
- user resolver
- password updater

### Privacy Protection (Critical)

```php
'privacy' => [
    'hide_user_existence' => true,
],
```
When enabled:

- response is always identical
- prevents email enumeration attacks

### Token Protection

```php
'token' => [
    'max_attempts' => 5,
    'decay_minutes' => 1,
],
```
### User resolution

Password reset flows must locate the target user for a given identity value.

AuthKit supports two user resolution strategies:

```php
'user_resolver' => [
    'strategy' => 'provider',
    'resolver_class' => null,
],
```
#### `password_reset.user_resolver.strategy`

**Supported values:**

- `provider`
- `custom`

**provider**

Uses the configured guard’s user provider to resolve the user.

This is the recommended default for most applications.

**custom**

Uses your own resolver class instead of the configured provider.

This is useful when you need custom resolution behavior such as:

- multi-tenant user lookup
- alternate identity lookup rules
- non-standard authentication backends
- external user resolution logic

#### `password_reset.user_resolver.resolver_class`

When strategy is set to `custom`, this value must contain the fully qualified class name of your custom resolver.

That class must implement:

```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract
```

If `strategy` is `custom` and `resolver_class` is empty or invalid, AuthKit will fail when resolving the password reset user resolver.

**Example: Custom Password Reset User Resolver**

```php
'password_reset' => [
    'user_resolver' => [
        'strategy' => 'custom',
        'resolver_class' => App\Auth\PasswordReset\TenantPasswordResetUserResolver::class,
    ],
],
```

## Token Configuration

AuthKit uses a centralized token system for generating and validating **short-lived tokens** across multiple flows, including:

- email verification
- password reset
- pending login / step-up challenges
- any custom flows using the TokenRepository

This configuration defines the **shape and behavior of generated tokens**, not their storage or lifecycle.


### Overview

```php
'tokens' => [
    'default' => [...],
    'types' => [...],
],
```

- `default` → fallback token configuration
- `types` → per-use-case overrides

AuthKit resolves token options by:

- checking the requested token type
- falling back to `tokens.default` if not defined

### Default Token Configuration

```php
'default' => [
    'length' => 64,
    'alphabet' => 'alnum',
    'uppercase' => false,
],
```
### Key Options

#### `tokens.default.length`

Defines the number of characters in generated tokens.

**Example**: `64`

Applies when no type-specific override exists.

#### `tokens.default.alphabet`

Defines the character set used to generate tokens.

**Supported values:**

- `digits` → 0–9
- `alpha` → a–z
- `alnum` → a–z + 0–9
- `hex` → 0–9 + a–f

#### `tokens.default.uppercase`

```php
'uppercase' => false,
```
- Applies only to `alpha` and `alnum`.
- Converts output to uppercase for readability.

**Example:**
- `abc123` → `ABC123` (when enabled)

### Token Types (Overrides)

```php
'types' => [
    'email_verification' => [...],
    'password_reset' => [...],
    'pending_login' => [...],
],
```
These keys correspond directly to the type argument passed into:
`TokenRepositoryContract::create($type, ...)`

Each type can override:

- `length`
- `alphabet`

(implicitly inherits `uppercase` unless overridden globally)

#### `tokens.types.email_verification`

```php
'email_verification' => [
    'length' => 6,
    'alphabet' => 'digits',
],
```
Used for token-based email verification flows.

**Design intent:**

- short
- user-friendly
- easy manual entry

#### `tokens.types.password_reset`

```php
'password_reset' => [
    'length' => 6,
    'alphabet' => 'digits',
],
```
Used for token-based password reset flows.

**Design intent:**

- short numeric codes
- consistent with OTP UX expectations

#### `tokens.types.pending_login`

```php
'pending_login' => [
    'length' => 64,
    'alphabet' => 'alnum',
],
```
Used for internal login challenges (e.g., step-up authentication).

**Design intent:**

- long
- non-human-facing
- high entropy

### How AuthKit Uses This

When a token is created:

- AuthKit determines the token type
- looks up `tokens.types.{type}`
- falls back to `tokens.default` if not found
- generates token using configured:
    - length
    - alphabet
    - uppercase rules

### Security Considerations

#### Short tokens (e.g. 6 digits)

- easier for users
- must be protected with:
    - rate limiting
    - attempt throttling

AuthKit already enforces this via:

- `email_verification.token`
- `password_reset.token`
- `rate_limiting`

#### Long tokens (e.g. 64 alnum)

- higher entropy
- suitable for:
    - URLs
    - non-interactive flows
    - internal references

#### Alphabet choice

- `digits` → best UX, lowest entropy
- `alnum` → balanced
- `hex` → URL-safe and compact
- `alpha` → rarely used alone

### Best Practices

- use short numeric tokens for manual entry flows
- use long alphanumeric tokens for links and internal flows
- always combine short tokens with rate limiting
- avoid reducing token length below recommended defaults in production

**Example: Custom Token Configuration**

```php
'tokens' => [
    'default' => [
        'length' => 40,
        'alphabet' => 'hex',
    ],

    'types' => [
        'email_verification' => [
            'length' => 8,
            'alphabet' => 'digits',
        ],
    ],
],
```

### What This Affects

- token generation across all AuthKit flows
- UX of verification and reset processes
- security posture (entropy vs usability)
- compatibility with custom flows using `TokenRepository`

This configuration does **not** control:

- token storage
- expiration (TTL)
- validation rules

Those are handled by their respective feature configurations (email verification, password reset, etc.).   

## Two-Factor Authentication Configuration

AuthKit provides a **config-driven and driver-based two-factor authentication system**.

Out of the box, AuthKit ships with a TOTP driver, but the system is intentionally extensible so consumers can replace it or introduce their own drivers.

This section controls:

- whether two-factor authentication is enabled
- which driver is active
- how login challenges behave
- how secrets and recovery codes are stored
- how recovery codes are exposed temporarily to the user
- how your user model integrates with AuthKit’s two-factor layer


### Overview

```php
'two_factor' => [
    'enabled' => true,
    'driver' => 'totp',
    'methods' => ['totp'],
    'ttl_minutes' => 10,
    'challenge_strategy' => 'peek',
    'totp' => [
        'digits' => 6,
        'period' => 30,
        'window' => 1,
        'algo' => 'sha1',
    ],
    'table' => 'users',
    'columns' => [
        'enabled' => 'two_factor_enabled',
        'secret' => 'two_factor_secret',
        'recovery_codes' => 'two_factor_recovery_codes',
        'methods' => 'two_factor_methods',
        'confirmed_at' => 'two_factor_confirmed_at',
    ],
    'drivers' => [
        'totp' => \Xul\AuthKit\Support\TwoFactor\Drivers\TotpTwoFactorDriver::class,
    ],
    'security' => [
        'encrypt_secret' => true,
        'hash_recovery_codes' => true,
        'recovery_hash_driver' => 'bcrypt',
    ],
    'recovery_codes' => [
        'flash_key' => 'authkit.two_factor.recovery_codes',
        'response_key' => 'recovery_codes',
        'hide_when_empty' => true,
    ],
],
```
### Important Model Requirement

Before anything else, your authenticatable user model should use the AuthKit two-factor trait:

```php
use Xul\AuthKit\Concerns\Model\HasAuthKitTwoFactor;
```
**Example**

```php
class User extends Authenticatable
{
    use HasAuthKitTwoFactor;
}
```
This trait provides the model-side interface AuthKit expects for two-factor operations, including:

- checking whether two-factor is enabled
- enabling and disabling two-factor
- setting and retrieving the secret
- setting and retrieving recovery codes
- consuming recovery codes
- reading and setting enabled methods
- resolving configured column names
- hashing recovery codes when configured
- encrypting secrets when configured

### Why This Matters

AuthKit’s built-in driver and two-factor flows are designed to work with this trait.

The trait gives your model a consistent, config-aware API such as:

- `hasTwoFactorEnabled()`
- `enableTwoFactor()`
- `disableTwoFactor()`
- `twoFactorSecret()`
- `setTwoFactorSecret()`
- `twoFactorRecoveryCodes()`
- `setTwoFactorRecoveryCodes()`
- `consumeTwoFactorRecoveryCode()`
- `twoFactorMethods()`
- `setTwoFactorMethods()`

It also respects the configured column mappings under:

- `authkit.two_factor.columns.enabled`
- `authkit.two_factor.columns.secret`
- `authkit.two_factor.columns.recovery_codes`
- `authkit.two_factor.columns.methods`

### Recommended Setup

To use AuthKit’s two-factor system correctly, you should:

- add `HasAuthKitTwoFactor` to your user model
- publish and run the AuthKit two-factor migration, or implement an equivalent schema
- ensure your configured columns actually exist in the database

### Core Options

#### `two_factor.enabled`

```php
'enabled' => true,
```
Globally enables or disables AuthKit two-factor features.

When disabled:

- two-factor setup flows should not be available
- login-time two-factor challenge flows should not run
- verification through the active driver should be bypassed

This is the top-level feature switch for the entire two-factor module.

#### `two_factor.driver`

```php
'driver' => 'totp',
```

Defines the active driver used for two-factor verification.

This value must match a key in:

- `two_factor.drivers`

By default, AuthKit uses the built-in `totp` driver.
#### `two_factor.methods`

```php
'methods' => ['totp'],
```
Defines the allowed two-factor methods.

At the moment, the default system is centered on TOTP, so this is:

`['totp']`

This option is still useful because it keeps the configuration future-friendly and allows drivers or flows to reason about enabled methods.

#### `two_factor.ttl_minutes`
```php
'ttl_minutes' => 10,
```
Defines how long a pending login two-factor challenge remains valid.

Typical flow:

- user submits valid login credentials
- AuthKit determines that two-factor is required
- a pending login challenge is created
- the user must complete two-factor verification before this TTL expires

If the challenge expires, the user may need to restart the login flow.

#### `two_factor.challenge_strategy`

```php
'challenge_strategy' => 'peek',
```
Controls how pending login challenges are handled during verification.

**Supported values:**

- `peek`
- `consume`

##### `peek`

Best UX.

The challenge is checked without being consumed immediately, and is only invalidated after successful verification.

This allows a user to retry if they enter an incorrect code.

##### `consume`

Stricter behavior.

The challenge is consumed immediately, so an invalid attempt forces the user to restart the login process.

Use this only if your application requires a stricter challenge lifecycle.

### TOTP Configuration

```php
'totp' => [
    'digits' => 6,
    'period' => 30,
    'window' => 1,
    'algo' => 'sha1',
],
```
These settings apply to the built-in TOTP driver.

#### `two_factor.totp.digits`

Defines the number of digits expected in TOTP codes.

**Default:**

```php
6
```
#### `two_factor.totp.period`

Defines the TOTP time step in seconds.

**Default:**

```php
30
```

This means each TOTP code is valid for a 30-second interval.

#### `two_factor.totp.window`

Defines the verification drift window.

**Default:**

```php
1
```
This allows limited clock skew between the server and the user’s authenticator device.

For example, a window of `1` typically allows the previous, current, and next time step.

#### `two_factor.totp.algo`

Defines the hashing algorithm used for TOTP verification.

**Default:**

```php
'sha1'
```
### Database and Column Configuration

#### `two_factor.table`

```php
'table' => 'users',
```
Defines the table used by the publishable two-factor migration.

If your two-factor data lives on a different table structure, you should ensure your schema and model integration are aligned accordingly.

#### `two_factor.columns`

```php
'columns' => [
    'enabled' => 'two_factor_enabled',
    'secret' => 'two_factor_secret',
    'recovery_codes' => 'two_factor_recovery_codes',
    'methods' => 'two_factor_methods',
    'confirmed_at' => 'two_factor_confirmed_at',
],
```
These keys tell AuthKit where to read and write two-factor data on the user model.

They are especially important because the `HasAuthKitTwoFactor` trait resolves column names through this configuration.

- **enabled**  
  Boolean flag indicating whether two-factor is active.

- **secret**  
  Stored shared secret used by secret-based drivers such as TOTP.

- **recovery_codes**  
  Stored recovery codes, typically as JSON array values.

- **methods**  
  Stored enabled methods for the user.

- **confirmed_at**  
  Timestamp indicating when two-factor setup was confirmed.

### Important Note

> Changing these values does not modify your database automatically.
> 
> If you rename these columns in config, your schema must already match unless the change
> was before running `php artisan migrate` after publishing the migration file.

### Driver System

#### `two_factor.drivers`

```php
'drivers' => [
    'totp' => \Xul\AuthKit\Support\TwoFactor\Drivers\TotpTwoFactorDriver::class,
],
```
This is the driver map used by AuthKit to resolve the active two-factor driver.

- the keys are driver names
- the values are fully qualified class names

### Custom Driver Requirements

If you replace the built-in driver, your driver class must implement:
```php
Xul\AuthKit\Contracts\TwoFactorDriverContract
```
This contract defines the core responsibilities of a two-factor driver, including:

- identifying itself via `key()`
- exposing supported methods via `methods()`
- determining whether 2FA is enabled via `enabled()`
- verifying a submitted code via `verify()`
- verifying a recovery code via `verifyRecoveryCode()`
- consuming a recovery code via `consumeRecoveryCode()`
- generating recovery codes via `generateRecoveryCodes()`

### Secret-Based Drivers

If your driver requires a generated secret, it must also implement:
```php
Xul\AuthKit\Contracts\TwoFactorSecretProviderContract
```
This contract defines:

```php
public function generateSecret(): string;
```
This is required for drivers such as TOTP that depend on a shared secret.

Drivers that do not use secrets should not implement it.

### Built-in Driver Example

AuthKit’s default driver is:

```php
\Xul\AuthKit\Support\TwoFactor\Drivers\TotpTwoFactorDriver
```
It implements both:

- `TwoFactorDriverContract`
- `TwoFactorSecretProviderContract`

This is because TOTP requires:

- secret generation
- code verification
- recovery code generation and verification

### Model Integration Note

The built-in driver is designed to work especially well with the `HasAuthKitTwoFactor` trait.

For example, it prefers model methods such as:

- `hasTwoFactorEnabled()`
- `twoFactorSecret()`
- `twoFactorRecoveryCodes()`
- `consumeTwoFactorRecoveryCode()`

If those methods are present, the driver uses them.

That is another reason why adding `HasAuthKitTwoFactor` to your user model is the recommended integration path.

### Security Configuration

```php
'security' => [
    'encrypt_secret' => true,
    'hash_recovery_codes' => true,
    'recovery_hash_driver' => 'bcrypt',
],
```
This section controls how sensitive two-factor data is stored.

#### `two_factor.security.encrypt_secret`

```php
'encrypt_secret' => true,
```
When enabled, the stored two-factor secret is encrypted at rest.

The `HasAuthKitTwoFactor` trait handles this automatically in:

- `setTwoFactorSecret()`
- `twoFactorSecret()`

When enabled:

- secrets are encrypted before storage
- secrets are decrypted when read back

This should generally remain enabled in production.

#### `two_factor.security.hash_recovery_codes`

```php
'hash_recovery_codes' => true,
```

When enabled, recovery codes are stored as hashes rather than plaintext.

The `HasAuthKitTwoFactor` trait handles this automatically in:

- `setTwoFactorRecoveryCodes()`
- `consumeTwoFactorRecoveryCode()`

### Important Behavior

- you pass raw recovery codes into `setTwoFactorRecoveryCodes()`
- the trait hashes them before storage
- plaintext recovery codes are not persisted

This is the recommended production setting.

#### `two_factor.security.recovery_hash_driver`

```php
'recovery_hash_driver' => 'bcrypt',
```
Defines the hashing driver used for recovery codes.

**Supported values:**

- `bcrypt`
- `argon2i`
- `argon2id`

The `HasAuthKitTwoFactor` trait validates this value and throws if an unsupported driver is configured.

### Recovery Code Display and Transport

```php
'recovery_codes' => [
    'flash_key' => 'authkit.two_factor.recovery_codes',
    'response_key' => 'recovery_codes',
    'hide_when_empty' => true,
],
```
This section does not control how recovery codes are stored.

Instead, it controls how freshly generated plaintext recovery codes are temporarily exposed to the user immediately after:

- confirming two-factor setup
- regenerating recovery codes

### Important Distinction

Persistent storage is controlled by:

- `two_factor.security.hash_recovery_codes`
- `two_factor.security.recovery_hash_driver`

Presentation is controlled here.

#### `two_factor.recovery_codes.flash_key`

```php
'flash_key' => 'authkit.two_factor.recovery_codes',
```
Used for redirect-based web flows.

**Expected behavior:**

- action flashes plaintext recovery codes into session under this key
- the next rendered page reads the same key
- codes are shown once to the user

#### `two_factor.recovery_codes.response_key`

```php
'response_key' => 'recovery_codes',
```
Used for AJAX / JSON flows.

**Expected behavior:**

- action returns plaintext recovery codes in the public payload using this key
- page JavaScript reads the configured key
- the UI renders the recovery code block dynamically

#### `two_factor.recovery_codes.hide_when_empty`

```php
'hide_when_empty' => true,
```
Controls whether the recovery-code presentation section should remain hidden when no newly generated recovery codes are available.

This helps keep the UI clean.

**Example: Replacing the Driver**

```php
'two_factor' => [
    'driver' => 'sms',
    'methods' => ['sms'],
    'drivers' => [
        'sms' => \App\Auth\TwoFactor\SmsTwoFactorDriver::class,
    ],
],
```
Your custom driver must implement:

```php
Xul\AuthKit\Contracts\TwoFactorDriverContract
```
And if it requires a secret, it must also implement:
```php
Xul\AuthKit\Contracts\TwoFactorSecretProviderContract
```
You should also ensure your user model still exposes the two-factor state and storage methods AuthKit needs, typically by using:
```php
Xul\AuthKit\Concerns\Model\HasAuthKitTwoFactor
```
### What This Affects

This configuration affects:

- whether two-factor is enforced
- how login challenges are created and consumed
- how codes are verified
- how secrets are stored
- how recovery codes are stored and displayed
- how your user model integrates with the two-factor system
- how custom drivers can be introduced safely

### Best Practices

- add `HasAuthKitTwoFactor` to your user model
- keep `encrypt_secret` enabled
- keep `hash_recovery_codes` enabled
- use `peek` unless you explicitly need strict challenge consumption
- only implement `TwoFactorSecretProviderContract` for secret-based drivers
- ensure your configured columns exist in the database
- register every custom driver in `two_factor.drivers`  

## Rate Limiting Configuration

AuthKit ships with built-in rate limiting for sensitive authentication and account-security endpoints.

This configuration exists to give you:

- secure defaults
- consistent limiter naming across package routes
- flexibility to remap, tune, or replace limiter behavior
- support for more advanced strategies such as multi-tenant or custom identity-based throttling

AuthKit’s rate limiting system is centered around named Laravel rate limiters that are registered during package boot and then attached to AuthKit routes through configuration.


### Overview

```php
'rate_limiting' => [
    'map' => [...],
    'strategy' => [...],
    'limits' => [...],
    'resolvers' => [...],
],
```
### What This Section Controls

- which limiter name each AuthKit endpoint should use
- how each limiter should be built
- how many attempts are allowed for each bucket
- how throttle keys are resolved

### How AuthKit Uses Rate Limiting

AuthKit does not hard-code throttle names directly into route behavior.

Instead, the system works like this:

1. AuthKit defines logical limiter keys such as:
    - `login`
    - `two_factor_challenge`
    - `password_forgot`
    - `confirm_password`

2. Those logical keys are mapped to actual Laravel rate limiter names through:
```php
rate_limiting.map
```
3. AuthKit builds the limiter behavior using:

- `rate_limiting.strategy`
- `rate_limiting.limits`
- `rate_limiting.resolvers`

This makes the system easier to customize without changing package internals.

### Limiter Mapping

#### `rate_limiting.map`

This section maps AuthKit limiter keys to named Laravel rate limiters.

**Example:**

```php
'map' => [
    'login' => 'authkit.auth.login',
    'two_factor_challenge' => 'authkit.two_factor.challenge',
    'password_forgot' => 'authkit.password.forgot',
],
```
### Purpose

This allows consumers to:

- keep AuthKit’s default limiter names
- point AuthKit routes to custom limiter names
- disable route-level throttle attachment by setting a mapping to `null`

**Example: Custom Limiter Name**

```php
'map' => [
    'login' => 'myapp.auth.login',
],
```
**Example: disabling a mapped limiter**
```php
'map' => [
    'login' => null,
],
```
In that case, AuthKit should not attach a throttle middleware for that logical limiter key.

### Included Limiter Keys

The configuration includes mappings for:

- `login`
- `two_factor_challenge`
- `two_factor_resend`
- `two_factor_recovery`
- `password_forgot`
- `password_verify_token`
- `password_reset`
- `email_send_verification`
- `email_verify_token`
- `confirm_password`
- `confirm_two_factor`
- `password_update`
- `two_factor_confirm`
- `two_factor_disable`
- `two_factor_recovery_regenerate`

These cover both guest-facing authentication flows and authenticated security flows.

### Protection Strategy

#### `rate_limiting.strategy`

This section defines how each limiter should be built.

**Supported values:**

- `dual`
- `per_ip`
- `per_identity`
- `custom`

**Example:**

```php
'strategy' => [
    'login' => 'dual',
    'password_forgot' => 'dual',
],
```
### Strategy Meanings

#### `dual`

Applies both:

- a per-IP bucket
- a per-identity bucket

This is the default and recommended strategy for most sensitive endpoints.

It protects against:

- repeated attempts from one source IP
- repeated attacks against a single account identifier

#### `per_ip`

Applies only an IP-based bucket.

Use this when identity-based throttling is not relevant or not available.

#### `per_identity`

Applies only an identity-based bucket.

Use this when protecting a specific identity is more important than IP-level control.

#### `custom`

Delegates limiter construction to a custom limiter resolver.

This is for advanced cases where you want to fully replace AuthKit’s limiter-building logic.

### Limits and Decay Windows

#### `rate_limiting.limits`

This section defines the actual throttle windows for each logical limiter.

Each limiter can have:

- `per_ip`
- `per_identity`

Each bucket uses this shape:

```php
[
    'attempts' => 10,
    'decay_minutes' => 1,
]
```
**Example: Login**

```php
'login' => [
    'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
],
```
This means:

- a single IP may attempt login 10 times per minute
- a single identity may be targeted 5 times per minute

### Why Dual Limits Matter

For example, with login:

- the per-IP bucket helps against one attacker hammering from a single source
- the per-identity bucket helps protect a specific account from repeated attack attempts even if IPs vary

### Built-in Limit Defaults

AuthKit ships sensible defaults for several categories of risk.

### `Login`

**Threat model:**

- brute-force password guessing
- credential stuffing

**Defaults:**

```php
'login' => [
    'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
],
```
### `Two-Factor Challenge`

**Threat model:**

- brute-forcing 2FA codes

**Defaults:**

```php
'two_factor_challenge' => [
    'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
],
```
AuthKit may also apply a per-challenge bucket internally where supported.

### `Two-Factor Resend`

**Threat model:**

- abuse of notification delivery
- message spam

**Defaults are stricter:**

```php
'two_factor_resend' => [
    'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 2, 'decay_minutes' => 1],
],
```
### `Two-Factor Recovery`

**Threat model:**

- brute-forcing recovery codes

**Defaults:**

```php
'two_factor_recovery' => [
    'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
],
```
### `Password Reset Request`

**Threat model:**

- email/account enumeration probing
- delivery abuse

**Defaults:**

```php
'password_forgot' => [
    'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 3, 'decay_minutes' => 1],
],
```
This works especially well alongside:

`password_reset.privacy.hide_user_existence`

### `Password Reset Token Verification`

**Threat model:**

- brute-forcing short reset codes

**Defaults:**

```php
'password_verify_token' => [
    'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
],
```
These align with the password reset token security settings.

### `Password Reset Submission`

**Threat model:**

- repeated attempts against a reset flow
- resource abuse

**Defaults:**

```php
'password_reset' => [
    'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
],
```

### `Email Verification Resend`

**Threat model:**

- notification spam
- resend abuse

**Defaults:**

```php
'email_send_verification' => [
    'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 2, 'decay_minutes' => 1],
],
```

### `Email Verification Token Verification`

**Threat model:**

- brute-forcing short verification codes

**Defaults:**

```php
'email_verify_token' => [
    'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
],
```

### `Password Confirmation`

**Threat model:**

- repeated guessing of the current password during step-up confirmation

**Defaults:**

```php
'confirm_password' => [
    'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
],
```
These protect already-authenticated but security-sensitive flows.

### `Two-Factor Confirmation`

**Threat model:**

- brute-forcing TOTP codes during step-up confirmation

**Defaults:**

```php
'confirm_two_factor' => [
    'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
],
```

This is distinct from the login-time two-factor challenge flow.

### `Password Update`

**Threat model:**

- repeated abuse of password change endpoint
- brute-forcing current-password confirmation during password update

**Defaults:**

```php
'password_update' => [
    'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
],
```

### `Two-Factor Setup Confirmation`

**Threat model:**

- brute-forcing setup confirmation codes

**Defaults:**

```php
'two_factor_confirm' => [
    'per_ip' => ['attempts' => 10, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 5, 'decay_minutes' => 1],
],
```

### `Two-Factor Disable`

**Threat model:**

- repeated disable attempts
- attempts to weaken account security

**Defaults:**

```php
'two_factor_disable' => [
    'per_ip' => ['attempts' => 6, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 3, 'decay_minutes' => 1],
],
```

### `Recovery Code Regeneration`

**Threat model:**

- repeated regeneration abuse
- excessive rotation of recovery credentials

**Defaults are intentionally strict:**

```php
'two_factor_recovery_regenerate' => [
    'per_ip' => ['attempts' => 4, 'decay_minutes' => 1],
    'per_identity' => ['attempts' => 2, 'decay_minutes' => 1],
],
```

### Resolver Overrides

The `rate_limiting.resolvers` section allows you to replace AuthKit’s default logic for resolving throttle keys and building custom limiters.

```php
'rate_limiting' => [
    'resolvers' => [
        'identity' => null,
        'ip' => null,
        'challenge' => null,
        'limiter' => null,
    ],
],
```

By default, all of these are null, which means AuthKit uses its internal resolver behavior.

When you provide your own resolver classes, they must implement the correct AuthKit rate-limiting contracts.


### Important note

> These values are intended for advanced customization. In most applications, leaving them as `null` is the right choice.

### `rate_limiting.resolvers.identity`

Used to resolve the normalized identity for per-identity throttling.

**Expected use cases:**

- email-based throttling
- username-based throttling
- phone-based throttling
- tenant-aware identity keys

Your custom resolver must implement:
```php
Xul\AuthKit\RateLimiting\Contracts\IdentityResolverContract
```
**Contract:**

```php
public function resolve(Request $request): ?string;
```
**Behavior:**

- should return the normalized identity string
- should return `null` if identity is unavailable
- returning `null` allows the identity bucket to be skipped safely

**Example config**

```php
'rate_limiting' => [
    'resolvers' => [
        'identity' => App\Auth\RateLimiting\TenantAwareIdentityResolver::class,
    ],
],
```

### `rate_limiting.resolvers.ip`

Used to resolve the client IP identifier for per-IP throttling.

**This is useful when:**

- your app is behind a reverse proxy
- you need custom proxy-aware IP extraction
- you want to normalize IP resolution differently

Your custom resolver must implement:
```php
Xul\AuthKit\RateLimiting\Contracts\IpResolverContract
```
**Contract:**

```php
public function resolve(Request $request): string;
```
**Behavior:**

- must return a non-empty string
- this string becomes the throttle key for IP-based buckets

**Example config**

```php
'rate_limiting' => [
    'resolvers' => [
        'ip' => App\Auth\RateLimiting\TrustedProxyIpResolver::class,
    ],
],
```
**Contract:**

```php
public function resolve(Request $request): string;
```
### `rate_limiting.resolvers.challenge`

Used to resolve the challenge reference for per-challenge throttling.

**This is mainly relevant to flows such as:**

- two-factor challenge verification
- challenge-bound security flows

Your custom resolver must implement:
```php
Xul\AuthKit\RateLimiting\Contracts\ChallengeResolverContract
```
**Contract:**

```php
public function resolve(Request $request): ?string;
```
**Behavior:**

- should return a stable challenge reference string
- should return `null` when no challenge is available
- returning `null` allows the challenge bucket to be skipped safely

**Example config**

```php
'rate_limiting' => [
    'resolvers' => [
        'challenge' => App\Auth\RateLimiting\PendingLoginChallengeResolver::class,
    ],
],
```
### `rate_limiting.resolvers.limiter`

Used only when a limiter strategy is set to:

```php
'custom'
```
This allows you to completely replace AuthKit’s default limiter-building logic for one or more limiter keys.

Your custom resolver must implement:
```php
Xul\AuthKit\RateLimiting\Contracts\CustomLimiterResolverContract
```
**Contract:**

```php
public function resolve(string $limiterKey, Request $request): Limit|array;
```
**Behavior:**

- receives the logical limiter key such as `login` or `two_factor_challenge`
- must return either:
    - a single `Limit`
    - or an array of `Limit` objects

**Example config**

```php
'rate_limiting' => [
    'strategy' => [
        'login' => 'custom',
    ],
    'resolvers' => [
        'limiter' => App\Auth\RateLimiting\CustomLimiterBuilder::class,
    ],
],
```
**Full Example: Resolver Overrides in Config**

```php
'rate_limiting' => [
    'map' => [
        'login' => 'authkit.auth.login',
        'two_factor_challenge' => 'authkit.two_factor.challenge',
    ],

    'strategy' => [
        'login' => 'custom',
        'two_factor_challenge' => 'dual',
    ],

    'resolvers' => [
        'identity' => App\Auth\RateLimiting\TenantAwareIdentityResolver::class,
        'ip' => App\Auth\RateLimiting\TrustedProxyIpResolver::class,
        'challenge' => App\Auth\RateLimiting\PendingLoginChallengeResolver::class,
        'limiter' => App\Auth\RateLimiting\CustomLimiterBuilder::class,
    ],
],
```
### Practical Guidance

Use resolver overrides only when you actually need them.

**Common reasons include:**

- multi-tenant throttle keys
- custom identity schemes
- proxy-aware networking
- challenge-aware throttling
- fully custom limiter definitions

For most applications, leaving these values as `null` is the correct choice.

## Authenticated Application Area Configuration

The `app` configuration section controls AuthKit’s **logged-in application experience**.

This is the part of AuthKit that renders authenticated pages such as:

- dashboard
- settings
- security
- sessions
- two-factor management
- confirmation pages for sensitive actions

Unlike guest authentication pages such as login, register, forgot password, and reset password, the `app` section is focused on what happens **after the user is authenticated**.

This section allows you to configure:

- whether the authenticated application area is enabled
- branding shown in the app shell
- sidebar shell behavior
- available layout variants
- page definitions
- navigation structure
- per-page middleware protection

### Overview

```php
'app' => [
    'enabled' => true,
    'brand' => [...],
    'shell' => [...],
    'layouts' => [...],
    'pages' => [...],
    'navigation' => [...],
    'middleware' => [...],
],
```
This configuration gives you control over the overall authenticated AuthKit experience without forcing you to rewrite package internals.

### Important Concept

The `app` section is not a replacement for the general UI system.

It works together with the rest of AuthKit, especially:

- `ui`
- `themes`
- `assets`
- `components`
- `javascript`

So the authenticated area still uses the same engine, theme, mode, scripts, and component system already defined elsewhere in the configuration.

### `app.enabled`

```php
'enabled' => true,
```
This determines whether AuthKit should register and render its built-in authenticated application area.


### Behavior

**When set to `true`:**

- AuthKit loads authenticated app web routes
- AuthKit loads authenticated app API routes
- dashboard and account/security pages become available

**When set to `false`:**

- guest flows such as login, register, email verification, and password reset can still exist
- AuthKit does not provide the built-in post-login app area
- your application becomes responsible for its own dashboard and authenticated account pages

This is useful when you want AuthKit to provide only the authentication flows but not the logged-in UI shell.


### Brand Configuration

`app.brand`

This section controls the branding block rendered in the authenticated sidebar.

```php
'brand' => [
    'title' => env('APP_NAME', 'AuthKit'),
    'subtitle' => 'Application Console',
    'type' => 'letter',
    'letter' => 'AK',
    'image' => '',
    'image_alt' => env('APP_NAME', 'AuthKit'),
    'show_subtitle' => true,
],
```
### `app.brand.title`

Main title displayed in the app branding area.

**Example:**

```php
'title' => env('APP_NAME', 'AuthKit'),
```

### `app.brand.subtitle`

Secondary branding text shown below the main title.

**Example:**

```php
'subtitle' => 'Application Console',
```

This is optional supporting text that can help describe the authenticated area.


### `app.brand.type`

Determines whether the sidebar brand uses a text mark or an image.

**Supported values:**

- `letter`
- `image`

**`letter`**

- Displays a short text brand mark

**`image`**

- Displays an image logo instead

If `type` is set to `image` but no valid image path is provided, AuthKit falls back to the letter mode.

### `app.brand.letter`

Short text mark used when `type` is `letter`.

**Example:**

```php
'letter' => 'AK',
```

This is usually an abbreviation or initials.


### `app.brand.image`

Public asset path used when `type` is `image`.

**Example:**

```php
'image' => 'images/brand/authkit-logo.png',
```

This path should be resolvable through Laravel’s `asset()` helper.


### `app.brand.image_alt`

Alt text for the logo image.

**Example:**

```php
'image_alt' => env('APP_NAME', 'AuthKit'),
```
This improves accessibility and provides fallback meaning when the image cannot be displayed.


### `app.brand.show_subtitle`

Controls whether the subtitle should be shown.

**Example:**

```php
'show_subtitle' => true,
```
### Shell Configuration

`app.shell.sidebar`

This section controls the behavior of the authenticated sidebar shell.

```php
'shell' => [
    'sidebar' => [
        'allow_collapse' => true,
        'collapsed' => false,
        'mobile_drawer' => true,
        'storage_key' => 'authkit.app.sidebar.collapsed',
        'mobile_breakpoint' => 1024,
    ],
],
```
These settings influence the authenticated app shell experience and are also exposed to the browser runtime in the app layout.

### `allow_collapse`

```php
'allow_collapse' => true,
```
Allows the desktop sidebar to be collapsed.

When enabled, users can toggle the sidebar between expanded and collapsed states.

### `collapsed`

```php
'collapsed' => false,
```
Default collapsed state for the sidebar.

If `true`, the sidebar starts collapsed unless client-side persistence restores a different state.

### `mobile_drawer`

```php
'mobile_drawer' => true,
```
Enables mobile drawer behavior for smaller viewports.

This makes the sidebar behave more like an overlay drawer on mobile devices.

### `storage_key`

```php
'storage_key' => 'authkit.app.sidebar.collapsed',
```
Storage key used by the client-side app shell behavior to persist sidebar collapse state.

This lets the sidebar remember whether the user last left it expanded or collapsed.

### `mobile_breakpoint`

```php
'mobile_breakpoint' => 1024,
```
Defines the viewport width at which the shell should treat the layout as mobile.

This is used by the authenticated layout and browser configuration for shell behavior.


### Layout Variants

`app.layouts`

This section defines the available layout variants that authenticated pages may use.

```php
'layouts' => [
    'default' => 'authkit::app.layout',
],
```
Each value should be a Blade view or component reference.


### Purpose

This allows you to keep a named set of authenticated layout options and assign them per page.

For example, in the future you may introduce:

- a compact layout
- a minimal layout
- an admin layout

Even if you currently use only one authenticated shell, the configuration is already structured to support layout variation cleanly.

### Page Definitions

`app.pages`

This is one of the most important parts of the authenticated application configuration.

Each page definition controls:

- whether the page is enabled
- browser title
- visible heading
- route name
- layout variant
- page body view
- navigation label
- sidebar visibility

**Example page**

```php
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
```
### Common Page Keys

Most page definitions share these keys.

#### `enabled`

Controls whether the page is available at all.

If disabled, the page should not be treated as an available built-in destination.

#### `title`

Browser or page title.

This is typically used for the document title or page-level metadata.

#### `heading`

Primary heading shown inside the page header.

This is the user-facing page heading for the authenticated area.

#### `route`

Named route used to reach the page.

This should align with the corresponding route name in AuthKit.

#### `layout`

Layout variant key from `app.layouts`.

This lets you choose which authenticated shell layout to use for that page.


#### `view`

Blade view responsible for rendering the page body.

This allows consumers to point a page to a published or customized view without changing AuthKit internals.


#### `nav_label`

Text label used when the page appears in navigation.


#### `show_in_sidebar`

Controls whether the page should appear as a navigation destination in the sidebar.

Not every authenticated page should appear in the sidebar.

**For example:**

- dashboard should usually appear
- confirmation pages should usually not appear


### Built-in Pages

#### `dashboard_web`

Authenticated landing or overview page.

**Purpose:**

- post-login destination
- account overview
- app home page

#### `settings`

General account settings page.

**Purpose:**

- top-level settings destination
- account preferences and related actions

#### `security`

Security management page.

**Purpose:**

- password update
- two-factor management
- security summaries

This page also includes built-in section toggles.

### `security.sections`

```php
'sections' => [
    'password_update' => true,
    'two_factor' => true,
    'sessions_summary' => true,
],
```

These allow you to keep the security page enabled while selectively hiding packaged sections.

This is useful if:

- you want to hide password updates
- you want to replace the two-factor area with custom content
- you want to remove session summary from the built-in page

### `sessions`

Authenticated sessions page.

**Purpose:**

- show active sessions
- manage session awareness
- provide account session visibility

### `two_factor_settings`

Dedicated two-factor management page.

**Purpose:**

- setup two-factor
- confirm setup
- disable two-factor
- manage recovery codes

**By default:**

```php
'show_in_sidebar' => false,
```

This means it exists as an authenticated page but is not automatically promoted as a primary sidebar destination.


### `confirm_password`

Step-up confirmation page for password confirmation.

**Purpose:**

- shown when an authenticated user needs to re-confirm their password before accessing a sensitive destination

This is a utility page, not a normal navigation page.


### `confirm_two_factor`

Step-up confirmation page for two-factor confirmation.

**Purpose:**

- shown when an authenticated user must re-confirm two-factor before proceeding to a sensitive action or page

Like password confirmation, this is a utility page and is hidden from sidebar navigation by default.


### Navigation Configuration

`app.navigation.sidebar`

This section defines the default sidebar structure for the authenticated shell.

**Example**

```php
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
            ],
        ],
    ],
],
```
### Navigation Item Structure

Each sidebar item may define:

- `page`
- `route`
- `icon`
- `children`

#### `page`

References a key from `app.pages`.

This keeps the navigation system aligned with page configuration.

#### `route`

Named route or placeholder route for the item.

In nested menu structures, a parent can use `'#'` when it primarily exists as a grouping node rather than a direct destination.

#### `icon`

Semantic icon key used by the navigation renderer.

**Examples in the default config include:**

- `home`
- `settings`
- `shield`
- `devices`
- `key`

#### `children`

Allows nested navigation groups.

This is used in the default config for the settings navigation group.

### Default Sidebar Structure

The packaged sidebar includes:

- Dashboard
- Settings
    - Security
- Settings
    - Sessions
    - Two-factor

This default structure is intentionally configurable so consumers can:

- reorder items
- remove destinations
- hide certain pages
- keep the navigation aligned with their own product language

### Middleware Configuration

`app.middleware`

This section controls which middleware protect AuthKit’s built-in authenticated pages.

It is divided into:

- `base`
- `pages`

```php
'middleware' => [
    'base' => [
        \Xul\AuthKit\Http\Middleware\Authenticate::class,
    ],
    'pages' => [
        'dashboard_web' => [...],
        'settings' => [...],
    ],
],
```
### Base Middleware

`app.middleware.base`

This is the baseline middleware stack for authenticated AuthKit pages.

**Example:**

```php
'base' => [
    \Xul\AuthKit\Http\Middleware\Authenticate::class,
],
```

This is the default protection layer for the authenticated application area.

In practice, this ensures users must be authenticated before accessing the logged-in shell.

### Why class names are used

Middleware is configured using class names rather than aliases to keep package behavior explicit.

This helps because:

- configuration remains self-descriptive
- consumers do not need to depend on alias registration elsewhere
- project-specific middleware can be added more clearly

**Example: extending base middleware**

```php
'base' => [
    \Xul\AuthKit\Http\Middleware\Authenticate::class,
    \App\Http\Middleware\EnsureTenantIsResolved::class,
],
```

This is useful for:

- tenant-aware apps
- locale-aware apps
- project-specific access enforcement

### Per-Page Middleware

`app.middleware.pages`

This allows specific pages to have their own middleware stacks.

**Example**

```php
'pages' => [
    'two_factor_settings' => [
        \Xul\AuthKit\Http\Middleware\Authenticate::class,
        \Xul\AuthKit\Http\Middleware\RequirePasswordConfirmationMiddleware::class,
    ],
],
```
This lets you apply stricter protection to specific authenticated pages without affecting the entire app shell.

### Default Per-Page Behavior

#### `dashboard_web`

**Protected by:**

```php
\Xul\AuthKit\Http\Middleware\Authenticate::class
```
### Default Per-Page Behavior

#### `settings`

Protected by authentication.

#### `security`

Protected by authentication.


#### `sessions`

Protected by authentication.

#### `two_factor_settings`

**Protected by:**

- authentication
- fresh password confirmation requirement

**Default:**

```php
[
    \Xul\AuthKit\Http\Middleware\Authenticate::class,
    \Xul\AuthKit\Http\Middleware\RequirePasswordConfirmationMiddleware::class,
]
```
This is important because the two-factor settings area is security-sensitive.


### `confirm_password`

Protected only by authentication.

**Important:**

The page that performs password confirmation should not include the middleware that requires password confirmation, otherwise it would redirect back to itself.



### `confirm_two_factor`

Also protected only by authentication.

**Same principle:**

The page used to satisfy a step-up confirmation requirement should not include the middleware that enforces that same requirement.

### Practical Customization Example

Here is an example of customizing the authenticated application area:

```php
'app' => [
    'enabled' => true,

    'brand' => [
        'title' => 'Acme Portal',
        'subtitle' => 'Team Workspace',
        'type' => 'image',
        'image' => 'images/brand/acme-logo.png',
        'image_alt' => 'Acme Portal',
        'show_subtitle' => true,
    ],

    'pages' => [
        'dashboard_web' => [
            'enabled' => true,
            'title' => 'Home',
            'heading' => 'Welcome back',
            'route' => 'authkit.web.dashboard',
            'layout' => 'default',
            'view' => 'authkit::pages.app.dashboard',
            'nav_label' => 'Home',
            'show_in_sidebar' => true,
        ],

        'sessions' => [
            'enabled' => false,
            'title' => 'Sessions',
            'heading' => 'Active sessions',
            'route' => 'authkit.web.settings.sessions',
            'layout' => 'default',
            'view' => 'authkit::pages.app.sessions',
            'nav_label' => 'Sessions',
            'show_in_sidebar' => false,
        ],
    ],

    'middleware' => [
        'base' => [
            \Xul\AuthKit\Http\Middleware\Authenticate::class,
            \App\Http\Middleware\EnsureTenantIsResolved::class,
        ],
    ],
],
```
This example:

- changes branding
- renames dashboard to Home
- disables the sessions page
- adds tenant middleware to the authenticated area

### What This Affects

The `app` section affects:

- whether AuthKit provides a built-in post-login app area
- how the authenticated shell is branded
- how the sidebar behaves
- which authenticated pages exist
- which views and layouts those pages use
- what appears in sidebar navigation
- which middleware protect each page

### Best Practices

- leave `app.enabled` on if you want AuthKit to provide a complete logged-in account area
- keep utility pages such as confirmation pages out of primary sidebar navigation
- use page-level middleware for stricter protection on sensitive screens
- keep page route names aligned with `route_names.web`
- use published/custom views through the `view` key instead of editing package internals directly
- use `security.sections` when you want to hide specific built-in sections without disabling the whole page  

## Sensitive Action Confirmation Configuration

The `confirmations` section controls AuthKit’s **step-up confirmation system**.

This system is used when a user is **already authenticated** but must confirm their identity again before accessing a sensitive page or performing a sensitive action.

**Examples include:**

- opening a highly sensitive settings page
- viewing or regenerating recovery codes
- confirming identity before a destructive action
- re-verifying password or two-factor before account security changes

This is different from:

- login
- login-time two-factor challenge
- password reset

Those flows happen before or during authentication.  
The `confirmations` section applies **after the user is already signed in**.

### Overview

```php
'confirmations' => [
    'enabled' => true,
    'session' => [...],
    'ttl_minutes' => [...],
    'routes' => [...],
    'password' => [...],
    'two_factor' => [...],
],
```
This section controls:

- whether step-up confirmation is enabled
- where freshness timestamps are stored in session
- how long a successful confirmation stays valid
- where middleware should redirect users when confirmation is missing
- which confirmation types are enabled

### `confirmations.enabled`

```php
'enabled' => true,
```
This controls whether AuthKit’s step-up confirmation system is active.


### Behavior

**When set to `true`:**

- confirmation middleware can enforce fresh password or two-factor confirmation
- users may be redirected to confirmation pages before continuing to sensitive destinations

**When set to `false`:**

- confirmation middleware should ideally behave as pass-through
- the application may effectively disable these step-up protections

Use this when you want to globally enable or disable the confirmation layer.

### Session Key Configuration

`confirmations.session`

This section defines the session keys AuthKit uses for confirmation freshness and redirect state.

```php
'session' => [
    'password_key' => 'authkit.confirmed.password_at',
    'two_factor_key' => 'authkit.confirmed.two_factor_at',
    'intended_key' => 'authkit.confirmation.intended',
    'type_key' => 'authkit.confirmation.type',
],
```

### `password_key`

```php
'password_key' => 'authkit.confirmed.password_at',
```
This session key stores the timestamp of the last successful password confirmation.

The middleware can use this to decide whether password confirmation is still fresh enough.

### `two_factor_key`

```php
'two_factor_key' => 'authkit.confirmed.two_factor_at',
```

This session key stores the timestamp of the last successful two-factor confirmation.

The middleware can use this to determine whether the user has recently re-confirmed with two-factor.


### `intended_key`

```php
'intended_key' => 'authkit.confirmation.intended',
```

This stores the intended destination URL before the user is redirected to a confirmation page.

### Typical flow

- user tries to access a protected page
- middleware sees confirmation is missing or stale
- middleware stores the intended destination in session
- user is redirected to a confirmation page
- after success, AuthKit redirects the user back to the stored destination

### `type_key`

```php
'type_key' => 'authkit.confirmation.type',
```

This can be used to store which confirmation type is currently being requested.

**Example values may conceptually include:**

- `password`
- `two_factor`

This helps downstream flow handling stay explicit.


### Freshness Lifetime

`confirmations.ttl_minutes`

This section controls how long a successful confirmation remains valid.

```php
'ttl_minutes' => [
    'password' => 15,
    'two_factor' => 10,
],
```
These values are measured in minutes.

### `ttl_minutes.password`

```php
'password' => 15,
```
A successful password confirmation remains fresh for 15 minutes.

During that window, the user should not need to confirm again for password-protected sensitive actions.


### `ttl_minutes.two_factor`

```php
'two_factor' => 10,
```
A successful two-factor confirmation remains fresh for 10 minutes.

During that period, the user may continue through protected two-factor confirmation gates without another prompt.


### Security tradeoff

Shorter values provide stronger security but more user friction.

Longer values reduce friction but increase the time window during which a prior confirmation is trusted.

A common pattern is:

- somewhat longer password freshness
- somewhat shorter two-factor freshness for higher-sensitivity checkpoints


### Confirmation Redirect Routes

`confirmations.routes`

This section tells AuthKit where middleware should redirect the user when confirmation is required.

```php
'routes' => [
    'password' => 'authkit.web.confirm.password',
    'two_factor' => 'authkit.web.confirm.two_factor',
    'fallback' => 'authkit.web.dashboard',
],
```
### `routes.password`

Named route for the password confirmation page.

This is where users are redirected when password confirmation is required but missing or no longer fresh.

### `routes.two_factor`

Named route for the two-factor confirmation page.

This is where users are redirected when a fresh two-factor confirmation is required before continuing.


### `routes.fallback`

Fallback named route used after successful confirmation when no intended destination is available in session.

This ensures AuthKit still has a safe place to send the user even if the original intended URL was not stored or can no longer be used.

### Per-Confirmation-Type Enablement    

### `confirmations.password.enabled`

Controls whether password-based step-up confirmation is enabled.

When disabled, password confirmation middleware should ideally behave as pass-through and not require a fresh password confirmation.


### `confirmations.two_factor.enabled`

Controls whether two-factor-based step-up confirmation is enabled.

When disabled, middleware requiring a fresh two-factor confirmation should ideally behave as pass-through.

**Practical Example**
```php
'confirmations' => [
    'enabled' => true,

    'session' => [
        'password_key' => 'authkit.confirmed.password_at',
        'two_factor_key' => 'authkit.confirmed.two_factor_at',
        'intended_key' => 'authkit.confirmation.intended',
        'type_key' => 'authkit.confirmation.type',
    ],

    'ttl_minutes' => [
        'password' => 20,
        'two_factor' => 5,
    ],

    'routes' => [
        'password' => 'authkit.web.confirm.password',
        'two_factor' => 'authkit.web.confirm.two_factor',
        'fallback' => 'authkit.web.settings',
    ],

    'password' => [
        'enabled' => true,
    ],

    'two_factor' => [
        'enabled' => true,
    ],
],
```
This example:

- keeps the confirmation system enabled
- allows password freshness for 20 minutes
- requires fresher two-factor confirmation after 5 minutes
- redirects users back to settings when no intended route is stored

### What this affects

This section affects:

- step-up confirmation behavior for sensitive authenticated pages
- how freshness is tracked in session
- how long confirmations remain valid
- where users are redirected when re-confirmation is required
- whether password and two-factor confirmation flows are active

### Best Practices

- keep `confirmations.enabled` on in production if you expose sensitive account operations
- use shorter TTLs for higher-risk actions
- keep password and two-factor confirmation conceptually separate from login-time authentication
- ensure your confirmation routes match the named routes configured in `route_names.web`
- do not apply the enforcement middleware to the confirmation page itself, otherwise it may redirect to itself  

## UI Component Configuration

The `components` section defines the Blade component references AuthKit uses when rendering its UI.

AuthKit is built around reusable components, and this configuration gives you a central place to change which component view is used for each UI responsibility.

This means you can customize AuthKit’s rendering layer without rewriting the route system, controllers, actions, or flow logic.

### Overview

```php
'components' => [
    'layout' => 'authkit::layout',
    'container' => 'authkit::container',
    'card' => 'authkit::card',
    'alert' => 'authkit::alert',
    'page' => 'authkit::page',
    'auth_header' => 'authkit::auth.header',
    'auth_footer' => 'authkit::auth.footer',
    'label' => 'authkit::form.label',
    'input' => 'authkit::form.input',
    'select' => 'authkit::form.select',
    'textarea' => 'authkit::form.textarea',
    'checkbox' => 'authkit::form.checkbox',
    'otp' => 'authkit::form.otp',
    'help' => 'authkit::form.help',
    'error' => 'authkit::form.error',
    'errors' => 'authkit::form.errors',
    'button' => 'authkit::button',
    'link' => 'authkit::link',
    'divider' => 'authkit::divider',
    'theme_toggle' => 'authkit::theme-toggle',
    'field' => 'authkit::form.field',
    'fields' => 'authkit::form.fields',
    'option_items' => 'authkit::form.option-items',
    'app_layout' => 'authkit::app.layout',
    'app_shell' => 'authkit::app.shell',
    'app_sidebar' => 'authkit::app.sidebar',
    'app_topbar' => 'authkit::app.topbar',
    'app_nav' => 'authkit::app.nav',
    'app_nav_item' => 'authkit::app.nav-item',
    'app_page_header' => 'authkit::app.page-header',
    'app_user_menu' => 'authkit::app.user-menu',
    'settings_section' => 'authkit::app.settings.section',
    'session_list' => 'authkit::app.sessions.list',
],
```
Each value in this section is a Blade component or view reference.

AuthKit uses these references whenever it needs to render layouts, pages, form elements, and the authenticated application shell. This means the UI layer is fully configurable without modifying internal package logic.

### How this works

AuthKit resolves component references dynamically from this configuration when rendering:

- layouts
- pages
- form controls
- field wrappers
- validation and feedback
- authenticated shell elements

Because of this, the rendering system is **config-driven rather than hard-coded**.

### Important note

> These values must point to valid Blade components or published views.
> If you override them, ensure the referenced components exist and are resolvable by Laravel.


### Layout-Level Components

These define the overall structure of AuthKit pages.

- **`layout`**  
  Root layout for guest pages. Handles the HTML structure, assets, and runtime config.

- **`container`**  
  Wraps page content and controls layout width/spacing.

- **`card`**  
  Used for grouped sections such as auth panels.

- **`alert`**  
  Displays status messages and notifications.

- **`page`**  
  General-purpose page wrapper.

### Auth Page Components

Used for structuring guest authentication pages.

- **`auth_header`**  
  Renders page heading and branding.

- **`auth_footer`**  
  Renders footer content.

### Form Primitive Components

These are the base components used to render individual inputs.

- **`label`** — field labels
- **`input`** — default for most input types (text, email, password, etc.)
- **`select`** — select and multiselect controls
- **`textarea`** — multiline inputs
- **`checkbox`** — boolean inputs
- **`otp`** — specialized one-time code input

The `input` component acts as the fallback for most field types.

### Form Feedback Components

Used for user guidance and validation output.

- **`help`** — supporting text
- **`error`** — single field error
- **`errors`** — grouped or summary errors

### Actions and Navigation

Reusable UI primitives.

- **`button`** — actions
- **`link`** — navigation links
- **`divider`** — visual separation
- **`theme_toggle`** — appearance mode switcher

### Schema-Driven Field Components

These power the form schema system.

- **`field`**  
  Renders a single field (label, control, help text, errors).

- **`fields`**  
  Renders a collection of fields for a form.

- **`option_items`**  
  Renders selectable options for fields like select/multiselect.

This layer keeps page templates clean by centralizing field rendering logic.

### Authenticated Application Components

These power the logged-in app experience.

- **`app_layout`** — root layout for authenticated pages
- **`app_shell`** — main app wrapper (sidebar + content)
- **`app_sidebar`** — sidebar navigation
- **`app_topbar`** — top navigation bar
- **`app_nav`** — navigation groups
- **`app_nav_item`** — individual navigation items
- **`app_page_header`** — page headers
- **`app_user_menu`** — user dropdown/menu

### Account & Settings Components

Reusable components for account-related pages.

- **`settings_section`** — structured sections in settings/security pages
- **`session_list`** — active sessions display

### Field Component Resolution

AuthKit automatically maps field types to components.

Typical mapping:

- textarea → textarea component
- select/multiselect → select component
- checkbox → checkbox component
- otp → otp component
- everything else → input component

This means changing a component here affects all forms using that field type.


### What this affects

This configuration controls how AuthKit renders:

- guest authentication pages
- authenticated application layout
- all form inputs and validation feedback
- navigation elements
- reusable UI sections

### Best Practices

- Ensure all component references are valid
- Override only what you need
- Prefer published views for customization
- Keep core form components (`input`, `select`, `checkbox`, `otp`) consistent
- Treat this as the single source of truth for UI rendering

## UI Configuration

The `ui` section controls how AuthKit renders its visual interface.

AuthKit separates UI concerns into three independent layers:

- **`engine`** → the styling system (e.g. Tailwind-like or Bootstrap-like)
- **`theme`** → the color/brand identity within that system
- **`mode`** → the appearance mode (light, dark, or system)

This separation allows you to change the look and feel of AuthKit **without changing component markup or application logic**.

### Overview

The UI system is designed to be:

- framework-agnostic (no dependency on Tailwind or Bootstrap in your app)
- configurable via simple keys
- extendable for custom branding
- compatible with light/dark/system modes
- optionally interactive via JavaScript

### `ui.engine`

Defines the visual styling family used across all AuthKit components.

Typical options include:

- **`tailwind`** → utility-inspired modern styling
- **`bootstrap`** → traditional component-based styling

This affects:

- spacing
- typography
- component shapes
- layout feel

### `ui.theme`

Defines the color palette and brand identity within the selected engine.

**Examples:**

- forest
- slate-gold
- midnight-blue
- red-beige

AuthKit resolves the final stylesheet using both engine and theme.

Conceptually:
{engine} + {theme} → final stylesheet


So switching theme changes branding, while switching engine changes the entire visual language.


### `ui.mode`

Controls the appearance mode.

Supported values:

- **`light`** → always light mode
- **`dark`** → always dark mode
- **`system`** → follow user’s OS/browser preference

When using `system`, AuthKit may detect `prefers-color-scheme` and apply the correct mode at runtime.

### `ui.use_data_attributes`

When enabled, AuthKit emits data attributes such as:

- `data-authkit-engine`
- `data-authkit-theme`
- `data-authkit-mode`

These serve as stable hooks for:

- CSS targeting
- JavaScript behavior
- consumer overrides

This is a key part of making AuthKit extensible without modifying internals.


### `ui.load_stylesheet`

Controls whether AuthKit automatically loads its packaged CSS.

- **`enabled`** → AuthKit loads the theme stylesheet
- **`disabled`** → you must load styles yourself

Disable this if you:

- bundle AuthKit styles into your own build pipeline
- want full control over styling
- are replacing the UI entirely

### `ui.load_script`

Controls whether AuthKit loads its base JavaScript.

When enabled, AuthKit can handle:

- theme mode resolution
- persistence
- toggle interactions

When disabled, you are responsible for handling these behaviors yourself.

### UI Persistence

### `ui.persistence`

Controls whether the user’s preferred appearance mode is remembered.

- **`enabled`** → mode is stored in browser storage
- **`storage_key`** → key used to store the preference

This allows users to keep their selected mode (light/dark/system) across visits.


### Theme Toggle

### `ui.toggle`

Controls the packaged theme toggle component.

This component is optional and can be placed anywhere in your UI

### `toggle.enabled`

Enables or disables the packaged toggle component.

Even if disabled, you can build your own toggle.

### `toggle.variant`

Controls how the toggle appears.

Common styles include:

- icon
- buttons
- dropdown

This only affects the packaged component, not your custom implementations.

### `toggle.allow_system`

Determines whether users can choose “system” mode.

- enabled → light, dark, and system options available
- disabled → only light and dark

### `toggle.show_labels`

Controls whether labels appear alongside toggle icons.

### `toggle.attribute`

Defines the HTML attribute used to bind toggle behavior.

AuthKit JavaScript uses this attribute to detect toggle elements and attach interactions.

### UI Extensions

### `ui.extensions`

Provides hooks for extending or overriding styling.

### `enable_root_hooks`

Enables stable CSS hooks such as:

- `.authkit`
- `[data-authkit-engine]`
- `[data-authkit-theme]`
- `[data-authkit-mode]`

These are essential for safe overrides.

### `extra_css`

Allows loading additional styles after AuthKit’s stylesheet.

Use cases:

- brand overrides
- minor tweaks
- custom components

### `extra_js`

Allows loading additional scripts after AuthKit’s runtime.

Use cases:

- custom UI interactions
- analytics hooks
- extended behaviors


### Theme Configuration

The `themes` section defines how AuthKit resolves theme assets.


### Engines

Lists supported styling systems.

This is primarily informational but can also support validation and tooling.


### Available Themes

Defines available theme names per engine.

This helps:

- documentation
- UI selectors
- validation

You can extend this list when adding custom themes.

### File Pattern

Defines how theme filenames are constructed.

AuthKit uses a pattern to resolve the final stylesheet dynamically.

This allows consistent naming and easy extension.

### JavaScript Configuration

The `javascript` section controls AuthKit’s browser runtime.

AuthKit ships a single entry file that boots internal modules responsible for:

- theme handling
- form enhancements
- page-specific behavior


### `javascript.enabled`

Enables or disables the entire runtime.

When disabled:

- pages still work (progressive enhancement)
- no client-side enhancements are applied

### Runtime Configuration

### `window_key`

Defines the global object exposed in the browser.

Example:

- `window.AuthKit`

This can be used for integration or debugging.


### `dispatch_events`

Controls whether AuthKit emits browser events.

These events allow external scripts to hook into AuthKit behavior.

### `event_target`

Defines where events are dispatched:

- document
- window

Recommended: use `document` for most use cases.


### Browser Events

AuthKit emits structured events during runtime.

Examples include:

- runtime ready
- theme initialized
- theme changed
- form lifecycle events
- page initialization

These events allow consumers to:

- extend behavior
- integrate analytics
- plug in custom UI systems

### Core Modules

AuthKit runtime is modular.


### Theme Module

Handles:

- resolving light/dark/system mode
- persisting preference
- syncing toggle UI
- reacting to system changes


### Forms Module

Handles:

- AJAX form submission
- payload serialization
- validation/error handling
- redirect handling
- lifecycle events

### Page Modules

AuthKit supports page-specific JavaScript modules.

Each module corresponds to a page type, such as:

- login
- register
- two-factor challenge
- password reset
- email verification
- dashboard and settings pages

These modules are booted based on page context (typically via DOM markers).

### Authenticated Page Modules

Includes modules for:

- dashboard
- settings
- security
- sessions
- two-factor management
- confirmation pages

These modules enhance the authenticated app experience.

### JavaScript Extensions

### `javascript.extensions`

Allows loading additional scripts alongside AuthKit runtime.

Use cases:

- analytics
- custom UI behavior
- integration logic

These scripts run after AuthKit initializes.

### What this affects

This entire configuration controls:

- how AuthKit looks (engine + theme)
- how it behaves visually (mode + toggle)
- how it loads assets (CSS + JS)
- how it exposes runtime hooks (events)
- how it enhances pages (modules)
- how you extend or override behavior

### Best Practices

- keep engine and theme consistent with your product design
- enable persistence for better user experience
- use system mode only if you support dark mode fully
- keep runtime enabled unless you are replacing all client behavior
- use events instead of modifying package JS directly
- use extensions for customization instead of editing core files