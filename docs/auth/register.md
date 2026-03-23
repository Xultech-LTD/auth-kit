---
title: Registration
outline: deep
---

## Overview

The registration flow in AuthKit is responsible for creating new user accounts in a **fully configuration-driven and extensible way**.

Unlike traditional authentication scaffolding, AuthKit does not hardcode how registration behaves. Instead, it composes the flow dynamically using:

- form schemas
- validation providers
- payload mappers
- action classes
- configuration

This allows you to customize registration behavior without modifying package internals.


### What Happens During Registration

At a high level, the registration flow:

1. accepts user input from a form (HTTP or AJAX)
2. validates the input using schema-aware rules
3. transforms the validated input into a normalized payload
4. creates a new user using the configured authentication provider
5. dispatches lifecycle events
6. optionally starts the email verification process
7. returns a structured response (redirect or JSON)


### Key Characteristics

AuthKit’s registration flow is designed with the following principles:

- **Schema-driven**  
  The structure of the registration form comes from configuration (`authkit.schemas.register`), not hardcoded views.

- **Validation-aware**  
  Validation rules are automatically derived from the schema and can be overridden via providers.

- **Mapper-controlled persistence**  
  Only fields explicitly marked as persistable are saved to the database.

- **Action-based execution**  
  All registration logic is handled by a dedicated action class (`RegisterAction`).

- **Event-driven side effects**  
  Email verification and other behaviors are triggered through events, not tightly coupled logic.

- **Response abstraction**  
  The same flow supports both:
    - standard web requests (redirects + flash messages)
    - API/AJAX requests (structured JSON responses)

### Email Verification Integration

If an email address is present, AuthKit automatically initiates the email verification flow after successful registration.

This behavior is controlled entirely through configuration and can use:

- signed link verification
- token/code-based verification

### Extensibility

Every part of the registration flow can be customized:

- change fields via schema
- override validation rules
- define custom payload mappers
- replace the action or controller
- hook into events

This makes AuthKit suitable for simple apps as well as complex, production-grade systems.

In the next section, we will walk through the **default registration flow step by step**.

## Default Registration Flow

AuthKit’s registration flow is not a fixed sequence of hardcoded steps.  
It is a **composed pipeline** built from configuration, contracts, and resolvers.

Each stage in the flow can be **extended, replaced, or reshaped** without modifying package internals.


### Step-by-Step Flow

#### 1. User submits the registration form

The user submits the registration form via:

- standard HTTP form submission
- or AJAX (when enabled via `authkit.forms.mode`)

The form structure is defined entirely by:

```php
authkit.schemas.register
```
This means:

- fields can be added or removed from config
- validation and mapping automatically adapt to the schema

#### 2. Request validation (RegisterRequest)

The request is validated using the RegisterRequest.

Validation is built from:

- the resolved schema (authkit.schemas.register)
- default rules derived from the schema
- optional override via: `authkit.validation.providers.register`

##### Default validation behavior

- Only fields present in the schema are validated

##### Common defaults include:

- name → required, string
- email → required, email
- password → required, secure defaults
- password_confirmation → must match password

##### Identity uniqueness enforcement

AuthKit conditionally applies a unique rule based on:

- `authkit.registration.enforce_unique_identity`
- `authkit.registration.unique_identity.table`
- `authkit.registration.unique_identity.column  `

**Resolution order:**

- configured table/column
- auth provider model table + identity field

##### Custom validation provider

You may fully override validation by setting:

```php
'validation' => [
    'providers' => [
        'register' => \App\Auth\RegisterRulesProvider::class,
    ],
],
```
Your provider must implement:
```php
Xul\AuthKit\Contracts\Validation\RulesProviderContract
```
This allows you to:

- replace all rules
- extend default rules
- customize messages and attributes

AuthKit resolves the provider through:
```php
RulesProviderResolver::resolvePayload(...)
```
If no provider is configured, defaults are used.

#### 3. Payload mapping (MappedPayloadBuilder)

After validation, input is transformed into a normalized payload:

```php
MappedPayloadBuilder::build('register', $request->validated());
```
The payload is structured into:

- attributes → persistable data
- options → behavioral flags
- meta → contextual data

##### Default mapper

AuthKit uses a default mapper for registration, configured via:
```php
authkit.mappers.contexts.register
```
This typically resolves to:

```php
Xul\AuthKit\Support\Mappers\Auth\RegisterPayloadMapper
```
###### Default mapping behavior:

