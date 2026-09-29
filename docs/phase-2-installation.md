# Phase 2 — Installation Wizard

This page records the installation-wizard phase. Later roadmap phases have since been implemented to varying degrees; see [the current implementation status](../ARCHITECTURE.md#13-development-roadmap-and-status) for what is complete and what remains.

## Architecture and request flow

The installer is a small pre-Laravel module. `backend/public/index.php` checks for `backend/storage/app/installed.lock` before loading Composer or Laravel. Without the lock, ordinary requests redirect to `/install`; installer requests render a self-contained, session-protected wizard. After installation, `/install` responds with HTTP 410 and all other requests boot Laravel.

```text
Requirements → Database connection → Application configuration → Administrator
       └──────── pre-Laravel PHP bootstrap ────────┘
                                                       ↓
                                      private .env → Artisan migrations/seeders
                                                       ↓
                                      administrator → atomic install lock
```

This boundary lets a fresh customer deployment start without `.env`, `APP_KEY`, database access, or an initialized Laravel application. The web server must use `backend/public/` as its document root.

## Files and responsibilities

```text
backend/
├── installer/
│   ├── installer.php                 # Wizard, validation, session and rendering
│   └── InstallationManager.php       # MySQL test, env creation and Laravel install
├── public/index.php                  # Pre-boot redirect and installed lock guard
├── app/Models/User.php               # Laravel administrator model
├── database/migrations/              # Laravel base tables, run non-destructively
├── database/seeders/DatabaseSeeder.php # Intentionally no demo/test account
└── tests/Feature/InstallationWizardTest.php
```

## Database and relationships

The Phase 2 installer milestone added no product-domain schema or custom migration. It ran the framework migrations for `users`, `password_reset_tokens`, `sessions`, cache, and queue tables. The initial administrator is stored in Laravel's `users` table with the framework password hash cast. The later database and product phases have since added organization, role, permission, and task reference tables plus authorization and organization setup.

Before migrating, setup rejects unknown tables and any database that already contains users. It permits a fresh database and known Laravel tables from a safe partial-install retry. It only runs `migrate --force` and `db:seed --force`; it never uses `migrate:fresh`, truncation, or table drops. The base seeder is empty so no sample account is created.

## Installer request contract

The installer remains an HTML setup flow, not a product REST API. `GET /install` displays the current step; form POSTs submit one of `requirements`, `database`, `application`, or `install`. `/install/requirements` is accepted as a compatibility URL and redirects to the wizard. Product API routes are now implemented under `/api/v1` and documented in the [OpenAPI file](api/openapi.yaml).

The wizard checks PHP 8.3+, required PHP extensions, PDO MySQL, writable framework directories, and `.env` creation/write access. It tests the supplied MySQL connection before advancing. Application input is validated and passwords/database credentials are kept in the server-side session until installation. The final step writes a restrictive `.env` atomically, generates `APP_KEY`, runs migrations and seeders, creates the administrator, then atomically commits the lock file.

## Access control and security

The installer is available only while the lock is absent. It has no normal-user authentication yet because no user exists before setup. It uses strict, HTTP-only, SameSite session cookies, session ID regeneration, CSRF tokens, no-store headers, output escaping, and field validation. Database and administrator passwords are not put in URLs, HTML after submission, or installer logs. Database errors are reduced to safe messages. The lock is written with restrictive permissions and all subsequent install requests return 410.

Installation does not expose reset/reinstall controls. Recovery requires host-level access and is not implemented as a public URL. Do not point a site's document root at `backend/`; point it at `backend/public/` so `.env`, source, storage, and logs stay outside web access. In production, use HTTPS and least-privilege PHP ownership for `storage/` and `bootstrap/cache/`.

## Services, controllers, models, and permissions

There is no Laravel HTTP controller or Form Request for pre-installation because Laravel cannot safely boot before generated configuration exists. The isolated `InstallationManager` service handles database validation, environment generation, migration/seed execution, administrator creation, and locking. No API permissions exist in this phase; later Laravel policies must protect application resources after authentication is implemented.

## Run and verify

```bash
cd backend
composer install
php artisan test
```

Configure a local virtual host whose document root is `backend/public/`, visit `/`, and confirm the redirect to `/install`. Complete installation against a fresh MySQL database. The automated installer feature tests render the pre-boot requirements page and verify its progress indicators, CSRF field, and lack of database-secret output. A full MySQL installation should be exercised in a disposable database before deployment; this workspace run did not use customer database credentials.

## Current limits

The wizard creates the initial user. Login, roles, task/project features, application settings, and browser landing screens have since been implemented. Mail and optional provider credentials still require deployment configuration. No public installer reset or reinstall mechanism is provided.
