<?php

namespace App\Support\Modules;

use Illuminate\Console\Scheduling\Schedule;
use ReflectionClass;

/**
 * Base class of every module. A module is a folder in app/Modules/{Name}/ with a Module.php that extends this class.
 * Everything is optional except key() and name(); the framework discovers and wires up the rest by convention:
 *
 *   app/Modules/Contacts/
 *     Module.php                      metadata, permissions, menu, widgets, settings (this class)
 *     Routes/web.php                  authenticated routes (middleware: web, auth, active)
 *     Routes/public.php               public routes (middleware: web only), e.g. tracking pixel, unsubscribe
 *     Resources/views/                views, used as view('contacts::name')
 *     Database/Migrations/            migrations (picked up by `php artisan migrate` and Admin > Modules > Run updates)
 *     Http/, Models/, Services/ ...   your code, namespace App\Modules\Contacts\...
 *
 * Scaffold one with:  php artisan make:module Contacts
 */
abstract class Module
{
    /** Unique id: lower-case letters, digits and underscores. Used for view namespace, permissions and settings. */
    abstract public function key(): string;

    abstract public function name(): string;

    public function description(): string
    {
        return '';
    }

    /** Bump this to ship an update: new default permissions are applied once per version. */
    public function version(): string
    {
        return '1.0.0';
    }

    /** Bootstrap Icons name (without the "bi-" prefix). */
    public function icon(): string
    {
        return 'puzzle';
    }

    /** Core modules are always on and cannot be disabled in Admin > Modules. */
    public function core(): bool
    {
        return false;
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    /**
     * Keys of modules that must be active before this one starts.
     *
     * @return array<int,string>
     */
    public function dependsOn(): array
    {
        return [];
    }

    /**
     * Permissions this module adds: "contacts.view" => "View contacts". Check them with @can / can: middleware / Gate.
     *
     * @return array<string,string>
     */
    public function permissions(): array
    {
        return [];
    }

    /**
     * Roles this module needs to exist: "viewer" => ['name' => 'Viewer', 'description' => '...'].
     *
     * @return array<string,array{name:string,description?:string}>
     */
    public function systemRoles(): array
    {
        return [];
    }

    /**
     * Permissions granted to roles the first time this version is installed. Patterns: "contacts.*", "*", "!contacts.delete".
     *
     * @return array<string,array<int,string>>  role key => patterns
     */
    public function defaultPermissions(): array
    {
        return [];
    }

    /**
     * Sidebar entries. Keys: label, route (named route), icon, permission?, match? (e.g. "contacts.*"),
     * group? ("main" | "admin"), section? (heading in the sidebar), order?.
     *
     * @return array<int,array<string,mixed>>
     */
    public function menu(): array
    {
        return [];
    }

    /**
     * Dashboard widgets. Keys: view ("contacts::widgets.summary"), permission?, col? (bootstrap column classes), order?.
     *
     * @return array<int,array<string,mixed>>
     */
    public function widgets(): array
    {
        return [];
    }

    /**
     * Module settings (Admin > Modules > Settings). "retention_days" => ['label' => 'Keep for (days)', 'type' => 'number',
     * 'default' => 180, 'rules' => 'integer|min:1']. Types: text, number, bool, select (with 'options' => [value => label]).
     * Read with: app(ModuleManager::class)->setting('contacts', 'retention_days').
     *
     * @return array<string,array<string,mixed>>
     */
    public function settings(): array
    {
        return [];
    }

    /** @return array<int,class-string> artisan commands */
    public function commands(): array
    {
        return [];
    }

    /** Bind services into the container. Runs before anything is booted. */
    public function register(): void
    {
    }

    /** Observers, event listeners, route model bindings. Runs when the module is active. */
    public function boot(): void
    {
    }

    /** Scheduled tasks (only run if the optional cron entry exists). */
    public function schedule(Schedule $schedule): void
    {
    }

    /** Absolute path inside the module folder. */
    public function path(string $relative = ''): string
    {
        $base = dirname((string) (new ReflectionClass($this))->getFileName());

        return $relative === '' ? $base : $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
