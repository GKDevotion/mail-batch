<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Modules\Access\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessModuleTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleKey): User
    {
        return User::factory()->create(['role_id' => Role::idForKey($roleKey)]);
    }

    public function test_built_in_roles_exist_with_their_defaults(): void
    {
        foreach (['admin', 'manager', 'campaign_manager', 'content_manager', 'viewer'] as $key) {
            $this->assertTrue(Role::where('key', $key)->where('is_system', true)->exists(), $key);
        }

        $this->assertContains('activity.view', Role::where('key', 'admin')->first()->permissionNames());
        $this->assertContains('activity.view', Role::where('key', 'manager')->first()->permissionNames());
        $this->assertSame([], Role::where('key', 'viewer')->first()->permissionNames());
    }

    public function test_permissions_follow_the_role(): void
    {
        $this->assertTrue($this->userWithRole('manager')->hasPermission('activity.view'));
        $this->assertFalse($this->userWithRole('viewer')->hasPermission('activity.view'));
        $this->assertFalse(User::factory()->create()->hasPermission('activity.view'));          // default role: Campaign Manager
        $this->assertTrue(User::factory()->admin()->create()->hasPermission('anything.at.all'));  // Super Admin
    }

    public function test_only_people_with_access_manage_open_the_pages(): void
    {
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.access.roles'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.access.users'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.access.roles'))->assertOk()->assertSee('Campaign Manager');

        $custom = Role::create(['key' => 'role_admins', 'name' => 'Role admins', 'is_system' => false]);
        $custom->permissions()->create(['permission' => 'access.manage']);
        $user = User::factory()->create(['role_id' => $custom->id]);

        $this->actingAs($user)->get(route('admin.access.roles'))->assertOk();
    }

    public function test_create_a_custom_role_with_permissions(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.access.roles.store'), [
            'name' => 'Auditors', 'description' => 'Read the log', 'permissions' => ['activity.view'],
        ])->assertRedirect(route('admin.access.roles'));

        $role = Role::where('name', 'Auditors')->firstOrFail();
        $this->assertFalse($role->is_system);
        $this->assertSame(['activity.view'], $role->permissionNames());
        $this->assertSame('auditors', $role->key);

        $this->actingAs($admin)->post(route('admin.access.roles.store'), ['name' => 'Auditors'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('admin.access.roles.store'), ['name' => 'Bad', 'permissions' => ['made.up']])->assertSessionHasErrors('permissions.*');
    }

    public function test_editing_a_role_replaces_permissions_and_applies_immediately(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = Role::where('key', 'viewer')->firstOrFail();
        $user = User::factory()->create(['role_id' => $viewer->id]);

        $this->assertFalse($user->hasPermission('activity.view'));

        $this->actingAs($admin)->put(route('admin.access.roles.update', $viewer), [
            'name' => 'Renamed?', 'description' => 'x', 'permissions' => ['activity.view'],
        ])->assertRedirect();

        $this->assertSame('Viewer', $viewer->fresh()->name);     // built-in names are fixed
        $this->assertTrue($user->fresh()->hasPermission('activity.view'));

        $this->actingAs($admin)->put(route('admin.access.roles.update', $viewer), ['name' => 'Viewer'])->assertRedirect();
        $this->assertFalse($user->fresh()->hasPermission('activity.view'));
    }

    public function test_deleting_roles_is_guarded(): void
    {
        $admin = User::factory()->admin()->create();
        $custom = Role::create(['key' => 'temp', 'name' => 'Temp', 'is_system' => false]);

        $this->actingAs($admin)->delete(route('admin.access.roles.destroy', Role::where('key', 'viewer')->first()))->assertSessionHas('error');
        $this->assertTrue(Role::where('key', 'viewer')->exists());

        User::factory()->create(['role_id' => $custom->id]);
        $this->actingAs($admin)->delete(route('admin.access.roles.destroy', $custom))->assertSessionHas('error');
        $this->assertTrue(Role::where('key', 'temp')->exists());

        User::where('role_id', $custom->id)->update(['role_id' => null]);
        $this->actingAs($admin)->delete(route('admin.access.roles.destroy', $custom))->assertSessionHas('status');
        $this->assertFalse(Role::where('key', 'temp')->exists());
    }

    public function test_assigning_roles_to_users(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $manager = Role::where('key', 'manager')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.access.users'))->assertOk()->assertSee($user->email);

        $this->actingAs($admin)->put(route('admin.access.users.update', $user), ['role_id' => $manager->id])->assertSessionHas('status');
        $this->assertSame($manager->id, $user->fresh()->role_id);

        $this->actingAs($admin)->put(route('admin.access.users.update', $user), ['role_id' => ''])->assertSessionHas('status');
        $this->assertNull($user->fresh()->role_id);

        $this->actingAs($admin)->put(route('admin.access.users.update', $user), ['role_id' => 9999])->assertSessionHasErrors('role_id');
        $this->actingAs($admin)->put(route('admin.access.users.update', User::factory()->admin()->create()), ['role_id' => $manager->id])->assertSessionHas('error');
    }
}
