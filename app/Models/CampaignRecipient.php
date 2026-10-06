<?php

namespace App\Models;

use App\Enums\RecipientState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'row_number', 'name', 'email', 'website', 'contact',
        'status', 'is_valid_email', 'skip_reason', 'retry_count',
        'last_attempt_at', 'queued_at', 'sending_at', 'sent_at', 'error_message', 'dedupe_key', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'is_valid_email' => 'boolean',
            'retry_count' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'queued_at' => 'datetime',
            'sending_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class, 'recipient_id');
    }

    // ---- Scopes ----

    /**
     * Recipients that may be emailed: status NULL/0, never sent, valid email, not skipped,
     * and below the retry ceiling. status = 1 is NEVER eligible.
     */
    public function scopeEligible(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', 0))
            ->whereNull('sent_at')
            ->whereNull('skip_reason')
            ->where('is_valid_email', true)
            ->where('retry_count', '<', (int) config('mailbatch.max_retries'));
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 1)->whereNotNull('sent_at');
    }

    public function scopeSkipped(Builder $query): Builder
    {
        return $query->whereNotNull('skip_reason');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereNull('skip_reason')
            ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', 0))
            ->whereNotNull('error_message');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('skip_reason')
            ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', 0))
            ->whereNull('error_message')
            ->whereNull('sent_at');
    }

    /** Not claimed by a batch, not mid-send, valid address. */
    public function scopeQueueable(Builder $query): Builder
    {
        return $query->whereNull('queued_at')->whereNull('sending_at')->where('is_valid_email', true);
    }

    /** Failed rows that may still be retried (never status 1, never beyond the retry cap). */
    public function scopeRetryable(Builder $query): Builder
    {
        return $query->failed()->whereNull('sent_at')->where('retry_count', '<', (int) config('mailbatch.max_retries'));
    }

    /** Table filter; mirrors the counter rules (skipped > sent > failed > pending). */
    public function scopeInState(Builder $query, string $state): Builder
    {
        return match ($state) {
            'skipped' => $query->whereNotNull('skip_reason'),
            'sent' => $query->whereNull('skip_reason')->where(fn (Builder $q) => $q->where('status', 1)->orWhereNotNull('sent_at')),
            'failed' => $query->failed()->whereNull('sent_at'),
            'pending' => $query->pending(),
            default => $query,
        };
    }

    // ---- Helpers ----

    public function alreadySent(): bool
    {
        return $this->status === 1 || $this->sent_at !== null;
    }

    public function canRetry(): bool
    {
        return ! $this->alreadySent()
            && $this->skip_reason === null
            && $this->is_valid_email
            && $this->retry_count < (int) config('mailbatch.max_retries');
    }

    protected function state(): Attribute
    {
        return Attribute::get(function (): RecipientState {
            if ($this->skip_reason !== null) {
                return RecipientState::Skipped;
            }
            if ($this->alreadySent()) {
                return RecipientState::Sent;
            }
            if ($this->error_message !== null) {
                return RecipientState::Failed;
            }

            return RecipientState::Pending;
        });
    }
}
