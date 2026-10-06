(function ($) {
    'use strict';

    var suggested = { ssl: 465, tls: 587 };

    // Suggest the usual port for the chosen encryption, but never override a custom port.
    $(document).on('change', '#encryption', function () {
        var $port = $(this).closest('form').find('#smtp_port'), v = $port.val();
        if (v === '' || v === '465' || v === '587') { $port.val(suggested[$(this).val()]); }
    });

    function show($res, ok, text) {
        $res.empty().append($('<div class="alert py-2 mb-0">').addClass(ok ? 'alert-success' : 'alert-danger').text(text));
    }

    // Test connection / send test email. Only a status + sanitised message comes back; the password is never echoed.
    $(document).on('click', '[data-smtp-test-btn]', function () {
        var $btn = $(this),
            $panel = $btn.closest('[data-smtp-test]'),
            $form = $btn.closest('form'),
            $res = $panel.find('[data-smtp-result]'),
            mode = $btn.data('smtp-test-btn'),
            id = $form.find('select[name="smtp_account_id"]').val() || $panel.data('account-id'),
            data = $form.find('.smtp-field').serialize()
                + '&mode=' + encodeURIComponent(mode)
                + '&test_email=' + encodeURIComponent($panel.find('[name="test_email"]').val() || '');

        if (id) { data += '&smtp_account_id=' + encodeURIComponent(id); }

        var $all = $panel.find('button').prop('disabled', true);
        $res.html('<div class="text-body-secondary"><span class="spinner-border spinner-border-sm me-2"></span>Testing… this can take a few seconds.</div>');

        $.ajax({ url: $panel.data('url'), method: 'POST', data: data })
            .done(function (r) { show($res, r.ok, r.message); })
            .fail(function (xhr) {
                var msg = 'The test could not be run. Please try again.', j = xhr.responseJSON;
                if (xhr.status === 429) { msg = 'Too many tests. Please wait a minute and try again.'; }
                else if (j && j.errors) { msg = Object.values(j.errors)[0][0]; }
                else if (j && j.message) { msg = j.message; }
                show($res, false, msg);
            })
            .always(function () { $all.prop('disabled', false); });
    });
})(jQuery);
