<?php

namespace App\Console\Commands;

use App\Support\Modules\ModuleManager;
use Illuminate\Console\Command;

class ModulesSyncCommand extends Command
{
    protected $signature = 'modules:sync';

    protected $description = 'Record installed modules, create system roles and apply default permissions for new module versions';

    public function handle(ModuleManager $manager): int
    {
        $s = $manager->sync();
        $this->info("Synced {$s['modules']} module(s); {$s['roles']} new role(s); {$s['grants']} new permission grant(s).");

        return self::SUCCESS;
    }
}
