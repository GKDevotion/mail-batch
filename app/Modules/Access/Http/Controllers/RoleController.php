<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Http\Requests\RoleRequest;
use App\Modules\Access\Models\Role;
use App\Support\Modules\Events\ActivityRecorded;
use App\Support\Modules\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly PermissionRegistry $registry)
    {
    }

    public function index(): View
    {
        return view('access::roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('name')->get(),
            'defaultKey' => Role::DEFAULT_KEY,
        ]);
    }

    public function create(): View
    {
        return view('access::roles.form', ['role' => new Role(), 'groups' => $this->registry->all(), 'granted' => []]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'key' => $this->uniqueKey($data['name']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $this->syncPermissions($role, $data['permissions'] ?? []);

        return redirect()->route('admin.access.roles')->with('status', "Role “{$role->name}” created.");
    }

    public function edit(Role $role): View
    {
        return view('access::roles.form', ['role' => $role, 'groups' => $this->registry->all(), 'granted' => $role->permissionNames()]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        $role->fill(['description' => $data['description'] ?? null]);

        if (! $role->is_system) {
            $role->name = $data['name'];   // system role names are fixed
        }

        $role->save();
        $this->syncPermissions($role, $data['permissions'] ?? []);

        return redirect()->route('admin.access.roles')->with('status', "Role “{$role->name}” saved.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'Built-in roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Move the users of this role to another role first.');
        }

        $role->delete();

        return back()->with('status', "Role “{$role->name}” deleted.");
    }

    /** Replace the role's permissions and leave an audit entry with what changed. */
    private function syncPermissions(Role $role, array $wanted): void
    {
        $current = $role->permissionNames();
        $added = array_values(array_diff($wanted, $current));
        $removed = array_values(array_diff($current, $wanted));

        $role->permissions()->whereIn('permission', $removed)->delete();

        foreach ($added as $permission) {
            $role->permissions()->create(['permission' => $permission]);
        }

        if ($added || $removed) {
            ActivityRecorded::record('access.permissions_changed', "Changed permissions of role “{$role->name}”", $role, ['added' => $added, 'removed' => $removed]);
        }
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'role';
        $key = $base;

        for ($i = 2; Role::where('key', $key)->exists(); $i++) {
            $key = $base.'_'.$i;
        }

        return $key;
    }
}
