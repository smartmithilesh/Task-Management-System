<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpgradeApplication extends Command
{
    protected $signature = 'app:upgrade {--retry=60 : Seconds to retry requests while maintenance mode is active}';

    protected $description = 'Run forward database migrations, baseline seeders, and refresh Laravel caches';

    public function handle(): int
    {
        if ($this->call('down', ['--retry' => $this->option('retry')]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        foreach ([
            ['migrate', ['--force' => true]],
            ['db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]],
            ['optimize:clear', []],
            ['optimize', []],
        ] as [$command, $arguments]) {
            if ($this->call($command, $arguments) !== self::SUCCESS) {
                $this->components->error('Upgrade stopped at '.$command.'. The application remains in maintenance mode; restore a verified backup or correct the issue before bringing it online.');

                return self::FAILURE;
            }
        }

        if ($this->call('up') !== self::SUCCESS) {
            $this->components->error('Upgrade tasks completed, but maintenance mode could not be cleared.');

            return self::FAILURE;
        }

        $this->components->info('Upgrade completed. Restart queue workers if they are running.');

        return self::SUCCESS;
    }
}
