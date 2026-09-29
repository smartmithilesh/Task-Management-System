# Task Management System

Laravel 13 API and installer, static-export Next.js browser client, and Flutter mobile source for an organization task and project workspace.

## Local browser setup

The app's document root must be the repository's `backend/public` directory, or Apache must apply the root forwarding rules in `.htaccess`. Run the standalone installer at `/install`, then sign in at `/`. The checked-in host `taskmangment.local` should open `http://taskmangment.local/`; `/backend/` is a protected application-source path, not the product entry point.

The browser client is in `web/`. Build it with `cd web && npm ci && npm run build`. Static pages and assets publish into `backend/public`; Laravel serves the root page and `/api/v1` endpoints. The web client uses same-origin Laravel sessions and CSRF tokens.

## Components

- `backend/`: Laravel API, pre-boot installer, migrations, seed data, private attachments, and API token authentication.
- `web/`: Next.js browser client for the overview, people, projects, tasks, board, calendar, and reports.
- `mobile/`: Flutter sign-in, task list, task details, comments, checklist, and timer client. See [mobile setup](mobile/README.md).
- `docs/`: API, deployment, operational, backup, and upgrade guidance.

Start with [deployment](docs/deployment/production.md), [operations](docs/operations/backup-and-upgrade.md), [OAuth provider setup](docs/api/oauth-integrations.md), and the [OpenAPI document](docs/api/openapi.yaml).
