# Two-Factor Confirmation

AuthKit provides a **step-up two-factor confirmation flow** for already-authenticated users who must confirm their identity again before accessing a sensitive page or performing a protected action.

This flow is different from the login-time two-factor challenge.

It is controlled primarily by:

```php
authkit.confirmations.enabled
authkit.confirmations.two_factor.enabled
authkit.confirmations.session
authkit.confirmations.ttl_minutes.two_factor
authkit.confirmations.routes
authkit.auth.guard
authkit.two_factor.driver
authkit.two_factor.columns.enabled
```
## Overview

Two-factor confirmation is part of AuthKit’s authenticated confirmation system.

It is used when a user is already signed in, but must provide a fresh two-factor code before continuing.

### Typical use cases include:

- accessing high-sensitivity settings pages
- confirming a dangerous account action
- protecting recovery code pages
- enforcing step-up security for privileged operations

At a high level, the flow works like this:

- the user tries to access a protected page or action
- middleware checks whether a fresh two-factor confirmation already exists
- if not, AuthKit stores the intended destination in session
- the user is redirected to the two-factor confirmation page
- the user submits a valid authentication code
- AuthKit verifies the code using the active two-factor driver
- AuthKit stores a fresh confirmation timestamp in session
- the user is redirected back to the intended destination or fallback route

## Middleware Protection

Route protection is handled by:

```php
Xul\AuthKit\Http\Middleware\RequireTwoFactorConfirmationMiddleware
```

This middleware is responsible for enforcing a fresh two-factor confirmation before the request can continue.

### What it checks

The middleware checks:

- authkit.confirmations.enabled
- authkit.confirmations.two_factor.enabled
- authkit.auth.guard
- authkit.confirmations.session.two_factor_key
- authkit.confirmations.ttl_minutes.two_factor
- authkit.confirmations.session.intended_key
- authkit.confirmations.session.type_key
- authkit.confirmations.routes.two_factor

### Middleware behavior

When a request enters the middleware:

- AuthKit confirms that the confirmation system is enabled
- AuthKit confirms that two-factor confirmation is enabled
- AuthKit resolves the authenticated user using the configured guard
- AuthKit checks whether a fresh two-factor confirmation timestamp exists in session
- if confirmation is still fresh, the request continues
- otherwise, AuthKit stores:
    - the intended destination URL
    - the confirmation type
- AuthKit redirects the user to the configured two-factor confirmation page

### Freshness window

Freshness is determined using:

- authkit.confirmations.session.two_factor_key
- authkit.confirmations.ttl_minutes.two_factor

If the stored timestamp is older than the configured TTL, the confirmation is considered stale and a new confirmation is required.

### Users without two-factor enabled

The middleware also includes support for redirecting users who do not yet have two-factor enabled to the authenticated two-factor settings page.

That redirect uses:

```php
authkit.route_names.web.two_factor_settings
```
and is implemented by:

```php
Xul\AuthKit\Http\Middleware\RequireTwoFactorConfirmationMiddleware::redirectToTwoFactorSetup()
```

At the moment, that check is present in the middleware but commented out in the provided implementation.

If enabled in your own flow, it allows you to require that users first enable two-factor before they can access the protected area.

## Controller Flow

Two-factor confirmation submissions are handled by:

```php
Xul\AuthKit\Http\Controllers\Api\App\Confirmations\ConfirmTwoFactorController
```

This controller is intended for already-authenticated users and remains intentionally thin.

### Responsibilities

The controller:

- resolves the authenticated user from authkit.auth.guard
- validates the request through Xul\AuthKit\Http\Requests\App\Confirmations\ConfirmTwoFactorRequest
- builds the normalized mapped payload for the confirm_two_factor context
- delegates verification to Xul\AuthKit\Actions\App\Confirmations\ConfirmTwoFactorAction
- returns JSON for API or AJAX consumers
- returns redirects for standard web submissions

## Response behavior

The controller uses:

```php
Xul\AuthKit\Support\Resolvers\ResponseResolver
```

