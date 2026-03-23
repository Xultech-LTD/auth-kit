# Rate Limiting

AuthKit provides a **flexible, multi-bucket rate limiting system** built on top of Laravel’s RateLimiter.

It is designed to:

- protect authentication flows against abuse
- support multiple throttling strategies (IP, identity, challenge)
- remain fully configuration-driven
- allow complete override at every layer

## Overview

AuthKit rate limiting is based on three concepts:

1. **Limiter keys** (e.g. `login`, `password_reset`)
2. **Strategies** (how requests are throttled)
3. **Buckets** (what is being throttled: IP, identity, challenge)

All behavior is controlled via:

```php
authkit.rate_limiting.map
authkit.rate_limiting.strategy
authkit.rate_limiting.limits
authkit.rate_limiting.resolvers
```

## Core Design

AuthKit does not hardcode Laravel limiter names.

Instead, it uses:
```php
Xul\AuthKit\RateLimiting\AuthKitRateLimiterRegistrar
```

This class:

- reads limiter mappings from config
- registers Laravel rate limiters dynamically
- delegates limit construction to the builder

## Limiter Mapping

Limiter keys are mapped to Laravel limiter names:
```php
authkit.rate_limiting.map
```

**Example:**

```php
'map' => [
    'login' => 'authkit.auth.login',
    'password_reset' => 'authkit.password.reset',
    'password_reset_token' => 'authkit.password.reset.token',
],
```
**Behavior**
- key = logical AuthKit limiter key
- value = Laravel limiter name
- `null` disables the limiter

## Middleware Integration

AuthKit provides:
```php
Xul\AuthKit\RateLimiting\RateLimitMiddlewareFactory
```
This resolves middleware strings dynamically:
```php
$factory->middlewareFor('login');
// returns: throttle:authkit.auth.login
```

**Behavior**
- returns `null` if limiter is disabled or unmapped
- avoids invalid middleware declarations
- allows route definitions to remain clean and config-driven

## Limiter Builder

All limiter logic is handled by:
```php
Xul\AuthKit\RateLimiting\RateLimiterBuilder
```
This class:
- translates limiter keys into one or more Limit buckets
- applies the configured strategy
- ensures safe defaults
- guarantees at least one throttle bucket (per-IP)

## Throttling Strategies

This is configured via:
```php
authkit.rate_limiting.strategy
```

**Supported strategies:**

`dual (default)`

 which applies:

- per-IP bucket
- per-identity bucket (if available)

> This is the recommended default.

`per_ip

which applies:

- per-IP bucket only

> Used when identity is not meaningful.

`per_identity`

which applies:

- per-identity bucket only
- falls back to per-IP if identity is missing

> Useful for login/email-based flows.

`custom`

Delegates limit building to:
```php
Xul\AuthKit\RateLimiting\Contracts\CustomLimiterResolverContract
```
> If the custom resolver fails, AuthKit falls back to dual.

### Bucket Configuration

Defined via:
```php
authkit.rate_limiting.limits
```

**Example:**
```php
'limits' => [
    'login' => [
        'per_ip' => [
            'attempts' => 10,
            'decay_minutes' => 1,
        ],
        'per_identity' => [
            'attempts' => 5,
            'decay_minutes' => 1,
        ],
    ],
],
```

#### Bucket types
- `per_ip`
- `per_identity`

Each bucket defines:
- `attempts`
- `decay_minutes`

**Behavior** 
- invalid values are normalized
- minimum value is enforced (>= 1)
- missing config falls back to safe defaults

## Throttle Keys

Throttle keys are built using:
```php
Xul\AuthKit\RateLimiting\ThrottleKeyFactory
```
Format
```php
authkit|{limiter_key}|{bucket_type}|{bucket_value}
```

**Example:**

```php
authkit|login|ip|192.168.1.1
authkit|login|identity|user@example.com
```
#### Design goals
- prevent collisions with application throttles
- avoid empty segments
- ensure stable bucket isolation

## Bucket Resolvers

AuthKit resolves bucket values through dedicated resolvers.

#### IP Resolver
```php
Xul\AuthKit\RateLimiting\Contracts\IpResolverContract
```

**Default:**

```php
Xul\AuthKit\RateLimiting\DefaultIpResolver
```
- returns a non-empty IP string
- falls back to unknown if unavailable

#### Identity Resolver
```php
Xul\AuthKit\RateLimiting\Contracts\IdentityResolverContract
```

**Default:**
```php
Xul\AuthKit\RateLimiting\DefaultIdentityResolver
```
- reads identity from request input
- field is configured via: `authkit.identity.login.field`
- normalization via: `authkit.identity.login.normalize`

**Supported normalization:**

- `lower`
- `trim (implicit)`
- none

Returns `null` when identity is missing.

#### Challenge Resolver
```php
Xul\AuthKit\RateLimiting\Contracts\ChallengeResolverContract
```

**Default:**
```php
Xul\AuthKit\RateLimiting\DefaultChallengeResolver
```
- resolves a challenge identifier from request input
- used primarily for 2FA flows
- returns `null` when not applicable

### Challenge-Based Throttling

AuthKit supports optional per-challenge throttling - this is not applied automatically.

Instead, you may use:
```php
RateLimiterBuilder::resolveChallenge()
RateLimiterBuilder::challengeKey()
```

**Use case**
- two-factor authentication attempts
- OTP verification flows

**Example**
```php
$challenge = $builder->resolveChallenge($request);

