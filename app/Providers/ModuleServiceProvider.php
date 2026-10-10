<?php

namespace App\Providers;

use App\Support\Modules\MenuRegistry;
use App\Support\Modules\ModuleManager;
use App\Support\Modules\PermissionRegistry;
use App\Support\Modules\WidgetRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Wires every active module into the application. See app/Support/Modules/Module.php for the folder convention. */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class, fn ($app) => new ModuleManager($app));
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(MenuRegistry::class);
        $this->app->singleton(WidgetRegistry::class);

        foreach ($this->app->make(ModuleManager::class)->active() as $module) {
            $module->register();
        }
    }

    public function boot(): void
    {
        $manager = $this->app->make(ModuleManager::class);
        $permissions = $this->app->make(PermissionRegistry::class);
        $menu = $this->app->make(MenuRegistry::class);
        $widgets = $this->app->make(WidgetRegistry::class);

        foreach ($manager->active() as $module) {
            $permissions->register($module->key(), $module->name(), $module->permissions());
            $menu->add($module->key(), $module->menu());
            $widgets->add($module->key(), $module->widgets());

            if (is_dir($views = $module->path('Resources/views'))) {
                $this->loadViewsFrom($views, $module->key());
            }

            if (is_dir($migrations = $module->path('Database/Migrations'))) {
                $this->loadMigrationsFrom($migrations);
            }

            if (! $this->app->routesAreCached()) {
                if (is_file($routes = $module->path('Routes/web.php'))) {
                    Route::middleware(['web', 'auth', 'active', 'throttle:240,1'])->group($routes);
                }

                if (is_file($routes = $module->path('Routes/public.php'))) {
                    Route::middleware('web')->group($routes);
                }
            }

            if ($this->app->runningInConsole() && $module->commands() !== []) {
                $this->commands($module->commands());
            }

            $module->boot();
        }

        // one Gate ability per permission: @can('contacts.view'), can: middleware, Gate::allows()
        foreach ($permissions->names() as $permission) {
            Gate::define($permission, fn ($user) => method_exists($user, 'hasPermission') && $user->hasPermission($permission));
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($manager) {
            foreach ($manager->active() as $module) {
                $module->schedule($schedule);
            }
        });

        // after any migration (artisan migrate, migrate:fresh, Admin > Modules > Run updates)
        Event::listen(MigrationsEnded::class, fn () => $manager->sync());

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\MakeModuleCommand::class,
                \App\Console\Commands\ModulesListCommand::class,
                \App\Console\Commands\ModulesSyncCommand::class,
            ]);
        }
    }
}
