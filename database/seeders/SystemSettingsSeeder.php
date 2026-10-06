<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'max_batch_size' => (string) config('mailbatch.max_batch_size'),
            'default_daily_limit' => (string) config('mailbatch.default_daily_limit'),
            'max_retries' => (string) config('mailbatch.max_retries'),
            'force_unsubscribe' => '0',
        ];

        foreach ($defaults as $key => $value) {
            SystemSetting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
