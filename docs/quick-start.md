# Quick Start

## Overview

This guide provides a step-by-step walkthrough for setting up AuthKit in a Laravel application with the minimum required configuration.

AuthKit is designed to work out of the box using sensible defaults. Once installed, it automatically registers its services, loads its routes, and prepares authentication flows such as registration, login, email verification, password reset, and two-factor authentication.

This guide focuses on getting you from installation to a fully working authentication system as quickly as possible, while still giving you enough context to understand what is happening under the hood.

If you are looking for deeper customization or architectural understanding, those are covered in later sections of the documentation.

## What You Will Have by the End

By the end of this guide, your application will have a fully functional authentication system powered by AuthKit.

This includes:

- user registration and login
- logout functionality
- email verification (if enabled)
- password reset flow
- password confirmation for sensitive actions
- two-factor authentication (if enabled)

In addition:

- AuthKit routes will be active and accessible
- your user model will be compatible with AuthKit features
- your application will be able to send authentication-related emails
- core security flows will be operational

You will also be in a position to:

- customize configuration and behavior
- override views and UI
- extend authentication flows using actions, mappers, and notifiers

## Step 1: Install AuthKit

Install AuthKit via Composer:

```bash
composer require xul/auth-kit
```
Once installed, Laravel will automatically discover and register the package.
No manual service provider registration is required.

## Step 2: Publish Configuration and Migrations

Publish the core AuthKit resources:

```bash
php artisan vendor:publish --tag=authkit-config
php artisan vendor:publish --tag=authkit-migrations
```
This will:
- publish the AuthKit configuration file into config/authkit.php 
- publish the required database migrations into your application's database/migrations directory 
- Publishing the configuration allows you to control how AuthKit behaves, including routes, middleware, flows, and feature toggles.

## Step 3: Run Database Migrations

Run your database migrations to create the necessary tables and columns required by AuthKit:
```bash
php artisan migrate
```
These migrations prepare your application for:
- authentication-related state tracking
- two-factor authentication data
- password reset and verification flows

Ensure your database connection is properly configured before running migrations.

## Step 4: Prepare Your User Model

AuthKit expects your user model to work with Laravel’s authentication system and to support the persistence and security features used by the package.

At a minimum, your user model should:

- extend `Illuminate\Foundation\Auth\User` or implement Laravel’s `Authenticatable` contract
- be the model resolved by your configured authentication provider
- include the database columns required by the AuthKit features you plan to use
- use the AuthKit traits needed for mapped persistence and two-factor authentication

A typical AuthKit user model will include both of these traits:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
use Xul\AuthKit\Concerns\Model\HasAuthKitTwoFactor;

class User extends Authenticatable
{
    use HasAuthKitMappedPersistence;
    use HasAuthKitTwoFactor;
}
```
### Why These Traits Matter

#### HasAuthKitMappedPersistence

This trait gives your model the persistence interface expected by AuthKit’s mapper-driven flows.

It is especially important when AuthKit actions need to persist mapped payload data in a controlled and explicit way rather than assuming hard-coded field behavior.

It enables AuthKit to work cleanly with:

- configurable registration payloads
- mapped form input
- controlled persistence of only allowed fields
- extensible registration and profile-related flows

#### HasAuthKitTwoFactor

This trait adds the model-side behavior required for AuthKit’s two-factor authentication features.

It provides a consistent interface for working with:

- whether two-factor authentication is enabled
- the stored two-factor secret
- recovery codes
- enabled two-factor methods
- confirmation state

### Make Sure Your Schema Supports Your Features

Depending on which AuthKit modules you enable, your users table should support the fields required by those flows.

Common examples include:

- `email_verified_at` for email verification

- two-factor columns such as:
    - `two_factor_enabled`
    - `two_factor_secret`
    - `two_factor_recovery_codes`
    - `two_factor_methods`
    - `two_factor_confirmed_at`

If you rename any of these columns in your application, update the corresponding keys in `config/authkit.php`.

### Recommended Model Casting

If your application stores JSON-based AuthKit fields such as methods or recovery codes in JSON columns, ensure your model casts are compatible with your chosen storage strategy where needed.

AuthKit provides the operational layer, but your model and schema should still reflect the features you enable.

## Step 5: Confirm Your Authentication Guard and Provider

AuthKit does not assume a fixed authentication guard beyond what you configure.

By default, the package uses the guard defined in:
```php
authkit.auth.guard
```
The default value is:
```php
'guard' => 'web'
```
This means AuthKit will typically resolve users and authentication state through Laravel’s standard `web` guard unless you change it.

### Confirm Your Laravel Auth Configuration

Open `config/auth.php` and verify that your intended guard and provider are correctly configured.
A common Laravel setup looks like this:
```php
'defaults' => [
    'guard' => 'web',
    'passwords' => 'users',
],

