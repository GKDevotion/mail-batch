<?php

namespace App\Http\Controllers;

use App\Exports\CampaignResultsExport;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CampaignExportController extends Controller
{
    /** Generates a fresh .xlsx on every download; the original upload is never modified. */
    public function download(Campaign $campaign): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('view', $campaign);

        if ($campaign->total_records === 0) {
            return back()->with('error', 'There is nothing to export yet. Upload and import an Excel file first.');
        }

        $name = (Str::slug($campaign->name) ?: 'campaign').'-results-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new CampaignResultsExport($campaign), $name, ExcelWriter::XLSX);
    }
}
