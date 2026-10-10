<?php

namespace App\Support\Modules\Events;

class ModuleToggled
{
    public function __construct(public readonly string $key, public readonly bool $enabled)
    {
    }
}
