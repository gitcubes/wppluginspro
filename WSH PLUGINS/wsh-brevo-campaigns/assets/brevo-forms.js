(function ($) {
    $(document).on('submit', '.brevo-form-inner', function (e) {
        e.preventDefault();
        var $f = $(this), $wrap = $f.closest('.brevo-form');
        var $btn = $f.find('.bf-submit'), $msg = $f.find('.bf-msg');
        $msg.removeClass('ok err').text('');
        $btn.prop('disabled', true).addClass('loading');

        var data = $f.serializeArray();
        data.push({ name: 'action', value: 'brevo_form_subscribe' });
        data.push({ name: 'nonce', value: $f.find('input[name="nonce"]').val() });

        $.post(BREVO_FORMS.ajaxUrl, data)
            .done(function (resp) {
                if (resp && resp.success) {
                    $msg.addClass('ok').text($msg.data('ok'));
                    $f[0].reset();
                    setTimeout(
                function(){$('.nl-modal, .nl-backdrop').hide();}, 3000);
                    
                } else {
                    var txt = (resp && resp.data && resp.data.message) ? resp.data.message : $msg.data('err');
                    $msg.addClass('err').text(txt);
                }
            })
            .fail(function (xhr) {
                $msg.addClass('err').text($msg.data('err'));
            })
            .always(function () {
                $btn.prop('disabled', false).removeClass('loading');
            });
    });
})(jQuery);
