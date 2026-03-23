# Two-Factor Authentication

AuthKit provides a **driver-based, challenge-driven two-factor authentication (2FA) system** designed to be flexible, extensible, and fully configuration-driven.

It supports:

- login-time two-factor challenges
- recovery code fallback
- optional resend flows (driver-dependent)
- pluggable drivers (TOTP, email, custom, etc.)


## Overview

Two-factor authentication in AuthKit operates as a **second-step authentication layer** after primary credential validation.

The flow is built around a **pending login challenge**, which temporarily stores the context required to complete authentication.

Core responsibilities:

- create a pending login challenge
- present a challenge UI
- verify a submitted code or recovery code
- establish an authenticated session


## Pending Login Challenge

AuthKit uses:

```php
Xul\AuthKit\Support\PendingLogin
```
### Responsibilities

- generate a short-lived challenge token
- store challenge payload (user + metadata)
- allow peek (non-destructive read)
- allow consume (single-use validation)
- allow explicit deletion

### Stored payload

A pending challenge contains:

- `user_id` → the user being authenticated
- `remember` → remember-me preference
- `methods` → allowed 2FA methods (e.g. totp)
- `created_at` → timestamp

### Key behavior

- tokens are short-lived
- tokens are single-use when consumed
- invalid or expired tokens terminate the flow

## Routes

Two-factor routes are resolved via:

```php
authkit.route_names.web.two_factor_challenge
authkit.route_names.web.login
authkit.route_names.api.two_factor_challenge
authkit.route_names.api.two_factor_recovery
authkit.route_names.api.two_factor_resend
```

These routes power:

- the challenge page
- challenge submission
- recovery submission
- resend requests

## Challenge Flow

### 1. Challenge creation

After successful primary authentication:

- AuthKit creates a pending challenge using:
```php
Xul\AuthKit\Support\PendingLogin
```
- The challenge token is stored (typically in session or request context)

### 2. Challenge page

The user is redirected to the two-factor page - the page:

- verifies the challenge exists (via peek)
- renders the configured form schema
- allows:
    - code submission
    - recovery code fallback
    - resend (if supported)  

### 3. Challenge completion

Handled by:

```php
Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorChallengeController
```
Delegates to:
```php
Xul\AuthKit\Actions\Auth\TwoFactorChallengeAction
```
#### Flow:

- validate request
- build mapped payload
- resolve challenge
- resolve user from provider
- verify code using active driver
- consume or invalidate challenge
- log user in
- dispatch events
- redirect

## Recovery Flow

Recovery codes provide a fallback when the primary method is unavailable.
Handled by:
```php
Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorRecoveryController
```
Delegates to:
```php
Xul\AuthKit\Actions\Auth\TwoFactorRecoveryAction
```
#### Flow:

- validate challenge + recovery code
- resolve pending challenge
- resolve user
- verify recovery code
- consume recovery code
- complete login
- clear challenge
- redirect

## Resend Flow (Optional)

Some drivers support resending a challenge (e.g. email/SMS).

Handled by:
```php
Xul\AuthKit\Http\Controllers\Api\Auth\TwoFactorResendController
```
Delegates to:
```php
Xul\AuthKit\Actions\Auth\TwoFactorResendAction
```
#### Requirements

The active driver must implement:
```php
Xul\AuthKit\Contracts\TwoFactorResendableContract
```
If not implemented:
- resend will fail gracefully
- no resend UI should be shown

### Driver System

AuthKit uses a pluggable driver system:
```php
Xul\AuthKit\Contracts\TwoFactorDriverContract
```
#### Responsibilities
Every driver must:
- declare its key
- determine if 2FA is enabled for a user
- declare supported methods
- verify authentication codes
- verify recovery codes
- consume recovery codes
- generate recovery codes  

#### Default Driver (TOTP)

AuthKit ships with:
```php
Xul\AuthKit\Support\TwoFactor\Drivers\TotpTwoFactorDriver
```
Features:

- RFC 6238 TOTP verification
- configurable digits, period, window
- Base32 secret support
- recovery code generation
- optional hashing of recovery codes

#### Secret-Based Drivers

Drivers that require secrets must implement:
```php
Xul\AuthKit\Contracts\TwoFactorSecretProviderContract
```
Used for:
- TOTP
- any shared-secret-based systems

#### Model Requirements

To enable two-factor on your user model, include:
```php
Xul\AuthKit\Concerns\Model\HasAuthKitTwoFactor
```

##### Responsibilities of the trait

