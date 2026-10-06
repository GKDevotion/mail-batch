<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Jobs\ProcessCampaignBatchJob;
use App\Jobs\SendCampaignEmailJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\SmtpAccount;
use App\Models\User;
use App\Services\EmailSendingService;
use App\Services\CampaignWorkerService;
use App\Services\SmtpConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueWorkerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Campaign,2:\Illuminate\Support\Collection} */
    private function scenario(int $recipients = 2, array $campaign = []): array
    {
        $user = User::factory()->create();
        $account = SmtpAccount::factory()->for($user)->create();
        $c = Campaign::factory()->for($user)->create(array_merge([
            'smtp_account_id' => $account->id, 'status' => CampaignStatus::Processing, 'include_unsubscribe' => false,
        ], $campaign));

        return [$user, $c, CampaignRecipient::factory()->count($recipients)->for($c)->create()];
    }

    private function arrayMailer()
    {
        $mailer = app('mail.manager')->mailer('array');
        $this->partialMock(SmtpConnectionService::class, fn ($m) => $m->shouldReceive('mailer')->andReturn($mailer));

        return $mailer;
    }

    /** Regression: a Processing campaign must NOT be treated as "cancelled" (enum vs string comparison bug). */
    public function test_processing_campaign_is_sent_not_cancelled(): void
    {
        [, $c, $rs] = $this->scenario(1);
        $mailer = $this->arrayMailer();

        $result = app(EmailSendingService::class)->send($c->load('smtpAccount'), $rs->first());

        $this->assertSame('sent', $result['outcome']);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
        $this->assertNotNull($rs->first()->fresh()->sent_at);
    }

    public function test_batch_job_is_unique_per_campaign(): void
    {
        config(['queue.default' => 'database']);
        [, $c] = $this->scenario(1);

        ProcessCampaignBatchJob::dispatch($c->id);
        ProcessCampaignBatchJob::dispatch($c->id);   // duplicate click / double dispatch

        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_work_url_processes_only_its_own_campaign_queue_then_stops(): void
    {
        config(['queue.default' => 'database']);
        [$user, $c, $rs] = $this->scenario(2);
        [, $other, $otherRs] = $this->scenario(1);
        $mailer = $this->arrayMailer();

        foreach ($rs as $r) {
            $r->update(['queued_at' => now()]);
            SendCampaignEmailJob::dispatch($r->id, $c->id);
        }
        $otherRs->first()->update(['queued_at' => now()]);
        SendCampaignEmailJob::dispatch($otherRs->first()->id, $other->id);

        $this->assertSame(2, DB::table('jobs')->where('queue', CampaignWorkerService::queueName($c->id))->count());
        $this->assertSame(1, DB::table('jobs')->where('queue', CampaignWorkerService::queueName($other->id))->count());

        $this->actingAs($user)->postJson(route('campaigns.work', $c))
            ->assertOk()->assertJsonPath('has_more', false)->assertJsonPath('busy', false);

        $this->assertSame(0, DB::table('jobs')->where('queue', CampaignWorkerService::queueName($c->id))->count());
        $this->assertSame(1, DB::table('jobs')->where('queue', CampaignWorkerService::queueName($other->id))->count());   // untouched
        $this->assertCount(2, $mailer->getSymfonyTransport()->messages());
        $this->assertSame(2, CampaignRecipient::where('campaign_id', $c->id)->whereNotNull('sent_at')->count());
        $this->assertSame(CampaignStatus::Completed, $c->fresh()->status);
    }

    public function test_a_second_work_request_for_the_same_campaign_reports_busy(): void
    {
        config(['queue.default' => 'database']);
        [$user, $c] = $this->scenario(1);

        $lock = Cache::lock('mailbatch:worker:campaign:'.$c->id, 60);
        $this->assertTrue($lock->get());

        $this->actingAs($user)->postJson(route('campaigns.work', $c))->assertOk()->assertJsonPath('busy', true);

        $lock->release();
    }

    public function test_work_url_is_forbidden_for_another_users_campaign(): void
    {
        [, $c] = $this->scenario(1);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->postJson(route('campaigns.work', $c))->assertForbidden();
    }

    public function test_start_hands_over_to_the_browser_worker(): void
    {
        Queue::fake();

        [$user, $c] = $this->scenario(2, ['status' => CampaignStatus::Ready]);

        $this->actingAs($user)->postJson(route('campaigns.start', $c))
            ->assertOk()->assertJsonPath('worker', 'browser')->assertJsonPath('work_url', route('campaigns.work', $c));
    }
}
