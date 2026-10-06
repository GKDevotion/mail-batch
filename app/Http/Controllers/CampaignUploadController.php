<?php

namespace App\Http\Controllers;

use App\Exceptions\ExcelImportException;
use App\Http\Requests\UploadExcelRequest;
use App\Models\Campaign;
use App\Services\ExcelImportService;
use Illuminate\Http\RedirectResponse;

class CampaignUploadController extends Controller
{
    public function store(UploadExcelRequest $request, Campaign $campaign, ExcelImportService $import): RedirectResponse
    {
        try {
            $import->attachFile($campaign, $request->file('file'));
        } catch (ExcelImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('campaigns.preview', $campaign)
            ->with('status', 'File uploaded. Review the preview, then continue to column mapping.');
    }
}
