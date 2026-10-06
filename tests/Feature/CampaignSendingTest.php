<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Jobs\ProcessCampaignBatchJob;
use App\Jobs\SendCampaignEmailJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\DailySendCount;
use App\Models\EmailLog;
use App\Models\SmtpAccount;
use App\Models\Unsubscribe;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\SmtpConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class CampaignSendingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Campaign,2:\Illuminate\Support\Collection} */
    private function scenario(int $recipients = 3, array $campaign = []): array
    {
        $user = User::factory()->create();
        $account = SmtpAccount::factory()->for($user)->create();
        $c = Campaign::factory()->for($user)->create(array_merge([
            'smtp_account_id' => $account->id, 'status' => CampaignStatus::Processing,
            'include_unsubscribe' => true, 'total_records' => $recipients,
        ], $campaign));
        $rs = CampaignRecipient::factory()->count($recipients)->for($c)->create();

        return [$user, $c, $rs];
    }

    private function arrayMailer()
    {
        $mailer = app('mail.manager')->mailer('array');
        $this->partialMock(SmtpConnectionService::class, fn ($m) => $m->shouldReceive('mailer')->andReturn($mailer));

        return $mailer;
    }

    private function runSend(CampaignRecipient $r): void
    {
        app()->call([new SendCampaignEmailJob($r->id), 'handle']);
    }

    // ------------------------------------------------------------ batching

    public function test_start_queues_a_batch_job_and_marks_campaign_processing(): void
    {
        Queue::fake();
        [$user, $c] = $this->scenario(3, ['status' => CampaignStatus::Ready]);

        $this->actingAs($user)->postJson(route('campaigns.start', $c))
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('snapshot.status', 'processing');

        Queue::assertPushed(ProcessCampaignBatchJob::class, fn ($j) => $j->campaignId === $c->id && $j->mode === 'pending');
        $this->assertNotNull($c->fresh()->started_at);
    }

    public function test_batch_job_claims_at_most_the_batch_size(): void
    {
        Queue::fake();
        [, $c] = $this->scenario(60, ['batch_size' => 50]);

        app()->call([new ProcessCampaignBatchJob($c->id), 'handle']);

        Queue::assertPushed(SendCampaignEmailJob::class, 50);
        $this->assertSame(50, $c->recipients()->whereNotNull('queued_at')->count());
    }

    public function test_batch_never_claims_sent_skipped_or_failed_rows(): void
    {
        Queue::fake();
        [, $c] = $this->scenario(2);
        CampaignRecipient::factory()->for($c)->sent()->create();
        CampaignRecipient::factory()->for($c)->skipped()->create();
        CampaignRecipient::factory()->for($c)->failed()->create();

        app()->call([new ProcessCampaignBatchJob($c->id), 'handle']);

        Queue::assertPushed(SendCampaignEmailJob::class, 2);
    }

    public function test_second_start_while_running_is_rejected(): void
    {
        Queue::fake();
        [$user, $c] = $this->scenario(3);   // already processing

        $this->actingAs($user)->postJson(route('campaigns.start', $c))->assertStatus(422);
    }

    public function test_daily_limit_blocks_start(): void
    {
        Queue::fake();
        [$user, $c] = $this->scenario(5, ['status' => CampaignStatus::Ready]);
        $user->forceFill(['daily_send_limit' => 2])->save();
        DailySendCount::create(['user_id' => $user->id, 'date' => now()->toDateString(), 'sent' => 2]);

        $this->actingAs($user)->postJson(route('campaigns.start', $c))
            ->assertStatus(422)->assertJsonPath('ok', false);
        Queue::assertNothingPushed();
    }

    public function test_start_requires_complete_setup(): void
    {
        Queue::fake();
        [$user, $c] = $this->scenario(3, ['status' => CampaignStatus::Ready, 'smtp_account_id' => null]);

        $this->actingAs($user)->postJson(route('campaigns.start', $c))->assertStatus(422);
    }

    // ------------------------------------------------------------ sending

    public function test_successful_send_marks_recipient_and_logs(): void
    {
        [, $c, $rs] = $this->scenario(1);
        $mailer = $this->arrayMailer();
        $r = $rs->first();
        $r->update(['queued_at' => now()]);

        $this->runSend($r);

        $r->refresh();
        $this->assertSame(1, $r->status);
        $this->assertNotNull($r->sent_at);
        $this->assertNull($r->error_message);
        $this->assertNull($r->queued_at);
        $this->assertNull($r->sending_at);
        $this->assertDatabaseHas('email_logs', ['recipient_id' => $r->id, 'status' => 'sent']);
        $this->assertSame(1, DailySendCount::today($c->user_id));

        $messages = $mailer->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertTrue($messages[0]->getOriginalMessage()->getHeaders()->has('List-Unsubscribe'));
        $this->assertSame($c->id, $r->campaign_id);
    }

    public function test_already_sent_recipient_is_never_sent_again(): void
    {
        [, $c] = $this->scenario(0);
        $mailer = $this->arrayMailer();
        $sentByStatus = CampaignRecipient::factory()->for($c)->create(['status' => 1]);
        $sentByDate = CampaignRecipient::factory()->for($c)->create(['status' => 0, 'sent_at' => now()]);

        $this->runSend($sentByStatus);
        $this->runSend($sentByDate);

        $this->assertCount(0, $mailer->getSymfonyTransport()->messages());
    }

    public function test_recipient_error_keeps_status_zero_and_counts_a_retry(): void
    {
        [, $c, $rs] = $this->scenario(1);
        $this->partialMock(SmtpConnectionService::class, fn ($m) => $m->shouldReceive('mailer')
            ->andThrow(new TransportException('Expected response code "250" but got code "550", with message "550 no such user"')));
        $r = $rs->first();

        $this->runSend($r);

        $r->refresh();
        $this->assertSame(0, $r->status);
        $this->assertNull($r->sent_at);
        $this->assertNotNull($r->error_message);
        $this->assertStringNotContainsString('550 no such user', $r->error_message);
        $this->assertSame(1, $r->retry_count);
        $this->assertDatabaseHas('email_logs', ['recipient_id' => $r->id, 'status' => 'failed']);
        $this->assertSame(CampaignStatus::Processing, $c->fresh()->status);
    }

    public function test_smtp_auth_failure_pauses_campaign_without_burning_retries(): void
    {
        [, $c, $rs] = $this->scenario(2);
        $this->partialMock(SmtpConnectionService::class, fn ($m) => $m->shouldReceive('mailer')
            ->andThrow(new TransportException('Failed to authenticate on SMTP server with username "x". Expected response code "235" but got code "535"')));
        $r = $rs->first();

        $this->runSend($r);

        $r->refresh();
        $this->assertNull($r->error_message);
        $this->assertSame(0, $r->retry_count);
        $this->assertSame(CampaignStatus::Paused, $c->fresh()->status);
        $this->assertStringContainsString('Authentication failed', $c->fresh()->last_error);
    }

    public function test_paused_campaign_releases_queued_rows_without_sending(): void
    {
        [, $c, $rs] = $this->scenario(1, ['status' => CampaignStatus::Paused]);
        $mailer = $this->arrayMailer();
        $r = $rs->first();
        $r->update(['queued_at' => now()]);

        $this->runSend($r);

        $this->assertNull($r->fresh()->queued_at);
        $this->assertCount(0, $mailer->getSymfonyTransport()->messages());
    }

    public function test_unsubscribed_recipient_is_skipped_at_send_time(): void
    {
        [$user, $c, $rs] = $this->scenario(1);
        $mailer = $this->arrayMailer();
        $r = $rs->first();
        Unsubscribe::create(['user_id' => $user->id, 'email' => strtolower($r->email), 'token' => 'x', 'unsubscribed_at' => now()]);

        $this->runSend($r);

        $this->assertSame('unsubscribed', $r->fresh()->skip_reason);
        $this->assertCount(0, $mailer->getSymfonyTransport()->messages());
    }

    public function test_row_already_being_sent_is_left_alone(): void
    {
        [, , $rs] = $this->scenario(1);
        $mailer = $this->arrayMailer();
        $r = $rs->first();
        $r->update(['sending_at' => now()]);

        $this->runSend($r);

        $this->assertCount(0, $mailer->getSymfonyTransport()->messages());
    }

    // ------------------------------------------------------------ lifecycle

    public function test_campaign_completes_or_returns_to_ready(): void
    {
        [, $c, $rs] = $this->scenario(2);
        $rs->each->update(['status' => 1, 'sent_at' => now()]);

        app(CampaignService::class)->finishBatchIfIdle($c->id);
        $c->refresh();
        $this->assertSame(CampaignStatus::Completed, $c->status);
        $this->assertSame(2, $c->sent_count);

        [, $c2, $rs2] = $this->scenario(2);
        $rs2->first()->update(['status' => 1, 'sent_at' => now()]);
        app(CampaignService::class)->finishBatchIfIdle($c2->id);
        $this->assertSame(CampaignStatus::Ready, $c2->fresh()->status);
    }

    public function test_batch_is_not_finished_while_rows_are_still_queued(): void
    {
        [, $c, $rs] = $this->scenario(2);
        $rs->first()->update(['queued_at' => now()]);

        app(CampaignService::class)->finishBatchIfIdle($c->id);

        $this->assertSame(CampaignStatus::Processing, $c->fresh()->status);
    }

    public function test_stale_sending_rows_become_failed_not_resent(): void
    {
        [, $c, $rs] = $this->scenario(1);
        $r = $rs->first();
        $r->update(['sending_at' => now()->subHour(), 'queued_at' => now()->subHour()]);

        app(CampaignService::class)->finishBatchIfIdle($c->id);

        $r->refresh();
        $this->assertNull($r->sending_at);
        $this->assertStringContainsString('result unknown', $r->error_message);
        $this->assertSame(1, $r->retry_count);
        $this->assertNull($r->sent_at);
    }

    public function test_pause_endpoint(): void
    {
        [$user, $c] = $this->scenario(2);

        $this->actingAs($user)->postJson(route('campaigns.pause', $c))->assertOk();

        $this->assertSame(CampaignStatus::Paused, $c->fresh()->status);
    }

    public function test_retry_only_takes_failed_rows_below_the_cap(): void
    {
        Queue::fake();
        [$user, $c] = $this->scenario(0, ['status' => CampaignStatus::Completed]);
        CampaignRecipient::factory()->for($c)->failed()->create(['retry_count' => 3]);

        $this->actingAs($user)->postJson(route('campaigns.retry-failed', $c))->assertStatus(422);

        CampaignRecipient::factory()->for($c)->failed()->create(['retry_count' => 1]);
        $this->actingAs($user)->postJson(route('campaigns.retry-failed', $c))->assertOk();
        Queue::assertPushed(ProcessCampaignBatchJob::class, fn ($j) => $j->mode === 'retry');

        app()->call([new ProcessCampaignBatchJob($c->id, 'retry'), 'handle']);
        Queue::assertPushed(SendCampaignEmailJob::class, 1);
    }

    public function test_structural_edits_are_blocked_after_sending_started(): void
    {
        [$user, $c] = $this->scenario(1, ['status' => CampaignStatus::Ready, 'started_at' => now()]);

        $this->assertFalse($user->can('update', $c));   // upload / mapping
        $this->assertTrue($user->can('configure', $c)); // template, SMTP, between batches
    }

    public function test_foreign_users_cannot_control_a_campaign(): void
    {
        [, $c] = $this->scenario(1);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->postJson(route('campaigns.start', $c))->assertForbidden();
        $this->actingAs($intruder)->postJson(route('campaigns.pause', $c))->assertForbidden();
        $this->actingAs($intruder)->postJson(route('campaigns.retry-failed', $c))->assertForbidden();
        $this->actingAs($intruder)->getJson(route('campaigns.progress.status', $c))->assertForbidden();
        $this->actingAs($intruder)->get(route('campaigns.confirm', $c))->assertForbidden();
    }

    public function test_confirm_page_and_status_endpoint(): void
    {
        [$user, $c] = $this->scenario(3, ['status' => CampaignStatus::Ready]);

        $this->actingAs($user)->get(route('campaigns.confirm', $c))->assertOk()->assertSee('Start sending');
        $this->actingAs($user)->getJson(route('campaigns.progress.status', $c))
            ->assertOk()->assertJsonPath('total', 3)->assertJsonPath('remaining', 3)->assertJsonPath('percent', 0);
    }
}
