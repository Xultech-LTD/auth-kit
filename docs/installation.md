---
title: Installation
outline: deep
---

# Installation

This guide walks you through installing AuthKit into a Laravel application and preparing the package for first use.

AuthKit is built to feel native inside a Laravel project. Once installed, it registers its core services, loads its packaged routes and views, and exposes publishable resources so you can customize the package to match your application.

## Requirements

Before installing AuthKit, make sure your application meets the following requirements:

- PHP 8.3 or higher
- Laravel 12 or higher
- A Laravel application using standard service container, routing, view, and authentication features
- A user model and authentication setup compatible with your chosen guard

AuthKit is designed to work with Laravel applications that want a reusable authentication layer with configurable routes, middleware, views, flows, themes, and security features.

## Install via Composer

Install the package using Composer:

```bash
composer require xul/auth-kit
```
## Automatic Package Registration

AuthKit uses Laravel package discovery, so installation does more than just place files in `vendor/`.

Once the package is installed, AuthKit automatically registers its main service provider with Laravel.

```php
Xul\AuthKit\AuthKitServiceProvider::class
```
This service provider is responsible for bootstrapping the package and wiring its internal services into your application.

## What AuthKit Registers

During registration and boot, AuthKit sets up the core pieces needed for the package to function.

This includes:

- merging the package configuration into your application
- registering internal authentication support services
- registering token and pending-flow services
- binding password reset services and notifier contracts
- binding email verification notifier contracts
- registering form schema and field resolver services
- registering the two-factor manager and active two-factor driver
- loading packaged routes
- loading packaged views
- registering optional event listeners for email verification and password reset delivery
- registering publishable package resources
- defining default password rules

In practical terms, this means that after installation, AuthKit is already prepared to:

- serve authentication routes
- render packaged views
- resolve configured flows
- dispatch notifications
- enforce default password validation rules
- publish resources for customization

## What AuthKit Loads Automatically

If the relevant features are enabled in configuration, AuthKit automatically loads its route files and view namespace during boot.

### Routes

AuthKit loads:

- guest web routes
- guest action/API routes
- authenticated app web routes
- authenticated app action/API routes

The authenticated application routes are loaded only when the authenticated app area is enabled.

This means the package can support both:

- a guest-only authentication setup
- a full authentication plus account/settings/security application shell

### Views

AuthKit registers its packaged Blade views under the `authkit::` namespace.

That means package views can be referenced like this:
```blade
authkit::pages.app.dashboard
```
This namespaced approach keeps package views organized and also makes it easy to publish and override them later.

## Publishable Resources

AuthKit ships with publishable resources so you can move package files into your application and customize them as needed.

The package exposes publish tags for:

- configuration
- views
- assets
- migrations
- route stubs

### Available Publish Tags

#### Configuration
```bash
php artisan vendor:publish --tag=authkit-config
```
Publishes:

- `config/authkit.php`

#### Views
```bash
php artisan vendor:publish --tag=authkit-views
```
Publishes package views into:
- `resources/views/vendor/authkit`
#### Assets
```bash
php artisan vendor:publish --tag=authkit-assets
```

Publishes built frontend assets into:

- `public/vendor/authkit`

#### Migrations
```bash
php artisan vendor:publish --tag=authkit-migrations
```

Publishes package migrations into:

- `database/migrations`

#### Routes
```bash
php artisan vendor:publish --tag=authkit-routes
```

Publishes package route files into your application's `routes/` directory as separate AuthKit route files.

### Recommended First Publish Commands

For most applications, the recommended starting point is to publish:

- configuration
- migrations
- assets

You can do that with:

```bash
php artisan vendor:publish --tag=authkit-config
php artisan vendor:publish --tag=authkit-migrations
php artisan vendor:publish --tag=authkit-assets
```
If you plan to customize the UI immediately, also publish the views:
```bash
php artisan vendor:publish --tag=authkit-views
```

### Installation Summary

At this stage, you have:
- installed AuthKit through Composer 
- registered the package automatically through Laravel package discovery 
- made AuthKit services available to your application 
- prepared the package for configuration, migrations, assets, and UI customization

The next step is to publish the resources you need and configure AuthKit for your application's authentication flow.