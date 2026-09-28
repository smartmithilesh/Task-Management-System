# Task Management System — Phase 1 Architecture

This is the system blueprint and setup plan for Phase 1. It does not implement the modules that follow. The Laravel API is the source of truth for business rules and data; the web and mobile clients are consumers of that API.

## 1. System architecture

```text
 Browser ── HTTPS ── Next.js web client ─┐
                                         ├── Laravel application ── MySQL 8+
 Flutter app ── HTTPS ── /api/v1 ────────┘        ├── Queue / scheduler
                                                  ├── Private file storage
                                                  ├── Mail / push providers
                                                  └── Integration adapters

 First visit (not installed): web server ── standalone installer ── .env,
                                                                  migrations,
                                                                  initial admin,
                                                                  installed lock
```

Deploy the Laravel application and its `public/` directory as one unit. Next.js and Flutter are separate clients. For cPanel/Plesk deployments, the Laravel `public/` directory is the document root; application source, `.env`, storage, and logs must remain outside public web access. A customer may host the Next.js client separately or use a built static/client deployment where supported. Flutter builds are distributed through the relevant mobile stores or customer channels.

Keep a single-organization data model for the initial release, but include `organization_id` on organization-owned records and enforce tenant scoping in services/policies. This allows later multi-organization support without pretending that a complete multi-tenant SaaS control plane exists in v1.

## 2. Repository and folder structure

Use a monorepo so API contracts, client integrations, and deployment documentation can be versioned together. Keep framework applications independently installable.

```text
task-management-system/
├── backend/                         # Laravel API and web-facing backend
│   ├── app/
│   │   ├── Actions/                 # Small application use cases
│   │   ├── Events/  Listeners/ Jobs/ Notifications/
│   │   ├── Http/Controllers/Api/V1/
│   │   ├── Http/Requests/Api/V1/
│   │   ├── Http/Resources/Api/V1/
│   │   ├── Models/ Policies/ Providers/
│   │   └── Services/{Installation,Projects,Tasks,Integrations}/
│   ├── bootstrap/  config/  database/{migrations,seeders,factories}/
│   ├── public/                      # Only public web root
│   ├── routes/{api.php,web.php,console.php}
│   ├── storage/                     # Private uploads, logs, cache
│   ├── tests/{Feature,Unit}/
│   ├── .env.example
│   └── composer.json
├── web/                             # Next.js, React, TypeScript, Tailwind
│   ├── src/{app,components,features,lib,styles}/
│   ├── public/  tests/  package.json
│   └── .env.example                 # Public API base URL only
├── mobile/                          # Flutter / Dart client
│   ├── lib/{app,core,features}/
│   ├── test/  android/  ios/  pubspec.yaml
│   └── .env.example                 # Non-secret API endpoint per build flavor
├── docs/{architecture,api,deployment,operations}/
├── deploy/{apache,nginx,plesk,cpanel}/
├── .gitignore
└── README.md
```

Use feature-oriented boundaries inside clients, but keep authorization and business decisions in Laravel. Do not commit `.env`, credentials, customer data, generated keys, vendor/node_modules/build output, or private uploads. Commit `.env.example` files containing names and safe defaults only.

## 3. Laravel project creation

Run these from a development machine with PHP 8.3+, Composer, Node.js/npm, Flutter SDK, and MySQL available. Create each framework project in the indicated subdirectory; do not run the commands in this existing workspace until the architecture is reviewed.

```bash
mkdir task-management-system && cd task-management-system
composer create-project laravel/laravel backend
cd backend
composer require laravel/sanctum
php artisan install:api
cd ..
```

Use Laravel's supported release compatible with PHP 8.3 and pin dependency versions through `composer.lock`. Sanctum provides personal access tokens for Flutter and can support cookie-based SPA authentication if the web client is configured for the same-site deployment model. Keep API behavior under `/api/v1`; use Laravel API Resources and Form Requests. Add dependencies only when a chosen feature needs them (for example, queue/mail drivers or an OpenAPI generator), then record and review them in Composer.

## 4. Next.js project setup

```bash
npx create-next-app@latest web --typescript --tailwind --eslint --app --src-dir
```

Use server components for public/static presentation where helpful and client components for interactive task views. The browser calls the versioned Laravel API through a typed client in `web/src/lib/api`. Treat the API response as untrusted input and never embed server secrets in `NEXT_PUBLIC_*` variables. Keep auth tokens out of local storage where feasible; prefer secure, HTTP-only same-site cookies for a same-origin web deployment, with CSRF protections. If deployed on another origin, configure an explicit CORS allowlist and document the chosen token/session flow.

