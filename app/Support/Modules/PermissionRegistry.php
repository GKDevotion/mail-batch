<?php

namespace App\Support\Modules;

use Illuminate\Support\Str;

/** All permissions of all active modules. */
class PermissionRegistry
{
    /** @var array<string,array{name:string,permissions:array<string,string>}> */
    private array $modules = [];

    /** @param array<string,string> $permissions permission => label */
    public function register(string $moduleKey, string $moduleName, array $permissions): void
    {
        if ($permissions === []) {
            return;
        }

        $this->modules[$moduleKey] = ['name' => $moduleName, 'permissions' => $permissions];
    }

    /** @return array<string,array{name:string,permissions:array<string,string>}> */
    public function all(): array
    {
        return $this->modules;
    }

    /** @return array<int,string> */
    public function names(): array
    {
        return array_keys($this->labels());
    }

    /** @return array<string,string> */
    public function labels(): array
    {
        $labels = [];
        foreach ($this->modules as $module) {
            $labels += $module['permissions'];
        }

        return $labels;
    }

    public function has(string $permission): bool
    {
        return array_key_exists($permission, $this->labels());
    }

    /**
     * Turn patterns into permission names: "contacts.*" (a module), "*" (everything), "!contacts.delete" (remove).
     *
     * @param  array<int,string>  $patterns
     * @return array<int,string>
     */
    public function expand(array $patterns): array
    {
        $all = $this->names();
        $granted = [];

        foreach ($patterns as $pattern) {
            $negate = str_starts_with($pattern, '!');
            $pattern = ltrim($pattern, '!');
            $matches = $pattern === '*' ? $all : array_values(array_filter($all, fn (string $p) => Str::is($pattern, $p)));

            $granted = $negate ? array_diff($granted, $matches) : array_merge($granted, $matches);
        }

        return array_values(array_unique($granted));
    }
}
