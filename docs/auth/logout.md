---
title: Logout
outline: deep
editLink: true
---

# Logout

AuthKit provides a dedicated logout flow for ending the current authenticated session through the configured guard.

Like the rest of AuthKit, logout is handled through a thin controller and a dedicated action class, with a standardized action result used for both web and JSON responses.

The guard used for logout is resolved from:

```php
authkit.auth.guard
```
## How It Works

Logout is handled by:

- LogoutController
- LogoutAction

The controller handles the HTTP response and session cleanup.

The action handles the actual logout logic.

## Controller

Default controller:
```php
Xul\AuthKit\Http\Controllers\Api\Auth\LogoutController
```

### Responsibilities:

- call LogoutAction
- invalidate the session after successful logout
- regenerate the CSRF token
- return JSON or redirect response

## Action

Default action:
```php
Xul\AuthKit\Actions\Auth\LogoutAction
```
### Responsibilities:

- resolve the configured guard
- ensure the guard is stateful
- get the current authenticated user
- fail if no user is authenticated
- log the user out
- dispatch AuthKitLoggedOut

After success, AuthKit redirects to the login route from:
```php
authkit.route_names.web.login
```
## Route

Logout uses the API/action route name:
```php
authkit.route_names.api.logout
```
Default value:
```php
authkit.api.auth.logout
```
## Event

After a successful logout, AuthKit dispatches:
```php
AuthKitLoggedOut
```
This can be used for:

- audit logs
- analytics
- custom security handling

## Best Practice

- Always keep logout behind authentication middleware, and always invalidate the session after logout.  