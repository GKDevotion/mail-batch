<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Exports\CampaignResultsExport;
use App\Jobs\SendCampaignEmailJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use App\Models\SmtpAccount;
use App\Models\User;
use App\Services\SmtpConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CampaignMonitoringTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Campaign} */
    private function scenario(array $campaign = []): array
    {
        $user = User::factory()->create();
        $account = SmtpAccount::factory()->for($user)->create();
        $c = Campaign::factory()->for($user)->create(array_merge([
            'smtp_account_id' => $account->id, 'status' => CampaignStatus::Completed,
            'started_at' => now(), 'include_unsubscribe' => false,
        ], $campaign));

        return [$user, $c];
    }

    private function seedStates(Campaign $c): void
    {
        CampaignRecipient::factory()->for($c)->create(['name' => 'PendingCo']);
        CampaignRecipient::factory()->for($c)->sent()->create(['name' => 'SentCo']);
        CampaignRecipient::factory()->for($c)->failed()->create(['name' => 'FailedCo']);
        CampaignRecipient::factory()->for($c)->skipped()->create(['name' => 'SkippedCo']);
    }

    // ------------------------------------------------------------ pages

    public function test_progress_page_and_status_feed(): void
    {
        [$user, $c] = $this->scenario();
        EmailLog::create(['campaign_id' => $c->id, 'email' => 'a@example.com', 'status' => 'sent', 'sent_at' => now()]);
        EmailLog::create(['campaign_id' => $c->id, 'email' => 't@example.com', 'status' => 'test', 'sent_at' => now()]);

        $this->actingAs($user)->get(route('campaigns.progress', $c))->assertOk()->assertSee('Recent activity');

        $this->actingAs($user)->getJson(route('campaigns.progress.status', $c))
            ->assertOk()->assertJsonCount(1, 'recent')->assertJsonPath('recent.0.email', 'a@example.com');
    }

    public function test_recipient_table_filters_by_state_and_search(): void
    {
        [$user, $c] = $this->scenario();
        $this->seedStates($c);

        $this->actingAs($user)->get(route('campaigns.recipients', ['campaign' => $c->id, 'state' => 'failed']))
            ->assertOk()->assertSee('FailedCo')->assertDontSee('SentCo')->assertDontSee('PendingCo')->assertDontSee('SkippedCo');

        $this->actingAs($user)->get(route('campaigns.recipients', ['campaign' => $c->id, 'state' => 'skipped']))
            ->assertSee('SkippedCo')->assertSee('Already sent (status = 1)')->assertDontSee('FailedCo');

        $this->actingAs($user)->get(route('campaigns.recipients', ['campaign' => $c->id, 'q' => 'PendingCo']))
            ->assertSee('PendingCo')->assertDontSee('FailedCo');

        $this->actingAs($user)->get(route('campaigns.recipients', $c))->assertSee('Retry');
    }

    public function test_logs_page_filters_by_status(): void
    {
        [$user, $c] = $this->scenario();
        EmailLog::create(['campaign_id' => $c->id, 'email' => 'ok@example.com', 'status' => 'sent', 'sent_at' => now()]);
        EmailLog::create(['campaign_id' => $c->id, 'email' => 'bad@example.com', 'status' => 'failed', 'error_message' => 'Authentication failed.']);

        $this->actingAs($user)->get(route('campaigns.logs', ['campaign' => $c->id, 'status' => 'failed']))
            ->assertOk()->assertSee('bad@example.com')->assertSee('Authentication failed.')->assertDontSee('ok@example.com');
    }

    public function test_pages_are_forbidden_for_other_users(): void
    {
        [, $c] = $this->scenario();
        $intruder = User::factory()->create();

        foreach (['campaigns.progress', 'campaigns.logs', 'campaigns.recipients', 'campaigns.export'] as $route) {
            $this->actingAs($intruder)->get(route($route, $c))->assertForbidden();
        }
        $this->actingAs($intruder)->getJson(route('campaigns.recipients.state', $c))->assertForbidden();
    }

    public function test_state_endpoint_returns_rows_for_this_campaign_only(): void
    {
        [$user, $c] = $this->scenario();
        $mine = CampaignRecipient::factory()->for($c)->create(['name' => 'MineCo']);
        $foreign = CampaignRecipient::factory()->create(['name' => 'ForeignCo']);

        $json = $this->actingAs($user)->getJson(route('campaigns.recipients.state', ['campaign' => $c->id, 'ids' => [$mine->id, $foreign->id]]))
            ->assertOk()->json('rows');

        $this->assertArrayHasKey($mine->id, $json);
        $this->assertArrayNotHasKey($foreign->id, $json);
        $this->assertStringContainsString('MineCo', $json[$mine->id]);
    }

    // ------------------------------------------------------------ retry

    public function test_retry_queues_one_failed_recipient(): void
    {
        Queue::fake();
        [$user, $c] = $this->scenario();
        $r = CampaignRecipient::factory()->for($c)->failed()->create();

        $this->actingAs($user)->postJson(route('campaigns.recipients.retry', ['campaign' => $c->id, 'recipient' => $r->id]))
            ->assertOk()->assertJsonPath('ok', true)->assertJsonStructure(['row']);

        Queue::assertPushed(SendCampaignEmailJob::class, fn ($j) => $j->recipientId === $r->id);
        $this->assertNotNull($r->fresh()->queued_at);
        $this->assertSame(CampaignStatus::Processing, $c->fresh()->status);
    }

    public function test_retry_is_refused_for_sent_capped_busy_or_foreign_recipients(): void
    {
        Queue::fake();
        [$user, $c] = $this->scenario();

        $sent = CampaignRecipient::factory()->for($c)->sent()->create();
        $capped = CampaignRecipient::factory()->for($c)->failed()->create(['retry_count' => 3]);
        $pending = CampaignRecipient::factory()->for($c)->create();
        $foreign = CampaignRecipient::factory()->create();

        foreach ([$sent, $capped, $pending] as $r) {
            $this->actingAs($user)->postJson(route('campaigns.recipients.retry', ['campaign' => $c->id, 'recipient' => $r->id]))
                ->assertStatus(422)->assertJsonPath('ok', false);
        }

        $this->actingAs($user)->postJson(route('campaigns.recipients.retry', ['campaign' => $c->id, 'recipient' => $foreign->id]))
            ->assertNotFound();

        $c->update(['status' => CampaignStatus::Processing]);
        $failed = CampaignRecipient::factory()->for($c)->failed()->create();
        $this->actingAs($user)->postJson(route('campaigns.recipients.retry', ['campaign' => $c->id, 'recipient' => $failed->id]))
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_retry_end_to_end_marks_sent_and_completes_campaign(): void
    {
        [$user, $c] = $this->scenario();
        $r = CampaignRecipient::factory()->for($c)->failed()->create();

        $mailer = app('mail.manager')->mailer('array');
        $this->partialMock(SmtpConnectionService::class, fn ($m) => $m->shouldReceive('mailer')->andReturn($mailer));

        // tests run with the sync queue, so the job executes inside the request
        $this->actingAs($user)->postJson(route('campaigns.recipients.retry', ['campaign' => $c->id, 'recipient' => $r->id]))->assertOk();

        $r->refresh();
        $this->assertSame(1, $r->status);
        $this->assertNotNull($r->sent_at);
        $this->assertNull($r->error_message);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
        $this->assertSame(CampaignStatus::Completed, $c->fresh()->status);
    }

    // ------------------------------------------------------------ export

    public function test_export_headings_and_status_mapping(): void
    {
        [, $c] = $this->scenario();
        $meta = fn (string $name) => ['Name' => $name, 'Website' => 'x.com', 'Email' => $name.'@x.com', 'Contact' => '1', 'Status' => '0'];
        $sent = CampaignRecipient::factory()->for($c)->sent()->create(['metadata' => $meta('s')]);
        $failed = CampaignRecipient::factory()->for($c)->failed()->create(['metadata' => $meta('f')]);
        $skipped = CampaignRecipient::factory()->for($c)->skipped()->create(['metadata' => $meta('k')]);
        $pending = CampaignRecipient::factory()->for($c)->create(['metadata' => $meta('p')]);

        $export = new CampaignResultsExport($c);

        $this->assertSame(['Name', 'Website', 'Email', 'Contact', 'Status', 'Result', 'Sent At', 'Error Message'], $export->headings());

        $rowSent = $export->map($sent);
        $this->assertSame('1', $rowSent[4]);
        $this->assertSame('Sent', $rowSent[5]);
        $this->assertNotSame('', $rowSent[6]);

        $rowFailed = $export->map($failed);
        $this->assertSame('0', $rowFailed[4]);
        $this->assertSame('Failed', $rowFailed[5]);
        $this->assertSame('', $rowFailed[6]);
        $this->assertNotSame('', $rowFailed[7]);

        $this->assertSame('1', $export->map($skipped)[4]);
        $this->assertStringStartsWith('Skipped - ', $export->map($skipped)[5]);
        $this->assertSame('0', $export->map($pending)[4]);
        $this->assertSame('Pending', $export->map($pending)[5]);
    }

    public function test_export_appends_a_status_column_when_none_was_mapped(): void
    {
        [, $c] = $this->scenario(['column_mapping' => ['name' => 'Name', 'email' => 'Email']]);

        $this->assertContains('MailBatch Status', (new CampaignResultsExport($c))->headings());   // "Status" header already exists
    }

    public function test_export_writes_text_cells_so_formulas_cannot_run(): void
    {
        [, $c] = $this->scenario();
        CampaignRecipient::factory()->for($c)->create(['metadata' => [
            'Name' => '=1+1', 'Website' => '+cmd|x', 'Email' => 'a@x.com', 'Contact' => '@SUM(1)', 'Status' => '',
        ]]);

        $binary = Excel::raw(new CampaignResultsExport($c), ExcelWriter::XLSX);
        $tmp = tempnam(sys_get_temp_dir(), 'mbx').'.xlsx';
        file_put_contents($tmp, $binary);

        $cell = IOFactory::load($tmp)->getActiveSheet()->getCell('A2');
        @unlink($tmp);

        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
        $this->assertSame('=1+1', $cell->getValue());
    }

    public function test_export_download_and_empty_campaign(): void
    {
        [$user, $c] = $this->scenario(['total_records' => 2]);
        CampaignRecipient::factory()->for($c)->create();

        $response = $this->actingAs($user)->get(route('campaigns.export', $c))->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('-results-', (string) $response->headers->get('content-disposition'));

        [$user2, $empty] = $this->scenario(['total_records' => 0]);
        $this->actingAs($user2)->from(route('campaigns.show', $empty))->get(route('campaigns.export', $empty))
            ->assertRedirect(route('campaigns.show', $empty))->assertSessionHas('error');
    }
}
