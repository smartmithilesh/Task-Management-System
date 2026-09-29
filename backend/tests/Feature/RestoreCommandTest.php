<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RestoreCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_restore_requires_confirmation_and_restores_only_a_private_snapshot(): void
    {
        $targetPath = tempnam(sys_get_temp_dir(), 'taskflow-target-');
        $backupPath = storage_path('app/backups/database-restore-test-'.bin2hex(random_bytes(4)).'.sqlite');
        @mkdir(dirname($backupPath), 0700, true);
        $existingBackups = array_fill_keys(glob(storage_path('app/backups/database-*.sqlite')) ?: [], true);

        $target = new \SQLite3($targetPath);
        $target->exec('CREATE TABLE restore_probe (value TEXT)');
        $target->exec("INSERT INTO restore_probe VALUES ('before')");
        $target->close();

        $backup = new \SQLite3($backupPath);
        $backup->exec('CREATE TABLE restore_probe (value TEXT)');
        $backup->exec("INSERT INTO restore_probe VALUES ('restored')");
        $backup->close();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $targetPath]);

        $this->artisan('app:restore', ['backup' => $backupPath])->assertFailed();
        $this->artisan('app:restore', ['backup' => $targetPath, '--force' => true])->assertFailed();
        $this->artisan('app:restore', ['backup' => $backupPath, '--force' => true])->assertSuccessful();

        $restored = new \SQLite3($targetPath, SQLITE3_OPEN_READONLY);
        $this->assertSame('restored', $restored->querySingle('SELECT value FROM restore_probe'));
        $restored->close();
        $safetyBackups = array_values(array_filter(
            glob(storage_path('app/backups/database-*.sqlite')) ?: [],
            fn (string $path): bool => ! isset($existingBackups[$path]) && $path !== $backupPath,
        ));
        $this->assertNotEmpty($safetyBackups);
        $this->assertSame(0600, fileperms(collect($safetyBackups)->sortDesc()->first()) & 0777);

        @unlink($targetPath);
        @unlink($backupPath);
        foreach ($safetyBackups as $safetyBackup) {
            @unlink($safetyBackup);
        }
    }
}
