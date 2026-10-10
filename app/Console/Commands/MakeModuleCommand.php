<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * php artisan make:module "Email Templates" --depends=access
 * Creates a working module skeleton: Module.php, route, controller, view, migration and a test.
 */
class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : Module name, e.g. Contacts or "Email Templates"}
        {--depends=* : Keys of modules this one needs (e.g. --depends=access)}
        {--core : Make it a core module (cannot be disabled)}
        {--path= : Base folder (default: app/Modules)}';

    protected $description = 'Create a new MailBatch module skeleton';

    public function handle(Filesystem $files): int
    {
        $studly = Str::studly($this->argument('name'));
        $key = Str::snake($studly);
        $title = Str::headline($studly);

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $studly)) {
            $this->error('The module name must start with a letter and contain only letters and digits.');

            return self::FAILURE;
        }

        $base = rtrim((string) ($this->option('path') ?: app_path('Modules')), '/\\');
        $dir = $base.DIRECTORY_SEPARATOR.$studly;

        if ($files->exists($dir)) {
            $this->error("Module {$studly} already exists at {$dir}");

            return self::FAILURE;
        }

        $depends = collect($this->option('depends'))->filter()->map(fn ($d) => "'".Str::snake($d)."'")->implode(', ');

        $replace = [
            '__STUDLY__' => $studly,
            '__KEY__' => $key,
            '__URL__' => str_replace('_', '-', $key),
            '__TITLE__' => $title,
            '__DEPENDS__' => $depends,
            '__CORE__' => $this->option('core') ? "\n\n    public function core(): bool\n    {\n        return true;\n    }" : '',
            '__STAMP__' => now()->format('Y_m_d_His'),
        ];

        $testDir = $this->option('path') ? $dir.DIRECTORY_SEPARATOR.'Tests' : base_path('tests/Feature/Modules');

        $targets = [
            $dir.'/Module.php' => $this->moduleStub(),
            $dir.'/Routes/web.php' => $this->routesStub(),
            $dir."/Http/Controllers/{$studly}Controller.php" => $this->controllerStub(),
            $dir.'/Resources/views/index.blade.php' => $this->viewStub(),
            $dir."/Database/Migrations/{$replace['__STAMP__']}_create_{$key}_items_table.php" => $this->migrationStub(),
            $testDir."/{$studly}ModuleTest.php" => $this->testStub(),
        ];

        foreach ($targets as $path => $stub) {
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, strtr($stub, $replace));
            $this->line('  <info>created</info> '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));
        }

        $this->newLine();
        $this->info("Module {$title} created. Next:");
        $this->line('  1. php artisan migrate        (or Admin > Modules > Run updates)');
        $this->line("  2. open /admin/modules to see it, then edit app/Modules/{$studly}/Module.php (permissions, menu, widgets)");
        $this->line("  3. php artisan test --filter={$studly}ModuleTest");

        return self::SUCCESS;
    }

    private function moduleStub(): string
    {
        return <<<'PHP'
<?php

namespace App\Modules\__STUDLY__;

use App\Support\Modules\Module as BaseModule;

class Module extends BaseModule
{
    public function key(): string
    {
        return '__KEY__';
    }

    public function name(): string
    {
        return '__TITLE__';
    }

    public function description(): string
    {
        return 'Describe what this module does.';
    }

    /** Bump to ship an update: pending migrations run on "Run updates" and new default permissions are applied once. */
    public function version(): string
    {
        return '1.0.0';
    }

    public function icon(): string
    {
        return 'puzzle';
    }__CORE__

    /** @return array<int,string> modules that must be active first */
    public function dependsOn(): array
    {
        return [__DEPENDS__];
    }

    public function permissions(): array
    {
        return [
            '__KEY__.view' => 'View __TITLE__',
            '__KEY__.manage' => 'Create, edit and delete __TITLE__',
        ];
    }

    /** Granted to these roles the first time this version is installed ("__KEY__.*" = all permissions of this module). */
    public function defaultPermissions(): array
    {
        return [
            'admin' => ['__KEY__.*'],
            'manager' => ['__KEY__.view'],
        ];
    }

    public function menu(): array
    {
        return [[
            'label' => '__TITLE__',
            'route' => '__KEY__.index',
            'match' => '__KEY__.*',
            'icon' => 'puzzle',
            'permission' => '__KEY__.view',
            'section' => 'Modules',
            'order' => 50,
        ]];
    }
}

PHP;
    }

    private function routesStub(): string
    {
        return <<<'PHP'
<?php

use App\Modules\__STUDLY__\Http\Controllers\__STUDLY__Controller;
use Illuminate\Support\Facades\Route;

// Loaded with the middleware: web, auth, active, throttle. Keep route names prefixed with the module key.
Route::prefix('__URL__')->name('__KEY__.')->middleware('can:__KEY__.view')->group(function () {
    Route::get('/', [__STUDLY__Controller::class, 'index'])->name('index');
});

PHP;
    }

    private function controllerStub(): string
    {
        return <<<'PHP'
<?php

namespace App\Modules\__STUDLY__\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class __STUDLY__Controller extends Controller
{
    public function index(): View
    {
        return view('__KEY__::index');
    }
}

PHP;
    }

    private function viewStub(): string
    {
        return <<<'BLADE'
@extends('layouts.app')
@section('title', '__TITLE__')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">__TITLE__</h1>
            <p class="text-body-secondary mb-0">Module "__KEY__" is working. Replace this page with your own.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">Hello from the __TITLE__ module.</div>
    </div>
@endsection

BLADE;
    }

    private function migrationStub(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('__KEY___items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('__KEY___items');
    }
};

PHP;
    }

    private function testStub(): string
    {
        return <<<'PHP'
<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Support\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class __STUDLY__ModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_is_discovered_and_active(): void
    {
        $this->assertTrue(app(ModuleManager::class)->isActive('__KEY__'));
    }

    public function test_page_requires_login_and_permission(): void
    {
        $this->get(route('__KEY__.index'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get(route('__KEY__.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('__KEY__.index'))->assertOk();
    }
}

PHP;
    }
}
