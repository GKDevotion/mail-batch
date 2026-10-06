@php($c = $campaign ?? null)
<div class="mb-3">
    <label for="name" class="form-label">Campaign name</label>
    <input id="name" type="text" name="name" maxlength="120" required value="{{ old('name', $c?->name) }}"
           class="form-control @error('name') is-invalid @enderror" placeholder="e.g. October outreach">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="batch_size" class="form-label">Batch size</label>
    <input id="batch_size" type="number" name="batch_size" min="1" max="{{ $maxBatch }}" required
           value="{{ old('batch_size', $c?->batch_size ?? $maxBatch) }}"
           class="form-control @error('batch_size') is-invalid @enderror">
    <div class="form-text">Emails processed per "Start Sending" action (maximum {{ $maxBatch }}).</div>
    @error('batch_size') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="sender_identification" class="form-label">Sender identification <span class="text-body-secondary">(optional)</span></label>
    <textarea id="sender_identification" name="sender_identification" rows="3" maxlength="500"
              class="form-control @error('sender_identification') is-invalid @enderror"
              placeholder="Company name, postal address or contact details shown in the email footer">{{ old('sender_identification', $c?->sender_identification) }}</textarea>
    @error('sender_identification') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="form-check form-switch mb-4">
    <input type="hidden" name="include_unsubscribe" value="0">
    <input class="form-check-input" type="checkbox" role="switch" id="include_unsubscribe" name="include_unsubscribe" value="1"
           @checked(old('include_unsubscribe', $c?->include_unsubscribe ?? true))>
    <label class="form-check-label" for="include_unsubscribe">Include an unsubscribe link in every email</label>
</div>