if ($challenge) {
    $key = $builder->challengeKey('two_factor', $challenge);
}
```

## Custom Limiter

For full control, you can provide a custom limiter:

```php
authkit.rate_limiting.resolvers.limiter
```
Must implement:
```php
Xul\AuthKit\RateLimiting\Contracts\CustomLimiterResolverContract
```
**Example responsibility**
- build custom buckets
- apply dynamic logic per endpoint
- integrate external signals (e.g. risk scoring)

**Return types**
- single `Limit`
- array of `Limit`

> Invalid return values fall back to per-IP bucket.

## Rate Limiter Registration

All limiters are registered through:

```php
Xul\AuthKit\RateLimiting\AuthKitRateLimiterRegistrar
```

**Behavior**

- iterates over authkit.rate_limiting.map
- registers each limiter using:
```php
RateLimiter::for($name, fn ($request) => $builder->build($key, $request));
```
- skips invalid or disabled mappings

## Service Provider Integration

AuthKit wires rate limiting via:

```php
Xul\AuthKit\RateLimiting\RateLimitingServiceProviderMixin
```
**Responsibilities**
- registers:
  - key factory
  - resolvers
  - limiter builder
  - middleware factory
  - registrar
- resolves custom implementations from config
- falls back to defaults safely

**Usage**

Called internally from the package service provider:

```php
$this->registerAuthKitRateLimiting();
$this->bootAuthKitRateLimiting();
```

## Example Configuration
```php
'rate_limiting' => [
    'map' => [
        'login' => 'authkit.auth.login',
        'password_reset' => 'authkit.password.reset',
        'password_reset_token' => 'authkit.password.reset.token',
    ],

    'strategy' => [
        'login' => 'dual',
        'password_reset' => 'per_ip',
        'password_reset_token' => 'per_identity',
    ],

    'limits' => [
        'login' => [
            'per_ip' => [
                'attempts' => 10,
                'decay_minutes' => 1,
            ],
            'per_identity' => [
                'attempts' => 5,
                'decay_minutes' => 1,
            ],
        ],

        'password_reset_token' => [
            'per_ip' => [
                'attempts' => 5,
                'decay_minutes' => 1,
            ],
            'per_identity' => [
                'attempts' => 3,
                'decay_minutes' => 1,
            ],
        ],
    ],

    'resolvers' => [
        'ip' => null,
        'identity' => null,
        'challenge' => null,
        'limiter' => null,
    ],
],
```

## Override Points

AuthKit allows full customization of rate limiting behavior.

### Custom IP resolver

Must implement:
```php
Xul\AuthKit\RateLimiting\Contracts\IpResolverContract
```

### Custom identity resolver

Must implement:
```php
Xul\AuthKit\RateLimiting\Contracts\IdentityResolverContract
```

### Custom challenge resolver

Must implement:
```php
Xul\AuthKit\RateLimiting\Contracts\ChallengeResolverContract
```

### Custom limiter

Must implement:
```php
Xul\AuthKit\RateLimiting\Contracts\CustomLimiterResolverContract
```

## Best Practices

- Use `dual` strategy for authentication endpoints such as login.
- Use `per_identity` for flows where identity is the main attack vector (e.g. password reset tokens).
- Always keep a `per-IP` bucket as a fallback to avoid unthrottled endpoints.
- Keep decay windows short for sensitive flows (login, OTP, reset).
- Avoid overly aggressive limits that degrade user experience.
- Override resolvers when operating behind proxies or using non-standard identity fields.
- Use custom limiters only when the default strategies cannot express your requirements.
- Keep limiter mappings explicit and avoid leaving critical endpoints unmapped.  