<?php

namespace App\Exports;

use App\Enums\RecipientState;
use App\Enums\SkipReason;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Builds a NEW workbook: every original Excel column (values kept exactly as imported) plus the sending result.
 * The uploaded file is never touched.
 *
 * - If a Status column was mapped, that column is updated in place: 1 = sent / already sent, otherwise 0.
 * - Otherwise a "Status" column is appended (named "MailBatch Status" if "Status" already exists).
 * - Always appended: Result, Sent At, Error Message.
 *
 * StringValueBinder writes every cell as plain text, so spreadsheet content such as "=HYPERLINK(...)"
 * can never be executed as a formula when the file is opened (formula-injection protection).
 */
class CampaignResultsExport extends StringValueBinder implements FromQuery, WithCustomValueBinder, WithHeadings, WithMapping
{
    /** @var array<int,string> */
    private array $headers;

    private ?string $statusHeader;

    private ?string $addedStatusName = null;

    public function __construct(private readonly Campaign $campaign)
    {
        $this->headers = array_values(array_map('strval', $campaign->excel_headers ?? []));

        $mapped = $campaign->column_mapping['status'] ?? null;
        $this->statusHeader = ($mapped !== null && in_array($mapped, $this->headers, true)) ? $mapped : null;

        if ($this->statusHeader === null) {
            $this->addedStatusName = in_array('Status', $this->headers, true) ? 'MailBatch Status' : 'Status';
        }
    }

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return CampaignRecipient::query()->where('campaign_id', $this->campaign->id)->orderBy('row_number');
    }

    public function headings(): array
    {
        return array_merge(
            $this->headers,
            $this->addedStatusName ? [$this->addedStatusName] : [],
            ['Result', 'Sent At', 'Error Message']
        );
    }

    /** @param CampaignRecipient $row */
    public function map($row): array
    {
        $meta = $row->metadata ?? [];
        $out = [];

        foreach ($this->headers as $header) {
            $value = (string) ($meta[$header] ?? '');
            $out[] = $header === $this->statusHeader ? $this->statusValue($row, $value) : $value;
        }

        if ($this->addedStatusName) {
            $out[] = $this->statusValue($row, '');
        }

        $out[] = $this->result($row);
        $out[] = $row->sent_at?->format('Y-m-d H:i:s') ?? '';
        $out[] = (string) ($row->error_message ?? '');

        return $out;
    }

    /** 1 = delivered or already delivered; 0 = still to send. Unrecognised original values are kept. */
    private function statusValue(CampaignRecipient $row, string $original): string
    {
        if ($row->alreadySent()) {
            return '1';
        }

        if ($row->skip_reason === SkipReason::UnknownStatus->value) {
            return $original;
        }

        return '0';
    }

    private function result(CampaignRecipient $row): string
    {
        $state = $row->state;

        if ($state === RecipientState::Skipped) {
            $reason = SkipReason::tryFrom((string) $row->skip_reason)?->label() ?? (string) $row->skip_reason;

            return 'Skipped - '.$reason;
        }

        return $state->label();
    }
}
