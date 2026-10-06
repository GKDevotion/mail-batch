@php
    $state = $recipient->state;
    $busy = $recipient->queued_at !== null || $recipient->sending_at !== null;
    $skip = $recipient->skip_reason ? (\App\Enums\SkipReason::tryFrom($recipient->skip_reason)?->label() ?? $recipient->skip_reason) : null;
    $canRetry = $canSend && ! $busy && $state === \App\Enums\RecipientState::Failed && $recipient->canRetry();
@endphp
<tr data-id="{{ $recipient->id }}" data-busy="{{ $busy ? 1 : 0 }}">
    <td class="text-body-secondary">{{ $recipient->row_number }}</td>
    <td class="text-truncate-cell">{{ $recipient->name }}</td>
    <td class="text-truncate-cell">{{ $recipient->email }}</td>
    <td class="d-none d-lg-table-cell text-truncate-cell">{{ $recipient->website }}</td>
    <td>
        @if ($busy)
            <span class="badge text-bg-info">Queued</span>
        @else
            <span class="badge text-bg-{{ $state->badgeClass() }}">{{ $state->label() }}</span>
        @endif
    </td>
    <td class="d-none d-md-table-cell text-nowrap small">{{ $recipient->sent_at?->format('d M Y H:i') }}</td>
    <td class="d-none d-xl-table-cell small" style="max-width: 280px;">
        @if ($skip)
            <span class="text-body-secondary">{{ $skip }}</span>
        @elseif ($recipient->error_message)
            <span class="text-danger text-break" title="{{ $recipient->error_message }}">{{ \Illuminate\Support\Str::limit($recipient->error_message, 120) }}</span>
            <div class="text-body-secondary">Attempts: {{ $recipient->retry_count }}/{{ config('mailbatch.max_retries') }}</div>
        @endif
    </td>
    <td class="text-end text-nowrap">
        @if ($canRetry)
            <button type="button" class="btn btn-sm btn-outline-secondary js-retry"
                    data-url="{{ route('campaigns.recipients.retry', [$campaign, $recipient]) }}">
                <i class="bi bi-arrow-repeat me-1"></i>Retry
            </button>
        @endif
    </td>
</tr>
