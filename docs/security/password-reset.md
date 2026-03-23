# Password Reset

AuthKit provides a **configurable, driver-based password reset system** that supports both:

- reset-link flows
- reset-token / reset-code flows

The flow is built to be privacy-aware, extensible, and fully configuration-driven.

---

## Overview

Password reset in AuthKit has three main phases:

1. **Requesting a password reset**
2. **Receiving a reset link or reset code**
3. **Completing the password reset**

The system is controlled through:

```php
authkit.password_reset.driver
authkit.password_reset.ttl_minutes
authkit.password_reset.delivery
authkit.password_reset.post_request
authkit.password_reset.post_reset
authkit.password_reset.user_resolver
authkit.password_reset.policy
authkit.password_reset.password_updater
authkit.password_reset.privacy
```
**Supported drivers:**

- `link`
- `token`

The `link` driver sends a reset URL containing the raw token.

The `token` driver sends a reset code or token that the user enters manually.

### Flow Overview

At a high level, the password reset flow works like this:

- the user submits a forgot-password request
- AuthKit resolves whether reset can be requested
- AuthKit creates a pending password reset token
- AuthKit dispatches a password reset event for delivery
- the user follows a link or enters a token
- AuthKit validates and consumes the token
- AuthKit resolves the target user
- AuthKit updates the password
- AuthKit optionally logs the user in
- AuthKit redirects according to configuration

### Pending Password Reset State

AuthKit tracks reset state through:

```php
Xul\AuthKit\Support\PendingPasswordReset
```

This class is responsible for:

- creating reset tokens
- tracking whether a reset flow is pending for an email
- peeking at a token without consuming it
- consuming a token as a single-use credential
- clearing pending presence after successful consumption

### Driver behavior

#### For the `link` driver:

- AuthKit generates a long token
- that token is typically embedded in a reset URL

#### For the `token` driver:

- AuthKit generates a short code or token
- the user manually enters it on the reset page

### Presence tracking

AuthKit also stores a short-lived presence marker keyed by email so that web pages and middleware can confirm that a reset flow exists without knowing the raw token.

## Routes

Password reset routes are resolved from configuration.

### Relevant web route names:

```php
authkit.route_names.web.password_forgot
authkit.route_names.web.password_forgot_sent
authkit.route_names.web.password_reset
authkit.route_names.web.password_reset_token_page
authkit.route_names.web.password_reset_success
authkit.route_names.web.login
```

### Relevant API route names:

```php
authkit.route_names.api.password_send_reset
authkit.route_names.api.password_verify_token
authkit.route_names.api.password_reset
```

These routes are used for:

- the forgot-password page
- the post-request confirmation page
- the reset form page
- the token entry page
- the password reset success page
- the send-reset action
- the token verification action
- the reset-password action

## Requesting a Password Reset

Forgot-password submissions are handled by:
```php
Xul\AuthKit\Http\Controllers\Api\PasswordReset\ForgotPasswordController
```
That controller delegates to:

```php
Xul\AuthKit\Actions\PasswordReset\RequestPasswordResetAction
```

### What the request action does

The request action:

- reads the normalized identity from the mapped payload
- resolves the active reset driver from authkit.password_reset.driver
- checks whether reset requests are allowed through the configured policy
- resolves the user through the configured user resolver
- supports privacy mode so account existence is not revealed
- creates a pending reset token
- builds a reset URL when the active driver is link
- dispatches a password reset event for delivery
- returns a standardized action result

### Privacy mode

Privacy behavior is controlled by:

```php
authkit.password_reset.privacy.hide_user_existence
authkit.password_reset.privacy.generic_message
```

When privacy protection is enabled:

- AuthKit returns the same public response whether or not a user exists
- reset tokens and events are only created when a real user exists
- the user-facing message remains intentionally generic

This helps reduce account enumeration risk.

## Password Reset Delivery

AuthKit does not deliver reset links or reset tokens directly from the action.
Instead, it dispatches:
```php
Xul\AuthKit\Events\AuthKitPasswordResetRequested
```
Delivery is controlled through:
```php
authkit.password_reset.delivery.use_listener
authkit.password_reset.delivery.listener
authkit.password_reset.delivery.notifier
authkit.password_reset.delivery.mode
authkit.password_reset.delivery.queue_connection
authkit.password_reset.delivery.queue
authkit.password_reset.delivery.delay
```

