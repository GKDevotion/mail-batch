<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Admin-editable key/value settings. One cached read serves all keys (5 minutes, busted on write). */
class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /** @return array<string,?string> */
    public static function allSettings(): array
    {
        return Cache::remember('mailbatch.settings', 300, fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allSettings()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('mailbatch.settings');
    }

    /** Overlay the stored settings on config/mailbatch.php (called at boot, before every queue job, and after saving). */
    public static function applyToConfig(): void
    {
        $s = static::allSettings();
        $hard = max(1, (int) config('mailbatch.hard_max_batch_size'));

        if (isset($s['max_batch_size'])) {
            config(['mailbatch.max_batch_size' => max(1, min($hard, (int) $s['max_batch_size']))]);
        }
        if (isset($s['default_daily_limit'])) {
            config(['mailbatch.default_daily_limit' => max(0, (int) $s['default_daily_limit'])]);
        }
        if (isset($s['max_retries'])) {
            config(['mailbatch.max_retries' => max(1, min(10, (int) $s['max_retries']))]);
        }
        if (isset($s['force_unsubscribe'])) {
            config(['mailbatch.unsubscribe.force' => $s['force_unsubscribe'] === '1']);
        }
    }
}