'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
],
```
### Confirm AuthKit matches your guard
Then open `config/authkit.php` and confirm that the configured guard matches the one your application should use:
```php
'auth' => [
    'guard' => 'web',
],
```
This is important because AuthKit uses the configured guard for things such as:

- resolving the authenticated user
- password confirmation flows
- email verification login behavior
- post-authentication redirects and stateful flows

If your application uses a different guard, such as an admin or tenant-specific guard, update this value accordingly.

### Confirm Your Provider Resolves the Correct Model

Your selected guard’s provider should resolve the exact model you want AuthKit to work with.
That model should be the same one you prepared in the previous step with the required AuthKit traits and schema support.
If the guard points to the wrong provider or model, flows such as login, registration persistence, password reset, and authenticated settings behavior may not work as expected.
## Step 6: Review the Default AuthKit Configuration

AuthKit is intentionally configuration-driven.
After publishing the package config, take time to review:
`config/authkit.php`
You do not need to customize everything immediately, but understanding the structure early will make the rest of the setup much easier.

### Important Sections to Review

#### Authentication

This section controls the guard AuthKit uses:

- `auth.guard`

#### Identity

This defines the primary login identity field and how it behaves:

- login field name
- UI label
- input type
- autocomplete
- normalization behavior

By default, AuthKit uses email as the login identity.

#### Routes and Route Names

These sections control:

- route prefix
- global and group middleware
- internal route naming
- separation between web pages and action/API endpoints

This is useful if you want AuthKit routes to fit an existing application structure.

#### Controllers

AuthKit allows controller overrides through configuration.

This means you can keep package routes while swapping the controllers used for specific endpoints.

#### Validation Providers

Validation can be customized per flow context without directly editing package requests.

Examples include:

- login
- register
- password reset
- email verification
- confirmation flows
- two-factor settings actions

#### Mappers

Mapped payload handling is one of the key architectural parts of AuthKit.

The mapper configuration defines how validated input is translated into the normalized payload consumed by package actions.

This becomes especially important for flows such as registration and other configurable forms.

#### Schemas

The schemas section defines the canonical form structure used by AuthKit.

This includes:

- submit metadata
- ordered field definitions
- labels
- input types
- rendering hints
- wrappers
- custom attributes

If you plan to customize AuthKit’s forms, this is one of the most important sections in the config.

#### Forms

This section controls whether forms use:

- standard HTTP submission
- AJAX submission through the AuthKit JavaScript runtime

It also controls loading state behavior and AJAX success handling.

#### Email Verification, Password Reset, and Two-Factor Authentication

These sections define the main security flows in the package, including:

- whether a feature is enabled
- driver selection
- token or link behavior
- delivery listener usage
- notifier classes
- post-success behavior
- token limits and throttling

#### Rate Limiting

AuthKit includes built-in limiter mappings and strategy configuration for sensitive endpoints.

This allows you to review and tune how login, reset, verification, confirmation, and two-factor flows are protected.

#### Authenticated App Pages

If you are using AuthKit’s logged-in application shell, the `app` section controls:

- page enablement
- layouts
- sidebar navigation
- authenticated middleware
- page view mapping

#### UI, Themes, and JavaScript Runtime

These sections define how AuthKit loads and behaves on the frontend, including:

- UI engine
- active theme
- mode handling
- theme toggle behavior
- packaged asset loading
- browser runtime modules
- page-level JavaScript behavior

### Why This Review Matters

Even if you keep the defaults, reviewing the config gives you a working mental model of how AuthKit is structured.

It helps you understand:

- what is enabled
- how flows are wired
- where to customize behavior later
- which extension points already exist

For first-time setup, the safest path is usually to keep the defaults, confirm they match your application, and then continue with route verification and mail setup.

## Step 7: Load and Verify AuthKit Routes

Once AuthKit is installed and configured, the package loads its route files automatically through the service provider.

By default, AuthKit loads:

- guest web routes
- guest action/API routes
- authenticated app web routes
- authenticated app action/API routes

The authenticated app routes are loaded only when the authenticated application area is enabled in configuration.

### What AuthKit loads

AuthKit registers routes from these internal package route files:

- `src/Routes/web.php`
- `src/Routes/api.php`
- `src/Routes/app-web.php`
- `src/Routes/app-api.php`

This means you do not need to manually include AuthKit route files in your application's `routes/web.php` or `routes/api.php`.

### Start your application

Run your application locally:

```bash
php artisan serve
```
Then verify that the expected routes are available.
Check the route list
Use Laravel’s route list command:
```bash
php artisan route:list
```
Look for AuthKit route names such as:

- `authkit.web.login`
- `authkit.web.register`
- `authkit.api.auth.login`
- `authkit.api.auth.register`
- `authkit.web.password.forgot`
- `authkit.web.password.reset`
- `authkit.web.email.verify.notice`

If the authenticated app area is enabled, you should also see routes such as:

- `authkit.web.dashboard`
- `authkit.web.settings`
- `authkit.web.settings.security`
- `authkit.web.settings.sessions`
- `authkit.web.settings.two_factor`

## Visit the Main Pages

Open the relevant routes in your browser and confirm they render correctly.

Typical examples include:

- `/login`
- `/register`

Depending on your route prefix configuration, these URLs may differ.

If you changed `authkit.routes.prefix`, include that prefix when checking URLs.

## Verify Route Naming and Middleware Behavior

AuthKit uses named routes internally, so route name resolution is important for:

- redirects
- success pages
- verification flows
- password reset flows
- authenticated app navigation

If you override route names or middleware stacks, verify they still align with the flows you want to use.

## Step 8: Configure Mail Delivery

AuthKit relies on Laravel’s mail system for authentication-related notifications such as:

- email verification
- password reset

If mail is not configured correctly, these flows may appear to work internally but will not deliver messages to users.

### Configure Your Mail Environment

Open your `.env` file and provide a valid mail configuration.

Example:
```bash
MAIL_MAILER=smtp
MAIL_HOST=your-mail-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Your App"
```
Use the correct values for your mail provider.

### Understand AuthKit Delivery Behavior

AuthKit supports configurable delivery behavior for both email verification and password reset.

By default, the package registers internal listeners that dispatch notifications using configured notifier classes.

Relevant config sections include:

- `authkit.email_verification.delivery`
- `authkit.password_reset.delivery`

These sections allow you to control:

- whether the built-in listener is used
- which listener class handles delivery
- which notifier class is used
- whether delivery runs synchronously, on the queue, or after the response

### If You Are Using Queues

If you switch delivery mode to `queue`, make sure your queue system is configured and running correctly.

You should confirm:

- your queue connection is set properly
- your queue worker is running
- any configured queue name matches your queue setup

### Test Mail Before Going Further

Before testing full authentication flows, make sure your Laravel application can send mail successfully.

This helps isolate delivery issues early instead of confusing them with AuthKit flow issues later.

## Step 9: Test the Core Authentication Flow

At this point, you should test the main guest authentication flow from start to finish.

The goal here is to confirm that AuthKit is correctly wired into your application before you begin testing more advanced security features.

### Test Registration

Visit the registration page and create a new user.

Confirm that:

- the registration page renders correctly
- validation works as expected
- the user record is created successfully
- mapped persistence behaves correctly for the configured fields

If your registration flow triggers email verification, note whether the user is redirected into a verification flow after registration.

### Test Login

Visit the login page and sign in with the new account.

Confirm that:

- the login page renders correctly
- credentials are validated correctly
- the configured identity field works as expected
- successful login redirects to the expected destination

By default, AuthKit uses the configured login redirect route or dashboard route.

### Test Logout

Once logged in, test logout behavior.

Confirm that:

- the user session is invalidated correctly
- protected pages are no longer accessible after logout
- the user is redirected as expected

### Confirm Authenticated Pages

If AuthKit’s authenticated app area is enabled, verify that the main authenticated pages load correctly after login.

Common examples include:

- dashboard
- settings
- security
- sessions

This confirms that the authenticated shell, page configuration, and middleware are all functioning correctly.

## Step 10: Test Verification, Reset, and Two-Factor Flows

After confirming the core authentication flow, test the security flows enabled in your configuration.

### Test Email Verification

If email verification is enabled, verify that:

- a verification flow begins when expected
- verification notifications are sent
- the correct verification driver is being used
- successful verification updates the user’s verification state
- post-verification redirect behavior works as configured

If you use the link driver, test the verification link flow.

If you use the token driver, test the token entry page and code submission flow.

### Test Password Reset

Verify the password reset flow from start to finish.

Confirm that:

- the forgot-password page renders correctly
- reset notifications are sent
- the correct reset driver is being used
- reset tokens or links are accepted correctly
- the password is updated successfully
- post-reset behavior matches your configuration

If privacy protection is enabled, also confirm that the forgot-password response does not reveal whether a user exists.

### Test Two-Factor Authentication

If two-factor authentication is enabled, test the setup and usage flow carefully.

Confirm that:

- two-factor setup can be initiated
- the secret and QR or setup details are generated correctly
- confirmation works using a valid code
- recovery codes are generated and shown correctly
- login-time two-factor challenge works
- recovery-code fallback works if enabled in your flow
- disabling two-factor works correctly
- regenerating recovery codes works correctly

### Test Confirmation Flows

If step-up confirmations are enabled, also verify:

- password confirmation pages
- two-factor confirmation pages
- redirection to the intended destination after successful confirmation

This is important for sensitive settings and protected account actions.

## Optional: Publish Views and Assets for Customization

AuthKit works without publishing views or assets, but publishing them gives you full control over presentation and frontend customization.

### Publish Views

To customize package views, publish them into your application:
```bash
php artisan vendor:publish --tag=authkit-views
```
This publishes the Blade files into:

- `resources/views/vendor/authkit`

Once published, you can customize:

- page layouts
- authentication pages
- app shell pages
- form components
- account and security pages

### Publish Assets

To customize or inspect the packaged frontend assets, publish them into your public directory:
```bash
php artisan vendor:publish --tag=authkit-assets
```
This publishes built assets into:

- `public/vendor/authkit`

These assets include the package’s frontend styling and JavaScript runtime.

### When to Publish

You should consider publishing views and assets when:

- you want to customize UI structure
- you want to change branding or theme behavior
- you want to override packaged components
- you want to inspect or extend the frontend runtime behavior

If you are only trying to get AuthKit running for the first time, you can leave this step until later.

## Troubleshooting First-Time Setup

If AuthKit does not work correctly on first setup, check the following areas.

### Routes Are Missing

If AuthKit routes do not appear:

- confirm the package is installed correctly
- confirm Laravel package discovery is working
- confirm the service provider is being discovered
- clear cached configuration and routes

Useful commands:
```bash
php artisan optimize:clear
php artisan route:list
```
### Views Do Not Render

If pages fail to render correctly:

- confirm the `authkit::` view namespace is being loaded
- confirm the package views exist in `vendor/`
- if you published views, confirm your published files are in the expected location
- check for errors in overridden Blade files

### Mail Is Not Sending

If verification or reset notifications are not arriving:

- confirm your `.env` mail settings
- test Laravel mail independently
- confirm the relevant AuthKit delivery listener is enabled
- confirm queue workers are running if using queued delivery

### Login or Registration Fails Unexpectedly

Check:

- your `config/auth.php` guard and provider setup
- your `authkit.auth.guard` configuration
- your user model
- required AuthKit traits on the user model
- required database columns and migrations

### Two-Factor Setup Fails

If two-factor flows are not working:

- confirm two-factor is enabled in configuration
- confirm your user model uses `HasAuthKitTwoFactor`
- confirm the required two-factor columns exist
- confirm the active two-factor driver is configured properly

### Custom Config Changes Are Not Taking Effect

If your config changes appear ignored, clear Laravel caches:
```bash
php artisan optimize:clear
```
Then test again.

## Next Steps

Now that AuthKit is installed and working, you can move deeper into configuration and customization.