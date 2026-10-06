{{-- Uses the nearest <form>'s ".smtp-field" inputs (or the selected saved account). Buttons must not submit. --}}
<div class="border rounded p-3 bg-body-tertiary" data-smtp-test data-account-id="{{ $accountId ?? '' }}" data-url="{{ route('smtp-accounts.test') }}">
    <div class="fw-semibold mb-2"><i class="bi bi-plug me-1"></i>Test SMTP</div>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-6">
            <label class="form-label small mb-1" for="test_email_{{ $suffix ?? 'x' }}">Send test email to</label>
            <input type="email" id="test_email_{{ $suffix ?? 'x' }}" name="test_email" class="form-control" value="{{ auth()->user()->email }}" maxlength="254">
        </div>
        <div class="col-12 col-md-6 d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" data-smtp-test-btn="connection">Test connection</button>
            <button type="button" class="btn btn-outline-primary" data-smtp-test-btn="email"><i class="bi bi-send me-1"></i>Send test email</button>
        </div>
    </div>
    <div class="mt-3" data-smtp-result role="status" aria-live="polite"></div>
</div>
