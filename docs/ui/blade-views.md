# Blade & Component System

## Overview

AuthKit’s UI is fully component-driven and configuration-controlled.

All Blade components used by AuthKit are defined in the `authkit.components` configuration. This allows you to:

- replace any component with your own implementation
- change component structure without editing package views
- customize UI behavior while keeping package logic intact

AuthKit uses **dynamic Blade components** (`<x-dynamic-component>`) internally, meaning component resolution happens at runtime based on configuration.


## How It Works

AuthKit does not hardcode component names in views.

Instead, it resolves components from config:

```php
config('authkit.components.input');
config('authkit.components.button');
config('authkit.components.field');
```

Then renders them dynamically:

```blade
<x-dynamic-component :component="$components['input']" />
```
This means:

- the Blade never depends on a fixed component
- swapping a component requires no Blade changes
- behavior is entirely controlled through configuration

## Component Categories

### 1. Layout Components

These control overall page structure.

```php
'layout' => 'authkit::layout',
'container' => 'authkit::container',
'card' => 'authkit::card',
'page' => 'authkit::page',
```
Used for:

- page wrappers
- spacing and structure
- layout consistency

###  2. Form Primitives

These render individual inputs.

```php
'label' => 'authkit::form.label',
'input' => 'authkit::form.input',
'select' => 'authkit::form.select',
'textarea' => 'authkit::form.textarea',
'checkbox' => 'authkit::form.checkbox',
'otp' => 'authkit::form.otp',
```

Used by the schema renderer based on field type.

### 3. Field Rendering Layer

These are higher-level abstractions.

```php
'field' => 'authkit::form.field',
'fields' => 'authkit::form.fields',
```

### Responsibilities:

- render wrapper
- render label
- render input component
- render help text
- render validation errors

> This is where schema → UI happens.

### 4. App Shell Components

These power the authenticated UI.

```php
'app_layout' => 'authkit::app.layout',
'app_shell' => 'authkit::app.shell',
'app_sidebar' => 'authkit::app.sidebar',
'app_topbar' => 'authkit::app.topbar',
'app_nav' => 'authkit::app.nav',
```

Used for:

- dashboard layout
- sidebar navigation
- authenticated experience

### 5. Utility Components

```php
'button' => 'authkit::button',
'link' => 'authkit::link',
'alert' => 'authkit::alert',
'divider' => 'authkit::divider',
```
Used across all pages.

## Replacing Components

You have two ways to customize components.

### Option 1 — Replace the Component View

Publish and override the Blade file:

```bash
php artisan vendor:publish --tag=authkit-views
```
Then edit:

```text
resources/views/vendor/authkit/form/input.blade.php
```
### Option 2 — Change Component Mapping (Recommended)

Update config:

```php
'input' => 'app.components.input',
```
Now AuthKit will use your component instead.

No need to touch package views.

## Important Rule: Dynamic Component Contract

Because AuthKit uses dynamic components, your custom components must:

### 1. Accept the expected props

Example:

```blade
@props([
    'name',
    'value' => null,
    'attributes' => [],
])
```

### 2. Respect passed attributes

```blade
<input {{ $attributes->merge([...]) }} />
```

### 3. Handle state properly

- old input (old())
- validation errors
- required flags
- accessibility attributes

> If your component ignores these, the form system will break.

## When to Replace What

### Replace primitives when:

- changing input UI (e.g. Tailwind → custom design)
- building a custom OTP UI
- changing checkbox behavior

### Replace field when:

- changing form layout structure
- adding wrappers, grids, or spacing logic

### Replace layout/app components when:

- redesigning the entire UI
- building your own dashboard shell

## Best Practices

- Prefer config overrides over editing package views
- Keep your component props compatible with AuthKit expectations
- Do not hardcode logic that conflicts with schema-driven behavior
- Test forms after replacing core components (input, field, button)

## Summary

AuthKit’s Blade system is:

- configuration-driven
- schema-powered
- component-based
- fully replaceable without touching package code

You don’t customize AuthKit by editing views.

You customize it by:

- changing config
- swapping components
- optionally overriding views  