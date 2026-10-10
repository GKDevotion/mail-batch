<?php

namespace App\Support\Modules;

use App\Models\SystemSetting;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\RolePermission;
use App\Support\Modules\Events\ModuleToggled;
use DomainException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Finds modules (app/Modules/{Name}/Module.php), orders them by dependency, knows which are on, and installs updates.
 *
 * Status of a module: active | disabled (switched off) | blocked (a required module is missing/off or the dependency is circular).
 */
class ModuleManager
{
    /** @var array<string,Module>|null */
    private ?array $discovered = null;

    /** @var array<string,object>|null rows of the "modules" table */
    private ?array $state = null;

    /** @var array<string,Module>|null */
    private ?array $active = null;

    /** @var array<string,string> */
    private array $blocked = [];

    public function __construct(private readonly Application $app)
    {
    }

    // ------------------------------------------------------------------ discovery

    /** @return array<string,Module> every module on disk, dependencies first */
    public function all(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $found = [];

        foreach (glob(app_path('Modules/*/Module.php')) ?: [] as $file) {
            $class = 'App\\Modules\\'.basename(dirname($file)).'\\Module';

            if (! class_exists($class) || ! is_subclass_of($class, Module::class)) {
                continue;
            }

            /** @var Module $module */
            $module = new $class();

            if (! preg_match('/^[a-z][a-z0-9_]*$/', $module->key())) {
                throw new InvalidArgumentException("Invalid module key \"{$module->key()}\" in {$class} (use a-z, 0-9 and _).");
            }

            $found[$module->key()] = $module;
        }

        return $this->discovered = $this->sort($found);
    }

    /** Replace the module list (tests and tooling). */
    public function useModules(array $modules): void
    {
        $keyed = [];
        foreach ($modules as $module) {
            $keyed[$module->key()] = $module;
        }

        $this->blocked = [];
        $this->discovered = $this->sort($keyed);
        $this->flushState();
    }

    public function get(string $key): ?Module
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string,Module> modules that are on and whose dependencies are on */
    public function active(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $active = [];

        foreach ($this->all() as $key => $module) {
            if (isset($this->blocked[$key]) || ! $this->flag($module)) {
                continue;
            }

            foreach ($module->dependsOn() as $dependency) {
                if (! isset($active[$dependency])) {
                    $this->blocked[$key] = isset($this->all()[$dependency])
                        ? 'Requires “'.$this->all()[$dependency]->name().'” to be enabled.'
                        : "Requires the module “{$dependency}”, which is not installed.";

                    continue 2;
                }
            }

            $active[$key] = $module;
        }

        return $this->active = $active;
    }

    public function isActive(string $key): bool
    {
        return isset($this->active()[$key]);
    }

    /** @return array{status:string,reason:?string} */
    public function status(string $key): array
    {
        $module = $this->get($key);
        $this->active();

        return match (true) {
            $module === null => ['status' => 'missing', 'reason' => 'Module not found.'],
            isset($this->active()[$key]) => ['status' => 'active', 'reason' => null],
            ! $this->flag($module) && ! isset($this->blocked[$key]) => ['status' => 'disabled', 'reason' => null],
            default => ['status' => 'blocked', 'reason' => $this->blocked[$key] ?? 'A required module is not available.'],
        };
    }

    // ------------------------------------------------------------------ switching on/off

    /** @throws DomainException with a user-friendly message */
    public function setEnabled(string $key, bool $enabled): void
    {
        $module = $this->get($key) ?? throw new DomainException('Module not found.');

        if ($module->core()) {
            throw new DomainException("“{$module->name()}” is a core module and cannot be disabled.");
        }

        if ($enabled) {
            foreach ($module->dependsOn() as $dependency) {
                $dep = $this->get($dependency);

                if (! $dep || ! $this->flag($dep)) {
                    throw new DomainException('Enable “'.($dep?->name() ?? $dependency).'” first.');
                }
            }
        } else {
            foreach ($this->active() as $other) {
                if ($other->key() !== $key && in_array($key, $other->dependsOn(), true)) {
                    throw new DomainException("Disable “{$other->name()}” first: it needs “{$module->name()}”.");
                }
            }
        }

        $now = now();
        DB::table('modules')->updateOrInsert(
            ['key' => $key],
            ['enabled' => $enabled, 'version' => $module->version(), 'updated_at' => $now] + ['created_at' => $now, 'installed_at' => $now]
        );

        $this->flushState();
        event(new ModuleToggled($key, $enabled));
    }

    // ------------------------------------------------------------------ updates

