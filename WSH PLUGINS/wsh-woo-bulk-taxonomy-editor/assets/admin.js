jQuery(function ($) {
    const actionValue = "wsh_wcbte_bulk_taxonomy_edit";

    function initTermSelect2IfPresent() {
        const $sel = $("#wsh-wcbte-term-select");
        if (!$sel.length) return;

        const tax = $sel.data("taxonomy");
        if ($.fn.select2) {
            $sel.select2({
                width: "100%",
                placeholder: "Search...",
                ajax: {
                    url: WSH_WCBTE.ajaxUrl,
                    dataType: "json",
                    delay: 200,
                    data: function (params) {
                        return {
                            action: "wsh_wcbte_search_terms",
                            nonce: WSH_WCBTE.nonce,
                            taxonomy: tax,
                            q: params.term || ""
                        };
                    },
                    processResults: function (data) {
                        if (!data || !data.success) return { results: [] };
                        return data.data;
                    }
                }
            });
        } else {
            // fallback: load first 20
            $.getJSON(WSH_WCBTE.ajaxUrl, {
                action: "wsh_wcbte_search_terms",
                nonce: WSH_WCBTE.nonce,
                taxonomy: tax,
                q: ""
            }).done(function (resp) {
                if (!resp || !resp.success) return;
                (resp.data.results || []).forEach(function (t) {
                    $sel.append(new Option(t.text, t.id, false, false));
                });
            });
        }
    }

    function normalizeText(s) {
        return (s || "").toString().toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    }

    function applyChecklistFilter(term) {
        const q = normalizeText(term).trim();
        const $lis = $("#wsh-wcbte-tax-checklist li");
        if (!q) { $lis.show(); return; }
        $lis.hide();
        $lis.each(function () {
            const $li = $(this);
            const labelText = $li.children("label").text() || $li.text();
            if (normalizeText(labelText).includes(q)) {
                $li.show();
                $li.parents("li").show();
                $li.children("ul").children("li").show();
            }
        });
    }

    function bindChecklistSearch() {
        $("#wsh-wcbte-tax-search").off("input").on("input", function () {
            applyChecklistFilter($(this).val());
        });
        $("#wsh-wcbte-tax-search-clear").off("click").on("click", function () {
            $("#wsh-wcbte-tax-search").val("");
            $("#wsh-wcbte-tax-checklist li").show();
        });
    }

    function loadTaxonomyUI(tax) {
        $("#wsh-wcbte-taxonomy-ui-wrap").html("Loading...");
        $.getJSON(WSH_WCBTE.ajaxUrl, {
            action: "wsh_wcbte_tax_ui",
            nonce: WSH_WCBTE.nonce,
            taxonomy: tax
        }).done(function (resp) {
            if (!resp || !resp.success) {
                const msg = resp?.data?.message || "Error";
                $("#wsh-wcbte-taxonomy-ui-wrap").html(msg);
                return;
            }
            $("#wsh-wcbte-taxonomy-ui-wrap").html(resp.data.html);
            // init whichever UI is present
            initTermSelect2IfPresent();
            bindChecklistSearch();
        }).fail(function (xhr) {
            $("#wsh-wcbte-taxonomy-ui-wrap").html(xhr.responseText || xhr.statusText);
        });
    }

    // initial load (default taxonomy)
    loadTaxonomyUI($("#wsh-wcbte-taxonomy").val());

    // on change
    $("#wsh-wcbte-taxonomy").on("change", function () {
        loadTaxonomyUI($(this).val());
    });


    /*function getSelectedProductIds() {
        const ids = [];
        $('#the-list input[type="checkbox"][name="post[]"]:checked').each(function () {
            ids.push(parseInt($(this).val(), 10));
        });
        return ids.filter(n => Number.isFinite(n) && n > 0);
    }*/

    function getSelectedTermIds() {
        // Hierarchical checklist?
        if ($("#wsh-wcbte-tax-checklist").length) {
            const ids = [];
            $("#wsh-wcbte-tax-checklist input[type='checkbox']:checked").each(function () {
                const v = parseInt($(this).val(), 10);
                if (Number.isFinite(v) && v > 0) ids.push(v);
            });
            return ids;
        }

        // Select2 / select
        const $sel = $("#wsh-wcbte-term-select");
        if ($sel.length) {
            if ($.fn.select2) {
                const data = $sel.select2("data") || [];
                return data.map(x => parseInt(x.id, 10)).filter(n => Number.isFinite(n) && n > 0);
            }
            return ($sel.val() || []).map(v => parseInt(v, 10)).filter(n => Number.isFinite(n) && n > 0);
        }

        return [];
    }

    function openModal(selectedIds) {
        $("#wsh-wcbte-selected-count").text(selectedIds.length);
        $("#wsh-wcbte-backdrop, #wsh-wcbte-modal").show();
        $("#wsh-wcbte-modal").data("productIds", selectedIds);
        $(".wsh-wcbte-status").hide();
        $(".wsh-wcbte-preview").hide();
        $("#wsh-wcbte-preview-body").empty();
        $(".wsh-wcbte-log").text("");
        $(".wsh-wcbte-progress__bar").css("width", "0%");

        if (typeof resetCatFilter === "function") resetCatFilter();
    }

    function closeModal() {
        $("#wsh-wcbte-backdrop, #wsh-wcbte-modal").hide();
    }

    // Intercept bulk action Apply
    $("#doaction, #doaction2").on("click", function (e) {
        const $select = $(this).attr("id") === "doaction" ? $("#bulk-action-selector-top") : $("#bulk-action-selector-bottom");
        if ($select.val() !== actionValue) return;

        e.preventDefault();

        const ids = getSelectedProductIds();
        if (!ids.length) {
            alert(WSH_WCBTE?.i18n?.noSelection || "Select at least one product.");
            return;
        }

        openModal(ids);
    });

    // Close
    $(document).on("click", ".wsh-wcbte-close, #wsh-wcbte-backdrop", function () {
        closeModal();
    });

    // Init category select (Select2 if available)
    function initCategorySelect() {
        const $sel = $("#wsh-wcbte-cats");
        $sel.empty();

        if ($.fn.select2) {
            $sel.select2({
                width: "100%",
                placeholder: "Search categories...",
                ajax: {
                    url: WSH_WCBTE.ajaxUrl,
                    dataType: "json",
                    delay: 200,
                    data: function (params) {
                        return {
                            action: "wsh_wcbte_search_terms",
                            nonce: WSH_WCBTE.nonce,
                            taxonomy: "product_cat",
                            q: params.term || ""
                        };
                    },
                    processResults: function (data) {
                        if (!data || !data.success) return { results: [] };
                        return data.data;
                    }
                }
            });
        } else {
            // fallback (bez select2): učitaj prvih 20 bez search-a
            $.getJSON(WSH_WCBTE.ajaxUrl, {
                action: "wsh_wcbte_search_terms",
                nonce: WSH_WCBTE.nonce,
                taxonomy: "product_cat",
                q: ""
            }).done(function (resp) {
                if (!resp || !resp.success) return;
                const results = resp.data.results || [];
                results.forEach(function (t) {
                    $sel.append(new Option(t.text, t.id, false, false));
                });
            });
        }
    }

    initCategorySelect();

    function logLine(msg) {
        const $log = $(".wsh-wcbte-log");
        $log.text($log.text() + msg + "\n");
    }

    function setProgress(percent) {
        $(".wsh-wcbte-progress__bar").css("width", Math.max(0, Math.min(100, percent)) + "%");
    }

    function escapeHtml(str) {
        return String(str)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    /*function getSelectedTermIds() {
        let termIds = [];
        if ($.fn.select2) {
            const data = $("#wsh-wcbte-cats").select2("data") || [];
            termIds = data.map(x => parseInt(x.id, 10)).filter(n => Number.isFinite(n) && n > 0);
        } else {
            termIds = ($("#wsh-wcbte-cats").val() || []).map(v => parseInt(v, 10)).filter(n => Number.isFinite(n) && n > 0);
        }
        return termIds;
    }*/
    function getSelectedTermIds() {
        // Hierarchical checklist?
        if ($("#wsh-wcbte-tax-checklist").length) {
            const ids = [];

            $("#wsh-wcbte-tax-checklist input[type='checkbox']:checked").each(function () {
            const rawVal = $(this).val();

            // Prefer numeric value (kod tebe: value="160")
            const v = parseInt(rawVal, 10);
            if (Number.isFinite(v) && v > 0) {
                ids.push(v);
                return;
            }

            // Fallback: id="in-product_cat-160-2" -> uzmi 160
            const idAttr = $(this).attr("id") || "";
            const m = idAttr.match(/^in-[^-]+-(\d+)-\d+$/);
            if (m && m[1]) ids.push(parseInt(m[1], 10));
            });

            return Array.from(new Set(ids));
        }

        // Non-hier taxonomy (select2)
        const $sel = $("#wsh-wcbte-term-select");
        if ($sel.length) {
            if ($.fn.select2) {
            const data = $sel.select2("data") || [];
            return data.map(x => parseInt(x.id, 10)).filter(n => Number.isFinite(n) && n > 0);
            }
            return ($sel.val() || []).map(v => parseInt(v, 10)).filter(n => Number.isFinite(n) && n > 0);
        }

        return [];
    }

    function getSelectedProductIds() {
        const ids = [];
        $('input[name="post[]"]:checked').each(function () {
            const v = parseInt($(this).val(), 10);
            if (Number.isFinite(v) && v > 0) ids.push(v);
        });
        return ids;
    }

    function normalizeText(s) {
        return (s || "")
            .toString()
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, ""); // skida dijakritike (č ć ž š đ)
    }

    function resetCatFilter() {
        const $root = $("#wsh-wcbte-cat-checklist .categorychecklist");
        $root.find("li").show();
        $("#wsh-wcbte-cat-search").val("");
    }

    function applyCatFilter(term) {
        const q = normalizeText(term).trim();
        const $root = $("#wsh-wcbte-cat-checklist .categorychecklist");
        const $lis = $root.find("li");

        if (!q) {
            $lis.show();
            return;
        }

        // Sakrij sve prvo
        $lis.hide();

        // Prikaži li-jeve koji matchuju label text + sve njihove parente
        $lis.each(function () {
            const $li = $(this);
            const labelText = $li.children("label").text() || $li.text();
            const t = normalizeText(labelText);

            if (t.includes(q)) {
                $li.show();
                // Prikaži sve parent LI (da se vidi hijerarhija)
                $li.parents("li").show();
                // Prikaži i direktne potomke UL (da se vidi grana)
                $li.children("ul").children("li").show();
            }
        });
    }

    // live search (debounce)
    let catSearchTimer = null;
    $("#wsh-wcbte-cat-search").on("input", function () {
        const val = $(this).val();
        window.clearTimeout(catSearchTimer);
        catSearchTimer = window.setTimeout(function () {
            applyCatFilter(val);
        }, 120);
    });

    $("#wsh-wcbte-cat-search-clear").on("click", function () {
        resetCatFilter();
    });

    // Apply batch
    $("#wsh-wcbte-apply").on("click", function () {
        const productIds = $("#wsh-wcbte-modal").data("productIds") || [];
        const mode = $('input[name="wsh_wcbte_mode"]:checked').val() || "add";
        const termIds = getSelectedTermIds();
        const taxonomy = $("#wsh-wcbte-taxonomy").val();

        console.log("TAX:", taxonomy, "TERM IDS:", termIds);

        // Selected term ids
        /*const termIds = getSelectedTermIds();
        if ($.fn.select2) {
            const data = $("#wsh-wcbte-cats").select2("data") || [];
            termIds = data.map(x => parseInt(x.id, 10)).filter(n => Number.isFinite(n) && n > 0);
        } else {
            termIds = ($("#wsh-wcbte-cats").val() || []).map(v => parseInt(v, 10)).filter(n => Number.isFinite(n) && n > 0);
        }*/


        $(".wsh-wcbte-status").show();
        logLine(WSH_WCBTE?.i18n?.working || "Working...");

        const chunk = parseInt(WSH_WCBTE.chunk || 50, 10);
        let offset = 0; // <-- mora let
        let updatedTotal = 0;

        const $btn = $("#wsh-wcbte-apply");
        $btn.prop("disabled", true);

        function step() {
            $.ajax({
                url: WSH_WCBTE.ajaxUrl,
                method: "POST",
                dataType: "json",
                data: {
                    action: "wsh_wcbte_apply",
                    nonce: WSH_WCBTE.nonce,
                    mode: mode,
                    product_ids: productIds,
                    term_ids: termIds,
                    taxonomy: taxonomy,
                    offset: offset,
                    chunk: chunk
                }
            }).done(function (resp) {
                $btn.prop("disabled", false);

                if (!resp || !resp.success) {
                    const msg = (resp && resp.data && resp.data.message) ? resp.data.message : "Unknown error";
                    logLine((WSH_WCBTE?.i18n?.error || "Error:") + " " + msg);
                    return;
                }

                const d = resp.data;
                offset = d.offset;
                updatedTotal += (d.updated || 0);

                const percent = Math.round((offset / d.total) * 100);
                setProgress(percent);

                logLine(`Processed ${offset}/${d.total} | Updated in this batch: ${d.updated} | Total updated: ${updatedTotal}`);

                if (d.errors && d.errors.length) {
                    d.errors.forEach(err => logLine("⚠ " + err));
                }

                if (d.done) {
                    setProgress(100);
                    logLine(WSH_WCBTE?.i18n?.done || "Done.");
                    return;
                }

                step();
            }).fail(function (xhr) {
                $btn.prop("disabled", false);
                logLine((WSH_WCBTE?.i18n?.error || "Error:") + " " + (xhr.responseText || xhr.statusText));
            });
        }

        step();
    });

    // PREVIEW button
    $("#wsh-wcbte-preview").on("click", function () {
        const productIds = $("#wsh-wcbte-modal").data("productIds") || [];
        const mode = $('input[name="wsh_wcbte_mode"]:checked').val() || "add";
        const termIds = getSelectedTermIds();
        const taxonomy = $("#wsh-wcbte-taxonomy").val();

        console.log("TAX:", taxonomy, "TERM IDS:", termIds);

        $(".wsh-wcbte-preview").show();
        $("#wsh-wcbte-preview-body").html('<tr><td colspan="3">Loading preview...</td></tr>');

        $.ajax({
            url: WSH_WCBTE.ajaxUrl,
            method: "POST",
            dataType: "json",
            data: {
                action: "wsh_wcbte_preview",
                nonce: WSH_WCBTE.nonce,
                mode: mode,
                taxonomy: taxonomy,
                product_ids: productIds,
                term_ids: termIds,
                limit: 50
            }
        }).done(function (resp) {
            if (resp.data && resp.data.tax_label) {
                $("#wsh-wcbte-preview-current-label").text("Current " + resp.data.tax_label);
                $("#wsh-wcbte-preview-final-label").text("Final " + resp.data.tax_label);
            }

            if (!resp || !resp.success) {
                const msg = (resp && resp.data && resp.data.message) ? resp.data.message : "Unknown error";
                $("#wsh-wcbte-preview-body").html('<tr><td colspan="3">' + escapeHtml(msg) + '</td></tr>');
                return;
            }

            const rows = resp.data.rows || [];
            if (!rows.length) {
                $("#wsh-wcbte-preview-body").html('<tr><td colspan="3">No rows.</td></tr>');
                return;
            }

            let html = "";
            rows.forEach(function (r) {
                const current = (r.current || []).join(", ");
                const fin = (r.final || []).join(", ");
                html += "<tr>";
                html += "<td><strong>" + escapeHtml(r.title) + "</strong><br><small>#" + escapeHtml(r.product_id) + "</small></td>";
                html += "<td>" + escapeHtml(current || "—") + "</td>";
                html += "<td>" + escapeHtml(fin || "—") + "</td>";
                html += "</tr>";
            });

            $("#wsh-wcbte-preview-body").html(html);
        }).fail(function (xhr) {
            $("#wsh-wcbte-preview-body").html('<tr><td colspan="3">' + escapeHtml(xhr.responseText || xhr.statusText) + '</td></tr>');
        });
    });

});