- manage enabled state
- store and resolve secrets
- manage recovery codes
- support hashing of recovery codes
- expose methods for driver interaction

#### Required columns (configurable)
```php
authkit.two_factor.columns.enabled
authkit.two_factor.columns.secret
authkit.two_factor.columns.recovery_codes
authkit.two_factor.columns.methods
```

## Payload Mapping

AuthKit uses mappers to normalize incoming data.

### Challenge mapper
```php
Xul\AuthKit\Support\Mappers\Auth\TwoFactorChallengePayloadMapper
```
Maps:
- challenge → attributes.challenge
- code → attributes.code

### Recovery mapper
```php
Xul\AuthKit\Support\Mappers\Auth\TwoFactorRecoveryPayloadMapper
```
Maps:

- `challenge`
- `recovery_code`

### Resend mapper
```php
Xul\AuthKit\Support\Mappers\Auth\TwoFactorResendPayloadMapper
```
Maps:
- `email`
## Session Handling
``
AuthKit stores the active challenge in session using:
```php
Xul\AuthKit\Support\AuthKitSessionKeys::TWO_FACTOR_CHALLENGE
```
### Behavior

- set during login
- cleared on:
    - success
    - expiration
    - invalid attempts (depending on strategy)

## Challenge Strategy

Controlled via:
```php
authkit.two_factor.challenge_strategy
```
### Options

- `peek` → challenge remains valid until explicitly consumed
- `consume` → challenge is invalidated immediately on verification attempt

## Setup Utilities (Optional)

AuthKit provides helper classes for setup flows.

### Ensure secret exists
```php
Xul\AuthKit\Support\TwoFactor\EnsureTwoFactorSecretForUser
```
Ensures:
- user has a secret when required
- safe to call multiple times

### Generate OTP URI
```php
Xul\AuthKit\Support\TwoFactor\TwoFactorOtpUriFactory
```
Generates:
- otpauth://totp/...
Used for:
- QR codes
- manual setup
### Render QR code
```php
Xul\AuthKit\Support\TwoFactor\TwoFactorQrCodeRenderer
```
Outputs:
- inline SVG QR code
## Configuration

```php
'two_factor' => [
    'enabled' => true,

    'driver' => 'totp',

    'challenge_strategy' => 'peek',

    'columns' => [
        'enabled' => 'two_factor_enabled',
        'secret' => 'two_factor_secret',
        'recovery_codes' => 'two_factor_recovery_codes',
        'methods' => 'two_factor_methods',
    ],

    'totp' => [
        'digits' => 6,
        'period' => 30,
        'window' => 1,
        'algo' => 'sha1',
    ],

    'security' => [
        'encrypt_secret' => true,
        'hash_recovery_codes' => true,
        'recovery_hash_driver' => 'bcrypt',
    ],
],
```

## Events

AuthKit emits events during two-factor flows:

- login completed via 2FA:
```php
 Xul\AuthKit\Events\AuthKitTwoFactorLoggedIn
```
- login completed via recovery:  
```php
Xul\AuthKit\Events\AuthKitTwoFactorRecovered
```
- resend triggered:
```php
 Xul\AuthKit\Events\AuthKitTwoFactorResent
```
- standard login:  
```php
Xul\AuthKit\Events\AuthKitLoggedIn
```

## Customization

You can customize the system via:

### Drivers
```php
authkit.two_factor.driver
```
Must implement:
```php
Xul\AuthKit\Contracts\TwoFactorDriverContract
```
### Resend capability (optional)
```php
Xul\AuthKit\Contracts\TwoFactorResendableContract
```
### Secret providers (optional)
```php
Xul\AuthKit\Contracts\TwoFactorSecretProviderContract
```
### Mappers
```php
authkit.mappers.contexts.two_factor_challenge.class
authkit.mappers.contexts.two_factor_recovery.class
authkit.mappers.contexts.two_factor_resend.class
```

## Best Practices

- always keep challenges short-lived
- prefer `peek` strategy unless strict single-attempt flows are required
- hash recovery codes in production
- encrypt secrets when using secret-based drivers
- only expose resend UI when the driver supports it
- keep driver logic isolated and stateless where possible

## Summary

AuthKit’s two-factor system is:

- challenge-driven (PendingLogin-based)
- driver-powered (pluggable verification logic)
- mapper-driven (normalized payload handling)
- configurable (no hardcoded flows)

This design allows you to:

- swap drivers without changing controllers
- customize validation and payloads
- extend into email/SMS/biometric flows
- maintain consistent UX across authentication flows  