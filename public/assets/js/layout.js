(function ($) {
    'use strict';

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------- top loading bar (page load + navigation) ----------
    var $loader = $('#mbLoader'), hideTimer = null, failSafe = null;

    function loaderStart() {
        clearTimeout(hideTimer);
        clearTimeout(failSafe);
        $loader.css({ opacity: 1, width: '0%' });
        window.requestAnimationFrame(function () { $loader.css('width', '70%'); });
        failSafe = setTimeout(loaderDone, 8000);   // never stay stuck (downloads, cancelled navigation)
    }

    function loaderDone() {
        clearTimeout(failSafe);
        $loader.css('width', '100%');
        hideTimer = setTimeout(function () {
            $loader.css('opacity', 0);
            setTimeout(function () { $loader.css('width', '0%'); }, 350);
        }, 250);
    }

    loaderStart();
    if (document.readyState === 'complete') { loaderDone(); } else { $(window).on('load', loaderDone); }
    $(window).on('pageshow', function (e) { if (e.originalEvent && e.originalEvent.persisted) { loaderDone(); } });

    $(document).on('click', 'a[href]', function (e) {
        var a = this, href = a.getAttribute('href');
        if (e.isDefaultPrevented() || e.ctrlKey || e.metaKey || e.shiftKey || e.which > 1) { return; }
        if ((a.target && a.target !== '_self') || a.hasAttribute('download') || a.hasAttribute('data-bs-toggle')) { return; }
        if (!href || href.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(href)) { return; }
        if (a.origin !== window.location.origin || /\/export(\?|$)/.test(a.pathname + a.search)) { return; }
        loaderStart();
    });
    $(document).on('submit', 'form', function (e) { if (!e.isDefaultPrevented()) { loaderStart(); } });

    // ---------- sidebar: collapse on desktop, off-canvas on tablet/mobile ----------
    $('#sbToggle').on('click', function () {
        if (window.matchMedia('(min-width: 992px)').matches) {
            var collapsed = $('html').toggleClass('sb-collapsed').hasClass('sb-collapsed');
            try { localStorage.setItem('mb.sb', collapsed ? '1' : '0'); } catch (e) {}
        } else {
            window.bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('sidebar')).toggle();
        }
    });

    // ---------- light / dark theme ----------
    function applyTheme(t) {
        document.documentElement.setAttribute('data-bs-theme', t);
        $('#themeToggle i').attr('class', t === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars');
        try { localStorage.setItem('mb.theme', t); } catch (e) {}
    }
    applyTheme(document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light');
    $('#themeToggle').on('click', function () {
        applyTheme(document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
    });

    // ---------- count-up numbers on the dashboard ----------
    function countUp($el) {
        var target = parseInt(String($el.text()).replace(/[^0-9]/g, ''), 10);
        if (isNaN(target) || target === 0 || reduce) { return; }
        var start = null, duration = 900;
        $el.text('0');
        function step(ts) {
            if (start === null) { start = ts; }
            var p = Math.min((ts - start) / duration, 1);
            $el.text(Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString());
            if (p < 1) { window.requestAnimationFrame(step); }
        }
        window.requestAnimationFrame(step);
    }
    $('[data-stat]').each(function () { countUp($(this)); });
})(jQuery);
