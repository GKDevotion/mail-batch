<?php

namespace App\Enums;

/** Display state derived from recipient columns (not stored). */
enum RecipientState: string
{
    case Sent = 'sent';
    case Pending = 'pending';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Sent => 'success',
            self::Pending => 'secondary',
            self::Failed => 'danger',
            self::Skipped => 'warning',
        };
    }
}
