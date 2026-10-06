<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private const MAPPING = [
        'name' => 'Name', 'email' => 'Email', 'website' => 'Website', 'contact' => 'Contact', 'status' => 'Status',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function xlsx(array $rows, string $name = 'companies.xlsx'): UploadedFile
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'mb').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function sampleRows(): array
    {
        return [
            ['Name', 'Website', 'Email', 'Contact', 'Status'],
            ['A', 'a.com', 'a@example.com', '1', 0],
            ['B', 'b.com', 'b@example.com', '2', 1],
            ['C', 'c.com', 'A@Example.com', '3', ''],      // duplicate of A (case-insensitive)
            ['D', 'd.com', 'not-an-email', '4', ''],
            ['E', 'e.com', '', '5', ''],                    // missing email
            ['F', 'f.com', 'f@example.com', '6', 0],
            ['G', 'g.com', 'g@example.com', '7', 'x'],      // unknown status
            ['H', 'h.com', 'h@example.com', '8', ''],       // blank status = send
        ];
    }

    private function uploaded(User $user): Campaign
    {
        $campaign = Campaign::factory()->for($user)->create([
            'excel_filename' => null, 'excel_headers' => null, 'column_mapping' => null,
        ]);

        $this->actingAs($user)
            ->post(route('campaigns.upload', $campaign), ['file' => $this->xlsx($this->sampleRows())])
            ->assertRedirect(route('campaigns.preview', $campaign));

        return $campaign->fresh();
    }

    public function test_upload_stores_file_headers_and_preview(): void
    {
        $campaign = $this->uploaded(User::factory()->create());

        $this->assertSame(['Name', 'Website', 'Email', 'Contact', 'Status'], $campaign->excel_headers);
        $this->assertSame(8, $campaign->total_records);
        $this->assertCount(8, $campaign->excel_preview['rows']);
        Storage::disk('local')->assertExists($campaign->excel_path);
    }

    public function test_rejects_text_file_renamed_to_xlsx(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create();
        $fake = UploadedFile::fake()->createWithContent('evil.xlsx', 'just plain text, not a workbook');

        $this->actingAs($user)->post(route('campaigns.upload', $campaign), ['file' => $fake])
            ->assertSessionHasErrors('file');
    }

    public function test_rejects_wrong_extension_and_empty_workbook(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create();

        $this->actingAs($user)->post(route('campaigns.upload', $campaign), [
            'file' => UploadedFile::fake()->create('data.csv', 10, 'text/csv'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($user)->post(route('campaigns.upload', $campaign), [
            'file' => $this->xlsx([[]]),
        ])->assertSessionHasErrors('file');
    }

    public function test_mapping_import_applies_status_duplicate_and_email_rules(): void
    {
        $user = User::factory()->create();
        $campaign = $this->uploaded($user);

        $this->actingAs($user)
            ->post(route('campaigns.mapping.update', $campaign), ['mapping' => self::MAPPING])
            ->assertSessionHasNoErrors();

        $campaign->refresh();
        $this->assertSame(8, $campaign->total_records);
        $this->assertSame(6, $campaign->valid_records);
        $this->assertSame(2, $campaign->invalid_records);
        $this->assertSame(1, $campaign->duplicate_records);
        $this->assertSame(3, $campaign->eligible_records);   // A, F, H
        $this->assertSame(5, $campaign->skipped_count);

        $this->assertSame(3, $campaign->recipients()->eligible()->count());
        $b = $campaign->recipients()->where('email', 'b@example.com')->first();
        $this->assertSame(1, $b->status);
        $this->assertSame('already_marked_sent', $b->skip_reason);
        $this->assertNull($b->sent_at);
        $this->assertSame('B', $b->metadata['Name']);   // original row preserved
        $this->assertSame('duplicate', $campaign->recipients()->where('name', 'C')->first()->skip_reason);
    }

    public function test_check_import_is_a_dry_run(): void
    {
        $user = User::factory()->create();
        $campaign = $this->uploaded($user);

        $this->actingAs($user)
            ->postJson(route('campaigns.mapping.preview', $campaign), ['mapping' => self::MAPPING])
            ->assertOk()
            ->assertJsonPath('stats.eligible', 3)
            ->assertJsonPath('stats.duplicates', 1);

        $this->assertSame(0, CampaignRecipient::count());
    }

    public function test_email_column_is_required(): void
    {
        $user = User::factory()->create();
        $campaign = $this->uploaded($user);

        $this->actingAs($user)
            ->post(route('campaigns.mapping.update', $campaign), ['mapping' => ['name' => 'Name', 'email' => '']])
            ->assertSessionHasErrors('mapping.email');
    }

    public function test_reimport_replaces_previous_recipients(): void
    {
        $user = User::factory()->create();
        $campaign = $this->uploaded($user);

        $this->actingAs($user)->post(route('campaigns.mapping.update', $campaign), ['mapping' => self::MAPPING]);
        $this->actingAs($user)->post(route('campaigns.mapping.update', $campaign), ['mapping' => self::MAPPING]);

        $this->assertSame(8, $campaign->recipients()->count());
    }

    public function test_other_users_cannot_upload_or_map(): void
    {
        $campaign = $this->uploaded(User::factory()->create());
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('campaigns.upload', $campaign), ['file' => $this->xlsx($this->sampleRows())])
            ->assertForbidden();
        $this->actingAs($intruder)->post(route('campaigns.mapping.update', $campaign), ['mapping' => self::MAPPING])
            ->assertForbidden();
        $this->actingAs($intruder)->get(route('campaigns.preview', $campaign))->assertForbidden();
    }
}
