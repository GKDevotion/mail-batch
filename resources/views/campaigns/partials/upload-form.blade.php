<form method="POST" action="{{ route('campaigns.upload', $campaign) }}" enctype="multipart/form-data"
      id="uploadForm" data-max-kb="{{ config('mailbatch.upload.max_kb') }}" novalidate>
    @csrf
    <label for="file" class="form-label">Excel file (.xls or .xlsx)</label>
    <input type="file" id="file" name="file" required
           accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
           class="form-control @error('file') is-invalid @enderror">
    @error('file') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    <div id="fileError" class="text-danger small mt-1 d-none"></div>
    <div class="form-text">
        Maximum {{ $maxMb }} MB and {{ number_format($maxRows) }} rows. The first row must contain column headings and only the
        first sheet is read. Your original file is never modified.
    </div>
    <button class="btn btn-primary mt-3" type="submit"><i class="bi bi-upload me-1"></i>Upload &amp; preview</button>
</form>
