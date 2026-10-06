@php($compact = $compact ?? false)
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
        <tr>
            <th>Name</th>
            <th>Status</th>
            <th class="d-none d-md-table-cell" style="min-width:150px">Progress</th>
            <th class="d-none d-lg-table-cell text-end">Records</th>
            <th class="d-none d-lg-table-cell">Created</th>
            @unless ($compact) <th class="text-end">Actions</th> @endunless
        </tr>
        </thead>
        <tbody>
        @forelse ($campaigns as $campaign)
            <tr>
                <td class="text-truncate-cell">
                    <a href="{{ route('campaigns.show', $campaign) }}" class="fw-semibold text-decoration-none">{{ $campaign->name }}</a>
                    <div class="small text-body-secondary">{{ $campaign->excel_filename ?? 'No Excel file yet' }}</div>
                </td>
                <td><x-status-badge :status="$campaign->status" /></td>
                <td class="d-none d-md-table-cell">
                    <div class="progress" style="height:6px" role="progressbar" aria-valuenow="{{ $campaign->progress_percent }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: {{ $campaign->progress_percent }}%"></div>
                    </div>
                    <div class="small text-body-secondary">{{ $campaign->progress_percent }}%</div>
                </td>
                <td class="d-none d-lg-table-cell text-end">{{ number_format($campaign->total_records) }}</td>
                <td class="d-none d-lg-table-cell text-nowrap">{{ $campaign->created_at->format('d M Y') }}</td>
                @unless ($compact)
                    <td class="text-end text-nowrap">
                        <a href="{{ route('campaigns.show', $campaign) }}" class="btn btn-sm btn-outline-primary">Open</a>
                        @can('delete', $campaign)
                            <button type="button" class="btn btn-sm btn-outline-danger" data-confirm-delete
                                    data-action="{{ route('campaigns.destroy', $campaign) }}"
                                    data-message="Delete campaign &quot;{{ $campaign->name }}&quot; and all its recipients and logs? This cannot be undone.">
                                <i class="bi bi-trash"></i>
                            </button>
                        @endcan
                    </td>
                @endunless
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-body-secondary py-5">No campaigns yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
