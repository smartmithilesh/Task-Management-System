<?php

declare(strict_types=1);

namespace TaskManagement\Installer;

use App\Models\Role;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use PDO;
use RuntimeException;
use Throwable;

final class InstallationManager
{
    public function __construct(private readonly string $basePath) {}

    /** @param array{host:string,port:int,database:string,username:string,password:string} $database */
    public static function connect(array $database): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $database['host'],
            $database['port'],
            $database['database'],
        );

        return new PDO($dsn, $database['username'], $database['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /** @param array<string, string> $configuration
     * @param  array{name:string,email:string,password:string}  $admin
     */
    public function install(array $configuration, array $database, array $admin, ?callable $onProgress = null): void
    {
        $storagePath = $this->basePath.'/storage/app';
        if (! is_dir($storagePath) || ! is_writable($storagePath)) {
            throw new RuntimeException('The application storage directory is not writable.');
        }

        $guard = fopen($storagePath.'/install.guard', 'c');
        if ($guard === false || ! flock($guard, LOCK_EX)) {
            throw new RuntimeException('The installer could not acquire its setup lock.');
        }

        try {
            if (is_file($storagePath.'/installed.lock')) {
                throw new RuntimeException('The application has already been installed.');
            }

            $connection = self::connect($database);
            $this->progress($onProgress, 'Database connection verified');
            $this->ensureNoUnrecognizedTables($connection);
            $this->ensureNoExistingUsers($connection);
            $this->progress($onProgress, 'Database checked for existing application data');

            $this->writeEnvironment($configuration, $database);
            $this->progress($onProgress, 'Application key and environment configured');
            $this->bootLaravel();

            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new RuntimeException('Database setup did not complete. Check the database and try again.');
            }
            $this->progress($onProgress, 'Database migrations completed');

            if (Artisan::call('db:seed', ['--force' => true]) !== 0) {
                throw new RuntimeException('Default application setup did not complete. Please retry the installation.');
            }
            $this->progress($onProgress, 'Default application data created');

            $this->createAdministrator($admin);
            $this->progress($onProgress, 'Administrator account created');
            $this->writeInstalledLock($storagePath.'/installed.lock', $admin['email']);
            $this->progress($onProgress, 'Installation lock created');
        } catch (Throwable $exception) {
            // Keep operational details in the server log, never in the response.
            error_log('Task Management installer: '.$exception::class.' during setup.');
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('Installation could not be completed. Verify the server configuration and retry.');
        } finally {
            flock($guard, LOCK_UN);
            fclose($guard);
        }
    }

    private function ensureNoUnrecognizedTables(PDO $connection): void
    {
        $applicationTables = [
            'migrations', 'users', 'password_reset_tokens', 'sessions',
            'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
            'organizations', 'departments', 'teams', 'roles', 'permissions',
            'role_permissions', 'user_roles', 'team_users', 'task_statuses',
            'task_priorities', 'task_categories', 'organization_settings', 'system_settings',
            'notification_types', 'notification_preferences', 'integrations', 'integration_credentials',
            'projects', 'project_members', 'tasks', 'task_assignees', 'task_watchers',
            'task_reviewers', 'tags', 'task_tag', 'checklists', 'checklist_items',
            'comments', 'comment_mentions', 'attachments', 'time_entries', 'activity_logs', 'notifications',
        ];
        $statement = $connection->query('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE()');

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (! in_array($table, $applicationTables, true)) {
                throw new RuntimeException('This database already contains application tables. Choose an empty database.');
            }
        }
    }

    private function ensureNoExistingUsers(PDO $connection): void
    {
        try {
            $count = (int) $connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        } catch (\PDOException $exception) {
            // A fresh database has no users table yet; other query failures are
            // unsafe to interpret as an empty database.
            if ($exception->getCode() === '42S02') {
                return;
            }

            throw new RuntimeException('The installer could not verify the existing database tables. Check database permissions.');
        }

        if ($count > 0) {
            throw new RuntimeException('This database already contains users. Choose an empty application database.');
        }
    }

    /** @param array<string, string> $configuration
     * @param  array{host:string,port:int,database:string,username:string,password:string}  $database
     */
    private function writeEnvironment(array $configuration, array $database): void
    {
        $environment = [
            'APP_NAME' => $configuration['name'],
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($configuration['url'], '/'),
            'APP_TIMEZONE' => $configuration['timezone'],
            'APP_LOCALE' => $configuration['language'],
            'APP_FALLBACK_LOCALE' => 'en',
            'APP_FAKER_LOCALE' => 'en_US',
            'APP_DATE_FORMAT' => $configuration['date_format'],
            'APP_TIME_FORMAT' => $configuration['time_format'],
            'LOG_CHANNEL' => 'stack',
            'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => $database['database'],
            'DB_USERNAME' => $database['username'],
            'DB_PASSWORD' => $database['password'],
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'MAIL_MAILER' => 'log',
            'FILESYSTEM_DISK' => 'local',
        ];

        $contents = "# Generated by the Task Management System installer. Keep this file private.\n";
        foreach ($environment as $key => $value) {
            $escaped = str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value);
            $contents .= $key.'="'.$escaped."\"\n";
        }

        $path = $this->basePath.'/.env';
        $temporaryPath = $path.'.install-'.bin2hex(random_bytes(6));
        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException('The environment configuration could not be written. Check file permissions.');
        }

        @chmod($temporaryPath, 0640);
        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException('The environment configuration could not be activated. Check file permissions.');
        }
    }

    private function bootLaravel(): void
    {
        if (! is_file($this->basePath.'/vendor/autoload.php')) {
            throw new RuntimeException('Application dependencies are missing. Upload the complete application before installing.');
        }

        require_once $this->basePath.'/vendor/autoload.php';
        $app = require $this->basePath.'/bootstrap/app.php';
        $app->make(ConsoleKernel::class)->bootstrap();
    }

    /** @param array{name:string,email:string,password:string} $admin */
    private function createAdministrator(array $admin): void
    {
        $userClass = '\\App\\Models\\User';
        if (! class_exists($userClass)) {
            throw new RuntimeException('The administrator account could not be created.');
        }

        $user = $userClass::query()->create([
            'name' => $admin['name'],
            'email' => $admin['email'],
            'password' => $admin['password'],
        ]);

        $superAdminRole = Role::query()
            ->where('scope_key', 'global:super-admin')
            ->first();

        if ($superAdminRole === null) {
            throw new RuntimeException('The administrator role could not be assigned.');
        }

        $user->roles()->syncWithoutDetaching([$superAdminRole->id => ['assigned_by' => null]]);
    }

    private function writeInstalledLock(string $path, string $adminEmail): void
    {
        $temporaryPath = $path.'.'.bin2hex(random_bytes(6));
        $contents = json_encode([
            'installed_at' => gmdate(DATE_ATOM),
            'admin_email' => $adminEmail,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Installation finished, but the installation lock could not be written. Check storage permissions.');
        }

        @chmod($temporaryPath, 0600);
        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Installation finished, but the installation lock could not be activated. Check storage permissions.');
        }
    }

    private function progress(?callable $callback, string $message): void
    {
        if ($callback !== null) {
            $callback($message);
        }
    }
}
