---
title: Introduction
outline: deep
---

# AuthKit

AuthKit is a **reusable, configuration-driven authentication system for Laravel** that gives you complete control over authentication flows, UI, and behavior — without locking you into rigid scaffolding.

It is designed for developers who want **speed, flexibility, and long-term maintainability** when building authentication into real applications.


## Why AuthKit?

Most authentication solutions fall into two extremes:

- **Too rigid** — difficult to customize beyond basic use cases
- **Too low-level** — requiring you to build everything from scratch

AuthKit sits in the middle.

It provides a **complete authentication system out of the box**, while still allowing you to **customize and extend every part of the flow**.



## What You Get

AuthKit ships with a full set of authentication and security features:

### Core Authentication
- User registration
- Login and logout
- Password confirmation for sensitive actions

### Security Flows
- Email verification (link and token-based)
- Password reset flows
- Two-factor authentication (TOTP, recovery codes, extensible methods)

### UI and Frontend
- Blade-based UI system
- Theme-ready CSS structure
- JavaScript runtime for enhanced form handling (AJAX support)

### Developer Experience
- Config-driven architecture
- Action-based flow handling
- DTO-based responses
- Clean separation of concerns

### Extensibility
- Replace or extend actions
- Custom mappers for persistence
- Custom notifiers for delivery (email, SMS, etc.)
- Flexible route and middleware configuration


## Designed for Real Applications

AuthKit is not just a starter template — it is built for production use.

It is especially suited for:

- SaaS platforms
- Multi-tenant applications
- Admin panels and dashboards
- Products requiring customizable authentication flows
- Teams that need long-term maintainability

## Requirements

- PHP 8.3 or higher
- Laravel 12 or 13

## Installation

Install AuthKit via Composer:

```bash
composer require xul/auth-kit
```
Publish configuration and resources:
```bash
php artisan vendor:publish --provider="Xul\AuthKit\AuthKitServiceProvider"
```
Run your migrations:
```bash
php artisan migrate
```

Continue with the [Quick Start](/quick-start) guide to get up and running.


## Quick Example

Once installed, AuthKit provides ready-to-use authentication flows.

A typical setup includes:

- Configuring routes and middleware
- Ensuring your user model supports required traits
- Publishing and customizing views if needed

From there, authentication flows such as registration, login, and two-factor authentication are immediately available.


## Core Concepts

Understanding these concepts will help you use AuthKit effectively:

### Configuration-Driven

Most behavior is controlled through configuration rather than hardcoded logic.

### Actions

Each authentication flow is handled by dedicated action classes.

### DTOs (Data Transfer Objects)

Actions return structured results that define:

- success state
- errors
- redirects
- public payloads

### Support Layer

Handles complex flows such as:

- password resets
- two-factor authentication
- notification delivery
- data mapping

### UI Layer

Blade views, CSS, and JavaScript work together to provide a flexible frontend system.

## Documentation Overview

Start here:

- [Installation](/installation)
- [Quick Start](/quick-start)
- [Configuration](/configuration)

Explore features:

- Authentication flows
- Security flows
- UI customization

Go deeper:

- [Extending AuthKit](/extending/overview)
- [Architecture Overview](/architecture/overview)


## Extending AuthKit

AuthKit is built to be extended.

You can:

- Replace default actions with your own
- Customize how data is persisted using mappers
- Change how notifications are delivered
- Modify or replace UI components and flows

See the [Extending AuthKit](/extending/overview) section for details.


## Contributing

Contributions are welcome.

If you want to improve AuthKit:

- Submit issues
- Open pull requests
- Suggest enhancements


## Support and Feedback

If you encounter issues or have ideas:

- Open an issue on GitHub
- Provide clear reproduction steps when possible


## Star the Repository

If AuthKit helps you build faster or cleaner authentication systems, consider starring the repository:

👉 https://github.com/Xultech-LTD/auth-kit

It helps others discover the project and supports continued development.


## Next Step

Proceed to the [Installation](/installation) guide to begin integrating AuthKit into your application.