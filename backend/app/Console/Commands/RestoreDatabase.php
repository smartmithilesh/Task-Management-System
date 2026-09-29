<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class RestoreDatabase extends Command
{
    protected $signature = 'app:restore {backup : Backup file under storage/app/backups} {--force : Confirm replacing current database contents}';

    protected $description = 'Restore a trusted private database backup after creating a safety backup';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->components->error('Restore replaces current database contents. Review the backup and rerun with --force.');

            return self::FAILURE;
        }

        $directory = realpath(storage_path('app/backups'));
        $path = realpath((string) $this->argument('backup'));
        if ($directory === false || $path === false || ! str_starts_with($path, $directory.DIRECTORY_SEPARATOR) || ! is_file($path) || filesize($path) < 1) {
            $this->components->error('Choose a non-empty backup file from storage/app/backups.');

            return self::FAILURE;
        }

        $driver = config('database.default');
        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            $this->components->error('Restore supports MySQL, MariaDB, and file-backed SQLite databases.');

            return self::FAILURE;
        }
        if (($driver === 'sqlite' && pathinfo($path, PATHINFO_EXTENSION) !== 'sqlite') || ($driver !== 'sqlite' && pathinfo($path, PATHINFO_EXTENSION) !== 'sql')) {
            $this->components->error('The backup file format does not match the configured database driver.');

            return self::FAILURE;
        }

        $before = array_fill_keys(glob($directory.'/database-*') ?: [], true);
        if ($this->call('app:backup') !== self::SUCCESS) {
            $this->components->error('A safety backup could not be created; restore was cancelled.');

            return self::FAILURE;
        }
        $safetyBackup = collect(glob($directory.'/database-*') ?: [])->reject(fn (string $candidate): bool => isset($before[$candidate]))->sortDesc()->first();

        try {
            if ($driver === 'sqlite') {
                $this->restoreSqlite($path);
            } else {
                $this->restoreMysql($path, $driver);
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->components->error('Restore failed. The pre-restore safety backup is '.$safetyBackup.'. Keep the application offline and follow the recovery guide.');

            return self::FAILURE;
        }

        $this->components->info('Database restored. Safety backup: '.$safetyBackup);

        return self::SUCCESS;
    }

    private function restoreSqlite(string $backup): void
    {
        $targetPath = config('database.connections.'.config('database.default').'.database');
        if (! is_string($targetPath) || $targetPath === ':memory:' || ! class_exists(\SQLite3::class)) {
            throw new \RuntimeException('SQLite restore requires a file-backed database and the SQLite3 extension.');
        }
        DB::purge(config('database.default'));
        $source = new \SQLite3($backup, SQLITE3_OPEN_READONLY);
        $target = new \SQLite3($targetPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $restored = $source->backup($target);
        $source->close();
        $target->close();
        if (! $restored) {
            throw new \RuntimeException('SQLite did not complete the database backup operation.');
        }
        DB::purge(config('database.default'));
    }

    private function restoreMysql(string $backup, string $driver): void
    {
        $connection = config('database.connections.'.config('database.default'));
        $binary = $driver === 'mariadb' ? 'mariadb' : 'mysql';
        $command = [
            $binary,
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? 3306),
            '--user='.($connection['username'] ?? ''),
            $connection['database'],
        ];
        $environment = getenv() ?: [];
        if (is_string($connection['password'] ?? null) && $connection['password'] !== '') {
            $environment['MYSQL_PWD'] = $connection['password'];
        }
        $input = fopen($backup, 'rb');
        if ($input === false) {
            throw new \RuntimeException('The database backup could not be opened.');
        }
        $process = new Process($command, base_path(), $environment, null, null);
        $process->setInput($input);
        try {
            $process->mustRun();
        } finally {
            fclose($input);
        }
    }
}
