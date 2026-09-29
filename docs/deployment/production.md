# Production deployment

## Runtime and web root

Use PHP 8.3+, Composer 2, MySQL 8+, Node.js 20+ to build the web client, and Apache with rewrite support or Nginx/PHP-FPM. Set the public document root to `backend/public`. Keep `.env`, `vendor`, installer internals, storage, logs, and backups outside direct web access. Enable TLS, disable directory indexes, deny dotfiles, and make `storage/` and `bootstrap/cache/` writable only by the application service account.

Copy `backend/.env.example` to `.env`, configure a unique `APP_KEY`, production database, HTTPS `APP_URL`, mail transport, queue/cache drivers, and trusted proxy settings. Run `composer install --no-dev --prefer-dist --optimize-autoloader`, `php artisan migrate --force`, and `php artisan config:cache && php artisan route:cache && php artisan view:cache`. Build the browser client and publish the static files before directing traffic to the release.

Optional OAuth providers remain disabled with blank client credentials. To enable one, configure its server-side client ID/secret, register the exact callback URL `APP_URL/api/v1/integrations/oauth/{provider}/callback` in the provider console, then rebuild Laravel's config cache. OAuth state uses the configured Laravel cache, which must be shared across application instances. Provider-specific scopes and current operations are listed in [OAuth provider integrations](../api/oauth-integrations.md).

For Apache shared hosting, point the domain to `backend/public` when possible. The repository-root `.htaccess` only forwards requests when the hosting provider forces the repository root as the document root; confirm the provider honors `AllowOverride` and file permissions. Never expose `/backend` as a browsable directory.

## Queues, scheduler, mail, and files

Set up a supervised `php artisan queue:work --sleep=3 --tries=3 --timeout=90` process for queued mail or integrations. On shared hosting use the database queue and provider cron to run `php artisan schedule:run` every minute. Local attachments use Laravel's private `local` disk and are downloaded through an authenticated organization-scoped route. Do not symlink private uploads into `public/`.

Configure outbound mail before inviting users or enabling password resets. Browser authentication depends on same-origin cookies and CSRF; if hosting the web bundle separately, configure explicit CORS, session domain, secure cookies, and trusted origins before rollout. Flutter API calls require HTTPS and a public API base URL.

## Release procedure

Build from locked dependencies in a staging copy, run the test suite and static client typecheck, take and verify a pre-release database backup, apply forward migrations, clear and rebuild Laravel caches, and smoke-test sign-in, organization scoping, file download authorization, and task creation. Keep the previous code release available for rollback; database rollback is not automatic. Review migration rollback behavior before release.