## 5. Flutter project setup

```bash
flutter create --org com.example task_management_mobile
```

Move the generated app into `mobile/` (or create it directly there). Organize by feature, with shared API/auth/error handling under `lib/core`. Use environment/build flavors for the API base URL; no private key or integration secret belongs in a mobile binary. Authenticate with Sanctum-issued, revocable, per-device tokens over TLS. Store tokens only in platform secure storage, clear them on logout, and route API errors consistently.

## 6. Database architecture overview

Use MySQL 8 with InnoDB, `utf8mb4`, foreign keys, indexed lookup/filter columns, and UTC timestamps. Laravel migrations are the schema source of truth. Prefer numeric internal primary keys plus non-sequential public UUID/ULID identifiers in APIs. Use soft deletes selectively for recoverable business records; retain immutable audit events separately.

Initial domain areas (implemented in later phases):

| Area | Main records and relationships |
|---|---|
| Identity and access | users, roles, permissions, role_permissions, user_roles; users belong to organization and optionally department/team |
| Organization | organizations, departments, teams, team_users; a department has teams and teams have members |
| Projects | projects belong to organization, have a manager and many members; projects have tasks |
| Tasks | tasks belong to a project and may have a parent task; task_assignees, watchers, reviewers, status and priority references, tags, checklists/items |
| Collaboration | polymorphic comments and private attachments; activity_logs record actor, subject, action, and safe change metadata |
| Time and notifications | time_entries belong to user/task; notifications and notification_preferences are per user/channel |
| Settings and integrations | organization settings, integration definitions/connections, encrypted integration credentials |

Enforce tenant ownership through scoped queries and policies, not only foreign keys. Add composite indexes for common organization/project/status/assignee/due-date filters. Avoid a separate `subtasks` table if tasks can reference `parent_task_id`; one hierarchical task model avoids duplicated task behavior. Use transactions around multi-record changes such as task assignment plus activity event. Define retention, backup, and restore policy before production data is accepted.

## 7. API architecture

Expose JSON endpoints under `/api/v1`, with route groups for authentication, users, organizations, projects, tasks, comments, attachments, time entries, notifications, reports, and integrations. Use plural resource routes and explicit action routes for transitions (for example, task status changes and timer start/stop). Apply authentication, throttling, request validation, policy authorization, and organization scoping at the backend boundary.

Return a stable envelope with `success`, `message`, `data`, and pagination metadata where applicable. Use consistent validation/error codes and HTTP status codes. API Resources define the public representation; never serialize models or secrets wholesale. Paginate/filter on the server, eager-load required relations, and make state-changing operations transactional and auditable. Publish an OpenAPI document from the actual routes/contracts as API phases are implemented.

## 8. Authentication and authorization architecture

Laravel owns identity, sessions/tokens, roles, permissions, and authorization. Use Laravel's password hashing, verified email flow, password reset, rate limits, and secure session configuration. Sanctum personal access tokens serve mobile/API clients; support revocation and token abilities, but still authorize each record through policies. For web, select one documented model: same-origin HTTP-only session cookies (recommended when practical) or a carefully configured Sanctum SPA flow. Do not treat client-side route hiding or token abilities as permission checks.

Represent granular permissions as database records and bind checks to Laravel policies/gates. A role grants permissions; a user receives roles through a pivot. Super-admin behavior is centralized and auditable. Every policy must check both action permission and organization/resource ownership. Seed only baseline roles, permissions, statuses, priorities, and settings; allow administrators to manage supported configuration later.

## 9. Installation wizard architecture

The first-request installer must work before `.env` exists, so it cannot depend on normal Laravel boot/configuration. Place a small, isolated installer entrypoint in the public deployment (for example `public/install/index.php`) and route uninstalled requests to it at the web-server/front-controller boundary. It checks `storage/app/installed.lock` before every request and refuses access once locked. Protect steps with a server-side session, CSRF token, strict input validation, throttling, and no-store responses. Do not include secrets in URLs, browser storage, logs, or exception output.

The installer checks PHP version/extensions and writable paths, validates the application URL, tests a PDO MySQL connection, writes `.env` atomically with restrictive permissions, generates `APP_KEY`, boots Laravel only after configuration exists, runs non-destructive migrations/seeders, creates the administrator through Laravel hashing, then writes the lock atomically as the final successful step. A failed step must not create the lock; retries must be safe and must not drop tables. Do not expose a public reset URL. Recovery requires an authenticated administrator/host operator procedure documented outside the installer. Ensure `.env`, storage, vendor, and installer internals cannot be served by Apache/Nginx/cPanel/Plesk document-root rules.

