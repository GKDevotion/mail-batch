<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\DailySendCount;
use App\Models\EmailLog;
use App\Models\User;
use App\Services\CampaignProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_logo_charts_and_sidebar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('logo-mark.svg')
            ->assertSee('favicon.svg')
            ->assertSee('Sending activity')
            ->assertSee('Campaign status')
            ->assertSee('mb-spark', false)
            ->assertSee(route('campaigns.index'), false)
            ->assertDontSee(route('admin.dashboard'), false);
    }

    public function test_admin_sees_the_admin_menu(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertOk()
            ->assertSee(route('admin.users.index'), false);
    }

    public function test_login_page_uses_the_branded_layout(): void
    {
        $this->get('/login')->assertOk()->assertSee('logo-mark.svg')->assertSee('Smart Excel to Email Automation');
    }

    public function test_daily_series_counts_per_day(): void
    {
        $user = User::factory()->create();
        $c = Campaign::factory()->for($user)->create();
        CampaignRecipient::factory()->count(3)->for($c)->create();
        DailySendCount::create(['user_id' => $user->id, 'date' => now()->toDateString(), 'sent' => 5]);
        EmailLog::create(['campaign_id' => $c->id, 'email' => 'x@example.com', 'status' => 'failed', 'error_message' => 'e']);
        EmailLog::create(['campaign_id' => $c->id, 'email' => 'y@example.com', 'status' => 'sent', 'sent_at' => now()]);

        $series = app(CampaignProgressService::class)->dailySeries($user, 14);

        foreach (['labels', 'campaigns', 'recipients', 'sent', 'failed'] as $key) {
            $this->assertCount(14, $series[$key]);
        }
        $this->assertSame(1, $series['campaigns'][13]);
        $this->assertSame(3, $series['recipients'][13]);
        $this->assertSame(5, $series['sent'][13]);
        $this->assertSame(1, $series['failed'][13]);
        $this->assertSame(0, array_sum(array_slice($series['sent'], 0, 13)));
    }

    public function test_other_users_data_is_not_in_the_series(): void
    {
        $other = Campaign::factory()->create();
        CampaignRecipient::factory()->count(2)->for($other)->create();

        $series = app(CampaignProgressService::class)->dailySeries(User::factory()->create(), 14);

        $this->assertSame(0, array_sum($series['campaigns']));
        $this->assertSame(0, array_sum($series['recipients']));
    }
}