    /** @return array<int,string> migration names that have not run yet */
    public function pendingMigrations(Module $module): array
    {
        $path = $module->path('Database/Migrations');

        if (! is_dir($path)) {
            return [];
        }

        try {
            $migrator = $this->app->make('migrator');
            $files = array_keys($migrator->getMigrationFiles($path));
            $ran = $migrator->getRepository()->repositoryExists() ? $migrator->getRepository()->getRan() : [];

            return array_values(array_diff($files, $ran));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Record installed modules and apply "first time" defaults: system roles and default permissions.
     * Runs automatically after every migration. Safe to run repeatedly.
     *
     * @return array{modules:int,roles:int,grants:int}
     */
    public function sync(): array
    {
        $summary = ['modules' => 0, 'roles' => 0, 'grants' => 0];

        try {
            if (! Schema::hasTable('modules')) {
                return $summary;
            }

            $now = now();
            foreach ($this->all() as $key => $module) {
                $exists = DB::table('modules')->where('key', $key)->exists();

                if ($exists) {
                    DB::table('modules')->where('key', $key)->update(['version' => $module->version(), 'updated_at' => $now]);
                } else {
                    DB::table('modules')->insert([
                        'key' => $key, 'enabled' => $module->core() || $module->enabledByDefault(), 'version' => $module->version(),
                        'installed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                $summary['modules']++;
            }

            $this->flushState();

            if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
                return $summary;
            }

            $permissions = $this->app->make(PermissionRegistry::class);

            foreach ($this->active() as $module) {
                foreach ($module->systemRoles() as $roleKey => $meta) {
                    $role = Role::firstOrCreate(['key' => $roleKey], [
                        'name' => $meta['name'], 'description' => $meta['description'] ?? null, 'is_system' => true,
                    ]);
                    $role->wasRecentlyCreated && $summary['roles']++;
                }
            }

            foreach ($this->active() as $key => $module) {
                $row = $this->state()[$key] ?? null;

                if ($row && $row->seeded_version === $module->version()) {
                    continue;   // already applied for this version: admins may have removed permissions since
                }

                foreach ($module->defaultPermissions() as $roleKey => $patterns) {
                    $role = Role::where('key', $roleKey)->first();

                    foreach ($role ? $permissions->expand($patterns) : [] as $permission) {
                        $grant = RolePermission::firstOrCreate(['role_id' => $role->id, 'permission' => $permission]);
                        $grant->wasRecentlyCreated && $summary['grants']++;
                    }
                }

                DB::table('modules')->where('key', $key)->update(['seeded_version' => $module->version()]);
            }

            $this->flushState();
        } catch (Throwable $e) {
            Log::warning('Module sync failed', ['error_type' => $e::class]);
        }

        return $summary;
    }

    // ------------------------------------------------------------------ settings

    public function setting(string $moduleKey, string $key, mixed $default = null): mixed
    {
        $definition = $this->get($moduleKey)?->settings()[$key] ?? [];
        $stored = SystemSetting::get("module.{$moduleKey}.{$key}");

        if ($stored === null) {
            return $default ?? ($definition['default'] ?? null);
        }

        return match ($definition['type'] ?? 'text') {
            'number' => (int) $stored,
            'bool' => $stored === '1',
            default => $stored,
        };
    }

    public function saveSetting(string $moduleKey, string $key, mixed $value): void
    {
        SystemSetting::set("module.{$moduleKey}.{$key}", is_bool($value) ? ($value ? '1' : '0') : (string) $value);
    }

    // ------------------------------------------------------------------ internals

    public function flushState(): void
    {
        $this->state = null;
        $this->active = null;
        $this->blocked = array_filter($this->blocked, fn ($reason) => $reason === 'Circular dependency.');
    }

    /** @return array<string,object> */
    private function state(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }

        try {
            return $this->state = DB::table('modules')->get()->keyBy('key')->all();
        } catch (Throwable) {
            return $this->state = [];   // before the first migration everything runs with its defaults
        }
    }

    /** Is the module switched on (ignoring dependencies)? */
    private function flag(Module $module): bool
    {
        if ($module->core()) {
            return true;
        }

        $row = $this->state()[$module->key()] ?? null;

        return $row ? (bool) $row->enabled : $module->enabledByDefault();
    }

    /**
     * Dependencies first; circular dependencies are flagged and left blocked.
     *
     * @param  array<string,Module>  $modules
     * @return array<string,Module>
     */
    private function sort(array $modules): array
    {
        ksort($modules);
        $sorted = [];
        $visiting = [];

        $visit = function (string $key) use (&$visit, &$sorted, &$visiting, $modules): void {
            if (isset($sorted[$key]) || ! isset($modules[$key])) {
                return;
            }

            if (isset($visiting[$key])) {
                $this->blocked[$key] = 'Circular dependency.';

                return;
            }

            $visiting[$key] = true;

            foreach ($modules[$key]->dependsOn() as $dependency) {
                $visit($dependency);
            }

            unset($visiting[$key]);
            $sorted[$key] = $modules[$key];
        };

        foreach (array_keys($modules) as $key) {
            $visit($key);
        }

        return $sorted;
    }
}
