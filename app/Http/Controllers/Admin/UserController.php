<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Campaign;
use App\Models\DailySendCount;
use App\Models\User;
use App\Services\CampaignService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns)
    {
    }

    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());

        $users = User::query()
            ->withCount(['campaigns', 'smtpAccounts'])
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'sentToday' => DailySendCount::where('date', now()->toDateString())
                ->whereIn('user_id', $users->pluck('id'))->pluck('sent', 'user_id'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->forceFill([
            'role' => UserRole::from($data['role']),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'daily_send_limit' => $data['daily_send_limit'] ?? null,
        ])->save();

        return back()->with('status', "User {$user->email} created.");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $role = UserRole::from($data['role']);
        $active = (bool) $data['is_active'];
        $losesAdmin = $role !== UserRole::Admin || ! $active;

        if ($user->is($request->user()) && $losesAdmin) {
            return back()->with('error', 'You cannot remove your own admin role or disable your own account.');
        }

        if ($user->isAdmin() && $user->is_active && $losesAdmin && $this->activeAdmins() <= 1) {
            return back()->with('error', 'At least one active administrator is required.');
        }

        $user->forceFill(['role' => $role, 'is_active' => $active, 'daily_send_limit' => $data['daily_send_limit'] ?? null]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];   // hashed by the model cast
        }

        $user->save();

        if (! $active) {
            // A disabled user must not keep sending.
            Campaign::where('user_id', $user->id)->where('status', 'processing')->each(fn (Campaign $c) => $this->campaigns->pause($c));
        }

        return back()->with('status', "User {$user->email} updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isAdmin() && $user->is_active && $this->activeAdmins() <= 1) {
            return back()->with('error', 'At least one active administrator is required.');
        }

        try {
            $user->campaigns->each(fn (Campaign $c) => $this->campaigns->delete($c));   // also removes uploaded files
        } catch (DomainException $e) {
            return back()->with('error', 'This user has a campaign that is sending. Pause it first. '.$e->getMessage());
        }

        $user->delete();

        return back()->with('status', 'User deleted.');
    }

    private function activeAdmins(): int
    {
        return User::where('role', UserRole::Admin->value)->where('is_active', true)->count();
    }
}