- name → attributes.name (trimmed)
- email → attributes.email (lower_trim)
- password → attributes.password (hashed)
- password_confirmation → excluded

##### Custom mapper support

You can override the mapper:

```php
'mappers' => [
    'contexts' => [
        'register' => [
            'class' => \App\Auth\CustomRegisterMapper::class,
            'schema' => 'register',
        ],
    ],
],
```
Your mapper must implement:
```php
Xul\AuthKit\Contracts\Mappers\PayloadMapperContract
```
##### Mapper capabilities

Through the contract, you can:

- define how fields are mapped
- control persistence (persist)
- transform values (transform, hash, encrypt)
- remove default mappings (remove())
- choose behavior mode:  
```php
PayloadMapperContract::MODE_MERGE  
PayloadMapperContract::MODE_REPLACE
```
This means you can:

- extend default mapping (merge)  
- completely redefine it (replace)  

#### 4. Action execution (RegisterAction)

The normalized payload is passed to:

```php
$result = $action->handle($payload);
```

The action is the source of truth for:

- business logic
- user creation
- verification flow
- response structure

#### 5. User creation

Inside the action:

- the auth provider is resolved from: `authkit.auth.guard`
- a model instance is created via the provider
- only persistable mapped attributes are written
- the model is saved

This ensures:

- no unintended fields are persisted
- persistence is controlled entirely by the mapper

#### 6. Registration event dispatched

After successful creation:

```php
event(new AuthKitRegistered($user));
```
This allows external systems to hook into:

- onboarding
- analytics
- integrations

#### 7. Email verification initialization (optional)

If an email exists:

- a verification token is created
- driver is resolved from: `authkit.email_verification.driver`
- verification event is dispatched:
```php
AuthKitEmailVerificationRequired
```
Depending on configuration, this may:

- send a signed link
- send a token/code

#### 8. Response generation

The action returns an AuthKitActionResult.

The controller converts this into:

- Web response
- redirect (typically to verify notice)
- flash message
- JSON response
- structured payload
- HTTP status

Response format is consistent across flows.

### Flow Summary

Form  
→ RegisterRequest (validation)  
→ RulesProvider (optional override)  
→ MappedPayloadBuilder  
→ PayloadMapper (default or custom)  
→ RegisterAction  
→ User Creation  
→ Events  
→ Email Verification  
→ Response (redirect or JSON)

### Why This Flow Matters

This architecture ensures:

- full control via config
- clean separation of concerns
- safe persistence through mappers
- replaceable validation and mapping layers
- event-driven extensibility  

## Routes

AuthKit defines registration routes as part of its **configuration-driven routing system**.

Routes are split into two layers:

- **Web routes** → render pages (GET)
- **API routes** → handle actions (POST)

This separation ensures clean architecture and flexibility across SSR and AJAX flows.

### Route Names

AuthKit does not rely on hardcoded URLs.  
Instead, it uses **named routes defined in configuration**:

```php
authkit.route_names.web.register
authkit.route_names.api.register
```
#### Default values:

```php
'web' => [
    'register' => 'authkit.web.register',
],

'api' => [
    'register' => 'authkit.api.auth.register',
],
```
You can override these names to match your application conventions.

##### Web Route (Register Page)

The web route is responsible for rendering the registration page.

- Method: GET
- Purpose: display the registration form
- Controller: RegisterViewController

Configured via:
```php
authkit.controllers.web.register
```
Default controller:
```php
Xul\AuthKit\Http\Controllers\Web\Auth\RegisterViewController
```
**Behavior**

When accessed:

- resolves the register view
- renders the form based on authkit.schemas.register
- prepares the page for HTTP or AJAX submission

##### API Route (Register Action)

The API route handles form submission.

- Method: POST
- Purpose: process registration
- Controller: RegisterController

Configured via:
```php
authkit.controllers.api.register
```
Default controller:
```php
Xul\AuthKit\Http\Controllers\Api\Auth\RegisterController
```
**Behavior**

When called:

- validates request (RegisterRequest)
- builds mapped payload
- executes RegisterAction
- returns:
    - JSON (AJAX/API)
    - redirect (web)

### Route Prefix and Middleware

All AuthKit routes are affected by global configuration:
```php
authkit.routes.prefix  
authkit.routes.middleware  
```

**Example:**

```php
'routes' => [
    'prefix' => 'auth',
    'middleware' => ['web'],
],
```
This would result in routes like:
```php
/auth/register  
/auth/register (POST)  
```
###  Route Groups

