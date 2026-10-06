<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unsubscribe extends Model
{
    protected $fillable = ['user_id', 'campaign_id', 'email', 'token', 'unsubscribed_at'];

    protected function casts(): array
    {
        return ['unsubscribed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
