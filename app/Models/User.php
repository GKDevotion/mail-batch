<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    // role / is_active / daily_send_limit are intentionally NOT mass-assignable.
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'daily_send_limit' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function smtpAccounts(): HasMany
    {
        return $this->hasMany(SmtpAccount::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function unsubscribes(): HasMany
    {
        return $this->hasMany(Unsubscribe::class);
    }

    /** Effective daily limit: per-user override, else system default. */
    public function effectiveDailyLimit(): int
    {
        return $this->daily_send_limit
            ?? (int) \App\Models\SystemSetting::get('default_daily_limit', config('mailbatch.default_daily_limit'));
    }
}
