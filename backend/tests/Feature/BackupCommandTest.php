<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_backup_command_creates_a_private_restorable_sqlite_snapshot(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'taskflow-source-');
        $database = new \SQLite3($sourcePath);
        $database->exec('CREATE TABLE backup_probe (value TEXT)');
        $database->exec("INSERT INTO backup_probe VALUES ('verified')");
        $database->close();
        $existingBackups = array_fill_keys(glob(storage_path('app/backups/database-*.sqlite')) ?: [], true);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $sourcePath]);

        $this->artisan('app:backup')->assertSuccessful();

        $backupDirectory = storage_path('app/backups');
        $backups = array_values(array_filter(glob($backupDirectory.'/database-*.sqlite') ?: [], fn (string $path): bool => ! isset($existingBackups[$path])));
        $backupPath = collect($backups)->sortDesc()->first();
        $this->assertNotNull($backupPath);
        $snapshot = new \SQLite3($backupPath, SQLITE3_OPEN_READONLY);
        $this->assertSame('verified', $snapshot->querySingle('SELECT value FROM backup_probe'));
        $snapshot->close();
        $this->assertSame(0600, fileperms($backupPath) & 0777);

        @unlink($sourcePath);
        @unlink($backupPath);
    }
}
