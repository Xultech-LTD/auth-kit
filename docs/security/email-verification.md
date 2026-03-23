# Email Verification

AuthKit provides a **configurable, driver-based email verification system** that supports both:

- signed link verification
- token/code verification

The flow is controlled through configuration and can be extended or replaced without modifying package internals.

## Overview

Email verification in AuthKit has three main phases:

1. **Verification initiation** during registration, login, or resend
2. **Verification delivery** through a signed link or token
3. **Verification completion** when the user verifies the email address

The system is driven by:

```php
authkit.email_verification.enabled
authkit.email_verification.driver
authkit.email_verification.ttl_minutes
authkit.email_verification.columns
authkit.email_verification.post_verify
authkit.email_verification.delivery
```
Supported drivers:
- link
- token
  
## Routes

AuthKit resolves email verification routes from configuration:
```php
authkit.route_names.web.verify_notice
authkit.route_names.web.verify_token_page
authkit.route_names.web.verify_link
authkit.route_names.web.verify_success
authkit.route_names.web.login
authkit.route_names.api.send_verification
authkit.route_names.api.verify_token
```
These routes are used for:

- the verification notice page 
- the token entry page 
- signed link verification 
- the success page 
- resend actions 
- token verification actions

## Verification Flow
### 1. Triggering verification

Verification is typically triggered when:

- a user registers 
- a user logs in but is not verified 
- a resend request is made

AuthKit creates a pending verification context through:
```php
Xul\AuthKit\Support\PendingEmailVerification
```
That context stores the pending verification token and related metadata such as the user ID and active driver.
### 2. Delivery

AuthKit does not send verification emails directly from its action classes.

Instead, it dispatches:

```php
Xul\AuthKit\Events\AuthKitEmailVerificationRequired
```

Delivery behavior is controlled by:

```php
authkit.email_verification.delivery.use_listener
authkit.email_verification.delivery.listener
authkit.email_verification.delivery.notifier
authkit.email_verification.delivery.mode
authkit.email_verification.delivery.queue_connection
authkit.email_verification.delivery.queue
authkit.email_verification.delivery.delay
```

When the packaged listener is enabled, AuthKit uses:

```php
Xul\AuthKit\Listeners\SendEmailVerificationNotification
```

That listener delegates delivery to the configured notifier implementing:

```php
Xul\AuthKit\Contracts\EmailVerificationNotifierContract
```

Supported delivery modes:
- `sync`
- `queue` 
- `after_response`

### 3. Verification completion

Depending on the configured driver:

`Link driver`

The user clicks a signed verification URL handled by:

```php
Xul\AuthKit\Http\Controllers\Web\EmailVerification\VerifyEmailLinkController
```

That controller delegates verification to:

```php
Xul\AuthKit\Actions\EmailVerification\VerifyEmailLinkAction
```

`Token driver`

The user submits an email address and verification token handled by:

```php
Xul\AuthKit\Http\Controllers\Api\EmailVerification\VerifyEmailTokenController
```

That controller delegates verification to:

```php
Xul\AuthKit\Actions\EmailVerification\VerifyEmailTokenAction
```
## Middleware Protection

Pending verification pages are protected by:

```php
Xul\AuthKit\Http\Middleware\EnsurePendingEmailVerificationMiddleware
```

This middleware validates that the current request still has a valid pending verification context.

For the link driver, it can validate:

- route `id`
- route `hash`

For the token driver, it requires:

- a valid email context
- a pending verification presence for that email

If no valid pending context exists, AuthKit redirects to the route configured at:

```php
authkit.route_names.web.login
```

## Validation

Validation is handled by these request classes:

- `resend verification`:
```php
Xul\AuthKit\Http\Requests\EmailVerification\SendEmailVerificationRequest
```
- `token verification`:
```php
Xul\AuthKit\Http\Requests\EmailVerification\EmailVerificationTokenRequest
```
These requests are driven by:

```php
authkit.schemas.email_verification_send
authkit.schemas.email_verification_token
authkit.validation.providers.email_verification_send
authkit.validation.providers.email_verification_token
```

#### Default rules

For resend verification:

- `email` → `required|string|email`

For token verification:

- `email` → `required|string|email`
- `token` → `required|string`
#### Custom validation providers

You may override default validation through:
```php
authkit.validation.providers.email_verification_send
authkit.validation.providers.email_verification_token
```

Any custom provider must implement:
```php
Xul\AuthKit\Contracts\Validation\RulesProviderContract
```

