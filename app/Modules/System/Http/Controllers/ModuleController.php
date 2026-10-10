<?php

namespace App\Modules\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Modules\Events\ActivityRecorded;
use App\Support\Modules\ModuleManager;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ModuleController extends Controller
{
    public function __construct(private readonly ModuleManager $modules)
    {
    }

    public function index(): View
    {
        $rows = [];

        foreach ($this->modules->all() as $key => $module) {
            $rows[] = [
                'module' => $module,
                'status' => $this->modules->status($key),
                'pending' => $this->modules->pendingMigrations($module),
                'dependents' => collect($this->modules->all())->filter(fn ($m) => in_array($key, $m->dependsOn(), true))->map(fn ($m) => $m->name())->values()->all(),
            ];
        }

        return view('system::modules.index', [
            'rows' => $rows,
            'pendingTotal' => collect($rows)->sum(fn ($r) => count($r['pending'])),
        ]);
    }

    public function toggle(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $module = $this->modules->get($key) ?? abort(404);

        try {
            $this->modules->setEnabled($key, (bool) $data['enabled']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "“{$module->name()}” ".($data['enabled'] ? 'enabled' : 'disabled').'. The menu updates on the next page load.');
    }

    /** Same as `php artisan migrate`, for hosts without a terminal. Back up the database first. */
    public function migrate(): RedirectResponse
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());
            $this->modules->sync();
        } catch (Throwable $e) {
            Log::error('Module updates failed', ['error_type' => $e::class]);

            return back()->with('error', 'The update failed: '.Str::limit($e->getMessage(), 300));
        }

        ActivityRecorded::record('system.updates_run', 'Ran module updates (database migrations)');

        return back()->with('status', 'Updates finished.')->with('migrate_output', $output);
    }

    public function settings(string $key): View
    {
        $module = $this->modules->get($key);
        abort_unless($module && $module->settings() !== [], 404);

        $values = [];
        foreach ($module->settings() as $name => $definition) {
            $values[$name] = $this->modules->setting($key, $name);
        }

        return view('system::modules.settings', ['module' => $module, 'definitions' => $module->settings(), 'values' => $values]);
    }

    public function updateSettings(Request $request, string $key): RedirectResponse
    {
        $module = $this->modules->get($key);
        abort_unless($module && $module->settings() !== [], 404);

        $rules = [];
        foreach ($module->settings() as $name => $definition) {
            $rules[$name] = $definition['rules'] ?? match ($definition['type'] ?? 'text') {
                'number' => ['required', 'integer'],
                'bool' => ['boolean'],
                'select' => ['required', Rule::in(array_keys($definition['options'] ?? []))],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        $data = $request->validate($rules);

        foreach ($module->settings() as $name => $definition) {
            $value = ($definition['type'] ?? 'text') === 'bool' ? $request->boolean($name) : ($data[$name] ?? '');
            $this->modules->saveSetting($key, $name, $value);
        }

        ActivityRecorded::record('system.settings_saved', "Saved settings of module “{$module->name()}”", null, ['module' => $key]);

        return back()->with('status', 'Settings saved.');
    }
}
