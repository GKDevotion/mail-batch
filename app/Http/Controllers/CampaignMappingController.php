<?php

namespace App\Http\Controllers;

use App\Exceptions\ExcelImportException;
use App\Http\Requests\SaveMappingRequest;
use App\Models\Campaign;
use App\Services\ExcelColumnMappingService;
use App\Services\ExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class CampaignMappingController extends Controller
{
    public function __construct(
        private readonly ExcelColumnMappingService $mapping,
        private readonly ExcelImportService $import,
    ) {
    }

    public function edit(Request $request, Campaign $campaign): View|RedirectResponse
    {
        $this->authorize('view', $campaign);

        if (! $campaign->excel_path) {
            return redirect()->route('campaigns.preview', $campaign)->with('error', 'Upload an Excel file first.');
        }

        return view('campaigns.mapping', [
            'campaign' => $campaign,
            'fields' => $this->mapping->fields(),
            'headers' => $campaign->excel_headers ?? [],
            'current' => $campaign->column_mapping ?? [],
            'suggested' => $this->mapping->suggest($campaign->excel_headers ?? []),
            'canEdit' => $request->user()->can('update', $campaign),
        ]);
    }

    /** AJAX: dry-run analysis, nothing is written. */
    public function preview(SaveMappingRequest $request, Campaign $campaign): JsonResponse
    {
        try {
            $mapping = $this->mapping->clean($request->validated('mapping'), $campaign->excel_headers ?? []);

            return response()->json($this->import->analyze($campaign, $mapping));
        } catch (ExcelImportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(SaveMappingRequest $request, Campaign $campaign): RedirectResponse
    {
        try {
            $mapping = $this->mapping->clean($request->validated('mapping'), $campaign->excel_headers ?? []);
            $stats = $this->import->importRecipients($campaign, $mapping);
        } catch (ExcelImportException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = sprintf(
            'Imported %s rows: %s eligible, %s invalid, %s duplicate, %s already sent.',
            number_format($stats['total']), number_format($stats['eligible']), number_format($stats['invalid']),
            number_format($stats['duplicates']), number_format($stats['already_sent'])
        );

        if ($stats['unknown_status'] > 0) {
            $message .= ' '.number_format($stats['unknown_status']).' rows have an unrecognised status value and were skipped.';
        }

        $next = Route::has('campaigns.smtp') ? 'campaigns.smtp' : 'campaigns.show';

        return redirect()->route($next, $campaign)->with('status', $message);
    }
}
