// File: assets/admin/admin.js
(function ($) {

  function collectHomeFreeLayout() {
    // Inputs are named wsh_ar_home_free__raw[header][title] etc.
    function val(name) {
      var el = document.querySelector('[name="' + name + '"]');
      return el ? el.value : "";
    }
    function checked(name) {
      var el = document.querySelector('[name="' + name + '"]');
      return !!(el && el.checked);
    }

    var headerTitle = val('wsh_ar_home_free__raw[header][title]') || 'Top';
    var headerLimit = parseInt(val('wsh_ar_home_free__raw[header][limit]') || '5', 10);
    var headerIds = (val('wsh_ar_home_free__raw[header][post_ids]') || '')
      .split(',')
      .map(function (x) { return parseInt(x.trim(), 10); })
      .filter(function (x) { return !isNaN(x) && x > 0; });

    var mainTitle = val('wsh_ar_home_free__raw[main][title]') || 'Main';
    var mainLimit = parseInt(val('wsh_ar_home_free__raw[main][limit]') || '4', 10);
    var mainCat = parseInt(val('wsh_ar_home_free__raw[main][category_id]') || '0', 10);

    var latestTitle = val('wsh_ar_home_free__raw[latest][title]') || 'Latest';
    var latestLimit = parseInt(val('wsh_ar_home_free__raw[latest][limit]') || '5', 10);
    var latestExcl = checked('wsh_ar_home_free__raw[latest][exclude_duplicates]');

    var moreTitle = val('wsh_ar_home_free__raw[more][title]') || 'More';
    var moreLimit = parseInt(val('wsh_ar_home_free__raw[more][limit]') || '6', 10);
    var moreParam = val('wsh_ar_home_free__raw[more][paged_param]') || 'more_page';
    var moreExcl = checked('wsh_ar_home_free__raw[more][exclude_duplicates]');

    // clamp
    function clamp(n, a, b) { n = parseInt(n, 10); if (isNaN(n)) n = a; return Math.max(a, Math.min(b, n)); }
    headerLimit = clamp(headerLimit, 1, 50);
    mainLimit = clamp(mainLimit, 1, 50);
    latestLimit = clamp(latestLimit, 1, 50);
    moreLimit = clamp(moreLimit, 1, 50);

    return {
      sections: [
        { id: 'header', type: 'manual_posts', title: headerTitle, limit: headerLimit, post_ids: headerIds },
        { id: 'main', type: 'category_latest', title: mainTitle, limit: mainLimit, category_id: isNaN(mainCat) ? 0 : mainCat, exclude_duplicates: true },
        { id: 'latest', type: 'latest', title: latestTitle, limit: latestLimit, exclude_duplicates: !!latestExcl },
        { id: 'more', type: 'more_news', title: moreTitle, limit: moreLimit, paged_param: moreParam, exclude_duplicates: !!moreExcl }
      ]
    };
  }

  $(document).on("click", "#wsh-ar-home-preview-btn", function (e) {
    e.preventDefault();

    var $out = $("#wsh-ar-home-preview-output");
    $out.val("Loading preview...");

    var layout = collectHomeFreeLayout(); // null for now

    $.ajax({
      url: WSH_AR_ADMIN.ajax_url,
      method: "POST",
      dataType: "json",
      data: {
        action: "wsh_ar_home_preview",
        nonce: WSH_AR_ADMIN.nonce,
        layout: layout ? JSON.stringify(layout) : ""
      }
    })
      .done(function (resp) {
        if (!resp || !resp.success) {
          $out.val("Error: " + (resp && resp.data && resp.data.message ? resp.data.message : "Unknown"));
          return;
        }
        $out.val(JSON.stringify(resp.data.data, null, 2));
      })
      .fail(function (xhr) {
        $out.val("AJAX failed: HTTP " + xhr.status);
      });
  });
})(jQuery);
