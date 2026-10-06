(function ($) {
    'use strict';

    // Every AJAX request carries the CSRF token and asks for JSON.
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Accept': 'application/json'
        }
    });

    // Redirect to login when the session expires during an AJAX call.
    $(document).ajaxError(function (e, xhr) {
        if (xhr.status === 401 || xhr.status === 419) { window.location.reload(); }
    });

    // Shared delete confirmation modal.
    $(document).on('click', '[data-confirm-delete]', function () {
        $('#confirmForm').attr('action', $(this).data('action'));
        $('#confirmMessage').text($(this).data('message'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmModal')).show();
    });

    // Dashboard: refresh stat cards without reloading the page.
    var $stats = $('#dashboard-stats');
    if ($stats.length) {
        var url = $stats.data('url');
        var refresh = function () {
            if (document.hidden) { return; }
            $.getJSON(url).done(function (data) {
                $.each(data, function (key, value) {
                    $stats.find('[data-stat="' + key + '"]').text(Number(value).toLocaleString());
                });
            });
        };
        setInterval(refresh, 15000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { refresh(); } });
    }
})(jQuery);
