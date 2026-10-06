@extends('layouts.app')
@section('title', 'Failed emails (admin)')

@section('content')
    <h1 class="h3 mb-3">Administration</h1>
    @include('admin.partials.nav')

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>Campaign</th><th class="d-none d-md-table-cell">Owner</th><th>Email</th><th>Error</th>
                    <th class="d-none d-lg-table-cell text-center">Attempts</th><th class="d-none d-lg-table-cell">Last attempt</th>
                </tr></thead>
                <tbody>
                @forelse ($recipients as $r)
                    <tr>
                        <td class="text-truncate-cell"><a class="text-decoration-none" href="{{ route('admin.campaigns.show', $r->campaign_id) }}">{{ $r->campaign?->name }}</a></td>
                        <td class="d-none d-md-table-cell text-truncate-cell">{{ $r->campaign?->user?->name }}</td>
                        <td class="text-truncate-cell">{{ $r->email }}</td>
                        <td class="small text-danger text-break" style="max-width: 340px;">{{ \Illuminate\Support\Str::limit($r->error_message, 140) }}</td>
                        <td class="d-none d-lg-table-cell text-center">{{ $r->retry_count }}/{{ config('mailbatch.max_retries') }}</td>
                        <td class="d-none d-lg-table-cell text-nowrap small">{{ $r->last_attempt_at?->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-5">No failed emails.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($recipients->hasPages()) <div class="card-footer bg-body">{{ $recipients->links() }}</div> @endif
    </div>
    <p class="form-text mt-2">Owners retry failed emails from their own campaign (Recipients page or "Retry failed").</p>
@endsection
