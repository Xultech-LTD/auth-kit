---
title: Upgrade Guide
outline: deep
---

# Upgrade Guide

AuthKit is currently in its initial release phase.

There are no legacy versions to migrate from yet, but this guide establishes the standard workflow for future upgrades and ensures your application remains stable as AuthKit evolves.


## When to Use This Guide

Use this guide when:

- updating AuthKit to a newer version
- reviewing changes after a release
- ensuring your configuration stays in sync

If you are installing AuthKit for the first time, see the [Installation](/installation) guide instead.


## Upgrade Workflow

When upgrading AuthKit, follow these steps:

### 1. Update the Package

```bash
composer update xul/auth-kit
```
### 2. Review Release Notes

Before upgrading, review what the new version introduces:

- new features
- configuration changes
- deprecations (if any)


### 3. Update Configuration

AuthKit is configuration-driven, so updates may introduce new or changed keys.

When this happens:

- publish the updated config
- compare it with your existing file
- merge new keys carefully

```bash
php artisan vendor:publish --tag=authkit-config --force
```
### 4. Clear Cached Configuration

After updating configuration, clear cached values to ensure changes take effect:

```bash
php artisan config:clear
php artisan cache:clear
```
### 5. Verify Application Behavior

After upgrading, test key flows to ensure everything is working correctly:

- login
- registration
- password reset
- email verification
- two-factor authentication
- authenticated pages (dashboard, settings, security)


### What May Change in Future Versions

As AuthKit evolves, updates may introduce:

- new configuration sections
- additional page modules
- new UI engines or themes
- expanded rate limiting strategies
- new confirmation types
- additional component mappings

All changes will be documented in release notes.

### Best Practices

To ensure smooth upgrades:

- avoid modifying package internals directly
- rely on configuration and published views for customization
- keep your config close to the default structure
- test upgrades in a staging environment before production  