### Packaged listener

When the packaged listener is enabled, AuthKit uses:
```php
Xul\AuthKit\Listeners\SendPasswordResetNotification
```
That listener delegates delivery to the configured notifier implementing:
```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetNotifierContract
```

### Delivery modes

Supported modes:

- `sync`
- `queue`
- `after_response`

If queued delivery is enabled, AuthKit may use:
```php
Xul\AuthKit\Jobs\SendPasswordResetNotificationJob
```

### Packaged notifier

The default notifier is:
```php
Xul\AuthKit\Support\Notifiers\PasswordResetNotifier
```
It uses Laravel notifications and routes delivery to the provided email address.

### Packaged notifications include:

- `link` driver:  
```php
Xul\AuthKit\Notifications\AuthKitPasswordResetLinkNotification
```
- `token` driver:  
```php
Xul\AuthKit\Notifications\AuthKitPasswordResetTokenNotification
```
## Password Reset Pages
AuthKit supports two common reset experiences.
### Link driver page
For the `link` driver, the user typically opens a reset URL and lands on the reset form page. The page usually receives:
- `token`
- `email`
The actual token is not validated on the GET page. Validation and consumption happen only when the user submits the reset form.
### Token driver page
For the `token` driver, the user is taken to a token-entry page where they provide:
- `email`
- `token`
- `new password`
- `password confirmation`

Depending on your UI flow, token verification and password reset may happen together in one submission.

## Middleware Protection

Reset pages that require a pending reset context are protected by:
```php
Xul\AuthKit\Http\Middleware\EnsurePendingPasswordResetMiddleware
```
This middleware checks:
- that an email context exists in the query string
- that a pending password reset presence exists for that email

If no valid pending context exists, AuthKit redirects to:
```php
authkit.route_names.web.password_forgot
```
> Important: this middleware does not validate the reset token on the web route. Token validation happens only on submission to the API/action endpoint.

## Link Driver Reset Flow

The link-driver reset submission is handled by:
```php
Xul\AuthKit\Http\Controllers\Api\PasswordReset\ResetPasswordController
```
That controller delegates to:
```php
Xul\AuthKit\Actions\PasswordReset\ResetPasswordAction
```
### What the reset action does
```php
Xul\AuthKit\Actions\PasswordReset\ResetPasswordAction:
```
- reads the normalized mapped attributes
- checks whether reset is allowed through the configured policy
- consumes the reset token as a single-use credential
- resolves the target user through the configured resolver
- persists mapper-approved attributes when applicable
- updates the password through the configured password updater
- optionally logs the user in
- resolves the correct post-reset redirect

## Token Driver Reset Flow

Token-driver reset verification is handled by:
```php
Xul\AuthKit\Http\Controllers\Api\PasswordReset\VerifyPasswordResetTokenController
```
That controller delegates to:
```php
Xul\AuthKit\Actions\PasswordReset\VerifyPasswordResetTokenAction
```
### What the token action does
```php
Xul\AuthKit\Actions\PasswordReset\VerifyPasswordResetTokenAction:
```
- requires the active driver to be token
- checks that a pending reset context exists
- enforces the configured reset policy
- applies throttling for repeated token verification attempts
- validates and consumes the token
- resolves the target user
- persists mapper-approved attributes when applicable
- updates the password through the configured password updater
- optionally logs the user in
- returns the correct post-reset redirect

## Token Verification Throttling

For token-driver flows, AuthKit applies throttling through:
```php
authkit.password_reset.token.max_attempts
authkit.password_reset.token.decay_minutes
```
This protects short reset codes from brute-force attempts.
If too many verification attempts occur, AuthKit returns a throttled failure response instead of continuing verification.

## Validation

Password reset validation is handled by request classes for each context, such as:

- forgot password
- reset password
- reset token verification

