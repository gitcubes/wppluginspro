jQuery(document).ready(function ($) {

    //Update post counter manually
    $(document).on('click', '.wsh-views-counter-save-manual', function (e) {
        e.preventDefault();

        var $btn    = $(this);
        var $box    = $btn.parent('.wsh-views-counter-meta-field');
        var $input  = $box.find('#wsh_views_counter_manual_input');
        var $status = $box.find('.wsh-views-counter-status');

        var postId = $btn.data('post-id');
        var views  = $input.val();

        $status.text('');
        $btn.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: wshViewsCounterMeta.ajax_url,
            dataType: 'json',
            data: {
                action: 'wsh_views_counter_update_manual_views',
                nonce:  wshViewsCounterMeta.nonce,
                post_id: postId,
                views: views
            },
            success: function (response) {
                if (response && response.success) {
                    $input.val(response.data.views);
                    $status.css('color', 'green').text(wshViewsCounterMeta.success);
                } else {
                    $status.css('color', 'red').text(
                        response && response.data && response.data.message
                            ? response.data.message
                            : wshViewsCounterMeta.error
                    );
                }
            },
            error: function () {
                $status.css('color', 'red').text(wshViewsCounterMeta.error);
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });

});
