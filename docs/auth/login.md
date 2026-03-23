---
title: Login
outline: deep
editLink: true
---

## Overview

The login flow in AuthKit is responsible for authenticating users through the configured guard and then determining the correct next step in the authentication journey.

Unlike rigid authentication scaffolding, AuthKit does not treat login as a simple credential check followed by an immediate session login. Instead, the flow is aware of additional authentication requirements such as:

- email verification
- two-factor authentication
- remember-me behavior
- redirect resolution

The login flow is configuration-driven and built from:

- form schemas
- validation providers
- payload mappers
- action classes
- guard and provider resolution

This allows applications to keep the default login experience or customize it deeply without changing package internals.

At a high level, the login flow:

1. accepts login input from a standard form or AJAX request
2. validates the input using schema-aware rules
3. normalizes the validated data into the mapped login payload
4. resolves and validates the user credentials against the configured provider
5. determines whether login can complete immediately or must continue through another step
6. returns a structured action result that is converted into either a redirect or JSON response

Because the login flow is action-based and DTO-driven, the same internal flow supports both server-rendered applications and API-style interactions.

## Routes

AuthKit defines login routes through its configuration-driven routing system.

Like the rest of the package, login routes are split into two layers:

- web routes for rendering pages
- API routes for handling submitted actions

The relevant route names are configured through:

```php
authkit.route_names.web.login
authkit.route_names.api.login
```
By default, these are:
```php

'web' => [
    'login' => 'authkit.web.login',
],

'api' => [
    'login' => 'authkit.api.auth.login',
],
```
The web login route renders the login page and is backed by the controller configured at:
```php
authkit.controllers.web.login
```
By default:
```php
Xul\AuthKit\Http\Controllers\Web\Auth\LoginViewController
```
The API login route handles submitted login requests and is backed by the controller configured at:
```php
authkit.controllers.api.login
```
By default:
```php
Xul\AuthKit\Http\Controllers\Api\Auth\LoginController
```
All login routes are also affected by the shared route configuration:
```php
authkit.routes.prefix
authkit.routes.middleware
authkit.routes.groups.web
authkit.routes.groups.api
```
This means you can change:

- the URI prefix
- global middleware
- web-specific middleware
- API-specific middleware

without editing the package route definitions directly.

## Controller Flow

Login submissions are handled by the controller configured at:
```php
authkit.controllers.api.login
```
By default, this points to:
```php
Xul\AuthKit\Http\Controllers\Api\Auth\LoginController
```
The controller is intentionally thin and focuses only on HTTP orchestration.

Its responsibilities are:

- validating the incoming request through LoginRequest
- building the normalized mapped payload for the login context
- delegating the authentication flow to LoginAction
- persisting internal two-factor challenge state to session when required
- converting the standardized action result into JSON or redirect responses

The default flow inside the controller is:
1. receive the request 
2. validate it using LoginRequest 
3. build the mapped payload using:

```php
MappedPayloadBuilder::build('login', $request->validated())
```
4. pass the mapped payload to LoginAction 
5. persist internal session transport data when the flow requires two-factor 
6. return JSON when the request expects JSON 
7. otherwise return a redirect-based web response

The controller uses ResponseResolver::expectsJson($request) to decide whether to return JSON or a redirect response.
One important part of the login controller is its handling of internal two-factor transport state. When the login action returns a two_factor_required flow with an internal challenge value, the controller stores that challenge in session using AuthKit’s session key helper. This keeps internal transport data server-side and out of the public response payload.
This design keeps the controller small while ensuring that:
- validation stays in the request layer
- authentication logic stays in the action layer
- transport handling stays in the HTTP layer  

## Validation

Login validation is handled by:
```php
Xul\AuthKit\Http\Requests\Auth\LoginRequest
```
The request is schema-aware and configuration-driven. It builds its validation behavior from:
```php
authkit.schemas.login
authkit.validation.providers.login
authkit.identity.login.field
authkit.identity.login.normalize
Identity Normalization Before Validation
```

Before validation runs, LoginRequest normalizes the configured login identity field.

