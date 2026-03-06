# Contributing to AuthKit

Thank you for considering contributing to **AuthKit**.

We welcome contributions that improve the package, documentation, or developer experience.

---

## Development Setup

Clone the repository:

```bash
git clone https://github.com/Xultech-LTD/auth-kit.git
cd auth-kit
```
Install dependencies:

```bash
composer install
npm install
```
Run the test suite:

```bash
./vendor/bin/pest
```

## Pull Requests

Before submitting a pull request:

- Fork the repository
- Create a new branch
- Write tests for new functionality
- Ensure all tests pass
- Follow the existing code style

### Example Workflow

```bash
git checkout -b feature/improve-auth-flow
git commit -m "Improve authentication flow"
git push origin feature/improve-auth-flow
```
Then open a Pull Request on GitHub.

---
## Coding Standards

AuthKit follows standard Laravel conventions:

- **PSR-12 coding style**
- **Clear, self-documenting code**
- **Extensive test coverage**

---

## Issues

Before opening an issue:

- Check if the issue already exists
- Provide a clear reproduction case
- Include relevant logs or stack traces

---

## Documentation

Documentation improvements are always welcome.

Docs live in the `/docs` directory and are powered by **VitePress**.

---

## Questions

If you have questions or ideas, open a **GitHub Discussion** or **Issue**.

We appreciate your contribution to making **AuthKit** better.

---

## Commit the Files

Run the following commands:

```bash
git add CODE_OF_CONDUCT.md SECURITY.md CONTRIBUTING.md

git commit -m "Add community guidelines: Code of Conduct, Security policy, and Contributing guide"

git push origin main
```