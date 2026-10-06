(function ($) {
    'use strict';

    var $panel = $('#sendPanel');
    if (!$panel.length) { return; }

    var timer = null, keys = ['total', 'eligible', 'processed', 'sent', 'failed', 'skipped', 'remaining'];

    function flash(ok, text) {
        $('#sendFlash').empty().append($('<div class="alert py-2 mb-0">').addClass(ok ? 'alert-success' : 'alert-danger').text(text));
    }

    function schedule(ms) { clearTimeout(timer); timer = setTimeout(poll, ms); }

    function renderFeed(items) {
        var $feed = $('#activityFeed');
        if (!$feed.length || !items) { return; }
        $feed.empty();
        if (!items.length) { $feed.append($('<li class="list-group-item text-body-secondary">').text('No emails yet.')); }
        $.each(items, function (_, r) {
            $feed.append(
                $('<li class="list-group-item d-flex justify-content-between gap-2">').attr('title', r.error || '')
                    .append($('<span class="text-truncate">').text(r.email))
                    .append($('<span class="badge">').addClass(r.status === 'sent' ? 'text-bg-success' : 'text-bg-danger').text(r.status))
            );
        });
    }

    function apply(s) {
        $.each(keys, function (_, k) { $panel.find('[data-k="' + k + '"]').text(Number(s[k]).toLocaleString()); });
        $('#progressBar').css('width', s.percent + '%').text(s.percent + '%').closest('.progress').attr('aria-valuenow', s.percent);
        $('#statusBadge').text(s.status_label).attr('class', 'badge text-bg-' + s.badge);
        $('#startBtn').prop('disabled', !s.can_start);
        $('#pauseBtn').prop('disabled', !s.can_pause);
        $('#retryBtn').prop('disabled', !s.can_retry).toggleClass('d-none', s.failed === 0);
        $('#lastError').toggleClass('d-none', !s.last_error).text(s.last_error || '');
        renderFeed(s.recent);
        // Sending runs from the browser: keep calling this campaign's work URL until its queue is empty.
        if (s.is_running && s.queue_waiting && window.MailBatchWorker) {
            window.MailBatchWorker.ensure($panel.data('work-url'), apply);
        }
        schedule(s.is_running ? 3000 : 10000);
    }

    function poll() {
        if (document.hidden) { schedule(10000); return; }
        $.getJSON($panel.data('status-url')).done(apply).fail(function () { schedule(10000); });
    }

    function act($btn, url) {
        $btn.prop('disabled', true);
        $.post(url)
            .done(function (r) { flash(r.ok, r.message); if (r.snapshot) { apply(r.snapshot); } })
            .fail(function (xhr) {
                var j = xhr.responseJSON;
                flash(false, j && j.message ? j.message : (xhr.status === 429 ? 'Too many requests. Please wait a moment.' : 'The request failed.'));
                poll();
            });
    }

    $('#startBtn').on('click', function () {
        if (window.confirm('Send up to ' + $panel.data('batch') + ' emails now? Sent emails cannot be recalled.')) {
            act($(this), $panel.data('start-url'));
        }
    });
    $('#pauseBtn').on('click', function () { act($(this), $panel.data('pause-url')); });
    $('#retryBtn').on('click', function () {
        if (window.confirm('Retry failed emails now?')) { act($(this), $panel.data('retry-url')); }
    });

    schedule(3000);
})(jQuery);
