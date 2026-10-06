<?php

namespace App\Enums;

enum SkipReason: string
{
    case MissingEmail = 'missing_email';
    case InvalidEmail = 'invalid_email';
    case Duplicate = 'duplicate';
    case AlreadyMarkedSent = 'already_marked_sent';
    case UnknownStatus = 'unknown_status';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return match ($this) {
            self::MissingEmail => 'Missing email',
            self::InvalidEmail => 'Invalid email',
            self::Duplicate => 'Duplicate email',
            self::AlreadyMarkedSent => 'Already sent (status = 1)',
            self::UnknownStatus => 'Unrecognised status value',
            self::Unsubscribed => 'Unsubscribed',
        };
    }
}