Routes are further organized into groups:
```php
authkit.routes.groups.web  
authkit.routes.groups.api
```
Each group can define its own middleware stack.

**Example Route Resolution**

With default configuration:

| Type | Method | Route Name                     | Controller               |
|------|--------|--------------------------------|--------------------------|
| Web  | GET    | authkit.web.register           | RegisterViewController   |
| API  | POST   | authkit.api.auth.register      | RegisterController       |

### Customizing Routes

You can customize registration routes without touching package code:

#### Change route names

```php
'route_names' => [
    'web' => [
        'register' => 'auth.register',
    ],
],
```
#### Replace controllers

```php
'controllers' => [
    'api' => [
        'register' => \App\Http\Controllers\Auth\RegisterController::class,
    ],
],
```
#### Add middleware

```php
'routes' => [
    'groups' => [
        'api' => [
            'middleware' => ['throttle:register'],
        ],
    ],
],
```
## Controller Flow

AuthKit handles registration submissions through the configured registration action controller:

```php
authkit.controllers.api.register
```
By default, this points to:

```php
Xul\AuthKit\Http\Controllers\Api\Auth\RegisterController
```
The controller is intentionally thin. It does not contain registration business logic itself.  
Instead, it coordinates the request lifecycle and delegates actual registration work to the action layer.

### Responsibility of the Controller

The registration controller is responsible for:

- receiving the incoming registration request
- validating the request through RegisterRequest
- building the normalized mapped payload
- delegating the flow to RegisterAction
- resolving the final response as either:
    - JSON
    - redirect

This keeps the controller focused on HTTP concerns while the action remains the source of truth for registration behavior.

### Default Flow Inside the Controller

The default controller flow is:

- accept the incoming request
- validate it using RegisterRequest
- build the mapped payload for the register context
- pass the payload into RegisterAction
- inspect the request type
- return JSON or redirect accordingly

#### Request Validation

The controller depends on:
```php
Xul\AuthKit\Http\Requests\Auth\RegisterRequest
```
Because the request class is type-hinted directly in the controller method, validation happens before the controller continues.

That means by the time the controller executes its main logic:

- the input has already been validated
- only validated data is passed forward
- validation behavior has already respected:  
```php
  authkit.schemas.register  
  authkit.validation.providers.register  
  authkit.registration.enforce_unique_identity  
  authkit.registration.unique_identity.table  
  authkit.registration.unique_identity.column
```

#### Payload Building

After validation, the controller builds the normalized payload using:

```php
$payload = MappedPayloadBuilder::build('register', $request->validated());
```
The `register` context is important because it tells AuthKit which mapper configuration to use:
```php
authkit.mappers.contexts.register
```
This means the controller does not work with raw validated input directly. Instead, it works with the mapped payload produced by the configured mapper.

This is what allows registration persistence to remain controlled by mapping rules rather than by controller-level assumptions.
You can override this default class by setting the `class` of the `authkit.mappers.contexts.register` 

#### Delegating to the Action

Once the payload is built, the controller delegates execution to the registration action:

```php
$result = $action->handle($payload);
```
The controller does not create the user itself.  
- It does not start email verification itself.  
- It does not decide redirect destinations itself.

All of that happens inside the action, which returns a standardized `AuthKitActionResult`.

This separation is important because it keeps:
- HTTP concerns in the controller
- business flow concerns in the action
- persistence rules in the mapper
- validation concerns in the request/provider layer

##### JSON vs Redirect Response

After the action returns an AuthKitActionResult, the controller determines how the response should be returned.

It does this through:
```php
ResponseResolver::expectsJson($request)
```
If the request expects JSON, the controller returns a JSON response.

If not, it converts the result into a redirect-based web response.

This makes the same registration flow work for both:

- standard server-rendered forms
- AJAX/API-driven flows

##### JSON Response Behavior

When the request expects JSON, the controller returns:

```php
return $this->ok($result->toArray(), $result->status);
```
This means the client receives the standardized action result payload, including values such as:

- ok
- message
- status
- flow
- redirect
- payload
- errors when applicable

Because the response comes from AuthKitActionResult, JSON consumers get the same flow outcome structure as web consumers.

##### Web Response Behavior

When the request does not expect JSON, the controller converts the action result into a redirect response using its internal toWebResponse() method.

This method handles two broad cases:

##### Failure response

If the action result is not successful:

- AuthKit checks whether the result includes a redirect route
- if a redirect route exists, it redirects there with an error message
- otherwise, it falls back to the configured verification notice route

