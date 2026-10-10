<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Support\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_manager_page_is_for_system_managers_only(): void
    {
        $this->get(route('admin.modules.index'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get(route('admin.modules.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role_id' => Role::idForKey('manager')]))->get(route('admin.modules.index'))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.modules.index'))
            ->assertOk()->assertSee('Roles &amp; Permissions', false)->assertSee('Activity Log')->assertSee('Run updates');
    }

    public function test_optional_modules_can_be_switched_off_and_on(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = app(ModuleManager::class);

        $this->actingAs($admin)->post(route('admin.modules.toggle', 'activitylog'), ['enabled' => 0])->assertSessionHas('status');
        $this->assertSame('disabled', $manager->status('activitylog')['status']);
        $this->assertDatabaseHas('modules', ['key' => 'activitylog', 'enabled' => false]);

        $this->actingAs($admin)->post(route('admin.modules.toggle', 'activitylog'), ['enabled' => 1])->assertSessionHas('status');
        $this->assertSame('active', $manager->status('activitylog')['status']);
    }

    public function test_core_modules_refuse_to_be_disabled(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.modules.toggle', 'access'), ['enabled' => 0])
            ->assertSessionHas('error');

        $this->assertSame('active', app(ModuleManager::class)->status('access')['status']);
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.modules.toggle', 'nope'), ['enabled' => 0])->assertNotFound();
    }

    public function test_run_updates_applies_migrations(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.modules.migrate'))
            ->assertRedirect()->assertSessionHas('status', 'Updates finished.')->assertSessionHas('migrate_output');
    }

    public function test_module_settings_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.modules.settings', 'activitylog'))->assertOk()->assertSee('Keep entries for');

        $this->actingAs($admin)->put(route('admin.modules.settings.update', 'activitylog'), ['retention_days' => 30])->assertSessionHas('status');
        $this->assertSame(30, app(ModuleManager::class)->setting('activitylog', 'retention_days'));

        $this->actingAs($admin)->put(route('admin.modules.settings.update', 'activitylog'), ['retention_days' => 3])->assertSessionHasErrors('retention_days');

        $this->actingAs($admin)->get(route('admin.modules.settings', 'access'))->assertNotFound();   // no settings declared
    }
}
