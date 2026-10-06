@extends('layouts.app')
@section('title', 'Excel preview')

@section('content')
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="{{ route('campaigns.index') }}">Campaigns</a></li>
        <li class="breadcrumb-item"><a href="{{ route('campaigns.show', $campaign) }}">{{ \Illuminate\Support\Str::limit($campaign->name, 40) }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Excel preview</li>
    </ol></nav>
    <h1 class="h3 mb-3">Upload &amp; preview</h1>

    @if (! $campaign->excel_path)
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8 col-xl-6">
                <div class="card border-0 shadow-sm"><div class="card-body p-4">
                    @if ($canEdit)
                        @include('campaigns.partials.upload-form')
                    @else
                        <p class="mb-0 text-body-secondary">This campaign can no longer be changed.</p>
                    @endif
                </div></div>
            </div>
        </div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-body-secondary">File</div>
                <div class="fw-semibold text-break">{{ $campaign->excel_filename }}</div>
                <div class="small text-body-secondary">{{ number_format(($campaign->excel_size ?? 0) / 1024, 1) }} KB</div>
            </div></div></div>
            <div class="col-6 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-body-secondary">Data rows</div>
                <div class="fs-4 fw-semibold">{{ number_format($campaign->total_records) }}</div>
            </div></div></div>
            <div class="col-6 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-body-secondary">Columns detected</div>
                <div class="fs-4 fw-semibold">{{ count($campaign->excel_headers ?? []) }}</div>
            </div></div></div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-body fw-semibold">First {{ count($campaign->excel_preview['rows'] ?? []) }} rows</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th class="text-body-secondary">Row</th>
                        @foreach ($campaign->excel_headers ?? [] as $h)
                            <th class="text-nowrap">{{ $h }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($campaign->excel_preview['rows'] ?? [] as $r)
                        <tr>
                            <td class="text-body-secondary">{{ $r['row'] }}</td>
                            @foreach ($r['cells'] as $cell)
                                <td class="text-truncate-cell">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('campaigns.mapping', $campaign) }}" class="btn btn-primary">
                Continue to column mapping <i class="bi bi-arrow-right ms-1"></i>
            </a>
            @if ($canEdit)
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#replaceBox">
                    <i class="bi bi-arrow-repeat me-1"></i>Replace file
                </button>
            @endif
            <a href="{{ route('campaigns.show', $campaign) }}" class="btn btn-link">Back to campaign</a>
        </div>

        @if ($canEdit)
            <div class="collapse mt-3 @if ($errors->has('file')) show @endif" id="replaceBox">
                <div class="card border-0 shadow-sm"><div class="card-body">
                    <div class="alert alert-warning small">Replacing the file discards the current column mapping and imported recipients.</div>
                    @include('campaigns.partials.upload-form')
                </div></div>
            </div>
        @endif
    @endif
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    // Friendly client-side checks; the server validates everything again.
    $('#uploadForm').on('submit', function (e) {
        var f = $('#file')[0].files[0], $err = $('#fileError').addClass('d-none'), maxKb = $(this).data('max-kb');
        if (!f) { return; }
        var ext = f.name.split('.').pop().toLowerCase(), msg = null;
        if (['xls', 'xlsx'].indexOf(ext) === -1) { msg = 'Only .xls and .xlsx files are accepted.'; }
        else if (f.size === 0) { msg = 'The selected file is empty.'; }
        else if (f.size > maxKb * 1024) { msg = 'The file is too large (maximum ' + (maxKb / 1024) + ' MB).'; }
        if (msg) { e.preventDefault(); $err.text(msg).removeClass('d-none'); }
    });
</script>
@endpush