The field is resolved from: `authkit.identity.login.field`
The normalization strategy is resolved from: `authkit.identity.login.normalize`

### Custom Rules Provider

You may replace or extend the default login validation behavior through:
```php
authkit.validation.providers.login
```

**Example:**
```php
'validation' => [
    'providers' => [
        'login' => \App\Auth\LoginRulesProvider::class,
    ],
],
```

A custom login rules provider must implement:
```php
Xul\AuthKit\Contracts\Validation\RulesProviderContract
```

AuthKit resolves it through a `RulesProviderResolver` class.

If the configured class does not implement the contract, AuthKit ignores it and falls back to the default login rules, messages, and attributes.

## Form Schema

The login form is defined by:
```php
authkit.schemas.login
```

This schema is the canonical login form definition used by AuthKit.

By default, it includes:

- the identity field, which defaults to email
- password
- remember

The schema defines presentation metadata such as:

- labels
- field types
- placeholders
- autocomplete values
- wrapper classes
- submit button label

The identity field is closely related to:
```php
authkit.identity.login.field
authkit.identity.login.label
authkit.identity.login.input_type
authkit.identity.login.autocomplete
authkit.identity.login.normalize
```
These settings control how AuthKit interprets and presents the primary login identity.

Because the form schema is configuration-driven, you can:

- switch from email login to username login
- remove the remember checkbox
- change field labels and input types
- adjust presentation without rewriting the package form flow

The schema also affects validation behavior because LoginRequest derives its default rules from the resolved login schema.

## Payload Mapping

After validation, AuthKit transforms the validated input into a normalized payload using:

```php
MappedPayloadBuilder::build('login', $request->validated())
```
The mapper context is resolved from: 
```php
authkit.mappers.contexts.login
```
### Default Login Mapper

The default login mapper is:
```php
Xul\AuthKit\Support\Mappers\Auth\LoginPayloadMapper
```
By default, it maps:

- email → attributes.email with lower_trim
- password → attributes.password
- remember → options.remember with boolean

This gives the login action a normalized structure such as:

- attributes
- options
- meta when applicable

### Persistability

Default login fields are intentionally marked as non-persistable.

That means the login mapper does not treat standard login input as model persistence data.

This is important because login is primarily an authentication flow, not a profile update flow.

The login action still remains persistence-aware, but with the packaged mapper defaults, login does not persist any mapped fields.

### Custom Mapper

You may replace the login mapper through:
```php
authkit.mappers.contexts.login.class
```
**Example:**

```php
'mappers' => [
    'contexts' => [
        'login' => [
            'class' => \App\Auth\CustomLoginMapper::class,
            'schema' => 'login',
        ],
    ],
],
```
A custom login mapper must implement:
```php
Xul\AuthKit\Contracts\Mappers\PayloadMapperContract
```

This allows you to:

- change the identity field mapping
- adjust transform behavior
- merge with the package defaults
- fully replace the package defaults
- mark additional mapped values as persistable when your application needs that behavior

If the configured mapper does not implement the contract, AuthKit falls back to the default mapper behavior.

## Authentication Flow

The actual login flow is handled by:
```php
Xul\AuthKit\Actions\Auth\LoginAction
```

This action is responsible for authenticating the user and deciding what should happen next.

Its behavior is driven by configuration such as:
```php
authkit.auth.guard
authkit.identity.login.field
authkit.email_verification.enabled
authkit.two_factor.enabled
authkit.login.redirect_route
authkit.login.dashboard_route
``` 
## Guard Resolution

The action resolves the guard from:
```php
authkit.auth.guard
```
It then ensures that the resolved guard is a stateful guard.

If the configured guard is not stateful, the action returns a failure result because AuthKit’s login flow expects session-based authentication at this layer.

## Provider Resolution

After resolving the guard, the action retrieves its user provider.

If the provider is invalid, the action returns a failure result.

This keeps login aligned with Laravel’s configured authentication system rather than hardcoding a specific user model lookup.

## Credential Resolution

The login action reads the mapped payload and extracts:

- the configured identity field
- the password
- the remember option

The identity field key comes from:
```php
authkit.identity.login.field
```

