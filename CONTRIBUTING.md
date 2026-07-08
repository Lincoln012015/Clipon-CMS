# Contributing

Thanks for contributing to Clipon CMS. Keep changes focused, describe user-visible behavior, and avoid committing local runtime state or secrets.

## Development Setup

This repository contains the ready-to-run PHP CMS. It has no Composer or Node.js install step.

For local development, serve the repository root with PHP or an Apache/Nginx virtual host, then open `/clipon/setup.php`. For example:

```bash
php -S 127.0.0.1:8080
```

## Branches And Pull Requests

- Keep changes focused.
- Do not commit local runtime state, generated logs, private keys, `.env` files, SQLite databases, `node_modules/`, or `vendor/`.
- Add or update documentation for user-visible behavior.
- Describe how security, routing, storage, and admin workflow changes were verified.

## Verification

Run syntax checks for changed PHP files and manually exercise the affected workflow. A repository-wide syntax check can be run with:

```bash
find . -name '*.php' -type f -print0 | xargs -0 -n1 php -l
```

Before opening a pull request, verify setup, admin login, the changed feature, public routing, and that no runtime files appear in `git status`.
