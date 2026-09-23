// ===== assets/admin.js =====
(function ($) {
    /* ------------------ Helpers / UI ------------------ */
    const ACTION_BTNS = '#brevo-generate-preview, #brevo-create-campaign-submit, #brevo-send-test, #brevo-schedule, #brevo-send-now';

    function err(msg) { alert(msg); }
    function trim(v) { return (v || '').toString().trim(); }
    function isISO(v) { return /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})$/.test(trim(v)); }
    function markInvalid($el) { $el.addClass('brevo-invalid'); setTimeout(() => $el.removeClass('brevo-invalid'), 2000); }
    function enableBtns(flag) { $('#brevo-create-campaign-submit, #brevo-send-test, #brevo-schedule, #brevo-send-now').prop('disabled', !flag); }
    function debounce(fn, ms) { let t; return function () { clearTimeout(t); const a = arguments, ctx = this; t = setTimeout(() => fn.apply(ctx, a), ms); }; }

    // invalid highlight css (inline)
    (function () {
        const style = document.createElement('style');
        style.innerHTML = '.brevo-invalid{outline:2px solid #d63638 !important;background:#fff7f7 !important;}';
        document.head.appendChild(style);
    })();

    // Media picker
    function openMedia(targetInputName) {
        const frame = wp.media({ title: 'Select image', multiple: false, library: { type: 'image' } });
        frame.on('select', function () {
            const att = frame.state().get('selection').first().toJSON();
            $(`input[name="${targetInputName}"]`).val(att.url);
        });
        frame.open();
    }

    $(document).on('click', '.select-image', function (e) {
        e.preventDefault();
        openMedia($(this).data('target'));
    });

    /* ------------------ Button loading ------------------ */
    function restoreActionButtonsState() {
        if (typeof enableBtns === 'function') {
            const hasPreview = $.trim($('#brevo-preview-file').val() || '') !== '';
            enableBtns(hasPreview);
        } else {
            $(ACTION_BTNS).prop('disabled', false);
        }
    }

    function startBtnLoading($btn, loadingText) {
        if ($btn.data('loading')) return;
        $btn.data('loading', 1);
        $btn.attr('aria-busy', 'true');
        $btn.data('origHtml', $btn.html());         // uvek sačuvaj aktuelni HTML
        if (loadingText != null && loadingText !== '') $btn.text(loadingText);
        $btn.prepend('<span class="brevo-spinner" aria-hidden="true"></span>');
        $btn.addClass('brevo-btn-loading').prop('disabled', true);
        $(ACTION_BTNS).not($btn).prop('disabled', true);
    }

    function stopBtnLoading($btn) {
        $btn.find('.brevo-spinner').remove();
        const origHtml = $btn.data('origHtml');
        if (origHtml) $btn.html(origHtml);
        $btn.removeClass('brevo-btn-loading').prop('disabled', false);
        $btn.removeAttr('aria-busy');
        $btn.data('loading', 0);
        restoreActionButtonsState();
    }

    // spinner CSS
    (function () {
        const style = document.createElement('style');
        style.innerHTML = `
        .brevo-btn-loading{position:relative}
        .brevo-spinner{display:inline-block;width:14px;height:14px;margin-right:8px;vertical-align:-2px;border-radius:50%;
        border:2px solid currentColor;border-top-color:transparent;border-left-color:transparent;animation:brevo-spin .8s linear infinite}
        @keyframes brevo-spin{to{transform:rotate(360deg)}}
        `;
        document.head.appendChild(style);
    })();

    // ------------------ Banners (add/remove + media) ------------------
    function addBannerRow(sectionIndex, data) {
        const $picker = $(`.section[data-section-index="${sectionIndex}"] .banner-picker .banner-list`);
        const idx = $picker.find('.banner-item').length;

        const imgName = `sections[${sectionIndex}][banners][${idx}][image]`;
        const linkName = `sections[${sectionIndex}][banners][${idx}][link]`;
        const altName = `sections[${sectionIndex}][banners][${idx}][alt]`;
        const positionName = `sections[${sectionIndex}][banners][${idx}][position]`;

        const html = `
        <div class="banner-item" style="margin-bottom:10px;border:1px solid #ccc;padding:6px;">
            <label><span>Banner image URL</span>
                <input type="url" name="${imgName}" value="${(data && data.image) ? data.image : ''}"> <button type="button" class="button select-image" data-target="${imgName}">Upload/Choose Image</button>
            </label>
            <br>
            <label><span>Banner link</span>
                <input type="url" name="${linkName}" value="${(data && data.link) ? data.link : ''}">
            </label>
            <br>
            <label><span>Alt text (optional)</span>
                <input type="text" name="${altName}" value="${(data && data.alt) ? data.alt : ''}">
            </label>
            <br>
            <label><span>Positon(after post number)</span>
                <input type="number" class="position" name="${positionName}" value="${(data && data.position) ? data.position : '0'}">
            </label>
            <br>
            <button type="button" class="button remove-banner">Remove</button>
        </div>`;
        $picker.append(html);
    }

    // + Add Banner
    $(document).on('click', '.add-banner', function (e) {
        e.preventDefault();
        addBannerRow($(this).data('section'));
    });

    // Remove Banner
    $(document).on('click', '.remove-banner', function (e) {
        e.preventDefault();
        $(this).closest('.banner-item').remove();
    });


    /* ------------------ Post picker ------------------ */
    function sectionRoot($el) { return $el.closest('.post-picker'); }
    function getIds($root) { const csv = $root.find('.post-ids').val() || ''; return csv ? csv.split(',').map(x => parseInt(x, 10)).filter(Boolean) : []; }
    function setIds($root, ids) { $root.find('.post-ids').val(ids.join(',')); }

    const searchPosts = debounce(function (term, page, cb) {
        $.post(BREVO_CAMPAIGNS.ajaxUrl, { action: 'brevo_search_posts', nonce: BREVO_CAMPAIGNS.nonce, term, page })
            .done(resp => cb(resp && resp.success ? resp.data : { items: [], hasMore: false }))
            .fail(() => cb({ items: [], hasMore: false }));
    }, 250);

    function fetchPostsByIds(ids, cb) {
        $.post(BREVO_CAMPAIGNS.ajaxUrl, { action: 'brevo_posts_by_ids', nonce: BREVO_CAMPAIGNS.nonce, ids })
            .done(resp => cb(resp && resp.success ? resp.data.items : []))
            .fail(() => cb([]));
    }

    function renderResults($root, data) {
        const $box = $root.find('.post-results').empty().show();
        if (!data.items.length) { $box.html('<p class="description">No results.</p>'); return; }
        data.items.forEach(item => {
            const row = $(`
        <div class="pp-row">
          ${item.thumb ? `<img src="${item.thumb}" alt=""/>` : `<div class="pp-thumb-fallback"></div>`}
          <div class="pp-meta">
            <strong>${item.title}</strong>
            <div class="pp-excerpt">${(item.excerpt || '').substring(0, 100)}</div>
          </div>
          <button type="button" class="button pp-add"
            data-id="${item.id}"
            data-title="${$('<div>').text(item.title).html()}"
            data-excerpt="${$('<div>').text(item.excerpt || '').html()}"
            data-thumb="${item.thumb || ''}">Add</button>
        </div>
      `);
            $box.append(row);
        });
    }

    function ensureMeta($root, ids, cb) {
        const meta = $root.data('ppMeta') || {};
        const missing = ids.filter(id => !meta[id]);
        if (!missing.length) return cb(meta);
        fetchPostsByIds(missing, (items) => {
            items.forEach(it => { meta[it.id] = it; });
            $root.data('ppMeta', meta);
            cb(meta);
        });
    }

    function renderSelected($root) {
        const ids = getIds($root);
        const $list = $root.find('.post-selected-list').empty();
        const $desc = $root.find('.post-selected .description');

        if (!ids.length) {
            $desc.text('No posts selected.');
            $root.find('.post-selected').show();
            return;
        }

        const layout = $root.closest('.section').find('select[name$="[layout]"]').val() || 'l1';

        // nakon što obezbedimo metu, nacrtaj kartice
        function draw(meta) {
            $desc.html('Selected posts: <button type="button" class="button-link-delete pp-clear">Clear all</button>');
            ids.forEach(id => {
                const m = meta[id] || { id, title: '#' + id, excerpt: '', thumb: '' };
                const hasImg = (layout === 'l1' && m.thumb);
                const hasExcerpt = (layout === 'l1' || layout === 'l2') && m.excerpt;

                const li = $(`
                    <li class="pp-card" data-id="${id}" draggable="true">
                    ${hasImg ? `<div class="ppc-thumb"><img src="${m.thumb}" alt=""></div>` : ''}
                    <div class="ppc-body">
                        <div class="ppc-title"><strong>${m.title}</strong></div>
                        ${hasExcerpt ? `<div class="ppc-excerpt">${$('<div>').text(m.excerpt || '').html().substring(0, 160)}</div>` : ''}
                        <div class="ppc-meta">
                        <span class="ppc-id">#${id}</span>
                        <button type="button" class="button-link-delete pp-remove">Remove</button>
                        </div>
                    </div>
                    </li>
                `);
                $list.append(li);
            });
            $root.find('.post-selected').show();
        }

        // KLJUČ: uvek osiguraj metu pa crtaj
        ensureMeta($root, ids, draw);
    }

    // search handlers (sakrij rezultata kad je polje prazno)
    $(document).on('input', '.post-search', function () {
        const $root = sectionRoot($(this));
        const term = $(this).val().trim();
        if (!term) { $root.find('.post-results').empty().hide(); return; }
        searchPosts(term, 1, data => renderResults($root, data));
    });

    $(document).on('blur', '.post-search', function () {
        const $root = sectionRoot($(this));
        const term = $(this).val().trim();
        if (!term) { $root.find('.post-results').empty().hide(); }
    });

    // add/remove
    $(document).on('click', '.pp-add', function () {
        const $root = sectionRoot($(this));
        const id = parseInt($(this).data('id'), 10);
        const ids = getIds($root);
        if (!ids.includes(id)) ids.push(id);
        setIds($root, ids);

        const meta = $root.data('ppMeta') || {};
        meta[id] = {
            id,
            title: $(this).data('title'),
            excerpt: $(this).data('excerpt') || '',
            thumb: $(this).data('thumb') || ''
        };
        $root.data('ppMeta', meta);

        renderSelected($root);
        saveDraft(); // autosave
    });

    $(document).on('click', '.pp-remove', function () {
        const $root = sectionRoot($(this));
        const id = parseInt($(this).closest('li').data('id'), 10);
        const ids = getIds($root).filter(x => x !== id);
        setIds($root, ids);
        renderSelected($root);
        saveDraft();
    });

    // layout change => rerender
    $(document).on('change', 'select[name$="[layout]"]', function () {
        const $picker = $(this).closest('.section').find('.post-picker');
        renderSelected($picker);
        saveDraft();
    });

    // init post pickers
    $('.post-picker').each(function () {
        const $root = $(this);
        const ids = getIds($root);
        if (ids.length) {
            ensureMeta($root, ids, () => renderSelected($root));
        } else {
            $root.find('.post-results').hide();
            $root.find('.post-selected').show();
        }
    });

    /* ------------------ Sections payload ------------------ */
    function collectSections() {
        const sections = [];
        $('#brevo-builder-form .section').each(function () {
            const idx = $(this).data('section-index');
            const section = {
                title: $(`[name="sections[${idx}][title]"]`).val(),
                link: $(`[name="sections[${idx}][link]"]`).val(),
                image: $(`[name="sections[${idx}][image]"]`).val(),
                layout: $(`[name="sections[${idx}][layout]"]`).val() || 'l1',
                postIds: (function () {
                    const csv = $(`[name="sections[${idx}][postIds]"]`).val() || '';
                    return csv ? csv.split(',').map(n => parseInt(n, 10)).filter(Boolean) : [];
                })(),
                banners: []
            };

            // pokupi banere u ovoj sekciji
            $(`.section[data-section-index="${idx}"] .banner-picker .banner-item`).each(function () {
                const image = $(this).find(`input[name^="sections[${idx}][banners]"][name$="[image]"]`).val() || '';
                const link = $(this).find(`input[name^="sections[${idx}][banners]"][name$="[link]"]`).val() || '';
                const alt = $(this).find(`input[name^="sections[${idx}][banners]"][name$="[alt]"]`).val() || '';
                const position = $(this).find(`input[name^="sections[${idx}][banners]"][name$="[position]"]`).val() || '';
                if (image || link) {
                    section.banners.push({ image, link, alt, position });
                }
            });

            if (section.title || section.image || section.postIds.length || section.banners.length) {
                sections.push(section);
            }
        });
        return sections;
    }


    /* ------------------ Autosave (per-campaign ID) + D&D reorder ------------------ */
    function draftKey() {
        const id = $.trim($('#brevo-campaign-id').val() || '');
        return 'brevoBuilderDraft_v2_' + (id ? id : 'NEW');
    }

    function collectState() {
        const state = {
            name: $('[name="name"]').val(),
            subject: $('[name="subject"]').val(),
            listIds: ($('[name="listIds[]"]').val() || []),
            sections: []
        };
        $('#brevo-builder-form .section').each(function () {
            const idx = $(this).data('section-index');

            // prikupi banere iz DOM-a
            const banners = [];
            $(this).find('.banner-picker .banner-item').each(function () {
                const image = $(this).find(`input[name^="sections[${idx}][banners]"][name$="[image]"]`).val() || '';
                const link = $(this).find(`input[name^="sections[${idx}][banners]"][name$="[link]"]`).val() || '';
                const alt = $(this).find(`input[name^="sections[${idx}][banners]"][name$="[alt]"]`).val() || '';
                const position = $(this).find(`input[name^="sections[${idx}][banners]"][name$="[position]"]`).val() || '';
                if (image || link) banners.push({ image, link, alt, position });
            });

            state.sections.push({
                idx,
                title: $(`[name="sections[${idx}][title]"]`).val(),
                link: $(`[name="sections[${idx}][link]"]`).val(),
                image: $(`[name="sections[${idx}][image]"]`).val(),
                layout: $(`[name="sections[${idx}][layout]"]`).val(),
                postIds: $(`[name="sections[${idx}][postIds]"]`).val() || '',
                banners: banners
            });
        });
        state.previewFile = $('#brevo-preview-file').val() || '';
        state.campaignId = $('#brevo-campaign-id').val() || '';
        return state;
    }

    const saveDraft = debounce(function () {
        try { localStorage.setItem(draftKey(), JSON.stringify(collectState())); } catch (e) { }
    }, 400);

    function loadDraft() {
        const id = $.trim($('#brevo-campaign-id').val() || '');
        if (!id) { try { localStorage.removeItem('brevoBuilderDraft_v1'); } catch (e) { } return; }
        const raw = localStorage.getItem(draftKey());
        if (!raw) return;
        try {
            const s = JSON.parse(raw);
            if (s.name) $('[name="name"]').val(s.name);
            if (s.subject) $('[name="subject"]').val(s.subject);
            if (s.listIds) $('[name="listIds[]"]').val(s.listIds);

            (s.sections || []).forEach(sec => {
                const idx = sec.idx;
                $(`[name="sections[${idx}][title]"]`).val(sec.title || '');
                $(`[name="sections[${idx}][link]"]`).val(sec.link || '');
                $(`[name="sections[${idx}][image]"]`).val(sec.image || '');
                $(`[name="sections[${idx}][layout]"]`).val(sec.layout || 'l1');
                $(`[name="sections[${idx}][postIds]"]`).val(sec.postIds || '');

                // vrati banere
                const banners = Array.isArray(sec.banners) ? sec.banners : [];
                const $list = $(`.section[data-section-index="${idx}"] .banner-picker .banner-list`);
                // ako u DOM-u nema dovoljno redova, kreiraj ih
                if ($list.length && banners.length) {
                    $list.empty();
                    banners.forEach(b => addBannerRow(idx, b));
                }
            });

            $('.post-picker').each(function () { renderSelected($(this)); });
        } catch (e) { }
    }


    // Clear all
    $(document).on('click', '.pp-clear', function () {
        const $root = sectionRoot($(this));
        setIds($root, []);
        renderSelected($root);
        saveDraft();
    });

    // Drag & drop reorder
    let dragId = null;
    $(document).on('dragstart', '.post-selected-list .pp-card', function (e) {
        dragId = parseInt($(this).data('id'), 10);
        e.originalEvent.dataTransfer.setData('text/plain', dragId);
        $(this).addClass('dragging');
    });

    $(document).on('dragend', '.post-selected-list .pp-card', function () {
        $(this).removeClass('dragging'); dragId = null;
    });

    $(document).on('dragover', '.post-selected-list', function (e) { e.preventDefault(); });
    $(document).on('drop', '.post-selected-list', function (e) {
        e.preventDefault();
        const $root = sectionRoot($(this));
        const ids = getIds($root);
        const $target = $(e.target).closest('.pp-card');
        const targetId = $target.length ? parseInt($target.data('id'), 10) : null;
        if (dragId == null || dragId === targetId) return;

        let order = ids.filter(id => id !== dragId);
        if (targetId && order.includes(targetId)) {
            const idx = order.indexOf(targetId);
            order.splice(idx, 0, dragId);
        } else {
            order.push(dragId);
        }
        setIds($root, order);
        renderSelected($root);
        saveDraft();
    });

    // autosave on any change
    $(document).on('input change', '#brevo-builder-form input, #brevo-builder-form select', saveDraft);
    $(function () { loadDraft(); });

    /* ------------------ Validation + Actions ------------------ */
    function ensurePreview() {
        const f = trim($('#brevo-preview-file').val());
        if (!f) { err('Please generate preview first.'); return false; }
        return true;
    }

    function validateDraftInput() {
        const $name = $('[name="name"]');
        const $subject = $('[name="subject"]');
        const lists = ($('[name="listIds[]"]').val() || []);
        const tplVal = $('input[name="template"]:checked').val() || '';
        let ok = true;

        if (!trim($name.val())) { markInvalid($name); err('Campaign name is required.'); ok = false; }
        if (!trim($subject.val())) { markInvalid($subject); err('Subject is required.'); ok = false; }
        if (!trim(tplVal)) { err('Template is required.'); ok = false; }
        if (!lists.length) { markInvalid($('[name="listIds[]"]')); err('Select at least one recipients list.'); ok = false; }
        if (!ensurePreview()) ok = false;
        return ok;
    }

    (function () {
        const style = document.createElement('style');
        style.innerHTML += `
        .banner-picker h4{ margin:8px 0 }
        .banner-picker .banner-item label{ display:block; margin:6px 0 4px }
    `;
        document.head.appendChild(style);
    })();

    function updateSaveButtonLabel() {
        const $btn = $('#brevo-create-campaign-submit');
        const hasId = $.trim($('#brevo-campaign-id').val() || '') !== '';
        const forceNew = $('#brevo-save-as-new').is(':checked');
        let label = 'Create Draft in Brevo';
        if (forceNew) label = 'Save as new copy';
        else if (hasId) label = 'Save / Update draft';
        if (!$btn.data('loading')) $btn.text(label); else $btn.data('origHtml', label);
    }

    function installHandlers() {
        // Generate & Preview
        $('#brevo-generate-preview').off('click').on('click', function () {
            const $btn = $(this);
            const $subject = $('[name="subject"]');
            const subject = trim($subject.val());
            if (!subject) { markInvalid($subject); return err('Subject is required for preview.'); }

            const $template = $('[name="template"]');
            const template = $('input[name="template"]:checked').val() || 'template1.html';
            if (!template) { markInvalid($template); return err('Template is required for preview.'); }


            startBtnLoading($btn, 'Generating…');

            $.post(BREVO_CAMPAIGNS.ajaxUrl, {
                action: 'brevo_generate_preview',
                nonce: BREVO_CAMPAIGNS.nonce,
                payload: { subject, template, sections: collectSections() }
            })
                .done(function (resp) {
                    if (resp && resp.success) {
                        $('#brevo-preview-file').val(resp.data.file);
                        window.open(resp.data.url, '_blank');
                        enableBtns(true);
                    } else {
                        err((resp && resp.data && resp.data.message) || 'Error generating preview');
                    }
                })
                .fail(function (xhr) { err('Preview failed: ' + (xhr.responseText || 'Network error')); })
                .always(function () { stopBtnLoading($btn); });
        });

        // Create Draft / Save
        $('#brevo-create-campaign-submit').off('click').on('click', function () {
            const $btn = $(this);
            if (!validateDraftInput()) return;
            startBtnLoading($btn, 'Saving…');

            const listIds = ($('[name="listIds[]"]').val() || []).map(Number);
            const forceNew = $('#brevo-save-as-new').is(':checked') ? 1 : 0;
            const campaignId = $('#brevo-campaign-id').val();
            const template = $('input[name="template"]:checked').val() || 'template1.html';

            $.post(BREVO_CAMPAIGNS.ajaxUrl, {
                action: 'brevo_create_campaign',
                nonce: BREVO_CAMPAIGNS.nonce,
                subject: $('[name="subject"]').val(),
                template: template,
                name: $('[name="name"]').val(),
                listIds: listIds,
                previewFile: $('#brevo-preview-file').val(),
                campaignId: campaignId,
                forceNew: forceNew,
                builderPayload: JSON.stringify({ sections: collectSections() })
            })
                .done(function (resp) {
                    if (resp && resp.success) {
                        if (resp.data && resp.data.id) {
                            $('#brevo-campaign-id').val(resp.data.id).trigger('change');
                            // premesti autosave na novi ključ ako je kreirana nova kopija
                            const newId = String(resp.data.id);
                            try {
                                const state = collectState();
                                state.campaignId = newId;
                                localStorage.setItem('brevoBuilderDraft_v2_' + newId, JSON.stringify(state));
                                localStorage.removeItem('brevoBuilderDraft_v2_NEW');
                            } catch (e) { }
                        }
                        if (resp.data && resp.data.duplicated) alert('Saved as a NEW copy (previous campaign could not be updated).');
                        else if (resp.data && resp.data.updated) alert('Draft updated in Brevo.');
                        else alert('Draft created in Brevo.');
                        $('#brevo-save-as-new').prop('checked', false);
                    } else {
                        alert((resp && resp.data && resp.data.message) || 'Error saving campaign');
                    }
                })
                .fail(function (xhr) { alert('Save failed: ' + (xhr.responseText || 'Network error')); })
                .always(function () { stopBtnLoading($btn); updateSaveButtonLabel(); });
        });

        // Send Test
        $('#brevo-send-test').off('click').on('click', function () {
            const $btn = $(this);
            const id = $('#brevo-campaign-id').val();
            if (!id) { alert('Create campaign draft first.'); return; }
            startBtnLoading($btn, 'Sending…');

            let emails = prompt('Test email(s), comma separated:');
            if (!emails) { stopBtnLoading($btn); return; }

            const listIds = ($('[name="listIds[]"]').val() || []).map(Number);

            $.post(BREVO_CAMPAIGNS.ajaxUrl, {
                action: 'brevo_send_test',
                nonce: BREVO_CAMPAIGNS.nonce,
                campaignId: id,
                emails: emails.split(',').map(e => e.trim()).filter(Boolean),
                listIds: listIds
            })
                .done(function (resp) { if (resp && resp.success) alert('Test sent'); else alert((resp && resp.data && resp.data.message) || 'Error sending test'); })
                .fail(function (xhr) { alert('Send test failed: ' + (xhr.responseText || 'Network error')); })
                .always(function () { stopBtnLoading($btn); });
        });

        // Schedule
        $('#brevo-schedule').off('click').on('click', function () {
            const $btn = $(this);
            const id = trim($('#brevo-campaign-id').val());
            if (!id) return err('Create campaign draft first.');
            const iso = prompt('ISO date-time (e.g. 2025-09-26T10:00:00+02:00)');
            if (!iso) return;
            if (!isISO(iso)) return err('Please enter a valid ISO 8601 datetime (e.g. 2025-09-26T10:00:00+02:00 or Z).');
            startBtnLoading($btn, 'Scheduling…');

            $.post(BREVO_CAMPAIGNS.ajaxUrl, { action: 'brevo_schedule_campaign', nonce: BREVO_CAMPAIGNS.nonce, campaignId: id, scheduleAt: iso })
                .done(function (resp) { if (resp && resp.success) alert('Scheduled'); else err((resp && resp.data && resp.data.message) || 'Error'); })
                .fail(function (xhr) { err('Schedule failed: ' + (xhr.responseText || 'Network error')); })
                .always(function () { stopBtnLoading($btn); });
        });

        // Confirm / Send Now
        $('#brevo-send-now').off('click').on('click', function () {
            const $btn = $(this);
            const id = trim($('#brevo-campaign-id').val());
            if (!id) return err('Create campaign draft first.');
            if (!confirm('Send campaign now?')) return;
            startBtnLoading($btn, 'Sending…');

            $.post(BREVO_CAMPAIGNS.ajaxUrl, { action: 'brevo_confirm_campaign', nonce: BREVO_CAMPAIGNS.nonce, campaignId: id })
                .done(function (resp) { if (resp && resp.success) alert('Sent'); else err((resp && resp.data && resp.data.message) || 'Error'); })
                .fail(function (xhr) { err('Send now failed: ' + (xhr.responseText || 'Network error')); })
                .always(function () { stopBtnLoading($btn); });
        });

        // dugmad su disabled dok nema previewa
        enableBtns(!!trim($('#brevo-preview-file').val()));
    }

    /* ------------------ Delete (list page) ------------------ */
    $(document).on('click', '.brevo-remove-campaign', function () {
        const $btn = $(this);
        const id = parseInt($btn.data('id'), 10);
        if (!id) return;
        if (!confirm('Remove this draft campaign?')) return;

        startBtnLoading($btn, 'Removing…');

        $.post(BREVO_CAMPAIGNS.ajaxUrl, { action: 'brevo_delete_campaign', nonce: BREVO_CAMPAIGNS.nonce, id: id })
            .done(function (resp) {
                if (resp && resp.success) {
                    const $row = $btn.closest('tr');
                    $row.css('background', '#fff3f3').fadeOut(200, function () { $(this).remove(); });
                } else {
                    alert((resp && resp.data && resp.data.message) || 'Delete failed');
                }
            })
            .fail(function (xhr) { alert('Delete failed: ' + (xhr.responseText || 'Network error')); })
            .always(function () { stopBtnLoading($btn); });
    });

    /* ------------------ Preview ------------------ */
    $(document).on('click', '.brevo-preview-campaign', function () {
        const $btn = $(this);
        const id = parseInt($btn.data('id'), 10);
        const status = parseInt($btn.data('status'), 10);
        if (!id) return;

        if (typeof startBtnLoading === 'function') startBtnLoading($btn, 'Opening…');

        $.post(BREVO_CAMPAIGNS.ajaxUrl, {
            action: 'brevo_campaign_preview',
            nonce: BREVO_CAMPAIGNS.nonce,
            id: id
        })
            .done(function (resp) {
                if (resp && resp.success) {
                    if (resp.data.type === 'url') {
                        window.open(resp.data.value, '_blank');
                    } else if (resp.data.type === 'html') {
                        const w = window.open('', '_blank');
                        if (w) { w.document.open(); w.document.write(resp.data.value); w.document.close(); }
                        else { alert('Popup blocked. Allow popups to view preview.'); }
                    }
                } else {
                    alert((resp && resp.data && resp.data.message) || 'Preview not available');
                }
            })
            .fail(function (xhr) {
                alert('Preview failed: ' + (xhr.responseText || 'Network error'));
            })
            .always(function () {
                if (typeof stopBtnLoading === 'function') stopBtnLoading($btn);
            });
    });

    /* ------------------ Send emails via Brevo ------------------ */
    $('#brevo-mail-test').on('click', function () {
        const to = prompt('Send test email to:');
        if (!to) return;
        $(this).prop('disabled', true).text('Sending…');
        $.post(ajaxurl, { action: 'brevo_mail_test', nonce: BREVO_CAMPAIGNS.nonce, to: to }, (resp) => {
            alert(resp && resp.success ? 'Test sent.' : (resp && resp.data && resp.data.message ? resp.data.message : 'Failed'));
        }).always(() => $(this).prop('disabled', false).text('Send test email'));
    });

    /* ------------------ Init ------------------ */
    $(installHandlers);
    $(function () { updateSaveButtonLabel(); });
    $(document).on('change', '#brevo-save-as-new', updateSaveButtonLabel);
    $(document).on('change', '#brevo-campaign-id', updateSaveButtonLabel);

    // ---- View stats modal ----
    function closeStats() { $('#brevo-stats-modal, #brevo-stats-backdrop').remove(); $(document).off('keydown.brevoStats'); }

    function kpi(label, value, sub) {
        return `
      <div class="brevo-kpi">
        <div class="brevo-kpi-label">${label}</div>
        <div class="brevo-kpi-value">${value}</div>
        ${sub != null ? `<div class="brevo-kpi-sub">${sub}</div>` : ''}
      </div>`;
    }

    function openStatsModal(data) {
        const m = data.metrics || {};
        const links = data.links || [];
        const fmt = (v) => (v == null ? '-' : (typeof v === 'number' ? (v % 1 ? v.toFixed(2) : v) : String(v)));

        const html = `
            <div id="brevo-stats-backdrop" class="brevo-modal-backdrop"></div>
            <div id="brevo-stats-modal" class="brevo-modal">
            <div class="brevo-modal-head">
                <h2>Campaign stats</h2>
                <button type="button" class="button-link brevo-modal-close" aria-label="Close">✕</button>
            </div>
            <div class="brevo-modal-body">
                <div class="brevo-kpi-grid">
                <div class="brevo-kpi">
                    <div class="brevo-kpi-label">Delivered</div>
                    <div class="brevo-kpi-value">${fmt(m.delivered)}</div>
                    <div class="brevo-kpi-sub">Delivery rate ${fmt(m.deliveryRate)}%</div>
                </div>
                <div class="brevo-kpi">
                    <div class="brevo-kpi-label">Opens</div>
                    <div class="brevo-kpi-value">${fmt(m.opens)}</div>
                    <div class="brevo-kpi-sub">Open rate ${fmt(m.openRate)}%</div>
                </div>
                <div class="brevo-kpi">
                    <div class="brevo-kpi-label">Clicks</div>
                    <div class="brevo-kpi-value">${fmt(m.clicks)}</div>
                    <div class="brevo-kpi-sub">CTR ${fmt(m.ctr)}% · CTOR ${fmt(m.ctor)}%</div>
                </div>
                <div class="brevo-kpi"><div class="brevo-kpi-label">Unsubscribes</div><div class="brevo-kpi-value">${fmt(m.unsubs)}</div></div>
                <div class="brevo-kpi"><div class="brevo-kpi-label">Bounces</div><div class="brevo-kpi-value">${fmt(m.bounces)}</div></div>
                <div class="brevo-kpi"><div class="brevo-kpi-label">Spam reports</div><div class="brevo-kpi-value">${fmt(m.spam)}</div></div>
                </div>

                <h3 style="margin-top:16px">Top links</h3>
                <div class="brevo-links">
                ${links.length ? links.map(l => `
                    <div class="brevo-link-row">
                    <a href="${l.url}" target="_blank" rel="noopener">${l.url}</a>
                    <span class="brevo-link-count">${fmt(l.count)}</span>
                    </div>`).join('') : '<p class="description">No link clicks recorded.</p>'}
                </div>
            </div>
            </div>`;
        $('body').append(html);
        $(document).on('keydown.brevoStats', e => { if (e.key === 'Escape') closeStats(); });
        $(document).on('click', '#brevo-stats-backdrop, #brevo-stats-modal .brevo-modal-close', closeStats);
    }

    $(document).on('click', '.brevo-view-stats', function () {
        const $btn = $(this);
        const id = parseInt($btn.data('id'), 10);
        if (!id) return;
        if (typeof startBtnLoading === 'function') startBtnLoading($btn, 'Loading…');

        $.post(BREVO_CAMPAIGNS.ajaxUrl, {
            action: 'brevo_get_campaign_stats',
            nonce: BREVO_CAMPAIGNS.nonce,
            id: id
        })
            .done(function (resp) {
                if (resp && resp.success) openStatsModal(resp.data);
                else alert((resp && resp.data && resp.data.message) || 'Failed to load stats');
            })
            .fail(function (xhr) { alert('Failed to load stats: ' + (xhr.responseText || 'Network error')); })
            .always(function () { if (typeof stopBtnLoading === 'function') stopBtnLoading($btn); });
    });


})(jQuery);
