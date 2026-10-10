<?php

namespace App\Support\Modules\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Fire this from any module to write an audit entry:
 *   ActivityRecorded::record('contacts.imported', 'Imported 5,000 contacts', $list, ['rows' => 5000]);
 * The ActivityLog module listens; if it is disabled nothing happens.
 */
class ActivityRecorded
{
    /** @param array<string,mixed> $properties */
    public function __construct(
        public readonly string $action,
        public readonly string $description,
        public readonly ?Model $subject = null,
        public readonly array $properties = [],
    ) {
    }

    /** @param array<string,mixed> $properties */
    public static function record(string $action, string $description, ?Model $subject = null, array $properties = []): void
    {
        event(new self($action, $description, $subject, $properties));
    }
}