The fallback route is resolved from: `authkit.route_names.web.verify_notice`
##### Success response

If the action result is successful:

- AuthKit checks whether the action returned a redirect route
- if it did, the controller redirects there with a success message
- otherwise, it falls back to the verification notice route

This is especially useful because registration often ends by redirecting the user into the email verification flow.

### Why the Controller Stays Thin

The thin-controller design gives AuthKit several benefits:

- registration logic is easier to test
- response behavior is consistent across flows
- validation, mapping, and action layers remain isolated
- consumers can override one layer without rewriting everything

For example, you may want to:

- keep the default controller
- replace the validation provider
- use a custom mapper
- keep the same action result format

That is possible because the controller is only coordinating the layers, not owning them.

### Replacing the Controller

If you want different registration HTTP behavior, you can replace the controller through configuration:

```php
'controllers' => [
    'api' => [
        'register' => \App\Http\Controllers\Auth\RegisterController::class,
    ],
],
```
This is configured under: `authkit.controllers.api.register`

## Validation

AuthKit validates registration requests through:

```php
Xul\AuthKit\Http\Requests\Auth\RegisterRequest
```
The request builds its validation behavior from configuration, not from hardcoded assumptions.

### Schema-Aware Validation

The register request resolves the canonical registration schema from: `authkit.schemas.register`

Only fields present in that schema are considered when building default rules.

This means:
- removing a field from the schema removes its default validation requirement
- adding fields may require either a custom rules provider or additional rule logic

### Default Validation Rules

By default, RegisterRequest applies rules for the built-in registration fields when they exist in the schema:

- name → required|string|max:255
- email → required|string|email|max:255
- password → required|Password::defaults()
- password_confirmation → required|same:password

These defaults are only applied when the fields are present in: `authkit.schemas.register.fields`

### Identity Uniqueness Enforcement

AuthKit can automatically apply a unique rule to the configured registration identity field.

This behavior is controlled by:

- `authkit.registration.enforce_unique_identity`
- `authkit.registration.unique_identity.table`
- `authkit.registration.unique_identity.column`
- `authkit.identity.login.field`

#### Resolution order:

- use `authkit.registration.unique_identity.table` when set
- otherwise, infer the table from the configured auth provider model
- use `authkit.registration.unique_identity.column` when set
- otherwise, use `authkit.identity.login.field`

If uniqueness enforcement is disabled, AuthKit does not add its built-in unique rule.

### Custom Rules Provider

You may replace or extend the default validation behavior by configuring: `authkit.validation.providers.register`

**Example:**

```php
'validation' => [
    'providers' => [
        'register' => \App\Auth\RegisterRulesProvider::class,
    ],
],
```
A custom rules provider must implement:
```php
Xul\AuthKit\Contracts\Validation\RulesProviderContract
```
This allows you to customize:

- rules
- messages
- attributes

AuthKit resolves it through RulesProviderResolver. If the configured class does not implement the contract, it is ignored and defaults are used.

## Form Schema

The registration form is defined by: `authkit.schemas.register`

This schema is the canonical form definition for the registration flow.

By default, it includes:

- name
- email
- password
- password_confirmation

It also defines UI metadata such as:

- labels
- input types
- placeholders
- autocomplete
- wrapper classes
- submit button label

Because the schema is configuration-driven, you can:

- add new registration fields
- remove existing fields
- change labels and field presentation
- reorder fields

AuthKit uses this schema not only for rendering, but also as the base reference for default validation and mapping.

### Payload Mapping

After validation, AuthKit converts the validated input into a normalized payload through:

```php
MappedPayloadBuilder::build('register', $request->validated())
```
The mapper context is resolved from: `authkit.mappers.contexts.register`

### Default Register Mapper

The default mapper for registration is:
```php
Xul\AuthKit\Support\Mappers\Auth\RegisterPayloadMapper
```
Its default behavior is:

- name → attributes.name with trim
- email → attributes.email with lower_trim
- password → attributes.password with hash
- password_confirmation → excluded from the mapped payload

This ensures that registration actions receive normalized data rather than raw validated input.

### Custom Mapper

You may replace the registration mapper by configuring: `authkit.mappers.contexts.register.class`

**Example:**

```php
'mappers' => [
    'contexts' => [
        'register' => [
            'class' => \App\Auth\CustomRegisterMapper::class,
            'schema' => 'register',
        ],
    ],
],
```
A custom mapper must implement:
```php
Xul\AuthKit\Contracts\Mappers\PayloadMapperContract
```
Through that contract, your mapper can:

