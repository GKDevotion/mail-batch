<?php

namespace App\Policies;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // lists are always scoped to the owner in the query
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->id === $campaign->user_id;
    }

    /** Structural changes (Excel file, mapping): only before the first email is attempted. */
    public function update(User $user, Campaign $campaign): bool
    {
        return $this->view($user, $campaign) && $campaign->isEditable();
    }

    /** Template, SMTP account, name, batch size: also allowed between batches, never while a batch runs. */
    public function configure(User $user, Campaign $campaign): bool
    {
        return $this->view($user, $campaign)
            && in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Ready, CampaignStatus::Paused, CampaignStatus::Failed], true);
    }

    /** Start / pause / retry. The service validates the state. */
    public function send(User $user, Campaign $campaign): bool
    {
        return $this->view($user, $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->view($user, $campaign) && $campaign->status !== CampaignStatus::Processing;
    }
}
