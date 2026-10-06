@php($s = $snapshot)
<div class="card border-0 shadow-sm">
    <div class="card-header bg-body d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Sending</span>
        <span id="statusBadge" class="badge text-bg-{{ $s['badge'] }}">{{ $s['status_label'] }}</span>
    </div>
    <div class="card-body">
        <div id="lastError" class="alert alert-warning small {{ $s['last_error'] ? '' : 'd-none' }}">{{ $s['last_error'] }}</div>

        <div class="progress mb-3" style="height: 22px;" role="progressbar" aria-label="Campaign progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $s['percent'] }}">
            <div id="progressBar" class="progress-bar progress-bar-striped" style="width: {{ $s['percent'] }}%">{{ $s['percent'] }}%</div>
        </div>

        <div class="row g-2 text-center mb-3">
            @foreach (['total' => 'Total', 'eligible' => 'Eligible', 'processed' => 'Processed', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped', 'remaining' => 'Remaining'] as $key => $label)
                <div class="col-4 col-md">
                    <div class="border rounded p-2 h-100">
                        <div class="small text-body-secondary">{{ $label }}</div>
                        <div class="fw-semibold" data-k="{{ $key }}">{{ number_format($s[$key]) }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($canSend)
            <div class="d-flex flex-wrap gap-2">
                <button type="button" id="startBtn" class="btn btn-primary" @disabled(! $s['can_start'])><i class="bi bi-play-fill me-1"></i>Start sending</button>
                <button type="button" id="pauseBtn" class="btn btn-outline-warning" @disabled(! $s['can_pause'])><i class="bi bi-pause-fill me-1"></i>Pause</button>
                <button type="button" id="retryBtn" class="btn btn-outline-secondary {{ $s['failed'] > 0 ? '' : 'd-none' }}" @disabled(! $s['can_retry'])><i class="bi bi-arrow-repeat me-1"></i>Retry failed</button>
            </div>
        @endif

        <div id="sendFlash" class="mt-3" role="status" aria-live="polite"></div>
        <p class="form-text mb-0">
            Each click sends up to {{ min($campaign->batch_size, \App\Services\CampaignService::maxBatchSize()) }} emails through the queue.
            Sending runs from this page: keep it open until the batch is done. It uses this campaign's own queue and stops by itself when all emails are processed.
            Failed emails can be retried up to {{ config('mailbatch.max_retries') }} times in total.
        </p>
    </div>
</div>
