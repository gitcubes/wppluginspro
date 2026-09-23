//Show box accordion
jQuery(function ($) {
	$(document).on('click', '.wsh-aine-accordion-toggle', function () {
		var $button = $(this);
		var expanded = $button.attr('aria-expanded') === 'true';
		var controls = $button.attr('aria-controls');
		var $panel = $('#' + controls);

		$button.attr('aria-expanded', expanded ? 'false' : 'true');
		$panel.prop('hidden', expanded);
	});
});

//Generate AI Article
jQuery(document).on('click', '#wsh-generate-ai', function () {
	let seed = jQuery(this).data('seed');
	let $btn = jQuery(this);

	$btn.text('Generating...').prop('disabled', true);

	jQuery.post(
		wshAineAdmin.ajaxUrl,
		{
			action: 'wsh_aine_generate_ai',
			seed: seed,
			nonce: wshAineAdmin.nonce
		},
		function (res) {
			if (res.success) {
				jQuery('#ai_title').val(res.data.title || '');
				jQuery('#ai_excerpt').val(res.data.excerpt || '');
				jQuery('#ai_seo_title').val(res.data.seo_title || '');

				/*if (typeof tinyMCE !== 'undefined' && tinyMCE.get('wsh_aine_ai_content')) {
					tinyMCE.get('wsh_aine_ai_content').setContent(res.data.content || '');
				} else {
					jQuery('#wsh_aine_ai_content').val(res.data.content || '');
				}*/
				if (typeof tinyMCE !== 'undefined' && tinyMCE.get('wsh_aine_ai_content')) {
					let editor = tinyMCE.get('wsh_aine_ai_content');
					let existingContent = editor.getContent() || '';
					let aiContent = res.data.content || '';

					if (aiContent) {
						//editor.setContent(aiContent + existingContent);
						editor.setContent(aiContent + '<!-- WSH_SOURCE_DIVIDER --><hr>' + existingContent);
					}
				} else {
					let $textarea = jQuery('#wsh_aine_ai_content');
					let existingContent = $textarea.val() || '';
					let aiContent = res.data.content || '';

					if (aiContent) {
						//$textarea.val(aiContent + "\n\n" + existingContent);
						$textarea.val(aiContent + "\n\n<!-- WSH_SOURCE_DIVIDER --><hr>\n\n" + existingContent);
					}
				}


				if (Array.isArray(res.data.tags)) {
					jQuery('#ai_tags').val(res.data.tags.join(', '));
				}
			} else {
				alert(res.data || 'AI generation failed.');
			}

			$btn.text('Generate AI Article').prop('disabled', false);
		}
	);
});

//Add Custom image
jQuery(function ($) {
	let wshAineMediaFrame = null;

	$(document).on('click', '#wsh-aine-select-custom-image', function (e) {
		e.preventDefault();

		if (wshAineMediaFrame) {
			wshAineMediaFrame.open();
			return;
		}

		wshAineMediaFrame = wp.media({
			title: (wshAineAdmin.strings && wshAineAdmin.strings.selectImage) ? wshAineAdmin.strings.selectImage : 'Select Image',
			button: {
				text: (wshAineAdmin.strings && wshAineAdmin.strings.useImage) ? wshAineAdmin.strings.useImage : 'Use this image'
			},
			multiple: false,
			library: {
				type: 'image'
			}
		});

		wshAineMediaFrame.on('select', function () {
			const attachment = wshAineMediaFrame.state().get('selection').first().toJSON();

			if (!attachment || !attachment.id) {
				return;
			}

			$('#wsh_aine_custom_image_id').val(attachment.id);
			$('#wsh-aine-custom-image-preview').attr('src', attachment.url);
			$('#wsh-aine-custom-image-preview-wrap').show();
			$('#wsh-aine-remove-custom-image').show();

			if ($('#wsh-aine-use-source-image').length) {
				$('#wsh-aine-use-source-image').prop('checked', false);
			}
		});

		wshAineMediaFrame.open();
	});

	$(document).on('click', '#wsh-aine-remove-custom-image', function (e) {
		e.preventDefault();

		$('#wsh_aine_custom_image_id').val('');
		$('#wsh-aine-custom-image-preview').attr('src', '');
		$('#wsh-aine-custom-image-preview-wrap').hide();
		$('#wsh-aine-remove-custom-image').hide();
	});
});

jQuery(document).on('click', '.wsh-aine-ai-quick', function () {
	const instruction = jQuery(this).data('instruction') || '';
	jQuery('#wsh-aine-ai-chat-instruction').val(instruction);
});

