<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup';

    protected $description = 'Create a private, streamed MySQL database backup';

    public function handle(): int
    {
        $connectionName = config('database.default');
        $configuration = config("database.connections.{$connectionName}");
        if (! in_array($connectionName, ['mysql', 'mariadb', 'sqlite'], true)) {
            $this->components->error('This command supports MySQL, MariaDB, and file-backed SQLite databases.');

            return self::FAILURE;
        }
        if (! is_string($configuration['database'] ?? null) || $configuration['database'] === '') {
            $this->components->error('The database name is not configured.');

            return self::FAILURE;
        }

        $backupDirectory = storage_path('app/backups');
        if (! is_dir($backupDirectory) && ! mkdir($backupDirectory, 0700, true) && ! is_dir($backupDirectory)) {
            $this->components->error('The private backup directory could not be created.');

            return self::FAILURE;
        }
        chmod($backupDirectory, 0700);
        $extension = $connectionName === 'sqlite' ? '.sqlite' : '.sql';
        $path = $backupDirectory.'/database-'.now('UTC')->format('Ymd\THis\Z').'-'.bin2hex(random_bytes(3)).$extension;
        $output = fopen($path, 'xb');
        if ($output === false) {
            $this->components->error('The backup file could not be created.');

            return self::FAILURE;
        }
        chmod($path, 0600);

        if ($connectionName === 'sqlite') {
            $sourcePath = $configuration['database'] ?? '';
            if (! is_string($sourcePath) || $sourcePath === '' || $sourcePath === ':memory:' || ! class_exists(\SQLite3::class)) {
                fclose($output);
                @unlink($path);
                $this->components->error('SQLite backup requires a file-backed database and the PHP SQLite3 extension.');

                return self::FAILURE;
            }
            fclose($output);
            $source = new \SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
            $destination = new \SQLite3($path, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $created = $source->backup($destination);
            $source->close();
            $destination->close();
            if (! $created) {
                @unlink($path);
                $this->components->error('The SQLite backup failed.');

                return self::FAILURE;
            }
            chmod($path, 0600);
            $this->components->info('Database backup created: '.$path);
            $this->line('SHA-256: '.hash_file('sha256', $path));

            return self::SUCCESS;
        }

        $command = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--default-character-set=utf8mb4',
            '--host='.($configuration['host'] ?? '127.0.0.1'),
            '--port='.($configuration['port'] ?? 3306),
            '--user='.($configuration['username'] ?? ''),
            $configuration['database'],
        ];
        $environment = getenv() ?: [];
        if (is_string($configuration['password'] ?? null) && $configuration['password'] !== '') {
            $environment['MYSQL_PWD'] = $configuration['password'];
        }
        $process = new Process($command, base_path(), $environment, null, null);
        $stderr = '';
        try {
            $exitCode = $process->run(function (string $type, string $buffer) use ($output, &$stderr): void {
                if ($type === Process::OUT) {
                    fwrite($output, $buffer);
                } else {
                    $stderr .= $buffer;
                }
            });
        } catch (\Throwable $exception) {
            fclose($output);
            @unlink($path);
            report($exception);
            $this->components->error('The database backup process could not be started. Confirm mysqldump is installed.');

            return self::FAILURE;
        }
        fclose($output);

        if ($exitCode !== 0) {
            @unlink($path);
            report(new \RuntimeException('Database backup failed: '.mb_substr($stderr, 0, 2000)));
            $this->components->error('The database backup failed. Check the application log for details.');

            return self::FAILURE;
        }

        $this->components->info('Database backup created: '.$path);
        $this->line('SHA-256: '.hash_file('sha256', $path));

        return self::SUCCESS;
    }
}