to determine whether the response should be JSON or redirect-based.

For redirect responses, it resolves the fallback confirmation page from:

```php
authkit.route_names.web.confirm_two_factor
```

## Validation

Validation is handled by:

```php
Xul\AuthKit\Http\Requests\App\Confirmations\ConfirmTwoFactorRequest
```

The request is schema-aware and driven by configuration.

### Relevant config keys:
```php
authkit.schemas.confirm_two_factor
authkit.validation.providers.confirm_two_factor
```

### Default behavior

The default confirm-two-factor form is built around the authentication code field.

The request ensures the confirmation input is present and valid for the schema context.

## Custom validation provider

If you want to override the default validation behavior, you may configure:
```php
authkit.validation.providers.confirm_two_factor
```

Any custom rules provider must implement:
```php
Xul\AuthKit\Contracts\Validation\RulesProviderContract
```

## Form Schema

The two-factor confirmation form is defined by:

```php
authkit.schemas.confirm_two_factor
```

This schema controls:

- the fields rendered on the confirmation page
- labels
- field types
- placeholders
- UI metadata
- submit button text

By default, this is a confirmation form for submitting an authentication code, not a login-time challenge form.

That distinction matters:

- `confirm_two_factor` is for authenticated step-up confirmation
- `two_factor_challenge` is for login-time authentication before a session is established

Because the flow is schema-driven, UI and validation stay aligned when the form is customized.

## Payload Mapping

After validation, AuthKit builds the normalized payload using:

```php
Xul\AuthKit\Support\Mappers\MappedPayloadBuilder
```

for the mapper context:

confirm_two_factor

This context is configured under:

```php
authkit.mappers.contexts.confirm_two_factor
```

If you override the mapper, your custom mapper must implement:

```php
Xul\AuthKit\Contracts\Mappers\PayloadMapperContract
```

As with other AuthKit confirmation flows, the action remains persistence-aware even though the packaged mapper does not persist confirmation fields by default.

## Action Flow

The actual confirmation logic is handled by:

```php
Xul\AuthKit\Actions\App\Confirmations\ConfirmTwoFactorAction
```

This action is the source of truth for the step-up two-factor confirmation outcome.

### Responsibilities

The action:

- ensures an authenticated user object exists
- ensures the user has two-factor enabled
- reads the submitted code from the mapped payload
- resolves the active two-factor driver
- verifies the submitted code
- persists mapper-approved attributes when supported
- stores a fresh two-factor confirmation timestamp in session
- clears transient confirmation navigation metadata
- resolves the correct redirect target
- returns a standardized `Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult`

## Two-factor enabled check

The action determines whether the current user has two-factor enabled by:

- calling `hasTwoFactorEnabled()` on the user model when available
- otherwise reading the configured enabled column from: `authkit.two_factor.columns.enabled`

If the user does not have two-factor enabled, the action returns a failure result and redirects to the two-factor settings page using:

```php
authkit.route_names.web.two_factor_settings
```

## Driver verification

The action resolves the active driver through:

```php
Xul\AuthKit\Support\TwoFactor\TwoFactorManager
```

using the configured driver name from:

```php
authkit.two_factor.driver
```

It then calls the active driver’s verification method against the submitted code.

This means the confirmation flow is driver-aware and not limited to one specific 2FA implementation.

## Session Persistence

On successful confirmation, the action writes a fresh session timestamp using:

authkit.confirmations.session.two_factor_key

It also clears:

```php
authkit.confirmations.session.intended_key
authkit.confirmations.session.type_key
```

This allows the middleware to recognize that the user has already completed a fresh two-factor confirmation and prevents repeated prompts until the configured TTL expires.

## Redirect Resolution

After a successful confirmation, redirect resolution follows this order:

- the stored intended URL from:  
```php
authkit.confirmations.session.intended_key
```
- the configured fallback route from:  
```php
authkit.confirmations.routes.fallback
```

If confirmation fails, the action redirects back to the configured two-factor confirmation page:

```php
authkit.route_names.web.confirm_two_factor
```

