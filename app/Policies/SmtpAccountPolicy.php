<?php

namespace App\Policies;

use App\Enums\CampaignStatus;
use App\Models\SmtpAccount;
use App\Models\User;

class SmtpAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, SmtpAccount $account): bool
    {
        return $user->id === $account->user_id;
    }

    public function update(User $user, SmtpAccount $account): bool
    {
        return $this->view($user, $account);
    }

    /** Not while a campaign is sending with it. */
    public function delete(User $user, SmtpAccount $account): bool
    {
        return $this->view($user, $account)
            && ! $account->campaigns()->where('status', CampaignStatus::Processing->value)->exists();
    }
}
