(function ($, w) {
    'use strict';

    // Browser-driven queue worker (replaces `php artisan queue:work`).
    // POSTs to the campaign's work URL in a loop. Each call sends e-mails for a few seconds on the server;
    // the loop ends by itself when the campaign's own queue is empty.
    var running = {};

    w.MailBatchWorker = {
        ensure: function (url, onSnapshot, onDone) {
            if (!url || running[url]) { return; }
            running[url] = true;

            var errors = 0;

            function stop() {
                delete running[url];
                if (onDone) { onDone(); }
            }

            function step() {
                $.ajax({ url: url, method: 'POST', timeout: 180000 })
                    .done(function (r) {
                        errors = 0;
                        if (r.snapshot && onSnapshot) { onSnapshot(r.snapshot); }
                        if (r.has_more) { setTimeout(step, r.busy ? 4000 : 300); } else { stop(); }
                    })
                    .fail(function (xhr) {
                        errors++;
                        var transient = xhr.status === 0 || xhr.status === 429 || xhr.status >= 500;
                        if (transient && errors <= 5) { setTimeout(step, 8000); } else { stop(); }
                    });
            }

            step();
        }
    };
})(jQuery, window);
