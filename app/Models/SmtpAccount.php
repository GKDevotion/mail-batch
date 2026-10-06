<?php

namespace App\Models;

use App\Enums\SmtpEncryption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmtpAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'smtp_host', 'smtp_port', 'smtp_username',
        'smtp_password_encrypted', 'encryption', 'from_name', 'from_email', 'status',
        'last_tested_at', 'last_test_ok',
    ];

    /** The password must never leave the server: hidden from toArray()/toJson(). */
    protected $hidden = ['smtp_password_encrypted'];

    protected function casts(): array
    {
        return [
            // Laravel's built-in "encrypted" cast uses Crypt (APP_KEY) transparently.
            'smtp_password_encrypted' => 'encrypted',
            'encryption' => SmtpEncryption::class,
            'smtp_port' => 'integer',
            'last_tested_at' => 'datetime',
            'last_test_ok' => 'boolean',
        ];
    }

    /** Decrypted password for server-side transport building only. */
    public function decryptedPassword(): string
    {
        return (string) $this->smtp_password_encrypted;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
