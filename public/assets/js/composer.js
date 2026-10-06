(function ($) {
    'use strict';

    var $form = $('#composeForm');
    if (!$form.length) { return; }

    var $active = $('#body_html'), timer = null, lastAutoText = '';

    $('#subject, #body_html, #body_text').on('focus', function () { $active = $(this); });

    function insertAtCursor($el, text) {
        var el = $el[0], s = el.selectionStart, e = el.selectionEnd;
        el.value = el.value.slice(0, s) + text + el.value.slice(e);
        el.selectionStart = el.selectionEnd = s + text.length;
        $el.trigger('input').trigger('focus');
    }

    function wrapSelection($el, open, close) {
        var el = $el[0], s = el.selectionStart, e = el.selectionEnd, sel = el.value.slice(s, e);
        el.value = el.value.slice(0, s) + open + sel + close + el.value.slice(e);
        el.selectionStart = s + open.length;
        el.selectionEnd = s + open.length + sel.length;
        $el.trigger('input').trigger('focus');
    }

    $(document).on('click', '[data-insert-var]', function () {
        insertAtCursor($active, '{{' + $(this).data('insert-var') + '}}');
    });

    $(document).on('click', '[data-wrap]', function () {
        wrapSelection($('#body_html'), String($(this).data('open')), String($(this).data('close')));
    });

    function payload() {
        return {
            subject: $('#subject').val(),
            body_html: $('#body_html').val(),
            body_text: $('#body_text').val(),
            recipient_id: $('#recipient_id').val()
        };
    }

    function render(res) {
        lastAutoText = res.auto_text || '';
        $('#previewSubject').text(res.subject || '');
        $('#previewFrame').attr('srcdoc', res.html);   // sandboxed iframe, content is sanitised server-side
        $('#previewText').text(res.text || '');

        var $w = $('#previewWarnings').empty();
        if (res.unknown && res.unknown.length) {
            var names = $.map(res.unknown, function (n) { return '{{' + n + '}}'; }).join(', ');
            $w.append($('<div class="alert alert-warning py-2 small">').text('Unknown variable(s): ' + names + '. Saving is blocked until they are fixed.'));
        }
    }

    function preview(done) {
        $.post($form.data('preview-url'), payload())
            .done(function (res) { render(res); if (done) { done(res); } })
            .fail(function (xhr) {
                var j = xhr.responseJSON, msg = 'Preview failed.';
                if (xhr.status === 429) { msg = 'Too many preview requests. Please wait a moment.'; }
                else if (j && j.errors) { msg = Object.values(j.errors)[0][0]; }
                $('#previewWarnings').empty().append($('<div class="alert alert-danger py-2 small">').text(msg));
            });
    }

    function schedule() { clearTimeout(timer); timer = setTimeout(preview, 600); }

    $('#subject, #body_html, #body_text, #recipient_id').on('input change', schedule);

    $('#genText').on('click', function () {
        preview(function (res) { $('#body_text').val(res.auto_text || '').trigger('input'); });
    });

    $('#sendTest').on('click', function () {
        var $btn = $(this).prop('disabled', true), $res = $('#testResult');
        $res.html('<span class="spinner-border spinner-border-sm me-2"></span>Sending…');

        var data = payload();
        data.test_email = $('#test_email').val();

        $.post($form.data('test-url'), data)
            .done(function (r) {
                $res.empty().append($('<div class="alert py-2 mb-0">').addClass(r.ok ? 'alert-success' : 'alert-danger').text(r.message));
            })
            .fail(function (xhr) {
                var j = xhr.responseJSON, msg = 'The test could not be sent.';
                if (xhr.status === 429) { msg = 'Too many tests. Please wait a minute and try again.'; }
                else if (j && j.errors) { msg = Object.values(j.errors)[0][0]; }
                $res.empty().append($('<div class="alert alert-danger py-2 mb-0">').text(msg));
            })
            .always(function () { $btn.prop('disabled', false); });
    });

    preview();
})(jQuery);