AuthKit builds validation from:
```php
authkit.schemas.password_forgot
authkit.schemas.password_reset
authkit.schemas.password_reset_token
authkit.validation.providers.password_forgot
authkit.validation.providers.password_reset
authkit.validation.providers.password_reset_token
```

Because the system is schema-driven:

- form rendering and validation stay aligned
- custom validation behavior can be supplied through providers

Any custom validation provider must implement:

```php
Xul\AuthKit\Contracts\Validation\RulesProviderContract
```

## Payload Mapping

AuthKit maps password reset input through mapper contexts such as:

- password_forgot
- password_reset
- password_reset_token

These are configured under:
```php
authkit.mappers.contexts.password_forgot
authkit.mappers.contexts.password_reset
authkit.mappers.contexts.password_reset_token
```
Mapped payload building is handled through:
```php
Xul\AuthKit\Support\Mappers\MappedPayloadBuilder
```
By default, password reset fields are treated as non-persistable. However, the actions remain persistence-aware so that consumer-defined mappers can mark additional fields as persistable when needed.

Any custom mapper must implement:

```php
Xul\AuthKit\Contracts\Mappers\PayloadMapperContract
```

## User Resolution

AuthKit resolves the user involved in the reset flow through:
```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract
```
This allows the password reset system to support:
- non-standard identity fields
- multiple user sources
- tenant-aware applications
- custom authentication setups

Configuration:
```php
authkit.password_reset.user_resolver.strategy
authkit.password_reset.user_resolver.resolver_class
```

If you provide a custom resolver, it must implement:
```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract
```
## Policy Checks

AuthKit supports application-specific reset rules through:
```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract
```
This policy is used to determine:

- whether a reset may be requested
- whether a password may be reset

Configuration:
```php
authkit.password_reset.policy
```

Typical uses:

- block reset for suspended accounts
- require certain account state before allowing reset
- enforce internal security rules

Any custom policy must implement:

```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract
```

## Password Updates

Actual password persistence is delegated to:
```php
Xul\AuthKit\Contracts\PasswordReset\PasswordUpdaterContract
```
Configuration:
```php
authkit.password_reset.password_updater.class
authkit.password_reset.password_updater.refresh_remember_token
```
This exists so applications can customize:
- hashing strategy
- remember-token refresh
- audit trails
- password history logic
- additional security operations

Any custom updater must implement:
```php
Xul\AuthKit\Contracts\PasswordReset\PasswordUpdaterContract
```
## Reset URLs

For link-driver flows, reset URL generation is delegated to:
```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetUrlGeneratorContract
```

Configuration:
```php
authkit.password_reset.url_generator
```

This allows you to customize:

- route shape
- frontend domain
- signed parameter handling
- external reset frontends

Any custom URL generator must implement:
```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetUrlGeneratorContract
```

## Post-Request Redirects

What happens after a forgot-password request is controlled by:
```php
authkit.password_reset.post_request.mode
authkit.password_reset.post_request.sent_route
authkit.password_reset.post_request.token_route
```

Supported modes:

- `sent_page`
- `token_page`

### Behavior

When mode is `sent_page`:

- AuthKit redirects to a confirmation page such as “check your email”

When mode is `token_page`:

- AuthKit redirects directly to the token-entry page for token-driver flows

## Post-Reset Redirects

What happens after a successful password reset is controlled by:
```php
authkit.password_reset.post_reset.mode
authkit.password_reset.post_reset.redirect_route
authkit.password_reset.post_reset.login_route
authkit.password_reset.post_reset.success_route
authkit.password_reset.post_reset.login_after_reset
authkit.password_reset.post_reset.remember
```

Supported modes:

- `redirect`
- `success_page`

### Redirect resolution

For reset completion, AuthKit resolves redirects in this order based on configuration:

- configured redirect route when mode is redirect
- configured login fallback when needed
- success page route when mode is success_page
- dashboard-style redirect when automatic login after reset is enabled

## Automatic Login After Reset

AuthKit can optionally authenticate the user immediately after a successful reset.

Controlled by:

```php
authkit.password_reset.post_reset.login_after_reset
authkit.password_reset.post_reset.remember
authkit.auth.guard
```

When enabled:

