<?php

namespace App\Enums;

enum SmtpEncryption: string
{
    case Ssl = 'ssl';
    case Tls = 'tls';

    /** Suggested default port only. Providers differ; users may override. */
    public function suggestedPort(): int
    {
        return match ($this) {
            self::Ssl => 465,
            self::Tls => 587,
        };
    }

    public function label(): string
    {
        return strtoupper($this->value);
    }
}
