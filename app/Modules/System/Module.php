<?php

namespace App\Modules\System;

use App\Support\Modules\Module as BaseModule;

/** Admin > Modules: see every module, switch optional ones on/off, run updates, edit module settings. */
class Module extends BaseModule
{
    public function key(): string
    {
        return 'system';
    }

    public function name(): string
    {
        return 'System & Modules';
    }

    public function description(): string
    {
        return 'Module manager, one-click updates and per-module settings.';
    }

    public function icon(): string
    {
        return 'boxes';
    }

    public function core(): bool
    {
        return true;
    }

    public function permissions(): array
    {
        return ['system.manage' => 'Manage modules, run updates and change module settings'];
    }

    public function menu(): array
    {
        return [[
            'label' => 'Modules', 'route' => 'admin.modules.index', 'match' => 'admin.modules.*',
            'icon' => 'boxes', 'permission' => 'system.manage', 'group' => 'admin', 'order' => 90,
        ]];
    }
}