AuthKit resolves these providers through:
```php
Xul\AuthKit\Support\Resolvers\RulesProviderResolver
```
## Form Schema

The UI structure for email verification is defined by:

```php
authkit.schemas.email_verification_send
authkit.schemas.email_verification_token
```

These schemas control:

- fields
- labels
- input types
- placeholders
- wrapper metadata
- submit labels

Because the flow is schema-driven, rendering and validation remain aligned when you customize the form.

The related page controllers are:

- verification notice page:
```php
Xul\AuthKit\Http\Controllers\Web\EmailVerification\EmailVerificationNoticeViewController
```
- token verification page:
```php
Xul\AuthKit\Http\Controllers\Web\EmailVerification\EmailVerificationTokenViewController
```
- verification success page:
```php
Xul\AuthKit\Http\Controllers\Web\EmailVerification\EmailVerificationSuccessViewController
```
## Payload Mapping

AuthKit maps email verification input through two mapper contexts:

- `email_verification_send`
- `email_verification_token`

These are configured under:

```php
authkit.mappers.contexts.email_verification_send
authkit.mappers.contexts.email_verification_token
```
### Default resend mapper

The default resend mapper is:

```php
Xul\AuthKit\Support\Mappers\EmailVerification\SendEmailVerificationPayloadMapper
```

#### Default behavior:

- `emai`l → `attributes.email`
- `transform` → `lower_trim`
- `persist` → `false`

### Default token mapper

The default token verification mapper is:
```php
Xul\AuthKit\Support\Mappers\EmailVerification\VerifyEmailTokenPayloadMapper
```

#### Default behavior:

- `email` → `attributes.email`
- `transform` → lower_trim`
- `persist` → false`
- `token` → attributes.token`
- `transform` → trim`
- `persist` → false`

### Custom mappers

You may override either mapper through:
```php
authkit.mappers.contexts.email_verification_send.class
authkit.mappers.contexts.email_verification_token.class
```

Any custom mapper must implement:

```php
Xul\AuthKit\Contracts\Mappers\PayloadMapperContract
```

Mapped payload building is handled through:
```php
Xul\AuthKit\Support\Mappers\MappedPayloadBuilder
```

## Resend Verification

Resend requests are handled by:

```php
Xul\AuthKit\Http\Controllers\Api\EmailVerification\SendEmailVerificationController
```

That controller delegates to:
```php
Xul\AuthKit\Actions\EmailVerification\SendEmailVerificationAction
```

The resend flow is:

- validate the request 
- build the mapped payload 
- resolve the user by email using the configured guard provider 
- ensure the email matches the authenticated user context when applicable 
- skip sending if the email is already verified 
- create a new pending verification token 
- dispatch `Xul\AuthKit\Events\AuthKitEmailVerificationRequired`
- redirect to the verification notice route

## Link Verification Flow

Signed-link verification is handled by:
```php
Xul\AuthKit\Http\Controllers\Web\EmailVerification\VerifyEmailLinkController
Xul\AuthKit\Actions\EmailVerification\VerifyEmailLinkAction
```

The flow is:

- extract the route id and hash 
- resolve the user through the configured provider 
- validate the pending link context 
- consume the token from the pending verification store 
- mark the user as verified 
- dispatch Laravel’s `Illuminate\Auth\Events\Verified` event when applicable 
- dispatch: `Xul\AuthKit\Events\AuthKitEmailVerified`
- optionally log the user in 
- redirect according to post-verification configuration

## Token Verification Flow

Token verification is handled by:
```php

Xul\AuthKit\Http\Controllers\Api\EmailVerification\VerifyEmailTokenController
Xul\AuthKit\Actions\EmailVerification\VerifyEmailTokenAction
```

The flow is:

- validate the submitted email and token 
- consume the token 
- resolve the user from the token payload 
- ensure the submitted email matches the user email 
- mark the user as verified 
- dispatch `Illuminate\Auth\Events\Verified` when applicable 
- dispatch `Xul\AuthKit\Events\AuthKitEmailVerified `
- optionally log the user in 
- redirect according to post-verification configuration

## Marking a User as Verified

Both verification actions support two common verification styles.

If the user model supports:
```php
markEmailAsVerified()
```

that method is used.

Otherwise, AuthKit writes to the configured verification column from:

```php
authkit.email_verification.columns.verified_at
```

and saves the model when supported.

This means your user model can work with Laravel’s `Illuminate\Contracts\Auth\MustVerifyEmail` pattern or with a simple timestamp column.

## Post Verification Behavior

What happens after successful verification is controlled by:
```php

