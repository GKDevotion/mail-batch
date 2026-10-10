(function ($) {
    'use strict';

    // Permission matrix: "select all" checkbox per module, kept in sync with its children.
    function syncGroup(group) {
        var $items = $('[data-check-group="' + group + '"]');
        var $all = $('[data-check-all="' + group + '"]');
        $all.prop('checked', $items.length > 0 && $items.filter(':checked').length === $items.length);
    }

    $(document).on('change', '[data-check-all]', function () {
        var group = $(this).data('check-all');
        $('[data-check-group="' + group + '"]').prop('checked', this.checked);
    });
    $(document).on('change', '[data-check-group]', function () { syncGroup($(this).data('check-group')); });
    $('[data-check-all]').each(function () { syncGroup($(this).data('check-all')); });
})(jQuery);
