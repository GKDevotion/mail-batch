<?php

namespace App\Modules\ActivityLog\Observers;

use App\Models\Campaign;
use App\Modules\ActivityLog\Services\ActivityLogger;
use BackedEnum;

class CampaignObserver
{
    public function created(Campaign $campaign): void
    {
        ActivityLogger::log('campaign.created', "Created campaign “{$campaign->name}”", $campaign);
    }

    public function updated(Campaign $campaign): void
    {
        $changes = $campaign->getChanges();

        if (array_key_exists('status', $changes)) {
            $old = $campaign->getOriginal('status');
            $old = $old instanceof BackedEnum ? $old->value : $old;
            $new = $campaign->status instanceof BackedEnum ? $campaign->status->value : $campaign->status;

            if ($old !== $new) {
                ActivityLogger::log('campaign.status_changed', "Campaign “{$campaign->name}” changed from {$old} to {$new}", $campaign, ['from' => $old, 'to' => $new]);
            }
        }

        if (array_key_exists('subject', $changes) || array_key_exists('body_html', $changes)) {
            ActivityLogger::log('campaign.template_updated', "Updated the email of campaign “{$campaign->name}”", $campaign);
        }
    }

    public function deleted(Campaign $campaign): void
    {
        ActivityLogger::log('campaign.deleted', "Deleted campaign “{$campaign->name}”", null, ['id' => $campaign->id]);
    }
}
