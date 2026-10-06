<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'smtp_account_id', 'name', 'subject', 'body_html', 'body_text',
        'excel_filename', 'excel_path', 'excel_size', 'excel_headers', 'excel_preview', 'column_mapping',
        'total_records', 'valid_records', 'invalid_records', 'duplicate_records',
        'eligible_records', 'sent_count', 'failed_count', 'skipped_count',
        'batch_size', 'include_unsubscribe', 'sender_identification',
        'status', 'last_error', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'excel_headers' => 'array',
            'column_mapping' => 'array',
            'excel_preview' => 'array',
            'include_unsubscribe' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ---- Relationships ----
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function smtpAccount(): BelongsTo
    {
        return $this->belongsTo(SmtpAccount::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    // ---- Derived values ----
    protected function processedCount(): Attribute
    {
        return Attribute::get(fn () => $this->sent_count + $this->failed_count + $this->skipped_count);
    }

    /** Processed / Total x 100 */
    protected function progressPercent(): Attribute
    {
        return Attribute::get(fn () => $this->total_records > 0
            ? min(100, (int) round($this->processed_count / $this->total_records * 100))
            : 0);
    }

    protected function remainingCount(): Attribute
    {
        return Attribute::get(fn () => max(0, $this->total_records - $this->processed_count));
    }

    /** The owner's choice, or the administrator's "always add unsubscribe" setting. */
    public function shouldIncludeUnsubscribe(): bool
    {
        return $this->include_unsubscribe || (bool) config('mailbatch.unsubscribe.force');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [CampaignStatus::Draft, CampaignStatus::Ready], true) && $this->started_at === null;
    }

    /** True when the wizard has everything needed before "Start Sending". */
    public function isConfigured(): bool
    {
        return $this->smtp_account_id
            && filled($this->subject)
            && filled($this->body_html)
            && ! empty($this->column_mapping['email'] ?? null);
    }
}