Keep installer code isolated under `backend/app/Services/Installation` after Laravel is bootable, with only the pre-boot bootstrap/HTTP handling outside Laravel. Installer success redirects to login. Add installation route/lock checks to ordinary requests so a partially configured app cannot serve the product.

## 10. Integration architecture

Define provider-neutral contracts under `backend/app/Services/Integrations`, with provider adapters for communication, calendars, source control, storage, and identity. Application services/events call those contracts; controllers never call vendor APIs directly. Store connection state separately from credentials, encrypt secrets at rest with Laravel encryption, and never return secret values from APIs. Use OAuth state/CSRF validation, least-privilege scopes, token refresh/revocation, timeouts, retries with backoff, and queued jobs for slow or repeatable operations. Each integration exposes capability checks and test/disconnect operations. Keep optional integrations disabled until configured; core tasks must work without third-party accounts.

## 11. Environment configuration

Maintain backend `.env.example` with safe keys such as `APP_NAME`, `APP_ENV`, `APP_KEY` (blank example), `APP_URL`, `APP_TIMEZONE`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (blank), mail/queue/cache settings, and optional integration placeholders. The installer generates real values; local developers copy the example to `.env`. Exclude all actual env files from Git and redact secrets in diagnostics. The Next.js client receives only the API base URL and other public configuration. Flutter receives only a public API endpoint/build flavor; secrets stay on the server.

## 12. Development and production requirements

**Development:** PHP 8.3+, Composer 2, MySQL 8+, Node.js LTS compatible with the selected Next.js release, npm, Flutter stable SDK, Git, and a local HTTPS-capable setup for auth callbacks. Redis is optional locally; database queue/cache drivers are acceptable for development. Use separate local databases and provider test credentials.

**Production baseline:** PHP 8.3+ with required extensions (PDO MySQL, OpenSSL, Mbstring, Fileinfo, Tokenizer, XML, Ctype, JSON, BCMath, GD, ZIP, cURL), Composer-built dependencies, MySQL 8+, Apache or Nginx/PHP-FPM, TLS, and writable storage/cache directories with least-privilege ownership. Configure backups and restore drills, log rotation, process supervision for queue workers, and a once-per-minute scheduler. Redis is recommended for shared cache/queues at scale, but should not be a hard install requirement for smaller shared hosting. Keep uploads private and serve them through authorized endpoints or short-lived signed URLs. Production web root must point only to Laravel `public/`; disable directory listing and deny access to dotfiles, source, logs, and environment files.

## 13. Development roadmap

Implement and review one phase at a time, following the supplied order:

1. Architecture and project setup (this document)
2. Standalone installation wizard
3. Database architecture and baseline seed data
4. Authentication
5. User management
6. Roles and permissions
7. Organizations, departments, and teams
8. Projects
9. Tasks
10. Subtasks and checklists
11. Comments and attachments
12. Activity logs
13. Time tracking
14. Notifications
15. Dashboard
16. Kanban
17. Calendar
18. Reports and exports
19. Integrations framework/providers
20. REST API completion and OpenAPI documentation
21. Next.js web client
22. Flutter mobile client and push notifications
23. Automated tests and installation scenarios
24. Security review
25. Performance optimization
26. Production deployment guides and operations
27. Upgrade and backup mechanisms

For each implementation phase, define schema/relationships, endpoint contracts, permission rules, validation, failure behavior, and operational impact before building its components. Add automated tests with that phase; do not claim production readiness until the complete security, recovery, deployment, and upgrade paths are verified.

## 14. Git and release workflow

Keep `main` deployable, use short-lived feature branches, review migrations and API compatibility, and tag releases with semantic versions. Require CI to install locked dependencies and run format/static checks and the tests introduced by each phase. Never put customer database dumps, `.env`, signing keys, FCM/OAuth secrets, or production uploads in the repository. Release notes must call out migrations, required runtime changes, queue/scheduler changes, and rollback limitations. Schema upgrades must be forward migrations that preserve customer data; destructive changes require a separately reviewed migration plan and backup.

## 15. Key risks and decisions to resolve during implementation

- Shared hosting may disable shell execution, long-running workers, or symlinks. The installer/deployment design must support the documented hosting profile without making unsafe assumptions; queue-backed features need a documented synchronous/cron fallback where appropriate.
- Cross-origin web hosting changes cookie and CSRF behavior. Choose deployment topology before finalizing web authentication and CORS settings.
- Multi-tenant isolation, private file access, token revocation, and installer locking require explicit security tests before release.
- Email, push, OAuth, and storage providers require customer-owned credentials and provider-specific setup. Keep them optional and report setup state without exposing secrets.