If the user does not have two-factor enabled, the action redirects to:
```php
authkit.route_names.web.two_factor_settings
```

## Relationship to Login-Time Two-Factor

It is important not to confuse this flow with the login-time challenge flow.

### Step-up two-factor confirmation

This flow is for:

- already-authenticated users
- sensitive pages and actions
- short-lived confirmation freshness stored in session

Main classes:

```php
Xul\AuthKit\Http\Middleware\RequireTwoFactorConfirmationMiddleware
Xul\AuthKit\Http\Controllers\Api\App\Confirmations\ConfirmTwoFactorController
Xul\AuthKit\Actions\App\Confirmations\ConfirmTwoFactorAction
```

### Login-time two-factor challenge

That separate flow is for:

- users who are not yet fully logged in
- completion of the primary authentication process
- pending login challenges

Main classes there are different and should not be mixed into this step-up confirmation flow.

## Key Configuration

The most important configuration entries for this flow are:

```php
authkit.confirmations.enabled
authkit.confirmations.two_factor.enabled
authkit.confirmations.session.two_factor_key
authkit.confirmations.session.intended_key
authkit.confirmations.session.type_key
authkit.confirmations.ttl_minutes.two_factor
authkit.confirmations.routes.two_factor
authkit.confirmations.routes.fallback
authkit.auth.guard
authkit.two_factor.driver
authkit.two_factor.columns.enabled
authkit.route_names.web.confirm_two_factor
authkit.route_names.web.two_factor_settings
authkit.schemas.confirm_two_factor
authkit.validation.providers.confirm_two_factor
authkit.mappers.contexts.confirm_two_factor
```

## Example Configuration

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
        'password' => 15,
        'two_factor' => 10,
    ],

    'routes' => [
        'password' => 'authkit.web.confirm.password',
        'two_factor' => 'authkit.web.confirm.two_factor',
        'fallback' => 'authkit.web.dashboard',
    ],

    'two_factor' => [
        'enabled' => true,
    ],
],
```

And the two-factor system it depends on may include:

```php
'two_factor' => [
    'enabled' => true,
    'driver' => 'totp',

    'columns' => [
        'enabled' => 'two_factor_enabled',
        'secret' => 'two_factor_secret',
        'recovery_codes' => 'two_factor_recovery_codes',
        'methods' => 'two_factor_methods',
        'confirmed_at' => 'two_factor_confirmed_at',
    ],
],
```

## Overrides and Customization

You can customize this flow through configuration without replacing the whole system.

### Common override points include:

- validation provider:  
```php
authkit.validation.providers.confirm_two_factor
```

- mapper:  
```php
authkit.mappers.contexts.confirm_two_factor.class
```

- confirmation page controller:  
```php
authkit.controllers.web.confirm_two_factor
```

- confirmation action controller:  
```php
authkit.controllers.api.confirm_two_factor
```

- active two-factor driver:  
```php
authkit.two_factor.driver
```

Any custom rules provider must implement:

```php
Xul\AuthKit\Contracts\Validation\RulesProviderContract
```

Any custom mapper must implement:

```php
Xul\AuthKit\Contracts\Mappers\PayloadMapperContract
```

Any custom two-factor driver must implement:

```php
Xul\AuthKit\Contracts\TwoFactorDriverContract
```

## Best Practices

- Use two-factor confirmation only for pages and actions that are genuinely sensitive.
- Do not apply `Xul\AuthKit\Http\Middleware\RequireTwoFactorConfirmationMiddleware` to the confirmation page itself.
- Keep `authkit.confirmations.ttl_minutes.two_factor` short enough to provide real security value, but not so short that it causes unnecessary friction.
- Make sure the authenticated user model can accurately report whether two-factor is enabled, either through `hasTwoFactorEnabled()` or the configured enabled column.
- Use the two-factor settings route as the fallback destination when step-up confirmation is required but the user has not yet enabled two-factor.
- Prefer extending this flow through configuration, drivers, mappers, and validation providers instead of modifying AuthKit internals directly.  