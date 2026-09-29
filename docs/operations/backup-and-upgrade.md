# Backup, restore, and upgrade

## Backup

Run `cd backend && php artisan app:backup` to create a mode-0600 MySQL/MariaDB dump or SQLite snapshot in `storage/app/backups`. The command streams MySQL output and reads the password from the process environment rather than the command arguments. It prints a SHA-256 checksum. Separately back up `backend/storage/app` (including private attachments and `installed.lock`) plus the deployment's `.env` through a secrets-safe system. Example for MySQL/InnoDB when using the hosting provider's backup tooling:

```sh
umask 077
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 "$DB_DATABASE" > task-management-$(date -u +%Y%m%dT%H%M%SZ).sql
```

The database backup contains personal and business data: encrypt it at rest, restrict access, define retention, and store an off-host copy. Keep the matching file-storage snapshot and application release identifier together. Do not place backups under `public/` or commit them.

## Restore drill

Restore the database and private file snapshot into an isolated environment first. The CLI command `php artisan app:restore storage/app/backups/<matching-backup> --force` verifies the file is inside the private backup folder and creates a fresh safety backup before replacement. It supports MySQL/MariaDB SQL dumps and file-backed SQLite snapshots; it does not alter attachments or `.env`. Restore those from the matching snapshot/secrets manager, then run `php artisan migrate:status` and verify attachments and sign-in. Production restore replaces customer data; stop writes first. No public reset or restore endpoint exists.

## Upgrade

Back up first, deploy the reviewed release and locked Composer dependencies, then run `composer install --no-dev --prefer-dist --optimize-autoloader` followed by `php artisan app:upgrade`. The command enables maintenance mode, applies forward migrations, runs idempotent baseline seeders, rebuilds Laravel caches, then returns the site online. On failure it leaves maintenance mode enabled for operator review. Build/publish the matching web client and restart queue workers. Migrations are forward-only customer data changes; do not use `migrate:fresh` or drop tables in an upgrade. If migration fails, preserve logs and the pre-upgrade backup, return application code to the prior release, and follow a reviewed database recovery plan.

## Operational checks

Monitor application and web-server logs, database space, queue backlog, backup age, TLS renewal, and writable storage. Test backups by restoring them on a schedule. Rotate device tokens by revoking unused rows from `api_tokens`; tokens expire after 90 days and can also be revoked by sign-out.
