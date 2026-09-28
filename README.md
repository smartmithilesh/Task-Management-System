# Task Management System

A self-hosted task management product built with a Laravel API, a Next.js web client, and a Flutter mobile client.

## Project status

- Phase 1 architecture: [ARCHITECTURE.md](ARCHITECTURE.md)
- Phase 2 installation wizard: Laravel backend scaffold and pre-boot web installer
- Phase 2 details and security notes: [docs/phase-2-installation.md](docs/phase-2-installation.md)
- Later product modules are planned in the architecture roadmap

## Backend development

Requirements: PHP 8.3+, Composer 2, MySQL 8+ (SQLite is available for local Laravel development), and the PHP extensions listed by the installer.

```bash
cd backend
composer install
php artisan serve
```

When no installation lock exists, requests are redirected to `/install`. The wizard checks server requirements, tests MySQL credentials, configures application settings, creates the first administrator, runs pending migrations and seeders, and writes the one-time lock to `backend/storage/app/installed.lock`. It does not drop existing tables. Use an empty application database; databases with existing users are rejected to prevent accidental takeover.

For web hosting, set the site's document root to `backend/public/`. Keep `backend/.env`, `backend/storage`, and all source files outside public web access. Ensure `backend/storage` and `backend/bootstrap/cache` are writable by the PHP process. HTTPS is required for production deployments.

The installer currently creates the initial Laravel administrator record. Login, roles, organization setup, and the rest of the task management product are scheduled for later phases.
