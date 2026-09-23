jQuery(document).ready(function ($) {

    // Key za geo info u localStorage.
    const WSH_VC_GEO_STORAGE_KEY = 'wsh_vc_geo_info';

    /**
     * Učitaj IP + GEO iz localStorage ili sa ipapi.co (u browseru).
     * callback dobija objekat { ip, country_code, country_name, city }.
     */
    function wsh_views_counter_get_geo_info(callback) {
        let geo = null;

        // 1) Pokušaj da pročitaš iz localStorage.
        try {
            if (window.localStorage) {
                const cached = localStorage.getItem(WSH_VC_GEO_STORAGE_KEY);
                if (cached) {
                    geo = JSON.parse(cached);
                }
            }
        } catch (e) {
            // Ignoriši grešku – nastavi na fetch.
        }

        if (geo && typeof geo === 'object' && geo.ip) {
            callback(geo);
            return;
        }

        // 2) Ako nema keša, pozovi ipapi.co/json (bez IP, automatski koristi IP posetioca).
        fetch('https://ipapi.co/json/')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                geo = {
                    ip: data && data.ip ? data.ip : '',
                    country_code: data && data.country ? data.country : '',
                    country_name: data && data.country_name ? data.country_name : '',
                    city: data && data.city ? data.city : ''
                };

                try {
                    if (window.localStorage) {
                        localStorage.setItem(WSH_VC_GEO_STORAGE_KEY, JSON.stringify(geo));
                    }
                } catch (e) {
                    // Ignoriši ako ne može da upiše.
                }

                callback(geo);
            })
            .catch(function () {
                // Ako ne uspe, vrati prazne vrednosti – bitno da AJAX i dalje radi.
                callback({
                    ip: '',
                    country_code: '',
                    country_name: '',
                    city: ''
                });
            });
    }

    // Generate / read persistent session ID.
    function wsh_views_counter_get_session_id() {
        const key = 'wsh_vc_session_id';
        let sid = '';

        try {
            if (window.localStorage) {
                sid = localStorage.getItem(key);
                if (!sid) {
                    sid = 's_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
                    localStorage.setItem(key, sid);
                }
            }
        } catch (e) {
            // Ako localStorage nije dostupan, sesija ostaje prazna (nije kritično).
            sid = '';
        }

        return sid;
    }

    // Diff in minutes between two timestamps (ms).
    function wsh_views_counter_diff_minutes(dt2, dt1) {
        const diffMs = Number(dt2) - Number(dt1);
        return Math.abs(Math.round(diffMs / 60000)); // 1000 * 60
    }

    /**
     * Ajax call to WordPress.
     * Prima geoInfo { ip, country_code, country_name, city }.
     */
    function wsh_views_counter_ajax(geoInfo) {
        let is_desktop = 1;
        let is_mobile = 0;

        const ua = navigator.userAgent || '';

        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|Windows Phone/i.test(ua)) {
            is_mobile = 1;
            is_desktop = 0;
        }

        // Session ID – koristi se za sessions tabelu + live hits.
        const session_id = wsh_views_counter_get_session_id();

        // Pravi referrer trenutne stranice (Google, Facebook, drugi sajt...)
        const referrer = document.referrer || '';

        const data = {
            // IMPORTANT: the name must match the PHP hook/action.
            action: 'wordpress_ajax_wsh_views_counter_init',
            is_mobile: is_mobile,
            is_desktop: is_desktop,
            post_id: (typeof window.wshViewsCounter !== 'undefined' && wshViewsCounter.post_id)
                ? parseInt(wshViewsCounter.post_id, 10)
                : 0,
            session_id: session_id,
            referrer: referrer,
            // NOVO: IP i geo iz browsera (ipapi.co)
            client_ip: geoInfo && geoInfo.ip ? geoInfo.ip : '',
            country_code: geoInfo && geoInfo.country_code ? geoInfo.country_code : '',
            country_name: geoInfo && geoInfo.country_name ? geoInfo.country_name : '',
            city: geoInfo && geoInfo.city ? geoInfo.city : ''
        };

        // ajax_url is provided from PHP via wp_localize_script,
        // fallback is the default /wp-admin/admin-ajax.php
        const ajaxUrl =
            (typeof window.wshViewsCounter !== 'undefined' && wshViewsCounter.ajax_url)
                ? wshViewsCounter.ajax_url
                : (typeof window.ajaxurl !== 'undefined'
                    ? window.ajaxurl
                    : '/wp-admin/admin-ajax.php');

        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            data: data,
            dataType: 'json',
            success: function (response) {
                // Currently you are not doing anything with the response, which is fine.
                if (response && response.error) {
                    // console.error(response.error);
                }
            }
        });
    }

    // Init counter (localStorage + interval logic).
    function wsh_views_counter_init() {

        // Ako nemamo post_id (nije single), nema šta da merimo.
        if (typeof window.wshViewsCounter === 'undefined' || !wshViewsCounter.post_id) {
            return;
        }

        // URL without hash part (so /post#comment does not count as a new view).
        const target_url = window.location.href.split('#')[0];

        let viewedPosts = [];
        const current_datetime = Date.now();

        // Default interval is 60 minutes.
        let cvc_interval = 60;

        const cvc_intervals = {
            '30m': 30,
            '1h': 60,
            '3h': 180,
            '6h': 360,
            '12h': 720,
            '1d': 1440
        };

        // Interval value is provided from PHP (wp_localize_script).
        if (typeof window.wshViewsCounter !== 'undefined'
            && typeof wshViewsCounter.count_interval !== 'undefined'
            && typeof cvc_intervals[wshViewsCounter.count_interval] !== 'undefined') {

            cvc_interval = parseInt(cvc_intervals[wshViewsCounter.count_interval], 10);
        }

        // Read from localStorage.
        try {
            if (window.localStorage && localStorage.getItem('cvc_viewedPosts') !== null) {
                const cvc_viewedPosts = localStorage.getItem('cvc_viewedPosts');
                viewedPosts = JSON.parse(cvc_viewedPosts);
                if (!Array.isArray(viewedPosts)) {
                    viewedPosts = [];
                }
            }
        } catch (e) {
            // If localStorage is not available, just send ajax and exit (sa GEO).
            wsh_views_counter_get_geo_info(function (geoInfo) {
                wsh_views_counter_ajax(geoInfo);
            });
            return;
        }

        if (viewedPosts.length > 0) {
            let exists = false;
            let viewtime_datetime = null;
            let index = -1;

            for (let i = 0; i < viewedPosts.length; i++) {
                const parts = String(viewedPosts[i]).split('|');
                if (parts[0] === target_url) {
                    exists = true;
                    viewtime_datetime = parts[1];
                    index = i;
                    break;
                }
            }

            if (exists) {
                const totalminutes = wsh_views_counter_diff_minutes(current_datetime, viewtime_datetime);
                if (totalminutes > cvc_interval) {
                    wsh_views_counter_get_geo_info(function (geoInfo) {
                        wsh_views_counter_ajax(geoInfo);
                    });
                    viewedPosts[index] = target_url + '|' + current_datetime;
                }
            } else {
                wsh_views_counter_get_geo_info(function (geoInfo) {
                    wsh_views_counter_ajax(geoInfo);
                });
                viewedPosts.push(target_url + '|' + current_datetime);
            }
        } else {
            wsh_views_counter_get_geo_info(function (geoInfo) {
                wsh_views_counter_ajax(geoInfo);
            });
            viewedPosts.push(target_url + '|' + current_datetime);
        }

        // Save back to localStorage.
        try {
            localStorage.setItem('cvc_viewedPosts', JSON.stringify(viewedPosts));
        } catch (e) {
            // Ignore if it cannot be written.
        }
    }

    // INIT
    wsh_views_counter_init();
});