If the identity or password is missing, AuthKit returns a structured failure result with validation-style errors.

## Credential Validation

The action authenticates by:

- retrieving the user through the configured provider using the identity field
- validating the submitted password against that user

If the user cannot be found or the password is invalid, AuthKit returns an Invalid credentials. failure result.

### Persistence Awareness

Even though the default login mapper marks login fields as non-persistable, the login action still calls its persistence-aware mapper support layer.

This is intentional.

It means that if a consumer provides a custom login mapper and marks certain mapped fields as persistable, the action can still honor that behavior without needing to be rewritten.

### Branching After Credential Success

Once the credentials are valid, AuthKit does not assume the user should be logged in immediately.

Instead, it evaluates whether:

- email verification is required
- two-factor authentication is required
- login can complete immediately

This makes login a full authentication flow rather than only a credential check.

### Successful Login Completion

If no additional step is required:

- the action logs the user into the configured guard
- it dispatches AuthKitLoggedIn
- it regenerates the session
- it resolves the redirect target through:  
```php
authkit.login.redirect_route  
 authkit.login.dashboard_route 
``` 

The action then returns a successful AuthKitActionResult with redirect and payload data.

This result is later converted by the controller into either a JSON response or a redirect response, depending on the request type.

## Email Verification During Login

AuthKit can block login completion when the user must verify their email address first.

This behavior is controlled by:

```php
authkit.email_verification.enabled
authkit.email_verification.columns.verified_at
authkit.email_verification.driver
authkit.email_verification.ttl_minutes
authkit.route_names.web.verify_notice
authkit.route_names.web.verify_link
```
During login, `LoginAction` checks whether email verification is enabled. If it is enabled, AuthKit determines whether the current user is verified.
Verification is resolved in one of two ways:

- if the user implements Laravel’s MustVerifyEmail contract, AuthKit uses hasVerifiedEmail()
- otherwise, AuthKit checks the configured verification column from: `authkit.email_verification.columns.verified_at`

If the user is not verified, AuthKit does not complete the session login.

Instead, it:
1. resolves the user email 
2. creates a pending verification token using the configured TTL 
3. resolves the verification driver from authkit.email_verification.driver 
4. creates a signed verification URL when the driver is link 
5. dispatches AuthKitEmailVerificationRequired 
6. returns a structured result that redirects the user to the verification notice page

The redirect target is resolved from: 
```php 
authkit.route_names.web.verify_notice 
```

In this state, login is intentionally interrupted until the verification requirement is satisfied.

## Two-Factor Requirement During Login

If email verification does not block the flow, AuthKit next determines whether the login attempt must complete two-factor authentication.

This behavior is controlled by:
```php
authkit.two_factor.enabled
authkit.two_factor.driver
authkit.two_factor.methods
authkit.two_factor.ttl_minutes
authkit.route_names.web.two_factor_challenge
```
`LoginAction` resolves the active two-factor driver through the TwoFactorManager and asks that driver whether two-factor is enabled for the current user.

If two-factor is required, AuthKit does not create the authenticated session immediately.

Instead, it:

1. resolves the active two-factor methods for the user 
2. falls back to authkit.two_factor.methods when needed 
3. creates a pending login challenge using the configured TTL 
4. dispatches AuthKitTwoFactorRequired 
5. returns a successful action result with:
    - a redirect to the two-factor challenge page
    - public payload data such as available methods
    - internal payload containing the pending challenge

The redirect target is resolved from:
```php
authkit.route_names.web.two_factor_challenge
```

The internal challenge is not exposed as public payload.  
The login controller stores it in session so the challenge flow can continue securely.

This keeps two-factor login state server-side while still allowing the controller to transport the next-step state correctly.

## Successful Login

If the submitted credentials are valid and no additional authentication step is required, AuthKit completes the login immediately.

In this case, `LoginAction`:

1. logs the user into the configured stateful guard 
2. applies the remember-me setting when requested 
3. dispatches `AuthKitLoggedIn`
4. regenerates the session 
5. resolves the post-login redirect 
6. returns a successful `AuthKitActionResult`

