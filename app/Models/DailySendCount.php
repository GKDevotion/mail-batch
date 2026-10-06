<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DailySendCount extends Model
{
    protected $fillable = ['user_id', 'date', 'sent'];

    protected function casts(): array
    {
        return ['sent' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function today(int $userId): int
    {
        return (int) static::query()->where('user_id', $userId)->where('date', now()->toDateString())->value('sent');
    }

    /** Atomic increment (insert on first send of the day). Never throws: a counter hiccup must not fail a delivered email. */
    public static function bump(int $userId, int $by = 1): void
    {
        $date = now()->toDateString();

        try {
            $updated = static::query()->where('user_id', $userId)->where('date', $date)->increment('sent', $by);

            if ($updated === 0) {
                try {
                    static::query()->create(['user_id' => $userId, 'date' => $date, 'sent' => $by]);
                } catch (QueryException) {
                    static::query()->where('user_id', $userId)->where('date', $date)->increment('sent', $by);
                }
            }
        } catch (Throwable $e) {
            Log::error('Daily counter update failed', ['user_id' => $userId, 'error_type' => $e::class]);
        }
    }
}
