<?php

namespace App\Modules\ActivityLog\Services;

use App\Models\User;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Support\UserAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ActivityLogger
{
    /**
     * Write one entry. Never throws: auditing must not break the action being audited.
     * Secrets are removed from $properties (any key containing password, token or secret).
     *
     * @param  array<string,mixed>  $properties
     */
    public static function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?User $user = null): void
    {
        try {
            $request = request();
            $user ??= auth()->user();
            $agent = UserAgent::parse((string) $request->userAgent());

            ActivityLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'module' => Str::before($action, '.'),
                'action' => $action,
                'description' => Str::limit($description, 480, '…'),
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'properties' => $properties === [] ? null : self::scrub($properties),
                'ip' => $request->ip(),
                'browser' => $agent['browser'] ?: null,
                'os' => $agent['os'] ?: null,
                'device' => $agent['device'] ?: null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Activity log write failed', ['action' => $action, 'error_type' => $e::class]);
        }
    }

    /** @param array<string,mixed> $data */
    private static function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/password|token|secret/i', $key)) {
                $data[$key] = '[hidden]';
            } elseif (is_array($value)) {
                $data[$key] = self::scrub($value);
            }
        }

        return $data;
    }
}
