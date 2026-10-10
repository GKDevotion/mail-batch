<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Access\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserRoleController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());

        return view('access::users.index', [
            'users' => User::query()->with('accessRole:id,name')
                ->when($search !== '', function ($q) use ($search) {
                    $like = '%'.addcslashes($search, '%_\\').'%';
                    $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
                })
                ->orderBy('name')->paginate(25)->withQueryString(),
            'roles' => Role::orderBy('name')->get(['id', 'key', 'name']),
            'search' => $search,
            'defaultName' => Role::query()->where('key', Role::DEFAULT_KEY)->value('name'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Super Admins already have every permission. Change the Admin flag on Admin > Users instead.');
        }

        $data = $request->validate(['role_id' => ['nullable', 'integer', 'exists:roles,id']]);

        $user->forceFill(['role_id' => $data['role_id'] ?? null])->save();
        User::flushPermissionCache();

        return back()->with('status', "Role of {$user->email} updated.");
    }
}