- define mapping definitions
- mark fields as persistable or non-persistable
- merge with package defaults
- fully replace package defaults
- remove specific default mappings

If the configured class does not implement the contract, AuthKit falls back to the package mapper behavior.

## User Creation and Persistence

User creation happens inside RegisterAction.
The action resolves the auth provider from: `authkit.auth.guard`

It then attempts to create a fresh model instance from that provider and applies only the mapper-approved persistable attributes.

### Persistable Attributes Only

AuthKit does not blindly save every validated field.

Instead, persistence is controlled by the mapper layer through the persist flag in the mapping definitions.

That means:

- validation-only fields like password_confirmation are not persisted
- only fields explicitly marked as persistable are written to the model

### Required Model Support

For AuthKit’s mapped persistence flow to work correctly, the user model must support AuthKit mapped persistence.

That model should use: 
```php
Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence
```
This trait provides the persistence surface AuthKit expects when writing mapped attributes onto the model.

Its responsibilities include:

- accepting persistable mapped attributes
- writing them via setAttribute() when available
- falling back to direct property assignment when needed
- saving the model automatically when supported and dirty

This keeps persistence aligned with the mapper layer instead of hardcoding field writes in the action.

## Registration Action

The registration flow is executed by:
```php
Xul\AuthKit\Actions\Auth\RegisterAction
```
This action is responsible for:

- reading the normalized mapped payload
- creating the user
- dispatching registration events
- starting email verification when applicable
- returning a standardized AuthKitActionResult

If user creation fails, the action returns a structured failure result.

If user creation succeeds:

- AuthKitRegistered is dispatched
- email verification may begin when an email is present
- the action returns a success result with redirect and public payload data

This keeps the controller thin and makes the action the source of truth for registration behavior.

## Email Verification After Registration

After successful registration, AuthKit checks whether an email address exists in the mapped registration attributes.

If no email exists, the action simply returns a successful account-created response.

If an email exists, AuthKit starts the email verification flow using:

- `authkit.email_verification.driver`
- `authkit.email_verification.ttl_minutes`

### Supported verification drivers include:

- link
- token

For the link driver, AuthKit builds a temporary signed verification URL.

For the token driver, AuthKit creates a verification token and leaves delivery to the configured verification delivery flow.

After initialization, AuthKit redirects the user to the verification notice route defined by: `authkit.route_names.web.verify_notice`

## Events

AuthKit dispatches two key events during registration.

### `AuthKitRegistered`

Dispatched immediately after a user is successfully created.

Use cases include:

- onboarding hooks
- analytics
- CRM sync
- internal audit handling

### `AuthKitEmailVerificationRequired`

Dispatched after AuthKit starts the email verification flow.

This event includes information such as:

- user
- email
- verification driver
- TTL
- token
- signed URL when applicable

This event allows verification delivery to remain event-driven and extensible.

### Example Configurations

**Username-Based Identity**

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

- AuthKit does not apply its built-in unique rule
- uniqueness must be enforced elsewhere if needed

**Custom Unique Identity Table or Column**

```php
'registration' => [
    'unique_identity' => [
        'table' => 'members',
        'column' => 'login_name',
    ],
],
```
With this configuration:

- the default registration unique rule targets members.login_name

**Custom Register Rules Provider**

```php
'validation' => [
    'providers' => [
        'register' => \App\Auth\RegisterRulesProvider::class,
    ],
],
```
Your class must implement:
```php
Xul\AuthKit\Contracts\Validation\RulesProviderContract
```

**Custom Register Mapper**

```php
'mappers' => [
    'contexts' => [
        'register' => [
            'class' => \App\Auth\RegisterPayloadMapper::class,
            'schema' => 'register',
        ],
    ],
],
```
Your class must implement:
```php
Xul\AuthKit\Contracts\Mappers\PayloadMapperContract
```

## Best Practices

- Keep the default identity uniqueness enabled in most production applications.
- Ensure your database-level unique indexes match your validation behavior.
- Use a custom rules provider when your registration rules go beyond AuthKit defaults.
- Use a custom mapper when persistence behavior needs to change.
- Keep validation-only fields out of persistence by marking them as non-persistable.
- Make sure your user model supports AuthKit mapped persistence through: `Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence `
- Prefer extending behavior through config, providers, mappers, and events instead of modifying package internals.