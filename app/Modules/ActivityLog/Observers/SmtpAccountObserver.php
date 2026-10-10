<?php

namespace App\Modules\ActivityLog\Observers;

use App\Models\SmtpAccount;
use App\Modules\ActivityLog\Services\ActivityLogger;

/** Logs which SMTP fields changed. Credentials are never written, only the fact that the password changed. */
class SmtpAccountObserver
{
    private const VISIBLE = ['name', 'smtp_host', 'smtp_port', 'smtp_username', 'encryption', 'from_name', 'from_email', 'status'];

    public function created(SmtpAccount $account): void
    {
        ActivityLogger::log('smtp.created', "SMTP account “{$account->name}” added ({$account->smtp_host}:{$account->smtp_port})", $account);
    }

    public function updated(SmtpAccount $account): void
    {
        $changes = array_keys($account->getChanges());

        if (in_array('last_test_ok', $changes, true) && $account->last_test_ok !== null) {
            ActivityLogger::log('smtp.tested', "SMTP account “{$account->name}” test ".($account->last_test_ok ? 'passed' : 'failed'), $account, ['ok' => (bool) $account->last_test_ok]);
        }

        $fields = array_values(array_intersect($changes, self::VISIBLE));
        $passwordChanged = in_array('smtp_password_encrypted', $changes, true);

        if ($fields !== [] || $passwordChanged) {
            ActivityLogger::log('smtp.updated', "Updated SMTP account “{$account->name}”", $account, ['fields' => $fields, 'credentials_changed' => $passwordChanged]);
        }
    }

    public function deleted(SmtpAccount $account): void
    {
        ActivityLogger::log('smtp.deleted', "Deleted SMTP account “{$account->name}”", null, ['id' => $account->id]);
    }
}