- AuthKit logs the user into the configured guard
- dispatches:  
```php
Xul\AuthKit\Events\AuthKitLoggedIn
```

This behavior is available in both reset actions.

## Key Action Classes

The main packaged action classes are:

### Request reset

```php
Xul\AuthKit\Actions\PasswordReset\RequestPasswordResetAction
```

Starts the reset flow and dispatches delivery.

### Verify token and reset

```php
Xul\AuthKit\Actions\PasswordReset\VerifyPasswordResetTokenAction
```

Used for token-driver flows where token verification and password reset happen together.

### Reset password
```php
Xul\AuthKit\Actions\PasswordReset\ResetPasswordAction
```

Used for standard reset completion, especially in link-driver flows.

## Key Controllers

The main packaged controllers are:

- Forgot password  
````php
Xul\AuthKit\Http\Controllers\Api\PasswordReset\ForgotPasswordController
````

- Verify reset token  
```php
Xul\AuthKit\Http\Controllers\Api\PasswordReset\VerifyPasswordResetTokenController
```

- Reset password  
```php
Xul\AuthKit\Http\Controllers\Api\PasswordReset\ResetPasswordController
```

These controllers remain thin and delegate the real flow orchestration to the corresponding actions.

## Example Configuration

```php
'password_reset' => [
    'driver' => 'link',
    'ttl_minutes' => 30,

    'delivery' => [
        'use_listener' => true,
        'listener' => \Xul\AuthKit\Listeners\SendPasswordResetNotification::class,
        'notifier' => \Xul\AuthKit\Support\Notifiers\PasswordResetNotifier::class,
        'mode' => 'sync',
        'queue_connection' => null,
        'queue' => null,
        'delay' => 0,
    ],

    'url_generator' => \Xul\AuthKit\Support\PasswordReset\PasswordResetUrlGenerator::class,
    'policy' => \Xul\AuthKit\Support\PasswordReset\PermissivePasswordResetPolicy::class,

    'token' => [
        'max_attempts' => 5,
        'decay_minutes' => 1,
    ],

    'post_request' => [
        'mode' => 'sent_page',
        'sent_route' => 'authkit.web.password.forgot.sent',
        'token_route' => 'authkit.web.password.reset.token',
    ],

    'post_reset' => [
        'mode' => 'success_page',
        'redirect_route' => null,
        'login_route' => 'authkit.web.login',
        'success_route' => 'authkit.web.password.reset.success',
        'login_after_reset' => false,
        'remember' => true,
    ],

    'user_resolver' => [
        'strategy' => 'provider',
        'resolver_class' => null,
    ],

    'password_updater' => [
        'class' => null,
        'refresh_remember_token' => true,
    ],

    'privacy' => [
        'hide_user_existence' => true,
        'generic_message' => 'If an account exists for this email, password reset instructions have been sent.',
    ],
],
```

## Override Points

AuthKit allows targeted overrides for the password reset flow.

### Custom notifier

Must implement:

```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetNotifierContract
```

### Custom policy

Must implement:

```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract
```

### Custom URL generator

Must implement:

```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetUrlGeneratorContract
```

### Custom user resolver

Must implement:

```php
Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract
```

### Custom password updater

Must implement:

```php
Xul\AuthKit\Contracts\PasswordReset\PasswordUpdaterContract
```

### Custom validation providers

Must implement:

```php
Xul\AuthKit\Contracts\Validation\RulesProviderContract
```

### Custom payload mappers

Must implement:

```php
Xul\AuthKit\Contracts\Mappers\PayloadMapperContract
```

## Best Practices

- Keep privacy mode enabled in production unless you have a very specific reason not to.
- Use the `link` driver for conventional browser-based reset flows.
- Use the `token` driver for OTP-style or API-first reset experiences.
- Keep `authkit.password_reset.ttl_minutes` short enough for security but long enough for usability.
- Use a custom user resolver when your application does not resolve reset users through a standard provider lookup.
- Use a custom policy for business rules instead of hardcoding those checks into controllers or actions.
- Use a custom password updater when you need password history, custom hashing behavior, or security audit hooks.
- Prefer extending delivery through the notifier and listener system instead of modifying the reset actions directly.  