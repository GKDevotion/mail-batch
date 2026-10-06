(function ($) {
    'use strict';

    var $table = $('#recipientsTable');
    if (!$table.length) { return; }

    var timer = null;

    function flash(ok, text) {
        $('#recipientFlash').empty().append(
            $('<div class="alert alert-dismissible fade show py-2">').addClass(ok ? 'alert-success' : 'alert-danger')
                .text(text).append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>')
        );
    }

    function busyIds() {
        return $table.find('tr[data-busy="1"]').map(function () { return $(this).data('id'); }).get();
    }

    // Rows being sent are refreshed until they finish. Row HTML is rendered (and escaped) by the server.
    function schedule() {
        clearTimeout(timer);
        if (busyIds().length) { timer = setTimeout(poll, 3000); }
    }

    function kickWorker() {
        if (window.MailBatchWorker) { window.MailBatchWorker.ensure($table.data('work-url')); }
    }

    function poll() {
        var ids = busyIds();
        if (!ids.length) { return; }
        $.getJSON($table.data('state-url'), { ids: ids })
            .done(function (r) {
                $.each(r.rows, function (id, html) { $table.find('tr[data-id="' + id + '"]').replaceWith(html); });
            })
            .always(schedule);
    }

    $table.on('click', '.js-retry', function () {
        var $btn = $(this).prop('disabled', true), $row = $btn.closest('tr');

        $.post($btn.data('url'))
            .done(function (r) { $row.replaceWith(r.row); flash(true, r.message); kickWorker(); schedule(); })
            .fail(function (xhr) {
                var j = xhr.responseJSON;
                flash(false, j && j.message ? j.message : (xhr.status === 429 ? 'Too many requests. Please wait a moment.' : 'The retry could not be queued.'));
                $btn.prop('disabled', false);
            });
    });

    if (busyIds().length) { kickWorker(); }
    schedule();
})(jQuery);
