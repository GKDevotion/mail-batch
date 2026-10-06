@extends('layouts.app')
@section('title', 'Column mapping')

@section('content')
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="{{ route('campaigns.index') }}">Campaigns</a></li>
        <li class="breadcrumb-item"><a href="{{ route('campaigns.show', $campaign) }}">{{ \Illuminate\Support\Str::limit($campaign->name, 40) }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Column mapping</li>
    </ol></nav>
    <h1 class="h3 mb-3">Column mapping</h1>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <form method="POST" action="{{ route('campaigns.mapping.update', $campaign) }}" id="mappingForm"
                  data-preview-url="{{ route('campaigns.mapping.preview', $campaign) }}" novalidate>
                @csrf
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-body fw-semibold">Match your Excel columns</div>
                    <div class="card-body">
                        @foreach ($fields as $key => $field)
                            @php($selected = old("mapping.$key", $current[$key] ?? ($suggested[$key] ?? null)))
                            <div class="mb-3">
                                <label for="map_{{ $key }}" class="form-label">
                                    {{ $field['label'] }} @if ($field['required']) <span class="text-danger">*</span> @endif
                                </label>
                                <select id="map_{{ $key }}" name="mapping[{{ $key }}]" @disabled(! $canEdit)
                                        class="form-select @error("mapping.$key") is-invalid @enderror">
                                    <option value="">
                                        {{ $key === 'status' ? '— No status column (managed by MailBatch) —' : '— Not mapped —' }}
                                    </option>
                                    @foreach ($headers as $h)
                                        <option value="{{ $h }}" @selected($selected === $h)>{{ $h }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">{{ $field['hint'] }}</div>
                                @error("mapping.$key") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endforeach

                        @verbatim
                            <div class="alert alert-info small mb-0">
                                <strong>Status rules:</strong> <code>1</code> = do not send &middot; <code>0</code>, empty or missing = send.
                                Any other value is skipped for safety. Every Excel column can also be used as a
                                <code>{{ '{{variable}}' }}</code> in your email.
                            </div>
                        @endverbatim
                    </div>
                    @if ($canEdit)
                        <div class="card-footer bg-body d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary" id="analyzeBtn"><i class="bi bi-search me-1"></i>Check import</button>
                            <button type="submit" class="btn btn-primary">Save mapping &amp; import</button>
                        </div>
                    @endif
                </div>
            </form>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-body fw-semibold">Import check</div>
                <div class="card-body" id="analysis">
                    <p class="text-body-secondary mb-0">
                        Click <strong>Check import</strong> to see how many valid, invalid, duplicate and already-sent rows this mapping produces.
                        Nothing is saved until you choose <strong>Save mapping &amp; import</strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(function ($) {
    var $form = $('#mappingForm'), $box = $('#analysis');
    var cards = [
        ['total', 'Total rows', 'secondary'], ['valid', 'Valid emails', 'info'], ['invalid', 'Invalid emails', 'danger'],
        ['duplicates', 'Duplicates', 'warning'], ['already_sent', 'Already sent', 'warning'], ['eligible', 'Eligible', 'success']
    ];

    function render(res) {
        var $row = $('<div class="row g-2 mb-3">');
        $.each(cards, function (_, c) {
            $row.append($('<div class="col-6 col-md-4">').append(
                $('<div class="border rounded p-2 h-100">')
                    .append($('<div class="small text-body-secondary">').text(c[1]))
                    .append($('<div class="fs-5 fw-semibold">').addClass('text-' + c[2] + '-emphasis').text(Number(res.stats[c[0]]).toLocaleString()))
            ));
        });
        var $out = $('<div>').append($row);
        if (res.stats.unknown_status > 0) {
            $out.append($('<div class="alert alert-warning small">').text(res.stats.unknown_status + ' row(s) have a status value other than 1, 0 or empty and will be skipped.'));
        }
        if (res.stats.unsubscribed > 0) {
            $out.append($('<div class="alert alert-secondary small">').text(res.stats.unsubscribed + ' row(s) belong to people who unsubscribed and will be skipped.'));
        }

        var $tbody = $('<tbody>');
        $.each(res.sample, function (_, r) {
            $tbody.append($('<tr>')
                .append($('<td class="text-body-secondary">').text(r.row))
                .append($('<td class="text-truncate-cell">').text(r.name))
                .append($('<td class="text-truncate-cell">').text(r.email))
                .append($('<td class="text-truncate-cell">').text(r.website))
                .append($('<td>').text(r.status))
                .append($('<td>').append(r.reason
                    ? $('<span class="badge text-bg-warning">').text(r.reason)
                    : $('<span class="badge text-bg-success">').text('Will send'))));
        });
        var $table = $('<table class="table table-sm align-middle mb-0">').append(
            '<thead class="table-light"><tr><th>Row</th><th>Name</th><th>Email</th><th>Website</th><th>Status</th><th>Result</th></tr></thead>'
        ).append($tbody);
        $out.append($('<div class="table-responsive">').append($table));
        $box.empty().append($out);
    }

    $('#analyzeBtn').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        $box.html('<div class="text-body-secondary"><span class="spinner-border spinner-border-sm me-2"></span>Analysing file…</div>');

        $.post($form.data('preview-url'), $form.serialize())
            .done(render)
            .fail(function (xhr) {
                var msg = 'The file could not be analysed.', j = xhr.responseJSON;
                if (j) { msg = j.errors ? Object.values(j.errors)[0][0] : (j.message || msg); }
                $box.empty().append($('<div class="alert alert-danger mb-0">').text(msg));
            })
            .always(function () { $btn.prop('disabled', false); });
    });
})(jQuery);
</script>
@endpush
