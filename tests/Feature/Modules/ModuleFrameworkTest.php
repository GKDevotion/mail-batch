<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Support\Modules\MenuRegistry;
use App\Support\Modules\Module;
use App\Support\Modules\ModuleManager;
use App\Support\Modules\PermissionRegistry;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ModuleFrameworkTest extends TestCase
{
    use RefreshDatabase;

    private function fake(string $key, array $deps = [], bool $core = false): Module
    {
        return new class($key, $deps, $core) extends Module
        {
            public function __construct(private string $k, private array $deps, private bool $isCore)
            {
            }

            public function key(): string
            {
                return $this->k;
            }

            public function name(): string
            {
                return ucfirst($this->k);
            }

            public function dependsOn(): array
            {
                return $this->deps;
            }

            public function core(): bool
            {
                return $this->isCore;
            }
        };
    }

    private function manager(Module ...$modules): ModuleManager
    {
        $manager = new ModuleManager(app());
        $manager->useModules($modules);

        return $manager;
    }

    public function test_core_modules_are_discovered_and_active(): void
    {
        $manager = app(ModuleManager::class);

        foreach (['system', 'access', 'activitylog'] as $key) {
            $this->assertNotNull($manager->get($key), "{$key} missing");
            $this->assertSame('active', $manager->status($key)['status']);
        }
    }

    public function test_dependencies_are_started_first_and_missing_ones_block(): void
    {
        $manager = $this->manager($this->fake('b', ['a']), $this->fake('a'), $this->fake('c', ['ghost']));

        $this->assertSame(['a', 'b', 'c'], array_keys($manager->all()));
        $this->assertSame('active', $manager->status('b')['status']);
        $this->assertSame('blocked', $manager->status('c')['status']);
        $this->assertStringContainsString('not installed', $manager->status('c')['reason']);
    }

    public function test_a_disabled_dependency_blocks_dependents(): void
    {
        $manager = $this->manager($this->fake('a'), $this->fake('b', ['a']));
        DB::table('modules')->insert(['key' => 'a', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
        $manager->flushState();

        $this->assertSame('disabled', $manager->status('a')['status']);
        $this->assertSame('blocked', $manager->status('b')['status']);
        $this->assertStringContainsString('enabled', $manager->status('b')['reason']);
    }

    public function test_circular_dependencies_do_not_loop_forever(): void
    {
        $manager = $this->manager($this->fake('x', ['y']), $this->fake('y', ['x']));

        $this->assertSame('blocked', $manager->status('x')['status']);
        $this->assertStringContainsString('Circular', $manager->status('x')['reason']);
        $this->assertSame([], $manager->active());
    }

    public function test_core_modules_cannot_be_disabled(): void
    {
        $this->expectException(DomainException::class);

        app(ModuleManager::class)->setEnabled('access', false);
    }

    public function test_switching_respects_dependencies(): void
    {
        $manager = $this->manager($this->fake('a'), $this->fake('b', ['a']));

        try {
            $manager->setEnabled('a', false);
            $this->fail('a is needed by b');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Disable', $e->getMessage());
        }

        $manager->setEnabled('b', false);
        $manager->setEnabled('a', false);
        $this->assertSame('disabled', $manager->status('a')['status']);

        try {
            $manager->setEnabled('b', true);
            $this->fail('b needs a');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Enable', $e->getMessage());
        }

        $manager->setEnabled('a', true);
        $manager->setEnabled('b', true);
        $this->assertSame('active', $manager->status('b')['status']);
    }

    public function test_permissions_become_gates(): void
    {
        $names = app(PermissionRegistry::class)->names();
        $this->assertContains('activity.view', $names);
        $this->assertContains('access.manage', $names);
        $this->assertContains('system.manage', $names);

        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('activity.view'));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('activity.view'));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('does.not.exist'));
    }

    public function test_permission_patterns(): void
    {
        $registry = new PermissionRegistry();
        $registry->register('contacts', 'Contacts', ['contacts.view' => 'View', 'contacts.delete' => 'Delete']);
        $registry->register('tpl', 'Templates', ['tpl.view' => 'View']);

        $this->assertEqualsCanonicalizing(['contacts.view', 'contacts.delete'], $registry->expand(['contacts.*']));
        $this->assertSame(['contacts.view'], $registry->expand(['contacts.*', '!contacts.delete']));
        $this->assertCount(3, $registry->expand(['*']));
        $this->assertSame(['tpl.view'], $registry->expand(['*', '!contacts.*']));
    }

    public function test_menu_only_lists_what_the_user_may_open(): void
    {
        $menu = app(MenuRegistry::class);
        $labels = fn (?User $u) => array_column($menu->items($u, 'admin'), 'label');

        $this->assertEqualsCanonicalizing(['Activity log', 'Modules', 'Roles & access'], $labels(User::factory()->admin()->create()));
        $this->assertSame([], $labels(User::factory()->create()));

        $manager = User::factory()->create(['role_id' => Role::idForKey('manager')]);
        $this->assertSame(['Activity log'], $labels($manager));

        $this->actingAs($manager)->get('/dashboard')->assertOk()->assertSee(route('admin.activity.index'), false);
    }

    public function test_settings_fall_back_to_defaults_and_can_be_saved(): void
    {
        $manager = app(ModuleManager::class);

        $this->assertSame(180, $manager->setting('activitylog', 'retention_days'));

        $manager->saveSetting('activitylog', 'retention_days', 30);
        $this->assertSame(30, $manager->setting('activitylog', 'retention_days'));
    }

    public function test_sync_grants_defaults_once_per_version(): void
    {
        $manager = app(ModuleManager::class);
        $role = Role::where('key', 'manager')->firstOrFail();
        $this->assertContains('activity.view', $role->permissionNames());

        $role->permissions()->where('permission', 'activity.view')->delete();
        $manager->sync();   // same version: an admin's removal is respected

        $this->assertNotContains('activity.view', $role->fresh()->permissionNames());
        $this->assertSame(0, $manager->sync()['grants']);
    }

    public function test_make_module_creates_a_complete_skeleton(): void
    {
        $tmp = sys_get_temp_dir().'/mb_modules_'.uniqid();

        $this->artisan('make:module', ['name' => 'Demo Items', '--path' => $tmp, '--depends' => ['access']])->assertSuccessful();

        $dir = $tmp.'/DemoItems';
        foreach (['Module.php', 'Routes/web.php', 'Http/Controllers/DemoItemsController.php', 'Resources/views/index.blade.php', 'Tests/DemoItemsModuleTest.php'] as $file) {
            $this->assertFileExists("{$dir}/{$file}");
        }
        $this->assertNotEmpty(glob($dir.'/Database/Migrations/*_create_demo_items_items_table.php'));

        $module = File::get($dir.'/Module.php');
        $this->assertStringContainsString("return 'demo_items';", $module);
        $this->assertStringContainsString("['access']", $module);
        $this->assertStringContainsString("'demo_items.view'", $module);

        $this->artisan('make:module', ['name' => 'Demo Items', '--path' => $tmp])->assertFailed();   // never overwrites

        File::deleteDirectory($tmp);
    }
}
