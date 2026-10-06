<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'values' => [
                'max_batch_size' => (int) config('mailbatch.max_batch_size'),
                'default_daily_limit' => (int) config('mailbatch.default_daily_limit'),
                'max_retries' => (int) config('mailbatch.max_retries'),
                'force_unsubscribe' => (bool) config('mailbatch.unsubscribe.force'),
            ],
            'hardMax' => (int) config('mailbatch.hard_max_batch_size'),
        ]);
    }

    public function update(SystemSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        SystemSetting::set('max_batch_size', (string) $data['max_batch_size']);
        SystemSetting::set('default_daily_limit', (string) $data['default_daily_limit']);
        SystemSetting::set('max_retries', (string) $data['max_retries']);
        SystemSetting::set('force_unsubscribe', $data['force_unsubscribe'] ? '1' : '0');

        SystemSetting::applyToConfig();

        return back()->with('status', 'Settings saved. Running queue workers pick them up within a few minutes.');
    }
}
