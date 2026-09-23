(function ($) {
  function showNotice(type, msg) {
    var css = type === "error" ? "notice-error" : "notice-success";
    var html =
      '<div class="notice ' + css + ' is-dismissible"><p>' + msg + "</p></div>";
    $("#wsh-wcbpm-notices").html(html);
  }

  function isProActive() {
    return !!window.WSH_WCBPM?.pro_active;
  }

  function ensureProIfNeeded() {
    var scope = $("#wsh_price_scope").val();
    if (scope === "sale_from_regular" && !isProActive()) {
      showNotice("error", "This is a PRO feature. Please activate your license to use it.");
      // opciono: scroll do license card-a
      var $lic = $("#wsh-wcbpm-license");
      if ($lic.length) $("html, body").animate({ scrollTop: $lic.offset().top - 20 }, 300);
      return false;
    }
    return true;
  }

  function getFormPayload() {
    var isProUI = $("#wsh_attr_terms").length > 0; // postoji samo u PRO

    return {
      filters: {
        categories: $("#wsh_filter_categories").val() || [],
        tags: $("#wsh_filter_tags").val() || [],

        attribute_tax: $("#wsh_attr_tax").val() || "",

        // PRO: term IDs (multi)
        attribute_terms: isProUI ? ($("#wsh_attr_terms").val() || []) : [],

        // FREE legacy: slug ili term_id iz text inputa
        attribute_term: !isProUI && $("#wsh_attr_term").length ? ($("#wsh_attr_term").val() || "") : "",

        // PRO OR toggle (ako nema checkbox, default AND)
        logic: $("#wsh_filters_or").length && $("#wsh_filters_or").is(":checked") ? "or" : "and",

        include_variable_children: $("#wsh_include_variations").is(":checked") ? 1 : 0,
        price_scope: $("#wsh_price_scope").val() || "both",
      },
      update: {
        method: $("#wsh_update_method").val() || "percent",
        action: $("#wsh_update_action").val() || "decrease",
        value: $("#wsh_update_value").val() || "",
        rounding: $("#wsh_rounding").val() || "none",
      },
    };
  }

  //PRO
  function loadAttributeTerms(tax) {
    var $sel = $("#wsh_attr_terms");

    // ✅ If PRO multiselect doesn't exist (FREE UI), do nothing.
    if (!$sel.length) return;

    // reset + disable while loading
    $sel.prop("disabled", true).empty().trigger("change");

    if (!tax) {
      // nothing selected: keep empty and disabled
      $sel.prop("disabled", true);
      $(document.body).trigger("wc-enhanced-select-init");
      return;
    }

    // optional: show loading option
    $sel.append(new Option("Loading…", "", false, false));

    $.post(window.WSH_WCBPM.ajaxurl, {
      action: "wsh_wcbpm_attr_terms",
      taxonomy: tax,
      _ajax_nonce: window.WSH_WCBPM.nonce
    })
      .done(function (resp) {
        $sel.empty();

        if (!resp || !resp.success || !resp.data || !Array.isArray(resp.data.terms)) {
          $sel.prop("disabled", true);
          $(document.body).trigger("wc-enhanced-select-init");
          return;
        }

        resp.data.terms.forEach(function (t) {
          // expect {id, text}
          var opt = new Option(String(t.text || ""), String(t.id || ""), false, false);
          $sel.append(opt);
        });

        $sel.prop("disabled", false);

        // re-init selectWoo
        $(document.body).trigger("wc-enhanced-select-init");
      })
      .fail(function () {
        // fail-safe: keep disabled & empty
        $sel.empty().prop("disabled", true);
        $(document.body).trigger("wc-enhanced-select-init");
      });
  }

  //PRO
  function isPerfModeEnabled() {
    return $("#wsh_perf_mode").length && $("#wsh_perf_mode").is(":checked");
  }

  function getBatchSize() {
    var v = parseInt($("#wsh_batch_size").val() || "200", 10);
    if (isNaN(v)) v = 200;
    if (v < 50) v = 50;
    if (v > 2000) v = 2000;
    return v;
  }

  function perfUiShow() {
    $("#wsh-perf-progress").show();
    $("#wsh-perf-progress-bar").css("width", "0%");
    $("#wsh-perf-progress-text").text("0%");
    $("#wsh-perf-progress-meta").text("Preparing…");
  }

  function perfUiUpdate(processed, total, applied, errors) {
    var pct = total > 0 ? Math.floor((processed / total) * 100) : 0;
    if (pct > 100) pct = 100;
    $("#wsh-perf-progress-bar").css("width", pct + "%");
    $("#wsh-perf-progress-text").text(pct + "%");
    $("#wsh-perf-progress-meta").text(
      "Processed: " + processed + "/" + total + " | Updated: " + applied + " | Errors: " + errors
    );
  }

  function perfUiHide() {
    $("#wsh-perf-progress").hide();
  }

  function renderPreview(rows, summary) {
    var $tbody = $("#wsh-wcbpm-preview-rows").empty();

    if (!rows || !rows.length) {
      $("#wsh-wcbpm-preview-wrap").hide();
      $("#wsh-wcbpm-preview-count").text("0"); // optional
      showNotice("error", window.WSH_WCBPM?.i18n?.no_products || "No products found.");
      return false;
    }

    $("#wsh-wcbpm-preview-wrap").show();
    $("#wsh-wcbpm-preview-count").text(
      summary && summary.count ? summary.count : rows.length
    );

    rows.forEach(function (r) {
      var tr = $("<tr/>");
      tr.append("<td>" + (r.product_id || "") + "</td>");
      tr.append(
        "<td>" +
        (r.name || "") +
        (r.variation_label ? '<br><span class="description">' + r.variation_label + "</span>" : "") +
        "</td>"
      );
      tr.append('<td style="text-align:right;">' + (r.old_regular !== null ? r.old_regular : "—") + "</td>");
      tr.append('<td style="text-align:right;">' + (r.old_sale !== null ? r.old_sale : "—") + "</td>");
      tr.append('<td style="text-align:right; font-weight:600;">' + (r.new_regular !== null ? r.new_regular : "—") + "</td>");
      tr.append('<td style="text-align:right; font-weight:600;">' + (r.new_sale !== null ? r.new_sale : "—") + "</td>");
      $tbody.append(tr);
    });

    return true;
  }

  function initEnhancedSelects() {
    // ✅ WooCommerce standard init (SelectWoo)
    $(document.body).trigger("wc-enhanced-select-init");
  }

  $(document).ready(function () {
    initEnhancedSelects();
  });

  $(document).on("click", "#wsh-btn-dry-run", function (e) {
    e.preventDefault();
    $("#wsh-wcbpm-notices").empty();

    //Check is PRO needed
    if (!ensureProIfNeeded()) return;

    var payload = getFormPayload();
    payload.action = "wsh_wcbpm_dry_run";
    payload._ajax_nonce = window.WSH_WCBPM.nonce;

    $("#wsh-btn-dry-run").prop("disabled", true).text("Calculating...");
    $.post(window.WSH_WCBPM.ajaxurl, payload)
      .done(function (resp) {
        if (!resp || !resp.success) {
          showNotice(
            "error",
            resp && resp.data && resp.data.message ? resp.data.message : "Preview error."
          );
          return;
        }

        var hasRows = renderPreview(resp.data.rows, resp.data.summary);

        // ✅ IMPORTANT: Save last preview "job context" for Schedule button
        window.WSH_WCBPM_LAST_PREVIEW = {
          payload: payload, // includes filters + update (what we just previewed)
          summary: resp.data.summary || {},
          rows_previewed: (resp.data.rows && resp.data.rows.length) ? resp.data.rows.length : 0,
          created_at: Date.now()
        };

        // ✅ Show success only if there are rows (otherwise renderPreview already showed error)
        if (hasRows) {
          showNotice("success", "Preview is ready. Review results and click Apply.");
        }
      })
      .fail(function () {
        showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
      })
      .always(function () {
        $("#wsh-btn-dry-run").prop("disabled", false).text("Preview / Dry run");
      });
  });

  $(document).on("click", "#wsh-btn-apply", function (e) {
    e.preventDefault();
    $("#wsv-wcbpm-apply-confirm").show();
  });

  $(document).on("click", "#wsh-btn-apply-confirm-no", function (e) {
    e.preventDefault();
    $("#wsv-wcbpm-apply-confirm").hide();
  });

  $(document).on("click", "#wsh-btn-apply-confirm-yes", function (e) {
    e.preventDefault();
    $("#wsv-wcbpm-apply-confirm").hide();
    $("#wsh-wcbpm-notices").empty();

    // PRO Performance mode apply
    if (isPerfModeEnabled()) {
      if (!ensureProIfNeeded()) return;

      var payload = getFormPayload();
      payload.action = "wsh_wcbpm_job_start_apply";
      payload._ajax_nonce = window.WSH_WCBPM.nonce;
      payload.batch_size = getBatchSize();

      perfUiShow();
      $("#wsh-btn-apply-confirm-yes").prop("disabled", true).text("Starting...");

      $.post(window.WSH_WCBPM.ajaxurl, payload)
        .done(function (resp) {
          if (!resp || !resp.success) {
            showNotice("error", resp?.data?.message || "Unable to start performance job.");
            perfUiHide();
            return;
          }

          var jobId = resp.data.job_id;
          var total = parseInt(resp.data.total || "0", 10) || 0;

          showNotice("success", resp.data.message || "Job started.");

          // step loop
          function step() {
            $.post(window.WSH_WCBPM.ajaxurl, {
              action: "wsh_wcbpm_job_step_apply",
              _ajax_nonce: window.WSH_WCBPM.nonce,
              job_id: jobId
            })
              .done(function (r2) {
                if (!r2 || !r2.success) {
                  showNotice("error", r2?.data?.message || "Job step failed.");
                  return;
                }

                var processed = parseInt(r2.data.processed || "0", 10) || 0;
                var total2 = parseInt(r2.data.total || total, 10) || total;
                var applied = parseInt(r2.data.applied || "0", 10) || 0;
                var errors = parseInt(r2.data.errors || "0", 10) || 0;

                perfUiUpdate(processed, total2, applied, errors);

                if (r2.data.done) {
                  showNotice("success", r2.data.message || "Performance apply completed.");
                  $("#wsh-btn-undo").prop("disabled", false);
                  $("#wsh-btn-apply-confirm-yes").prop("disabled", false).text("Yes, apply");
                  // optional: refresh preview by running dry-run again
                  return;
                }

                // next step (small delay to keep UI responsive)
                setTimeout(step, 120);
              })
              .fail(function () {
                showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
              });
          }

          // kick it off
          $("#wsh-btn-apply-confirm-yes").text("Processing...");
          perfUiUpdate(0, total, 0, 0);
          step();
        })
        .fail(function () {
          showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
          perfUiHide();
        })
        .always(function () {
          // do not re-enable here; we re-enable on done/fail in flow
        });

      return; // IMPORTANT: skip normal apply flow
    }

    //Check is PRO needed
    if (!ensureProIfNeeded()) return;

    var payload = getFormPayload();
    payload.action = "wsh_wcbpm_apply";
    payload._ajax_nonce = window.WSH_WCBPM.nonce;

    $("#wsh-btn-apply-confirm-yes").prop("disabled", true).text("Applying...");
    $.post(window.WSH_WCBPM.ajaxurl, payload)
      .done(function (resp) {
        if (!resp || !resp.success) {
          showNotice(
            "error",
            resp && resp.data && resp.data.message ? resp.data.message : "Apply error."
          );
          return;
        }

        showNotice("success", resp.data.message || "Prices have been updated.");
        if (resp.data.rows && resp.data.summary) {
          renderPreview(resp.data.rows, resp.data.summary);
        }
        $("#wsh-btn-undo").prop("disabled", false);
      })
      .fail(function () {
        showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
      })
      .always(function () {
        $("#wsh-btn-apply-confirm-yes").prop("disabled", false).text("Yes, apply");
      });
  });

  $(document).on("click", "#wsh-btn-undo", function (e) {
    e.preventDefault();
    $("#wsh-wcbpm-notices").empty();

    var $btn = $("#wsh-btn-undo");

    var payload = {
      action: "wsh_wcbpm_undo_last",
      _ajax_nonce: window.WSH_WCBPM.nonce
    };

    $btn.prop("disabled", true).text("Restoring...");

    $.post(window.WSH_WCBPM.ajaxurl, payload)
      .done(function (resp) {
        if (!resp || !resp.success) {
          showNotice(
            "error",
            resp && resp.data && resp.data.message ? resp.data.message : "Undo error."
          );
          $btn.prop("disabled", false).text("Undo last update");
          return;
        }

        showNotice("success", resp.data.message || "Prices have been restored.");

        // ✅ reset button text/state after success
        // (snapshot is deleted after restore, so disabling is OK)
        $btn.prop("disabled", true).text("Undo last update");

        // ✅ scroll so user sees message (notice is at top)
        var $wrap = $(".wrap.wsh-wcbpm");
        if ($wrap.length) {
          $("html, body").animate({ scrollTop: $wrap.offset().top - 20 }, 200);
        }
      })
      .fail(function () {
        showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
        $btn.prop("disabled", false).text("Undo last update");
      });
  });

  $(document).on("click", "#wsh-btn-export-template", function (e) {
    e.preventDefault();
    window.location.href = window.WSH_WCBPM.exportTemplateUrl;
  });

  $(document).on("click", "#wsh-btn-export-current", function (e) {
    e.preventDefault();

    var payload = getFormPayload();
    var filtersJson = "";
    try {
      filtersJson = encodeURIComponent(JSON.stringify(payload.filters || {}));
    } catch (err) {
      showNotice("error", "Unable to serialize filters for CSV export.");
      return;
    }

    window.location.href =
      window.WSH_WCBPM.exportCurrentBaseUrl + "&filters=" + filtersJson;
  });

  $(document).ready(function () {
    // If URL contains #wsh-wcbpm-license, scroll smoothly.
    if (window.location.hash === "#wsh-wcbpm-license") {
      var $el = $("#wsh-wcbpm-license");
      if ($el.length) {
        $("html, body").animate({ scrollTop: $el.offset().top - 40 }, 300);
      }
    }
  });

  //PRO
  $(document).on("click", ".wsh-restore-batch", function (e) {
    e.preventDefault();
    $("#wsh-wcbpm-notices").empty();

    if (!confirm("Restore this snapshot? This will overwrite current prices.")) return;

    if (!ensureProIfNeeded()) return;

    var batchId = $(this).data("batch") || "";
    if (!batchId) return;

    // If perf mode enabled => restore via job
    if (typeof isPerfModeEnabled === "function" && isPerfModeEnabled()) {
      perfUiShow();

      $.post(window.WSH_WCBPM.ajaxurl, {
        action: "wsh_wcbpm_job_start_restore",
        _ajax_nonce: window.WSH_WCBPM.nonce,
        batch_id: batchId,
        batch_size: (typeof getBatchSize === "function" ? getBatchSize() : 200)
      })
        .done(function (resp) {
          if (!resp || !resp.success) {
            showNotice("error", resp?.data?.message || "Unable to start restore job.");
            perfUiHide();
            return;
          }

          var jobId = resp.data.job_id;
          var total = parseInt(resp.data.total || "0", 10) || 0;

          showNotice("success", resp.data.message || "Restore job started.");
          perfUiUpdate(0, total, 0, 0);

          function step() {
            $.post(window.WSH_WCBPM.ajaxurl, {
              action: "wsh_wcbpm_job_step_restore",
              _ajax_nonce: window.WSH_WCBPM.nonce,
              job_id: jobId
            })
              .done(function (r2) {
                if (!r2 || !r2.success) {
                  showNotice("error", r2?.data?.message || "Restore step failed.");
                  return;
                }

                var processed = parseInt(r2.data.processed || "0", 10) || 0;
                var total2 = parseInt(r2.data.total || total, 10) || total;
                var restored = parseInt(r2.data.restored || "0", 10) || 0;
                var errors = parseInt(r2.data.errors || "0", 10) || 0;

                // reuse existing progress UI: "Updated" label is fine; it's still count
                perfUiUpdate(processed, total2, restored, errors);

                if (r2.data.done) {
                  showNotice("success", r2.data.message || "Performance restore completed.");
                  return;
                }

                setTimeout(step, 120);
              })
              .fail(function () {
                showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
              });
          }

          step();
        })
        .fail(function () {
          showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
          perfUiHide();
        });

      return;
    }

    // Fallback: old instant restore (non-perf)
    $.post(window.WSH_WCBPM.ajaxurl, {
      action: "wsh_wcbpm_restore_batch",
      _ajax_nonce: window.WSH_WCBPM.nonce,
      batch_id: batchId
    })
      .done(function (resp) {
        if (!resp || !resp.success) {
          showNotice("error", resp?.data?.message || "Restore failed.");
          return;
        }
        showNotice("success", resp.data.message || "Restore completed.");
      })
      .fail(function () {
        showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
      });
  });


  //PRO
  $(document).on("click", ".wsh-delete-batch", function (e) {
    e.preventDefault();
    $("#wsh-wcbpm-notices").empty();

    if (!confirm("Delete this snapshot?")) return;

    var batchId = $(this).data("batch") || "";
    if (!batchId) return;

    $.post(window.WSH_WCBPM.ajaxurl, {
      action: "wsh_wcbpm_delete_batch",
      _ajax_nonce: window.WSH_WCBPM.nonce,
      batch_id: batchId
    })
      .done(function (resp) {
        if (!resp || !resp.success) {
          showNotice("error", resp?.data?.message || "Delete failed.");
          return;
        }
        showNotice("success", resp.data.message || "Deleted.");
        // simplest: refresh page so table updates
        window.location.reload();
      })
      .fail(function () {
        showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
      });
  });

  //PRO Presets
  function applyPresetToUi(payload) {
    payload = payload || {};
    var filters = payload.filters || {};
    var update = payload.update || {};
    var perf = payload.perf || {};

    // Filters
    $("#wsh_filter_categories").val(filters.categories || []).trigger("change");
    $("#wsh_filter_tags").val(filters.tags || []).trigger("change");
    $("#wsh_attr_tax").val(filters.attribute_tax || "");
    $("#wsh_attr_term").val(filters.attribute_term || "");
    $("#wsh_include_variations").prop("checked", !!filters.include_variable_children);
    $("#wsh_price_scope").val(filters.price_scope || "both");

    // Update
    $("#wsh_update_method").val(update.method || "percent");
    $("#wsh_update_action").val(update.action || "decrease");
    $("#wsh_update_value").val(update.value || "");
    $("#wsh_rounding").val(update.rounding || "none");

    // Perf (PRO)
    if ($("#wsh_perf_mode").length) {
      $("#wsh_perf_mode").prop("checked", !!perf.enabled);
    }
    if ($("#wsh_batch_size").length && perf.batch_size) {
      $("#wsh_batch_size").val(perf.batch_size);
    }
  }

  function currentUiAsPresetPayload() {
    var p = getFormPayload();

    return {
      filters: p.filters || {},
      update: p.update || {},
      perf: {
        enabled: ($("#wsh_perf_mode").length ? $("#wsh_perf_mode").is(":checked") : false),
        batch_size: ($("#wsh_batch_size").length ? parseInt($("#wsh_batch_size").val() || "200", 10) : 200)
      }
    };
  }

  $(document).on("click", "#wsh_preset_save", function (e) {
    e.preventDefault();
    $("#wsh-wcbpm-notices").empty();

    if (!isProActive()) {
      showNotice("error", "This is a PRO feature. Please activate your license.");
      return;
    }

    var name = prompt("Preset name (required):", "");
    if (!name) return;

    var desc = prompt("Description (optional):", "") || "";

    var payloadObj = currentUiAsPresetPayload();
    var payloadJson = JSON.stringify(payloadObj);

    $.post(window.WSH_WCBPM.ajaxurl, {
      action: "wsh_wcbpm_preset_save",
      _ajax_nonce: window.WSH_WCBPM.nonce,
      name: name,
      description: desc,
      payload: payloadJson
    })
      .done(function (resp) {
        if (!resp || !resp.success) {
          showNotice("error", resp?.data?.message || "Save failed.");
          return;
        }
        showNotice("success", resp.data.message || "Preset saved.");
        // simplest: reload to refresh dropdown
        window.location.reload();
      })
      .fail(function () {
        showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
      });
  });

  function loadSelectedPreset(callback) {
    var id = $("#wsh_preset_select").val() || "";
    if (!id) {
      showNotice("error", "Select a preset first.");
      return;
    }

    $.post(window.WSH_WCBPM.ajaxurl, {
      action: "wsh_wcbpm_preset_get",
      _ajax_nonce: window.WSH_WCBPM.nonce,
      id: id
    })
      .done(function (resp) {
        if (!resp || !resp.success) {
          showNotice("error", resp?.data?.message || "Preset load failed.");
          return;
        }
        var preset = resp.data.preset || {};
        var payload = preset.payload || {};
        applyPresetToUi(payload);
        if (typeof callback === "function") callback(preset);
        showNotice("success", 'Preset loaded: ' + (preset.name || ""));
      })
      .fail(function () {
        showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
      });
  }

  $(document).on("click", "#wsh_preset_load", function (e) {
    e.preventDefault();
    $("#wsh-wcbpm-notices").empty();
    loadSelectedPreset();
  });

  $(document).on("click", "#wsh_preset_run", function (e) {
    e.preventDefault();
    $("#wsh-wcbpm-notices").empty();

    loadSelectedPreset(function () {
      // after loading, run Preview
      $("#wsh-btn-dry-run").trigger("click");
    });
  });

  $(document).on("click", "#wsh_preset_delete", function (e) {
    e.preventDefault();
    $("#wsh-wcbpm-notices").empty();

    var id = $("#wsh_preset_select").val() || "";
    if (!id) {
      showNotice("error", "Select a preset first.");
      return;
    }

    if (!confirm("Delete this preset?")) return;

    $.post(window.WSH_WCBPM.ajaxurl, {
      action: "wsh_wcbpm_preset_delete",
      _ajax_nonce: window.WSH_WCBPM.nonce,
      id: id
    })
      .done(function (resp) {
        if (!resp || !resp.success) {
          showNotice("error", resp?.data?.message || "Delete failed.");
          return;
        }
        showNotice("success", resp.data.message || "Deleted.");
        window.location.reload();
      })
      .fail(function () {
        showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
      });
  });

  //PRO Import
  function renderImportPreview(rows) {
    var $wrap = $("#wsh-import-preview-wrap");
    var $tbody = $("#wsh-import-preview-rows").empty();

    if (!rows || !rows.length) {
      $wrap.hide();
      return false;
    }

    // simple HTML escape (avoid breaking table if SKU/name contains < > etc.)
    function esc(s) {
      s = (s === null || s === undefined) ? "" : String(s);
      return s
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    rows.forEach(function (r) {
      var tr = $("<tr/>");

      var statusHtml = (r.status === "ok")
        ? '<span style="color:#1d7f2f; font-weight:600;">OK</span>'
        : '<span style="color:#b32d2e; font-weight:600;">ERROR</span><br><span class="description">' + esc(r.error || "") + "</span>";

      tr.append("<td>" + esc(r.product_id || "") + "</td>");

      // ✅ NEW: SKU column
      tr.append("<td>" + esc(r.sku || "") + "</td>");

      tr.append("<td>" + esc(r.name || "") + "</td>");
      tr.append('<td style="text-align:right;">' + (r.old_regular !== null ? esc(r.old_regular) : "—") + "</td>");
      tr.append('<td style="text-align:right;">' + (r.old_sale !== null ? esc(r.old_sale) : "—") + "</td>");
      tr.append('<td style="text-align:right; font-weight:600;">' + (r.new_regular !== null ? esc(r.new_regular) : "—") + "</td>");
      tr.append('<td style="text-align:right; font-weight:600;">' + (r.new_sale !== null ? esc(r.new_sale) : "—") + "</td>");
      tr.append("<td>" + statusHtml + "</td>");

      $tbody.append(tr);
    });

    $wrap.show();
    return true;
  }

  $(document).on("click", "#wsh_import_preview_btn", function (e) {
    e.preventDefault();
    $("#wsh-wcbpm-notices").empty();

    if (!isProActive()) {
      showNotice("error", "This is a PRO feature. Please activate your license.");
      return;
    }

    var fileInput = document.getElementById("wsh_import_file");
    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
      showNotice("error", "Please select a CSV file.");
      return;
    }

    var fd = new FormData();
    fd.append("action", "wsh_wcbpm_import_preview");
    fd.append("_ajax_nonce", window.WSH_WCBPM.nonce);
    fd.append("file", fileInput.files[0]);
    fd.append("clear_sale_empty", $("#wsh_import_clear_sale_empty").is(":checked") ? "1" : "0");

    $("#wsh_import_preview_btn").prop("disabled", true).text("Uploading...");

    $.ajax({
      url: window.WSH_WCBPM.ajaxurl,
      method: "POST",
      data: fd,
      processData: false,
      contentType: false
    })
      .done(function (resp) {
        if (!resp || !resp.success) {
          showNotice("error", resp?.data?.message || "Import preview failed.");
          return;
        }

        $("#wsh_import_id").val(resp.data.import_id || "");
        $("#wsh-import-summary").text(
          "Total rows: " + (resp.data.summary?.total_rows || 0) +
          " | Preview: " + (resp.data.summary?.preview_rows || 0) +
          " | Valid (in preview): " + (resp.data.summary?.valid_rows || 0) +
          " | Errors (in preview): " + (resp.data.summary?.error_rows || 0)
        );

        var has = renderImportPreview(resp.data.rows || []);
        $("#wsh_import_apply_btn").prop("disabled", !has);

        showNotice("success", resp.data.message || "Import preview ready.");
      })
      .fail(function () {
        showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
      })
      .always(function () {
        $("#wsh_import_preview_btn").prop("disabled", false).text("Preview Import");
      });
  });

  $(document).on("click", "#wsh_import_apply_btn", function (e) {
    e.preventDefault();
    $("#wsh-wcbpm-notices").empty();

    var importId = $("#wsh_import_id").val() || "";
    if (!importId) {
      showNotice("error", "Missing import_id. Please preview the import again.");
      return;
    }

    if (!confirm("Apply this import? Prices will be updated in the database.")) return;

    // Start job (always batch)
    perfUiShow();
    $("#wsh_import_apply_btn").prop("disabled", true).text("Starting...");

    $.post(window.WSH_WCBPM.ajaxurl, {
      action: "wsh_wcbpm_job_start_import",
      _ajax_nonce: window.WSH_WCBPM.nonce,
      import_id: importId,
      batch_size: (typeof getBatchSize === "function" ? getBatchSize() : 200)
    })
      .done(function (resp) {
        if (!resp || !resp.success) {
          showNotice("error", resp?.data?.message || "Unable to start import job.");
          perfUiHide();
          $("#wsh_import_apply_btn").prop("disabled", false).text("Apply Import (batch)");
          return;
        }

        var jobId = resp.data.job_id;
        var total = parseInt(resp.data.total || "0", 10) || 0;

        showNotice("success", resp.data.message || "Import started.");
        perfUiUpdate(0, total, 0, 0);

        function step() {
          $.post(window.WSH_WCBPM.ajaxurl, {
            action: "wsh_wcbpm_job_step_import",
            _ajax_nonce: window.WSH_WCBPM.nonce,
            job_id: jobId
          })
            .done(function (r2) {
              if (!r2 || !r2.success) {
                showNotice("error", r2?.data?.message || "Import step failed.");
                return;
              }

              var processed = parseInt(r2.data.processed || "0", 10) || 0;
              var total2 = parseInt(r2.data.total || total, 10) || total;
              var applied = parseInt(r2.data.applied || "0", 10) || 0;
              var errors = parseInt(r2.data.errors || "0", 10) || 0;

              perfUiUpdate(processed, total2, applied, errors);

              if (r2.data.done) {
                showNotice("success", r2.data.message || "Import completed.");
                $("#wsh_import_apply_btn").prop("disabled", false).text("Apply Import (batch)");
                return;
              }

              setTimeout(step, 120);
            })
            .fail(function () {
              showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
            });
        }

        step();
      })
      .fail(function () {
        showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
        perfUiHide();
        $("#wsh_import_apply_btn").prop("disabled", false).text("Apply Import (batch)");
      });
  });


  $(document).on("change", "#wsh_attr_tax", function () {
    loadAttributeTerms($(this).val() || "");
  });

  // PRO Schedule
  $(document).on("change", "#wsh_schedule_mode", function () {
    var mode = $(this).val() || "now";
    if (mode === "later") {
      $("#wsh_schedule_datetime_row").show();
    } else {
      $("#wsh_schedule_datetime_row").hide();
    }
  });

  /*
  $(document).on("click", "#wsh-btn-schedule", function (e) {
    e.preventDefault();
    $("#wsh-wcbpm-notices").empty();

    // PRO guard (isti kao za sale_from_regular)
    if (!isProActive()) {
      showNotice("error", "This is a PRO feature. Please activate your license.");
      var $lic = $("#wsh-wcbpm-license");
      if ($lic.length) $("html, body").animate({ scrollTop: $lic.offset().top - 20 }, 300);
      return;
    }

    var payload = getFormPayload();
    payload.action = "wsh_wcbpm_schedule";
    payload._ajax_nonce = window.WSH_WCBPM.nonce;

    payload.mode = $("#wsh_schedule_mode").val() || "now";
    payload.run_at = ($("#wsh_schedule_datetime").val() || "");
    payload.batch_size = parseInt($("#wsh_schedule_batch_size").val() || "200", 10);

    $("#wsh-btn-schedule").prop("disabled", true).text("Scheduling...");

    $.post(window.WSH_WCBPM.ajaxurl, payload)
      .done(function (resp) {
        if (!resp || !resp.success) {
          showNotice("error", resp && resp.data && resp.data.message ? resp.data.message : "Schedule error.");
          return;
        }
        showNotice("success", resp.data.message || "Job scheduled.");
      })
      .fail(function () {
        showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
      })
      .always(function () {
        $("#wsh-btn-schedule").prop("disabled", false).text("Schedule job");
      });
  }); */


  //Scheduler
  function escHtml(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function fmtStatusBadge(status) {
    status = (status || "").toLowerCase();
    var map = {
      scheduled: { t: "Scheduled", bg: "#f0f6fc", c: "#0a4b78", b: "#b6d7f2" },
      running: { t: "Running", bg: "#fff8e5", c: "#7a4b00", b: "#f5d48a" },
      done: { t: "Done", bg: "#edfaef", c: "#1d7f2f", b: "#a8e0b2" },
      failed: { t: "Failed", bg: "#fde8e8", c: "#b32d2e", b: "#f3b3b3" },
      canceled: { t: "Canceled", bg: "#f1f1f1", c: "#444", b: "#d0d0d0" }
    };
    var it = map[status] || { t: status || "—", bg: "#f1f1f1", c: "#444", b: "#ddd" };
    return (
      '<span style="display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:600; background:' +
      it.bg +
      "; color:" +
      it.c +
      "; border:1px solid " +
      it.b +
      ';">' +
      escHtml(it.t) +
      "</span>"
    );
  }

  function fmtDate(ts) {
    if (!ts) return "—";
    // WP date is better, but we can show local browser date too:
    var d = new Date(ts * 1000);
    if (isNaN(d.getTime())) return "—";
    return d.toLocaleString();
  }

  function ajaxPost(action, data) {
    data = data || {};
    data.action = action;
    data._ajax_nonce = window.WSH_WCBPM && window.WSH_WCBPM.nonce ? window.WSH_WCBPM.nonce : "";
    return $.post(window.WSH_WCBPM.ajaxurl, data);
  }

  function renderJobsCards(jobs) {
    var $list = $("#wsh-jobs-list").empty();
    var $empty = $("#wsh-jobs-empty");

    if (!jobs || !jobs.length) {
      $empty.show();
      return;
    }
    $empty.hide();

    jobs.forEach(function (j) {
      var title = j.title || ("Job " + (j.job_id || ""));
      var runAt = fmtDate(j.run_at);
      var createdAt = fmtDate(j.created_at);

      var sub = [];
      if (j.summary_update) sub.push('<div><strong>Update:</strong> ' + escHtml(j.summary_update) + "</div>");
      if (j.summary_filters) sub.push('<div><strong>Filters:</strong> ' + escHtml(j.summary_filters) + "</div>");
      if (j.target_count && parseInt(j.target_count, 10) > 0) sub.push('<div><strong>Targets:</strong> ' + escHtml(j.target_count) + "</div>");

      var errorHtml = "";
      if ((j.status || "").toLowerCase() === "failed" && j.last_error) {
        errorHtml =
          '<div style="margin-top:8px; padding:8px 10px; background:#fde8e8; border:1px solid #f3b3b3; border-radius:8px; color:#b32d2e;">' +
          "<strong>Error:</strong> " +
          escHtml(j.last_error) +
          "</div>";
      }

      var disabled = ((j.status || "").toLowerCase() === "done" || (j.status || "").toLowerCase() === "running") ? "disabled" : "";

      var card =
        '<div class="wsh-job-card" data-job-id="' + escHtml(j.job_id) + '" style="background:#fff; border:1px solid #e5e5e5; border-radius:12px; padding:12px 12px 10px; box-shadow:0 1px 2px rgba(0,0,0,.04);">' +
        '<div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">' +
        '<div style="min-width:0;">' +
        '<div style="font-weight:700; font-size:13px; line-height:1.35; margin:0 0 6px 0;">' + escHtml(title) + "</div>" +
        '<div class="description" style="margin:0; font-size:12px;">' +
        "<div><strong>Run at:</strong> " + escHtml(runAt) + "</div>" +
        "<div><strong>Created:</strong> " + escHtml(createdAt) + "</div>" +
        "</div>" +
        "</div>" +
        "<div>" + fmtStatusBadge(j.status) + "</div>" +
        "</div>" +

        '<div style="margin-top:10px; font-size:12px; line-height:1.5;">' + sub.join("") + "</div>" +
        errorHtml +

        '<div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">' +
        '<button type="button" class="button wsh-job-details">Details</button>' +
        '<button type="button" class="button wsh-job-run" ' + disabled + '>Run now</button>' +
        '<button type="button" class="button button-link-delete wsh-job-cancel" ' + disabled + '>Cancel</button>' +
        '<button type="button" class="button button-link-delete wsh-job-delete">Delete</button>' +
        "</div>" +
        "</div>";

      $list.append(card);
    });
  }

  function loadJobsList() {
    return ajaxPost("wsh_wcbpm_jobs_list", {})
      .done(function (resp) {
        if (!resp || !resp.success || !resp.data) return;
        renderJobsCards(resp.data.jobs || []);
      });
  }

  // ===== Modal helpers =====
  var currentModalJobId = "";

  function openModal(job) {
    currentModalJobId = job.job_id || "";

    $("#wsh-job-modal-title").text(job.title || ("Job " + currentModalJobId));

    var sub = [];
    sub.push("Status: " + (job.status || "—"));
    sub.push("Run at: " + fmtDate(job.run_at));
    $("#wsh-job-modal-sub").text(sub.join("  •  "));

    var html = "";

    html += '<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">';

    html += '<div style="border:1px solid #e5e5e5; border-radius:10px; padding:10px;">' +
      '<div style="font-weight:700; margin-bottom:6px;">Summary</div>' +
      '<div style="font-size:12px; line-height:1.5;">' +
      '<div><strong>Update:</strong> ' + escHtml(job.summary_update || "—") + "</div>" +
      '<div><strong>Filters:</strong> ' + escHtml(job.summary_filters || "—") + "</div>" +
      '<div><strong>Targets:</strong> ' + escHtml(job.target_count || 0) + "</div>" +
      '<div><strong>Batch size:</strong> ' + escHtml(job.batch_size || "—") + "</div>" +
      "</div>" +
      "</div>";

    html += '<div style="border:1px solid #e5e5e5; border-radius:10px; padding:10px;">' +
      '<div style="font-weight:700; margin-bottom:6px;">Runner</div>' +
      '<div style="font-size:12px; line-height:1.5;">' +
      '<div><strong>Type:</strong> ' + escHtml((job.runner && job.runner.type) ? job.runner.type : "—") + "</div>" +
      '<div><strong>Action ID:</strong> ' + escHtml((job.runner && job.runner.action_id) ? job.runner.action_id : "—") + "</div>" +
      "</div>" +
      "</div>";

    html += "</div>";

    if ((job.last_error || "") !== "") {
      html +=
        '<div style="margin-top:12px; padding:10px 12px; background:#fde8e8; border:1px solid #f3b3b3; border-radius:10px; color:#b32d2e;">' +
        "<strong>Error:</strong> " + escHtml(job.last_error) +
        "</div>";
    }

    // Result
    if (job.result && typeof job.result === "object" && (job.result.message || job.result.success !== undefined)) {
      html += '<div style="margin-top:12px; border:1px solid #e5e5e5; border-radius:10px; padding:10px 12px;">' +
        '<div style="font-weight:700; margin-bottom:6px;">Last result</div>' +
        '<div style="font-size:12px; line-height:1.5;">' +
        '<div><strong>Success:</strong> ' + escHtml(job.result.success ? "yes" : "no") + "</div>" +
        '<div><strong>Message:</strong> ' + escHtml(job.result.message || "—") + "</div>" +
        "</div>" +
        "</div>";
    }

    // Raw payload (collapsible)
    html +=
      '<details style="margin-top:12px;">' +
      '<summary style="cursor:pointer; font-weight:700;">Advanced (raw payload)</summary>' +
      '<pre style="white-space:pre-wrap; word-break:break-word; background:#f6f7f7; border:1px solid #e5e5e5; border-radius:10px; padding:10px; font-size:12px; margin-top:8px;">' +
      escHtml(JSON.stringify({ filters: job.filters || {}, update: job.update || {} }, null, 2)) +
      "</pre>" +
      "</details>";

    $("#wsh-job-modal-body").html(html);

    // Button states
    var st = (job.status || "").toLowerCase();
    var disableActions = (st === "running" || st === "done");
    $("#wsh-job-modal-run").prop("disabled", disableActions);
    $("#wsh-job-modal-cancel").prop("disabled", disableActions || st === "canceled");

    $("#wsh-job-modal").show();
  }

  function closeModal() {
    $("#wsh-job-modal").hide();
    $("#wsh-job-modal-body").empty();
    currentModalJobId = "";
  }

  function loadJobDetails(jobId) {
    return ajaxPost("wsh_wcbpm_job_details", { job_id: jobId })
      .done(function (resp) {
        if (!resp || !resp.success || !resp.data || !resp.data.job) return;
        openModal(resp.data.job);
      });
  }

  function cancelJob(jobId) {
    if (!jobId) return $.Deferred().reject().promise();
    if (!window.confirm("Cancel this job?")) return $.Deferred().reject().promise();

    return ajaxPost("wsh_wcbpm_job_cancel", { job_id: jobId })
      .done(function (resp) {
        if (!resp) return;
        if (resp.success) {
          loadJobsList();
          if ($("#wsh-job-modal").is(":visible")) closeModal();
        } else {
          alert((resp.data && resp.data.message) ? resp.data.message : "Unable to cancel job.");
        }
      });
  }

  function runJobNow(jobId) {
  if (!jobId) return $.Deferred().reject().promise();
  if (!window.confirm("Run this job now?")) return $.Deferred().reject().promise();

  // UX: disable the clicked button(s) while running (optional)
  var $btns = $('.wsh-job-card[data-job-id="' + jobId + '"] .wsh-job-run, #wsh-job-modal-run');
  $btns.prop("disabled", true).text("Running...");

  return ajaxPost("wsh_wcbpm_job_run_now", { job_id: jobId })
    .done(function (resp) {
      if (!resp) return;

      if (resp.success) {
        // Immediately refresh once
        loadJobsList();
        if ($("#wsh-job-modal").is(":visible")) loadJobDetails(jobId);

        // ✅ Then poll until status becomes done/failed/canceled
        pollJobUntilFinished(jobId, {
          intervalMs: 800,
          maxTries: 25
        }).always(function(){
          // final refresh (so UI is guaranteed correct)
          loadJobsList();
          if ($("#wsh-job-modal").is(":visible")) loadJobDetails(jobId);

          $btns.prop("disabled", false).text("Run now");
        });

      } else {
        alert(resp?.data?.message || "Unable to run job now.");
        $btns.prop("disabled", false).text("Run now");
      }
    })
    .fail(function(){
      $btns.prop("disabled", false).text("Run now");
    });
  }

  // --- Polling helper ---
  function pollJobUntilFinished(jobId, opts) {
    opts = opts || {};
    var intervalMs = opts.intervalMs || 800;
    var maxTries   = opts.maxTries || 25;

    var dfd = $.Deferred();
    var tries = 0;

    function tick() {
      tries++;

      // your endpoint that returns jobs list (or use a "job_get" endpoint if you have it)
      ajaxPost("wsh_wcbpm_jobs_list", {}) // <-- adjust action name if yours is different
        .done(function(resp){
          var jobs = resp?.success ? (resp.data?.jobs || []) : [];
          var job  = null;

          for (var i=0; i<jobs.length; i++) {
            if (String(jobs[i].job_id) === String(jobId)) { job = jobs[i]; break; }
          }

          if (!job) {
            // if job disappeared (deleted/cleaned), treat as finished
            dfd.resolve({ status: "missing" });
            return;
          }

          var st = String(job.status || "").toLowerCase();
          if (st === "done" || st === "failed" || st === "canceled") {
            dfd.resolve(job);
            return;
          }

          if (tries >= maxTries) {
            dfd.resolve(job); // stop polling, but don’t error UX
            return;
          }

          setTimeout(tick, intervalMs);
        })
        .fail(function(){
          if (tries >= maxTries) return dfd.resolve();
          setTimeout(tick, intervalMs);
        });
    }

    setTimeout(tick, 200);
    return dfd.promise();
  }

  function deleteJob(jobId) {
    if (!jobId) return;

    if (!window.confirm(
      "This will permanently delete the job.\n\n" +
      "The scheduled action will be removed and the job will disappear from history.\n\n" +
      "This cannot be undone. Continue?"
    )) {
      return;
    }

    ajaxPost("wsh_wcbpm_job_delete", { job_id: jobId })
      .done(function (resp) {
        if (resp && resp.success) {
          showNotice("success", resp.data.message || "Job deleted.");
          loadJobsList();
          if ($("#wsh-job-modal").is(":visible")) closeModal();
        } else {
          showNotice("error", resp?.data?.message || "Unable to delete job.");
        }
      });
  }

  // ===== Events (namespaced to prevent double-binding) =====

  // Job cards
  $(document)
    .off("click.wsh", ".wsh-job-details")
    .on("click.wsh", ".wsh-job-details", function () {
      var jobId = $(this).closest(".wsh-job-card").data("job-id");
      loadJobDetails(jobId);
    });

  $(document)
    .off("click.wsh", ".wsh-job-cancel")
    .on("click.wsh", ".wsh-job-cancel", function () {
      var jobId = $(this).closest(".wsh-job-card").data("job-id");
      cancelJob(jobId);
    });

  $(document)
    .off("click.wsh", ".wsh-job-run")
    .on("click.wsh", ".wsh-job-run", function () {
      var jobId = $(this).closest(".wsh-job-card").data("job-id");
      runJobNow(jobId);
    });

  $(document)
    .off("click.wsh", ".wsh-job-delete")
    .on("click.wsh", ".wsh-job-delete", function () {
      var jobId = $(this).closest(".wsh-job-card").data("job-id");
      deleteJob(jobId);
    });

  // Modal (details)
  $("#wsh-job-modal-close").off("click.wsh").on("click.wsh", function () {
    closeModal();
  });
  $("#wsh-job-modal").off("click.wsh").on("click.wsh", function (e) {
    if (e.target && e.target.id === "wsh-job-modal") closeModal();
  });
  $("#wsh-job-modal-run").off("click.wsh").on("click.wsh", function () {
    runJobNow(currentModalJobId);
  });
  $("#wsh-job-modal-cancel").off("click.wsh").on("click.wsh", function () {
    cancelJob(currentModalJobId);
  });

  // ===== Schedule modal helpers =====

  function openScheduleModal() {
    var last = window.WSH_WCBPM_LAST_PREVIEW;
    if (!last || !last.payload) {
      showNotice("error", "Please run Preview first.");
      return;
    }

    var count = (last.summary && last.summary.count) ? last.summary.count : 0;
    var scope = (last.payload.filters && last.payload.filters.price_scope) ? last.payload.filters.price_scope : "both";
    var method = last.payload.update?.method || "";
    var action = last.payload.update?.action || "";
    var value = last.payload.update?.value || "";

    $("#wsh-schedule-summary").html(
      "<p style='margin:0;'><strong>Targets:</strong> " + count +
      "<br><strong>Update:</strong> " + method + " / " + action + " / " + value +
      "<br><strong>Scope:</strong> " + scope +
      "</p>"
    );

    // Default datetime: now + 10 minutes (local)
    var d = new Date(Date.now() + 10 * 60 * 1000);
    var pad = (n) => String(n).padStart(2, "0");
    var localStr =
      d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()) +
      "T" + pad(d.getHours()) + ":" + pad(d.getMinutes());

    $("#wsh-schedule-datetime").val(localStr);

    $("#wsh-schedule-modal").show();
  }

  function closeScheduleModal() {
    $("#wsh-schedule-modal").hide();
  }

  // ===== Schedule button (ONLY opens modal) =====
  $(document)
    .off("click.wsh", "#wsh-btn-schedule")
    .on("click.wsh", "#wsh-btn-schedule", function (e) {
      e.preventDefault();

      if (!isProActive()) {
        showNotice("error", "This is a PRO feature. Please activate your license.");
        var $lic = $("#wsh-wcbpm-license");
        if ($lic.length) $("html, body").animate({ scrollTop: $lic.offset().top - 20 }, 300);
        return;
      }

      openScheduleModal();
    });

  // Close schedule modal buttons / outside click
  $(document)
    .off("click.wsh", "#wsh-schedule-close, #wsh-schedule-cancel")
    .on("click.wsh", "#wsh-schedule-close, #wsh-schedule-cancel", function (e) {
      e.preventDefault();
      closeScheduleModal();
    });

  $(document)
    .off("click.wsh", "#wsh-schedule-modal")
    .on("click.wsh", "#wsh-schedule-modal", function (e) {
      if (e.target && e.target.id === "wsh-schedule-modal") closeScheduleModal();
    });

  // ===== Confirm schedule (AJAX happens ONLY here) =====
  var WSH_SCHED_CONFIRM_INFLIGHT = false;

  $(document)
    .off("click.wsh", "#wsh-schedule-confirm")
    .on("click.wsh", "#wsh-schedule-confirm", function (e) {
      e.preventDefault();

      if (WSH_SCHED_CONFIRM_INFLIGHT) return;
      WSH_SCHED_CONFIRM_INFLIGHT = true;

      try {
        var last = window.WSH_WCBPM_LAST_PREVIEW;
        if (!last || !last.payload) {
          showNotice("error", "Please run Preview first.");
          return;
        }

        var runAt = ($("#wsh-schedule-datetime").val() || "").trim();
        var batch = parseInt($("#wsh-schedule-batch").val() || "200", 10);

        if (!runAt) {
          showNotice("error", "Please select date and time.");
          return;
        }
        if (!Number.isFinite(batch) || batch < 50) batch = 200;

        var postData = {
          action: "wsh_wcbpm_schedule",
          _ajax_nonce: window.WSH_WCBPM.nonce,
          mode: "later",
          run_at: runAt,
          batch_size: batch,
          target_count: (last.summary && last.summary.count) ? parseInt(last.summary.count, 10) || 0 : 0,
          filters: last.payload.filters || {},
          update: last.payload.update || {}
        };

        $("#wsh-schedule-confirm").prop("disabled", true).text("Scheduling...");

        $.post(window.WSH_WCBPM.ajaxurl, postData)
          .done(function (resp) {
            if (!resp || !resp.success) {
              showNotice("error", resp?.data?.message || "Unable to schedule.");
              return;
            }

            closeScheduleModal();
            showNotice("success", resp.data.message || "Job scheduled.");

            if (window.WSH_WCBPM_LoadJobsList) {
              window.WSH_WCBPM_LoadJobsList();
            }
          })
          .fail(function () {
            showNotice("error", window.WSH_WCBPM?.i18n?.ajax_error || "AJAX error");
          })
          .always(function () {
            $("#wsh-schedule-confirm").prop("disabled", false).text("Schedule job");
            WSH_SCHED_CONFIRM_INFLIGHT = false;
          });

      } finally {
        // ako je gore negde "return" pre AJAX-a, moramo reset
        if ($("#wsh-schedule-confirm").prop("disabled") !== true) {
          WSH_SCHED_CONFIRM_INFLIGHT = false;
        }
      }
    });

  // Auto-load on page open
  $(function () {
    if ($("#wsh-jobs-list").length) {
      loadJobsList();
    }
  });

  // Expose if you want to refresh after scheduling success:
  window.WSH_WCBPM_LoadJobsList = loadJobsList;

})(jQuery);
