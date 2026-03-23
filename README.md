# AuthKit

![Tests](https://github.com/Xultech-LTD/auth-kit/actions/workflows/tests.yml/badge.svg)
![License](https://img.shields.io/github/license/Xultech-LTD/auth-kit)
![PHP](https://img.shields.io/badge/php-%5E8.3-blue)
![Laravel](https://img.shields.io/badge/laravel-12.x-red)
![Laravel](https://img.shields.io/badge/laravel-13.x-green)

AuthKit is a reusable, configuration-driven authentication package for Laravel applications.

It provides a complete authentication system with Blade-based UI, flexible configuration, and extensible flows — without forcing a fixed implementation.

---

## 🚀 Documentation

Full documentation is available here:

👉 **https://xultech-ltd.github.io/auth-kit/**

The documentation covers:

- Installation
- Configuration
- Authentication flows
- UI customization
- Extensibility and overrides
- Security features

---

## ✨ Features

- Authentication (login & registration)
- Email verification (link or token-based)
- Password reset (link or token-based)
- Two-factor authentication (TOTP, extensible drivers)
- Blade-based UI components
- Theme and UI customization support
- Fully configuration-driven architecture
- Route, controller, and validation overrides
- Rate limiting and security controls

---

## 📦 Installation

> ⚠️ AuthKit is currently in active development.

Once published:

```bash
composer require xul/auth-kit
```
Then follow the installation guide in the documentation.

## ⚙️ Configuration Philosophy

AuthKit is built around a configuration-first approach.

Instead of hardcoding behavior, you can control:

- authentication flows 
- route structure and naming 
- validation and payload mapping 
- UI rendering and form schemas 
- security features (verification, 2FA, confirmations)
- frontend behavior and themes

This allows you to adapt AuthKit to your application without modifying package internals.

## 🧪 Testing
```bash
composer test
```

## 🤝 Contributing

Contributions are welcome.

Please:
- open issues for bugs or feature requests 
- submit pull requests for improvements 
- follow existing code style and structure

- ## 📄 License

AuthKit is open-sourced software licensed under the MIT license.