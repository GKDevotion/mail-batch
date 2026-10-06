<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\SkipReason;
use App\Exceptions\ExcelImportException;
use App\Imports\SheetArrayImport;
use App\Models\Campaign;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExcelImportService
{
    private const MAX_COLUMNS = 100;
    private const SAMPLE_ROWS = 10;

    /** @var array<int, array{headers:array<int,string>, rows:array<int,array{row:int,cells:array<int,string>,data:array<string,string>}>}> */
    private array $tableCache = [];

    public function __construct(private readonly ExcelColumnMappingService $mapping)
    {
    }

    // ------------------------------------------------------------------ upload

    /**
     * Validate, store (privately) and parse an uploaded workbook. The original file is never modified.
     *
     * @throws ExcelImportException
     */
    public function attachFile(Campaign $campaign, UploadedFile $file): void
    {
        $this->assertSignature($file);
        $this->assertNotStarted($campaign);

        $disk = config('mailbatch.upload.disk');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs(
            config('mailbatch.upload.directory').'/'.$campaign->user_id,
            Str::uuid()->toString().'.'.$ext,
            $disk
        );

        if (! $path) {
            throw new ExcelImportException('The file could not be stored. Please try again.');
        }

        try {
            $table = $this->parseFile($path);
        } catch (ExcelImportException $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);
            Log::warning('Excel parse failed', ['campaign_id' => $campaign->id, 'error_type' => $e::class]);
            throw new ExcelImportException('The file could not be read. Make sure it is a valid, unprotected .xls or .xlsx file.');
        }

        $oldPath = $campaign->excel_path;

        DB::transaction(function () use ($campaign, $file, $path, $table) {
            $campaign->recipients()->delete();   // replacing the file resets the import

            $campaign->update([
                'excel_filename' => $this->safeFilename($file->getClientOriginalName()),
                'excel_path' => $path,
                'excel_size' => $file->getSize(),
                'excel_headers' => $table['headers'],
                'excel_preview' => $this->buildPreview($table),
                'column_mapping' => null,
                'total_records' => count($table['rows']),
                'valid_records' => 0,
                'invalid_records' => 0,
                'duplicate_records' => 0,
                'eligible_records' => 0,
                'sent_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0,
                'status' => CampaignStatus::Draft,
            ]);
        });

        if ($oldPath) {
            Storage::disk($disk)->delete($oldPath);
        }
    }

    // ------------------------------------------------------------------ analyse / import

    /**
     * Dry run: what would happen with this mapping. Writes nothing.
     *
     * @param  array<string,string>  $mapping
     * @return array{stats:array<string,int>, sample:array<int,array<string,mixed>>}
     */
    public function analyze(Campaign $campaign, array $mapping): array
    {
        $built = $this->buildRecords($campaign, $mapping);

        return ['stats' => $built['stats'], 'sample' => $built['sample']];
    }

    /**
     * Import rows into campaign_recipients using the mapping (replaces any previous import).
     *
     * @param  array<string,string>  $mapping
     * @return array<string,int>  statistics
     */
    public function importRecipients(Campaign $campaign, array $mapping): array
    {
        if (empty($mapping['email'])) {
            throw new ExcelImportException('Please choose the column that contains the recipient email address.');
        }

        $built = $this->buildRecords($campaign, $mapping);
        $stats = $built['stats'];

        DB::transaction(function () use ($campaign, $mapping, $built, $stats) {
            $this->assertNotStarted($campaign);

            $campaign->recipients()->delete();

            foreach (array_chunk($built['records'], 500) as $chunk) {
                DB::table('campaign_recipients')->insert($chunk);
            }

            $skipped = $stats['total'] - $stats['eligible'];

            $campaign->update([
                'column_mapping' => $mapping,
                'total_records' => $stats['total'],
                'valid_records' => $stats['valid'],
                'invalid_records' => $stats['invalid'],
                'duplicate_records' => $stats['duplicates'],
                'eligible_records' => $stats['eligible'],
                'sent_count' => 0,
                'failed_count' => 0,
                'skipped_count' => $skipped,
            ]);
        });

        return $stats;
    }

    // ------------------------------------------------------------------ internals

    /**
     * @param  array<string,string>  $mapping
     * @return array{records:array<int,array<string,mixed>>, stats:array<string,int>, sample:array<int,array<string,mixed>>}
     */
    private function buildRecords(Campaign $campaign, array $mapping): array
    {
        $table = $this->loadTable($campaign);

        $unsubscribed = $campaign->user->unsubscribes()->whereNotNull('unsubscribed_at')->pluck('email')
            ->mapWithKeys(fn ($e) => [Str::lower($e) => true])->all();

        $stats = ['total' => 0, 'valid' => 0, 'invalid' => 0, 'duplicates' => 0,
            'already_sent' => 0, 'unknown_status' => 0, 'unsubscribed' => 0, 'eligible' => 0];

        $seen = [];
        $records = [];
        $sample = [];
        $now = now()->toDateTimeString();

        foreach ($table['rows'] as $row) {
            $m = $this->mapping->mapRow($row['data'], $mapping);
            [$email, $emailProblem] = $this->mapping->parseEmail($m['email']);
            [$status, $statusProblem] = $this->mapping->parseStatus($m['status']);

            $skip = null;
            $dedupe = null;

            if ($emailProblem !== null) {
                $skip = SkipReason::from($emailProblem);
            } else {
                $stats['valid']++;

                if (isset($seen[$email])) {
                    $skip = SkipReason::Duplicate;
                } else {
                    $seen[$email] = true;
                    $dedupe = $email;

                    if ($status === 1) {
                        $skip = SkipReason::AlreadyMarkedSent;
                    } elseif ($statusProblem !== null) {
                        $skip = SkipReason::UnknownStatus;
                    } elseif (isset($unsubscribed[$email])) {
                        $skip = SkipReason::Unsubscribed;
                    }
                }
            }

            $stats['total']++;
            match ($skip) {
                SkipReason::MissingEmail, SkipReason::InvalidEmail => $stats['invalid']++,
                SkipReason::Duplicate => $stats['duplicates']++,
                SkipReason::AlreadyMarkedSent => $stats['already_sent']++,
                SkipReason::UnknownStatus => $stats['unknown_status']++,
                SkipReason::Unsubscribed => $stats['unsubscribed']++,
                null => $stats['eligible']++,
            };

            if (count($sample) < self::SAMPLE_ROWS) {
                $sample[] = [
                    'row' => $row['row'],
                    'name' => Str::limit($m['name'], 60),
                    'email' => Str::limit($email ?? $m['email'], 80),
                    'website' => Str::limit($m['website'], 60),
                    'status' => $m['status'],
                    'reason' => $skip?->label(),
                ];
            }

            $records[] = [
                'campaign_id' => $campaign->id,
                'row_number' => $row['row'],
                'name' => $this->limit($m['name']),
                'email' => $this->limit($email ?? $m['email']),
                'website' => $this->limit($m['website']),
                'contact' => $this->limit($m['contact']),
                'status' => $status,
                'is_valid_email' => $emailProblem === null,
                'skip_reason' => $skip?->value,
                'retry_count' => 0,
                'dedupe_key' => $dedupe,
                'metadata' => json_encode($this->truncateValues($row['data']), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return ['records' => $records, 'stats' => $stats, 'sample' => $sample];
    }

    /** @return array{headers:array<int,string>, rows:array<int,array<string,mixed>>} */
    private function loadTable(Campaign $campaign): array
    {
        if (! $campaign->excel_path) {
            throw new ExcelImportException('Please upload an Excel file first.');
        }

        if (! Storage::disk(config('mailbatch.upload.disk'))->exists($campaign->excel_path)) {
            throw new ExcelImportException('The uploaded file is no longer available. Please upload it again.');
        }

        try {
            return $this->tableCache[$campaign->id] ??= $this->parseFile($campaign->excel_path);
        } catch (ExcelImportException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Excel re-read failed', ['campaign_id' => $campaign->id, 'error_type' => $e::class]);
            throw new ExcelImportException('The file could not be read. Please upload it again.');
        }
    }

    /** @return array{headers:array<int,string>, rows:array<int,array<string,mixed>>} */
    private function parseFile(string $path): array
    {
        $import = new SheetArrayImport();
        Excel::import($import, $path, config('mailbatch.upload.disk'));

        return $this->extractTable($import->rows);
    }

    /**
     * First non-empty row = headers. Empty rows are ignored.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function extractTable(array $rows): array
    {
        $headerIdx = null;
        foreach ($rows as $i => $r) {
            if ($this->rowHasData($r)) {
                $headerIdx = $i;
                break;
            }
        }

        if ($headerIdx === null) {
            throw new ExcelImportException('The Excel file is empty.');
        }

        $headerRow = array_map([$this, 'cell'], array_values($rows[$headerIdx]));
        $dataRows = [];
        $maxCols = count($headerRow);

        foreach (array_slice($rows, $headerIdx + 1, null, true) as $i => $r) {
            if (! $this->rowHasData($r)) {
                continue;
            }
            $cells = array_map([$this, 'cell'], array_values($r));
            $maxCols = max($maxCols, count($cells));
            $dataRows[] = ['row' => $i + 1, 'cells' => $cells];
        }

        if ($dataRows === []) {
            throw new ExcelImportException('The file has a header row but no data rows.');
        }

        $maxRows = (int) config('mailbatch.upload.max_rows');
        if (count($dataRows) > $maxRows) {
            throw new ExcelImportException('The file has '.number_format(count($dataRows)).' rows. The maximum is '.number_format($maxRows).' per campaign; please split the file.');
        }

        $maxCols = min($maxCols, self::MAX_COLUMNS);
        $headers = [];
        $used = [];
        for ($c = 0; $c < $maxCols; $c++) {
            $label = Str::limit(trim($headerRow[$c] ?? ''), 80, '');
            if ($label === '') {
                $label = 'Column '.$this->columnLetter($c);
            }
            $base = $label;
            $n = 2;
            while (isset($used[$label])) {
                $label = $base.' ('.$n++.')';
            }
            $used[$label] = true;
            $headers[] = $label;
        }

        foreach ($dataRows as &$dr) {
            $assoc = [];
            foreach ($headers as $c => $h) {
                $assoc[$h] = $dr['cells'][$c] ?? '';
            }
            $dr['data'] = $assoc;
            $dr['cells'] = array_slice(array_pad($dr['cells'], $maxCols, ''), 0, $maxCols);
        }
        unset($dr);

        return ['headers' => $headers, 'rows' => $dataRows];
    }

    /** @return array<string,mixed> */
    private function buildPreview(array $table): array
    {
        $rows = [];
        foreach (array_slice($table['rows'], 0, (int) config('mailbatch.upload.preview_rows')) as $r) {
            $rows[] = ['row' => $r['row'], 'cells' => array_map(fn ($v) => Str::limit($v, 150), $r['cells'])];
        }

        return ['rows' => $rows];
    }

    /** Replacing the recipients after sending began would erase the duplicate-protection history. */
    private function assertNotStarted(Campaign $campaign): void
    {
        $started = $campaign->recipients()->where(function ($q) {
            $q->whereNotNull('sent_at')->orWhereNotNull('last_attempt_at')->orWhereNotNull('queued_at');
        })->exists();

        if ($started) {
            throw new ExcelImportException('Sending has already started for this campaign, so the Excel file and import cannot be changed. Create a new campaign instead.');
        }
    }

    private function assertSignature(UploadedFile $file): void
    {
        $path = $file->getRealPath();
        $head = '';

        if ($path && ($fh = fopen($path, 'rb'))) {
            $head = (string) fread($fh, 8);
            fclose($fh);
        }

        if ($file->getSize() === 0 || $head === '') {
            throw new ExcelImportException('The uploaded file is empty.');
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $isZip = str_starts_with($head, "PK\x03\x04");
        $isOle = str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");

        if (($ext === 'xlsx' && ! $isZip) || ($ext === 'xls' && ! $isOle)) {
            throw new ExcelImportException('The file content does not match its extension. Upload a genuine .xls or .xlsx file (password-protected files are not supported).');
        }
    }

    private function rowHasData(array $row): bool
    {
        foreach ($row as $v) {
            if ($this->cell($v) !== '') {
                return true;
            }
        }

        return false;
    }

    private function cell(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (! is_scalar($v)) {
            return '';
        }

        $s = mb_scrub((string) $v);

        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? '');
    }

    private function columnLetter(int $index): string
    {
        $s = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $s = chr(65 + (($n - 1) % 26)).$s;
        }

        return $s;
    }

    private function limit(string $v): ?string
    {
        $v = trim($v);

        return $v === '' ? null : mb_substr($v, 0, 255);
    }

    /** @param array<string,string> $data */
    private function truncateValues(array $data): array
    {
        return array_map(fn ($v) => mb_substr((string) $v, 0, 2000), $data);
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^\pL\pN\s._()\-]/u', '', basename($name)) ?? 'upload';

        return Str::limit(trim($name) ?: 'upload.xlsx', 150, '');
    }
}