//AI chat action
jQuery(document).on('click', '#wsh-aine-ai-chat-submit', function () {
	let $btn = jQuery(this);
	let seed = $btn.data('seed') || '';
	let instruction = jQuery('#wsh-aine-ai-chat-instruction').val() || '';
	let title = jQuery('#ai_title').val() || '';
	let fullContent = '';
	let divider = '<!-- WSH_SOURCE_DIVIDER -->';

	if (!instruction.trim()) {
		alert('Unesi instrukciju za AI.');
		return;
	}

	if (typeof tinyMCE !== 'undefined' && tinyMCE.get('wsh_aine_ai_content')) {
		fullContent = tinyMCE.get('wsh_aine_ai_content').getContent() || '';
	} else {
		fullContent = jQuery('#wsh_aine_ai_content').val() || '';
	}

	let aiPart = fullContent;
	let sourcePart = '';

	if (fullContent.indexOf(divider) !== -1) {
		let parts = fullContent.split(divider);
		aiPart = parts[0] ? parts[0].trim() : '';
		sourcePart = parts.slice(1).join(divider).trim();

		if (sourcePart) {
			sourcePart = divider + "\n\n" + sourcePart;
		}
	}

	$btn.text('AI radi...').prop('disabled', true);

	jQuery.post(
		wshAineAdmin.ajaxUrl,
		{
			action: 'wsh_aine_editor_chat',
			seed: seed,
			title: title,
			content: aiPart,
			instruction: instruction,
			nonce: wshAineAdmin.nonce
		},
		function (res) {
			if (res.success) {
				jQuery('#ai_title').val(res.data.title || '');

				let newAiContent = (res.data.content || '').trim();
				let finalContent = newAiContent;

				if (sourcePart) {
					finalContent += "\n\n" + sourcePart;
				}

				if (typeof tinyMCE !== 'undefined' && tinyMCE.get('wsh_aine_ai_content')) {
					tinyMCE.get('wsh_aine_ai_content').setContent(finalContent);
				} else {
					jQuery('#wsh_aine_ai_content').val(finalContent);
				}
			} else {
				alert(res.data || 'AI rewrite failed.');
			}

			$btn.text('Pošalji AI').prop('disabled', false);
		}
	);
});

//Generate comments
jQuery(document).on('click', '#wsh-aine-generate-comments', function () {
	let $btn = jQuery(this);
	let seed = $btn.data('seed') || '';
	let title = jQuery('#ai_title').val() || '';
	let content = '';

	if (typeof tinyMCE !== 'undefined' && tinyMCE.get('wsh_aine_ai_content')) {
		content = tinyMCE.get('wsh_aine_ai_content').getContent() || '';
	} else {
		content = jQuery('#wsh_aine_ai_content').val() || '';
	}

	$btn.text('Generisanje...').prop('disabled', true);

	jQuery.post(
		wshAineAdmin.ajaxUrl,
		{
			action: 'wsh_aine_generate_comments',
			seed: seed,
			title: title,
			content: content,
			nonce: wshAineAdmin.nonce
		},
		function (res) {
			if (res.success && res.data.comments) {
				jQuery('#ai_comment_1').val(res.data.comments[0] || '');
				jQuery('#ai_comment_2').val(res.data.comments[1] || '');
				jQuery('#ai_comment_3').val(res.data.comments[2] || '');
				jQuery('#ai_comment_4').val(res.data.comments[3] || '');
				jQuery('#ai_comment_5').val(res.data.comments[4] || '');
			} else {
				alert(res.data || 'Comments generation failed.');
			}

			$btn.text('Generiši komentare').prop('disabled', false);
		}
	);
});

//Add Comments
jQuery(document).on('change', '#wsh-aine-insert-comments-toggle', function () {

	let checked = jQuery(this).is(':checked');

	if (checked) {
		jQuery('#wsh_aine_editor_form_action')
			.val('wsh_aine_create_draft_with_comments');
	} else {
		jQuery('#wsh_aine_editor_form_action')
			.val('wsh_aine_create_draft');
	}

});



// Settings tabs + repeater.
jQuery(function ($) {
	function initSettingsTabs() {
		var $nav = $('[data-wsh-settings-tabs]');
		if (!$nav.length) {
			return;
		}

		$nav.on('click', '[data-tab-target]', function () {
			var target = $(this).data('tab-target');
			$nav.find('[data-tab-target]').removeClass('is-active');
			$(this).addClass('is-active');
			$('.wsh-aine-settings-panel').removeClass('is-active');
			$('.wsh-aine-settings-panel[data-tab-panel="' + target + '"]').addClass('is-active');
		});
	}

	function renumberRepeater($repeater) {
		var fieldName = $repeater.data('field-name');
		$repeater.find('[data-repeater-row]').each(function (index) {
			$(this).find('input').each(function () {
				var name = $(this).attr('name') || '';
				name = name.replace(/__index__/g, index);
				name = name.replace(new RegExp(fieldName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[(\\d+|__index__)\\]'), fieldName + '[' + index + ']');
				$(this).attr('name', name);
			});
		});
	}

	function initRepeaters() {
		$(document).on('click', '[data-repeater-add]', function () {
			var $repeater = $(this).closest('[data-repeater]');
			var template = $.trim($repeater.find('.wsh-aine-repeater-template').html());
			var index = $repeater.find('[data-repeater-row]').length;
			template = template.replace(/__index__/g, index);
			$repeater.find('[data-repeater-rows]').append(template);
			renumberRepeater($repeater);
		});

		$(document).on('click', '[data-repeater-remove]', function () {
			var $repeater = $(this).closest('[data-repeater]');
			var $rows = $repeater.find('[data-repeater-row]');

			if ($rows.length === 1) {
				$rows.find('input[type="text"]').val('');
				$rows.find('input[type="checkbox"]').prop('checked', true);
				return;
			}

			$(this).closest('[data-repeater-row]').remove();
			renumberRepeater($repeater);
		});
	}

	initSettingsTabs();
	initRepeaters();
});
