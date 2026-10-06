(function ($) {
    'use strict';

    // Fill the shared "edit user" modal from the clicked row's data attributes.
    $(document).on('click', '[data-edit-user]', function () {
        var $b = $(this), $m = $('#editUserModal');

        $m.find('form').attr('action', $b.data('action'));
        $('#editUserName').text($b.data('name'));
        $('#eu_role').val($b.data('role'));
        $('#eu_active').prop('checked', String($b.data('active')) === '1');
        $('#eu_limit').val($b.data('limit'));
        $('#eu_pw').val('');

        bootstrap.Modal.getOrCreateInstance($m[0]).show();
    });
})(jQuery);
