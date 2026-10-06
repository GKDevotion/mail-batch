<?php

namespace App\Services;

use App\Models\SmtpAccount;
use App\Models\User;
use Illuminate\Support\Str;

class SmtpAccountService
{
    /** @param array<string,mixed> $data validated input */
    public function create(User $user, array $data): SmtpAccount
    {
        return SmtpAccount::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'smtp_host' => Str::lower($data['smtp_host']),
            'smtp_port' => (int) $data['smtp_port'],
            'smtp_username' => $data['smtp_username'],
            'smtp_password_encrypted' => $data['smtp_password'],   // encrypted by the model cast
            'encryption' => $data['encryption'],
            'from_name' => $data['from_name'],
            'from_email' => Str::lower($data['from_email']),
            'status' => 'active',
        ]);
    }

    /** @param array<string,mixed> $data validated input; blank password keeps the saved one */
    public function update(SmtpAccount $account, array $data): SmtpAccount
    {
        $account->fill([
            'name' => $data['name'],
            'smtp_host' => Str::lower($data['smtp_host']),
            'smtp_port' => (int) $data['smtp_port'],
            'smtp_username' => $data['smtp_username'],
            'encryption' => $data['encryption'],
            'from_name' => $data['from_name'],
            'from_email' => Str::lower($data['from_email']),
        ]);

        if (! empty($data['smtp_password'])) {
            $account->smtp_password_encrypted = $data['smtp_password'];
        }

        // Changed connection settings invalidate the previous test result.
        if ($account->isDirty(['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password_encrypted', 'encryption'])) {
            $account->last_tested_at = null;
            $account->last_test_ok = null;
        }

        $account->save();

        return $account;
    }
}