The remember-me value is derived from the mapped login options.  
The guard used for login comes from:
```php
authkit.auth.guard
```
The post-login redirect is resolved from:
```
authkit.login.redirect_route  
authkit.login.dashboard_route 
```
## Resolution order is:

- authkit.login.redirect_route when configured
- authkit.login.dashboard_route
- fallback to the login route if needed

This allows AuthKit to send users to a custom destination after login without rewriting the login action.

## Events

AuthKit dispatches several events during the login flow.

### `AuthKitLoggedIn`

This event is dispatched when AuthKit completes the login and the user is successfully signed into the configured guard.

It includes:

- the authenticated user
- the guard name
- the remember flag

Typical uses include:

- audit logging
- analytics
- security tracking
- onboarding hooks

### `AuthKitTwoFactorRequired`

This event is dispatched when credentials are valid, but the login attempt must continue through two-factor authentication before a session is established.

It includes:

- the user
- the guard name
- the pending challenge
- the available methods
- the remember flag

Typical uses include:

- audit logs
- security alerts
- analytics
- custom monitoring workflows

### `AuthKitEmailVerificationRequired`

This event is also part of the login flow when an unverified user attempts to sign in and verification is enforced.

It includes:

- the user
- the email
- the verification driver
- the TTL
- the token
- the signed URL when applicable

Typical uses include:

- delivery handling
- notification customization
- audit logging
- verification analytics

## Example Configurations

### **Username-Based Login**

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
With this configuration:

- AuthKit uses username as the primary login identity
- login validation resolves the identity field dynamically
- your login mapper should align with this identity structure

### **Lowercase Email Login**

```php
'identity' => [
    'login' => [
        'field' => 'email',
        'normalize' => 'lower',
    ],
],
```
With this configuration:

- AuthKit trims and lowercases the submitted email before validation
- identity matching stays consistent for email-based login

### **Custom Login Rules Provider**

```php
'validation' => [
    'providers' => [
        'login' => \App\Auth\LoginRulesProvider::class,
    ],
],
```

Your class must implement:
```php
Xul\AuthKit\Contracts\Validation\RulesProviderContract
```
Use this when login validation must go beyond AuthKit’s defaults.

### **Custom Login Mapper**

```php
'mappers' => [
    'contexts' => [
        'login' => [
            'class' => \App\Auth\CustomLoginMapper::class,
            'schema' => 'login',
        ],
    ],
],
```

Your class must implement:

```php
Xul\AuthKit\Contracts\Mappers\PayloadMapperContract
```

Use this when:

- your login identity is not the package default
- you need different field mapping
- you want to merge with or replace the default mapper behavior

### **Custom Post-Login Redirect**

```php
'login' => [
    'redirect_route' => 'dashboard.home',
    'dashboard_route' => 'authkit.web.dashboard',
],
```
With this configuration:

- AuthKit redirects to dashboard.home after successful login
- dashboard_route remains the fallback when redirect_route is null

### **Disable Email Verification Enforcement**

```php
'email_verification' => [
    'enabled' => false,
],
```

With this configuration:

- AuthKit does not block login for unverified users
- the email verification branch is skipped during login

### **Disable Two-Factor Requirement**

```php
'two_factor' => [
    'enabled' => false,
],
```
With this configuration:

- AuthKit does not require two-factor during login
- successful credential validation can complete the session login directly

## Best Practices

- Keep authkit.identity.login.field aligned with your login schema, mapper, and user provider expectations.
- Use authkit.identity.login.normalize deliberately so identity matching stays predictable.
- Use a custom rules provider when your login validation needs more than the packaged defaults.
- Use a custom mapper when you change the structure of the login identity or need custom transform behavior.
- Keep authkit.auth.guard pointed to a stateful guard for AuthKit’s session-based login flow.
- Make sure your configured post-login route values in authkit.login.redirect_route and authkit.login.dashboard_route are valid named routes.
- Keep email verification enabled in production when your application depends on verified accounts.
- Keep two-factor enabled for applications with higher security requirements.
- Prefer extending login through configuration, providers, mappers, and events instead of modifying package internals.  