<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Jobs\SendCampaignEmailJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\DailySendCount;
use App\Models\EmailLog;
use App\Models\SmtpAccount;
use App\Models\User;
use App\Services\CampaignProgressService;
use App\Services\EmailTemplateService;
use App\Services\SmtpConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAndOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_and_csp_are_sent(): void
    {
        $response = $this->get('/login')->assertOk();

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $csp);
    }

    public function test_signed_in_pages_are_not_cacheable(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_csp_can_be_switched_to_report_only(): void
    {
        config(['mailbatch.security.csp' => 'report']);

        $response = $this->get('/login');

        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_friendly_404_page_hides_internals(): void
    {
        $this->get('/definitely-not-here')->assertNotFound()->assertSee('Page not found')->assertDontSee('Exception');
    }

    public function test_login_is_rate_limited_per_account_and_ip(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_send_increments_the_permanent_daily_counter(): void
    {
        $user = User::factory()->create();
        $account = SmtpAccount::factory()->for($user)->create();
        $c = Campaign::factory()->for($user)->create(['smtp_account_id' => $account->id, 'status' => CampaignStatus::Processing, 'include_unsubscribe' => false]);
        $r = CampaignRecipient::factory()->for($c)->create();

        $mailer = app('mail.manager')->mailer('array');
        $this->partialMock(SmtpConnectionService::class, fn ($m) => $m->shouldReceive('mailer')->andReturn($mailer));

        app()->call([new SendCampaignEmailJob($r->id), 'handle']);

        $this->assertSame(1, DailySendCount::today($user->id));
        $this->assertSame($user->effectiveDailyLimit() - 1, app(CampaignProgressService::class)->dailyRemaining($user));

        // deleting the campaign (and its logs) must NOT reset the limit
        $c->delete();
        $this->assertSame(1, DailySendCount::today($user->id));
    }

    public function test_force_unsubscribe_setting_adds_the_link_even_when_the_campaign_opted_out(): void
    {
        $c = Campaign::factory()->create(['include_unsubscribe' => false]);
        $svc = app(EmailTemplateService::class);

        $this->assertStringNotContainsString('https://app.test/u', $svc->render($c, [], 'S', '<p>x</p>', null, 'https://app.test/u')['html']);

        config(['mailbatch.unsubscribe.force' => true]);
        $this->assertStringContainsString('https://app.test/u', $svc->render($c, [], 'S', '<p>x</p>', null, 'https://app.test/u')['html']);
    }

    public function test_prune_command_deletes_only_old_records(): void
    {
        $c = Campaign::factory()->create();
        $user = $c->user;
        $old = EmailLog::create(['campaign_id' => $c->id, 'email' => 'old@example.com', 'status' => 'sent']);
        $old->forceFill(['created_at' => now()->subDays(400)])->save();
        $new = EmailLog::create(['campaign_id' => $c->id, 'email' => 'new@example.com', 'status' => 'sent']);
        DailySendCount::create(['user_id' => $user->id, 'date' => now()->subDays(200)->toDateString(), 'sent' => 5]);
        DailySendCount::create(['user_id' => $user->id, 'date' => now()->toDateString(), 'sent' => 1]);

        $this->artisan('mailbatch:prune')->assertSuccessful();

        $this->assertDatabaseMissing('email_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('email_logs', ['id' => $new->id]);
        $this->assertSame(1, DailySendCount::count());
    }

    public function test_recover_command_finishes_a_stuck_campaign(): void
    {
        $c = Campaign::factory()->create(['status' => CampaignStatus::Processing]);
        CampaignRecipient::factory()->count(2)->for($c)->sent()->create();

        $this->artisan('mailbatch:recover')->assertSuccessful();

        $this->assertSame(CampaignStatus::Completed, $c->fresh()->status);
    }
}
