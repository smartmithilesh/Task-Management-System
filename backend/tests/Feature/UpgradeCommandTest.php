<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UpgradeCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_upgrade_command_migrates_seeds_refreshes_caches_and_clears_maintenance(): void
    {
        $this->artisan('app:upgrade')->assertSuccessful();

        $this->assertFileDoesNotExist(storage_path('framework/maintenance.php'));
        $this->assertDatabaseHas('permissions', ['name' => 'time_entries.manage']);
    }
}