authkit.email_verification.post_verify.mode
authkit.email_verification.post_verify.redirect_route
authkit.email_verification.post_verify.login_route
authkit.email_verification.post_verify.success_route
authkit.email_verification.post_verify.login_after_verify
authkit.email_verification.post_verify.remember
```

**Supported modes**
- `redirect`
- `success_page`

**Redirect resolution**

AuthKit resolves the post-verification destination in this order:

1. success page when `mode = success_page`
2. configured redirect route
3. login/dashboard redirect when `login_after_verify = true`
4. login route fallback

If automatic login after verification is enabled, AuthKit authenticates the user through the configured guard and dispatches:

```php
Xul\AuthKit\Events\AuthKitLoggedIn
```

## Notifications and Delivery Classes

AuthKit includes packaged notifications for the default mail flow:

- `token notification`:
```php
Xul\AuthKit\Notifications\AuthKitVerifyEmailTokenNotification
```
- `signed link notification`:
```php
Xul\AuthKit\Notifications\AuthKitVerifyEmailLinkNotification
```

When delivery mode is queued, AuthKit can dispatch:

````php
Xul\AuthKit\Jobs\SendEmailVerificationNotificationJob
````

That job also delegates to the configured notifier implementing:

```php
Xul\AuthKit\Contracts\EmailVerificationNotifierContract
```

## Events

AuthKit exposes two package-level events for email verification:

### Verification required
```php
Xul\AuthKit\Events\AuthKitEmailVerificationRequired
```
This is dispatched when AuthKit starts a verification flow and needs delivery to occur.

Typical uses:

- sending emails 
- analytics 
- audit logs 
- custom notification pipelines

### Verification completed
```php
Xul\AuthKit\Events\AuthKitEmailVerified
```

This is dispatched after a user has been successfully verified.

Typical uses:

- onboarding 
- post-verification workflows 
- analytics 
- audit logs

**Example Configuration**
```php
'email_verification' => [
    'enabled' => true,
    'driver' => 'link',
    'ttl_minutes' => 30,

    'columns' => [
        'verified_at' => 'email_verified_at',
    ],

    'delivery' => [
        'use_listener' => true,
        'listener' => \Xul\AuthKit\Listeners\SendEmailVerificationNotification::class,
        'notifier' => \Xul\AuthKit\Support\Notifiers\EmailVerificationNotifier::class,
        'mode' => 'sync',
        'queue_connection' => null,
        'queue' => null,
        'delay' => 0,
    ],

    'post_verify' => [
        'mode' => 'redirect',
        'redirect_route' => 'authkit.web.dashboard',
        'login_route' => 'authkit.web.login',
        'success_route' => 'authkit.web.email.verify.success',
        'login_after_verify' => false,
        'remember' => true,
    ],
],
```

## Overrides and Customization

You can customize the email verification flow through configuration without replacing the whole system.

Common override points include:

- `validation providers`:
```php
authkit.validation.providers.email_verification_send
authkit.validation.providers.email_verification_token
```
- `payload mappers`:

```php
authkit.mappers.contexts.email_verification_send.class
authkit.mappers.contexts.email_verification_token.class
```
- `delivery listener`:
```php
authkit.email_verification.delivery.listener
```
- `notifier`:
```php
authkit.email_verification.delivery.notifier
```
- `page and action controllers`:

```php
authkit.controllers.web.email_verify_notice
authkit.controllers.web.email_verify_token_page
authkit.controllers.web.email_verify_success
authkit.controllers.web.email_verify_link
authkit.controllers.api.email_send_verification
authkit.controllers.api.email_verify_token
```

> Any custom mapper must implement: `Xul\AuthKit\Contracts\Mappers\PayloadMapperContract`

> Any custom rules provider must implement: `Xul\AuthKit\Contracts\Validation\RulesProviderContract`

> Any custom notifier must implement: `Xul\AuthKit\Contracts\EmailVerificationNotifierContract`

## Best Practices
1. Use the `link` driver when you want the simplest browser-based verification experience.
2. Use the `token` driver when you want OTP-style or API-friendly verification. 
3. Keep: `authkit.email_verification.ttl_minutes` short enough for security. 
4. Keep the verification column aligned with: `authkit.email_verification.columns.verified_at`
5. Prefer customization through:
   - config 
   - custom mappers 
   - custom validation providers 
   - custom notifier implementations 
   - event listeners
   
instead of modifying AuthKit internals directly.