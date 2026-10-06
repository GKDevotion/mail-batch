<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Processing = 'processing';
    case Completed = 'completed';
    case Paused = 'paused';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Ready => 'info',
            self::Processing => 'primary',
            self::Completed => 'success',
            self::Paused => 'warning',
            self::Failed => 'danger',
        };
    }
}
