<?php

namespace App\Console\Commands;

use App\Support\Modules\ModuleManager;
use Illuminate\Console\Command;

class ModulesListCommand extends Command
{
    protected $signature = 'modules:list';

    protected $description = 'List modules, their status and pending migrations';

    public function handle(ModuleManager $manager): int
    {
        $rows = [];

        foreach ($manager->all() as $key => $module) {
            $status = $manager->status($key);

            $rows[] = [
                $key,
                $module->name().($module->core() ? ' (core)' : ''),
                $module->version(),
                $status['status'].($status['reason'] ? ' — '.$status['reason'] : ''),
                implode(', ', $module->dependsOn()) ?: '—',
                count($manager->pendingMigrations($module)),
            ];
        }

        $this->table(['Key', 'Name', 'Version', 'Status', 'Needs', 'Pending migrations'], $rows);

        return self::SUCCESS;
    }
}
