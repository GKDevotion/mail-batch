<?php

namespace Database\Seeders;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\SmtpAccount;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo User', 'password' => 'password']
        );

        SmtpAccount::factory()->for($user)->create(['name' => 'Demo SMTP']);

        $campaign = Campaign::factory()->for($user)->create([
            'name' => 'Demo Campaign',
            'status' => CampaignStatus::Ready,
        ]);

        CampaignRecipient::factory()->count(20)->for($campaign)->create();
        CampaignRecipient::factory()->count(3)->for($campaign)->sent()->create();
        CampaignRecipient::factory()->count(2)->for($campaign)->failed()->create();
        CampaignRecipient::factory()->count(2)->for($campaign)->skipped()->create();

        $campaign->update([
            'total_records' => 27,
            'valid_records' => 27,
            'eligible_records' => 22,
            'sent_count' => 3,
            'failed_count' => 2,
            'skipped_count' => 2,
        ]);
    }
}
