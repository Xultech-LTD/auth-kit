# Password Confirmation

AuthKit provides a **password confirmation flow** for protecting sensitive authenticated pages and actions.

This is part of the step-up confirmation system controlled by:

```php
authkit.confirmations.enabled
authkit.confirmations.password.enabled
authkit.confirmations.session
authkit.confirmations.ttl_minutes.password
authkit.confirmations.routes
```

Password confirmation is not a password reset flow and not a password update flow.

It is used when an already-authenticated user must re-enter their current password before continuing.

## Overview

AuthKit’s password confirmation flow works in two parts:

- RequirePasswordConfirmationMiddleware protects sensitive routes
- ConfirmPasswordController and ConfirmPasswordAction handle the confirmation submission

At a high level:

- user tries to access a protected page or action
- middleware checks whether a fresh password confirmation exists in session
- if confirmation is stale or missing, AuthKit stores the intended destination and redirects to the confirmation page
- user submits their current password
- AuthKit verifies it against the authenticated user’s stored password hash
- AuthKit stores a fresh confirmation timestamp in session
- user is redirected back to the intended destination or fallback route

## Middleware Protection

Password confirmation enforcement is handled by:

```php
Xul\AuthKit\Http\Middleware\RequirePasswordConfirmationMiddleware
```
This middleware checks:

```php
authkit.confirmations.enabled
authkit.confirmations.password.enabled
authkit.auth.guard
authkit.confirmations.session.password_key
authkit.confirmations.ttl_minutes.password
authkit.confirmations.session.intended_key
authkit.confirmations.session.type_key
authkit.confirmations.routes.password
```
### What the middleware does

When a protected request is made, AuthKit:

- checks whether the confirmation system is enabled
- checks whether password confirmation is enabled
- resolves the authenticated user from authkit.auth.guard
- reads the confirmation timestamp from the configured session key
- compares it against the configured TTL
- allows the request to continue if confirmation is still fresh

If confirmation is missing or expired, AuthKit:

- stores the current full URL in the configured intended session key
- stores the confirmation type as password
- redirects to the configured password confirmation route

### Session freshness

Freshness is determined from:
```php
authkit.confirmations.session.password_key
authkit.confirmations.ttl_minutes.password
```

By default, the middleware expects a timestamp in session and considers it valid while it is still within the configured TTL window.

## Controller Flow

Password confirmation submissions are handled by:
```php
authkit.controllers.api.confirm_password
```
By default:
```php
Xul\AuthKit\Http\Controllers\Api\App\Confirmations\ConfirmPasswordController
```
The controller is intentionally thin.

Its responsibilities are:

- validate the request through ConfirmPasswordRequest
- build the normalized payload for the confirm_password context
- resolve the authenticated user using authkit.auth.guard
- delegate verification to ConfirmPasswordAction
- return JSON or redirect responses depending on the request type

The controller uses:

```php
MappedPayloadBuilder::build('confirm_password', $request->validated())
```

and then passes the result into the action together with the session store.

## Validation

Validation is handled by:
```php
Xul\AuthKit\Http\Requests\App\Confirmations\ConfirmPasswordRequest
```
It is driven by:

```php
authkit.schemas.confirm_password
authkit.validation.providers.confirm_password
```

### Default validation

By default, if the password field exists in the schema, AuthKit applies:

- password → required|string

The request only validates input shape.  
It does not verify whether the password is correct.

That check happens inside ConfirmPasswordAction.

### Custom rules provider

You may override the default validation behavior through:
```php
authkit.validation.providers.confirm_password
```
Any custom provider must implement:
```php
Xul\AuthKit\Contracts\Validation\RulesProviderContract
```
## Form Schema

The password confirmation form is defined by:
```php
authkit.schemas.confirm_password
```
By default, the schema contains:

- password

This schema controls:

- the visible field structure
- labels
- input type
- autocomplete
- wrapper metadata
- submit button label

Because the flow is schema-driven, UI and validation remain aligned when you customize the form.

## Payload Mapping

After validation, AuthKit maps the submitted confirmation data using the confirm_password mapper context.

This is configured through:
```php
authkit.mappers.contexts.confirm_password
```
The default mapper is:
```php
Xul\AuthKit\Support\Mappers\App\Confirmations\ConfirmPasswordPayloadMapper
```

### Default mapping behavior:

- password → attributes.password
- transform → trim
- persist → false

This means the submitted password is normalized into the mapped payload, but it is not persisted by default.

### Custom mapper

You may override the mapper through:
```php
authkit.mappers.contexts.confirm_password.class
```
Any custom mapper must implement:
```php
Xul\AuthKit\Contracts\Mappers\PayloadMapperContract
```
## Action Flow

The actual password verification logic is handled by:
```php
Xul\AuthKit\Actions\App\Confirmations\ConfirmPasswordAction
```
Its responsibilities are:

- ensure an authenticated user object is present
- read the password from the mapped attributes bucket
- ensure the user model supports password retrieval
- verify the submitted password against the stored hash
- persist mapper-approved attributes when applicable
- store a fresh confirmation timestamp in session on success
- clear temporary confirmation navigation metadata
- resolve the correct post-confirmation redirect

### Password verification

The action checks:

- that a user object exists
- that the password value is present
- that the user exposes getAuthPassword()
- that the submitted password matches the stored hash

AuthKit uses Laravel’s hash checking to verify the password.

If the password is incorrect, it returns a structured failure result and redirects back to the confirmation page.

### Success Persistence and Redirects

On successful confirmation, the action writes a fresh timestamp into session using:
```php
authkit.confirmations.session.password_key
```
It also clears:
```php
authkit.confirmations.session.intended_key  
authkit.confirmations.session.type_key  
```
### Redirect resolution

After success, redirect resolution follows this order:

1. stored intended URL from authkit.confirmations.session.intended_key
2. fallback route from: `authkit.confirmations.routes.fallback`

If confirmation fails, AuthKit redirects to the configured password confirmation page from:

```php
authkit.route_names.web.confirm_password
```
## Key Configuration

The password confirmation flow depends mainly on these config entries:
```php
authkit.confirmations.enabled
authkit.confirmations.password.enabled
authkit.confirmations.session.password_key
authkit.confirmations.session.intended_key
authkit.confirmations.session.type_key
authkit.confirmations.ttl_minutes.password
authkit.confirmations.routes.password
authkit.confirmations.routes.fallback
authkit.auth.guard
authkit.schemas.confirm_password
authkit.validation.providers.confirm_password
authkit.mappers.contexts.confirm_password
authkit.route_names.web.confirm_password
``` 

## Best Practices

- Apply `RequirePasswordConfirmationMiddleware` only to sensitive authenticated pages or actions.
- Do not apply the password confirmation middleware to the confirmation page itself.
- Keep `authkit.confirmations.ttl_minutes.password` short enough for security, but long enough to avoid unnecessary friction.
- Use `authkit.confirmations.routes.fallback` as a safe post-confirmation destination when no intended URL exists.
- Use a custom rules provider or mapper only when your application needs behavior beyond AuthKit’s defaults.  