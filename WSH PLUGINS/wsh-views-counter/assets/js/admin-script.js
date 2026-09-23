// Save Plugin Settings
function wsh_views_counter_save_settings() {
    var $ = jQuery;

    var cvc_admin_visits = $('#wsh_views_counter-exclude-admin-visits').prop('checked') ? 1 : 0;
    var cvc_count_interval = $('#wsh_views_counter-count-interval').val();
    var cvc_exclude_ips    = $('#wsh_views_counter-exclude-ips').val();
    var cvc_exclude_bots    = $('#wsh_views_counter-exclude-bots').val();

    // NEW: visitor filters
    var exclude_logged_in = $('#wsh_views_counter-exclude-logged-in').prop('checked') ? 1 : 0;
    var exclude_guests    = $('#wsh_views_counter-exclude-guests').prop('checked') ? 1 : 0;
    var exclude_roles     = $('#wsh_views_counter-exclude-roles').val() || []; // array.

    // Display options
    var display_enable        = $('#wsh_views_counter-display-enable').prop('checked') ? 1 : 0;
    var display_position      = $('#wsh_views_counter-display-position').val();
    var display_label         = $('#wsh_views_counter-display-label').val();
    var display_format_number = $('#wsh_views_counter-display-format-number').prop('checked') ? 1 : 0;

    var post_types = jQuery('input[name="wsh_views_counter-post-types[]"]:checked')
        .map(function () { return jQuery(this).val(); })
        .get();

    var cleanup_period = jQuery('#wsh_views_counter-cleanup-period').val();

    var data = {
        action: 'wordpress_ajax_wsh_views_counter_save_settings',
        cvc_admin_visits: cvc_admin_visits,
        cvc_count_interval: cvc_count_interval,
        cvc_exclude_ips: cvc_exclude_ips,
        cvc_exclude_bots: cvc_exclude_bots,

        exclude_logged_in: exclude_logged_in,
        exclude_guests: exclude_guests,
        exclude_roles: exclude_roles,

        display_enable: display_enable,
        display_position: display_position,
        display_label: display_label,
        display_format_number: display_format_number,
        post_types: post_types,
        cleanup_period: cleanup_period
    };

    $.ajax({
        type: 'POST',
        url: (typeof window.ajaxurl !== 'undefined') ? window.ajaxurl : '/wp-admin/admin-ajax.php',
        data: data,
        dataType: 'json',
        beforeSend: function () {
            $('#wsh_views_counter-message').html('');
        },
        success: function (response) {
            if (!response) {
                $('#wsh_views_counter-message').html(
                    "<span style='color: red;'>Error! Please try again later!</span>"
                );
                return;
            }

            if (response.status === 'done') {
                $('#wsh_views_counter-message').html(
                    "<span style='color: green;'>" + (response.message || 'Success!') + "</span>"
                );
            } else {
                $('#wsh_views_counter-message').html(
                    "<span style='color: red;'>" + (response.message || 'Error! Please try again later!') + "</span>"
                );
            }
        },
        error: function () {
            $('#wsh_views_counter-message').html(
                "<span style='color: red;'>Error! Please try again later!</span>"
            );
        }
    });
}

// Import data from Post Views Counter plugin
function wsh_views_counter_import_from_pvc() {
    var $ = jQuery;

    if(confirm('Are you sure you want to import ALL data from Post Views Counter into WSH Views Counter?')){

        $('#wsh_views_import-message').html(
            "<span style='color: red;'>Import in progress... please do not close this page.</span>"
        );

        $.ajax({
            type: 'POST',
            url: (typeof window.ajaxurl !== 'undefined') ? window.ajaxurl : '/wp-admin/admin-ajax.php',
            data: {
                action: 'wordpress_ajax_wsh_views_counter_import_from_pvc'
            },
            dataType: 'json',
            beforeSend: function () {
                $('#wsh_views_import-message').html(
                    "<span style='color: red;'>Import in progress... please do not close this page.</span>"
                );
            },
            success: function (response) {
                if (response && response.status === 'done') {
                    $('#wsh_views_import-message').html(
                        "<span style='color: green;'>" + (response.message || 'Import completed successfully.') + "</span>"
                    );
                } else {
                    var msg = (response && response.message) ? response.message : 'Error! Please try again later.';
                    $('#wsh_views_import-message').html(
                        "<span style='color: red;'>" + msg + "</span>"
                    );
                }
            },
            error: function () {
                $('#wsh_views_import-message').html(
                    "<span style='color: red;'>Error! Please try again later.</span>"
                );
            }
        });
    }
}

// Delete ALL plugin data (logs + meta).
function wsh_views_counter_delete_all_data() {

    var $ = jQuery;
    
    if( confirm('Are you sure you want to delete ALL WSH Views Counter data? This action cannot be undone.') ) {
        var data = {
            action: 'wordpress_ajax_wsh_views_counter_delete_all_data'
        };

        $('#wsh_views_counter-delete-message').html(
            "<span style='color: red;'>Delete in progress... please do not close this page.</span>"
        );


        jQuery.ajax({
            type: 'POST',
            url: (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php',
            data: data,
            dataType: 'json',
            beforeSend: function () {
                jQuery('#wsh_views_counter-delete-message').html(
                    "<span style='color:#cc9900;'>Deleting data...</span>"
                );
            },
            success: function (response) {
                if (!response || !response.status) {
                    jQuery('#wsh_views_counter-delete-message').html(
                        "<span style='color:red;'>Unexpected response from server.</span>"
                    );
                    return;
                }

                var color = (response.status === 'done') ? 'green' : 'red';
                var msg   = response.message || 'Operation finished.';

                jQuery('#wsh_views_counter-delete-message').html(
                    "<span style='color:" + color + ";'>" + msg + "</span>"
                );
            },
            error: function (xhr) {
                jQuery('#wsh_views_counter-delete-message').html(
                    "<span style='color:red;'>AJAX error: " + xhr.status + " " + xhr.statusText + "</span>"
                );
            }
        });
    }

}
