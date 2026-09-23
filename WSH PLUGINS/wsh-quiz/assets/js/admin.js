(function ($) {
	"use strict";

	var config = window.wshQuizAdmin || {};

	function uuid() {
		if (window.crypto && crypto.randomUUID) {
			return crypto.randomUUID();
		}
		return "q" + Date.now() + Math.floor(Math.random() * 10000);
	}

	function questionCards() {
		return $(".wsh-quiz-questions > .wsh-quiz-question");
	}

	function refreshNumbers() {
		questionCards().each(function (index) {
			var $card = $(this);
			$card.find(".wsh-quiz-question-number").first().text(index + 1);
			refreshPreview($card);
		});
		$(".wsh-quiz-questions-empty").prop("hidden", questionCards().length > 0);
	}

	function refreshPreview($card) {
		var text = $.trim($card.find('textarea[name*="[question]"]').val() || "");
		$card.find(".wsh-quiz-question__preview").text(text);
	}

	function collapseAll() {
		questionCards().addClass("is-collapsed");
	}

	function openCard($card) {
		collapseAll();
		$card.removeClass("is-collapsed");
	}

	function typeIconClass(type) {
		var icons = {
			choice: "dashicons-editor-ul",
			yes_no: "dashicons-yes-alt",
			image_prompt: "dashicons-format-image",
			video_prompt: "dashicons-format-video",
			image_choice: "dashicons-images-alt2",
		};
		return icons[type] || icons.choice;
	}

	function applyType($card) {
		var type = $card.find(".wsh-quiz-type").val();
		var label = $card.find(".wsh-quiz-type option:selected").text() || "";
		$card.attr("data-type", type);
		$card.find(".wsh-quiz-question__type-icon")
			.attr("class", "dashicons wsh-quiz-question__type-icon " + typeIconClass(type))
			.attr("title", $.trim(label));

		if (type === "yes_no") {
			$card.find(".wsh-quiz-option").each(function (index) {
				var $row = $(this);
				if (index > 1) {
					$row.hide();
					return;
				}
				$row.show();
				$row.find(".wsh-quiz-option-text")
					.prop("readonly", true)
					.val(index === 0 ? config.yesLabel : config.noLabel);
			});
		} else {
			$card.find(".wsh-quiz-option").show();
			$card.find(".wsh-quiz-option-text").prop("readonly", false);
		}
	}

	function mediaWrap($el) {
		return $el.closest("p, td, .wsh-quiz-option-media, .wsh-quiz-sponsor-logo, .wsh-quiz-media-image");
	}

	function isImageUrl(url) {
		return /\.(png|jpe?g|gif|webp|avif|svg)(\?|#|$)/i.test(url || "");
	}

	function setQuestionMediaPreview($wrap, url) {
		var $preview = $wrap.find(".wsh-quiz-media-preview");
		if (!$preview.length) {
			return;
		}

		if (url && isImageUrl(url)) {
			var $img = $preview.find("img");
			if ($img.length) {
				$img.attr("src", url);
			} else {
				$preview.html('<img src="' + url + '" alt="" />');
			}
			$preview.attr("data-full", url).prop("hidden", false);
			return;
		}

		$preview.prop("hidden", true).attr("data-full", "").find("img").remove();
	}

	function setLogoPreview($wrap, url, id) {
		$wrap.find(".wsh-quiz-media-id").val(id || 0);
		$wrap.find(".wsh-quiz-media-url").val(url || "");
		$wrap.find(".wsh-quiz-clear-image").prop("hidden", !url);

		if ($wrap.hasClass("wsh-quiz-media-image") || $wrap.find(".wsh-quiz-media-preview").length) {
			setQuestionMediaPreview($wrap, url);
			if (url && isImageUrl(url)) {
				$wrap.closest(".wsh-quiz-field-media").find(".wsh-quiz-media-type").val("image");
			}
			return;
		}

		if (url) {
			if ($wrap.find(".wsh-quiz-sponsor-preview").length) {
				$wrap.find(".wsh-quiz-sponsor-preview").attr("src", url);
			} else {
				$wrap.find(".wsh-quiz-pick-image").before('<img src="' + url + '" alt="" class="wsh-quiz-sponsor-preview" />');
			}
			return;
		}

		$wrap.find(".wsh-quiz-sponsor-preview").remove();
	}

	function openMedia($button) {
		var frame = wp.media({
			title: config.i18n.selectImage,
			button: { text: config.i18n.useImage },
			multiple: false,
		});

		frame.on("select", function () {
			var attachment = frame.state().get("selection").first().toJSON();
			setLogoPreview(mediaWrap($button), attachment.url, attachment.id);
		});

		frame.open();
	}

	function openImagePreview(url) {
		var $modal = $("#wsh-quiz-image-modal");
		if (!$modal.length || !url) {
			return;
		}
		$modal.find("img").attr("src", url);
		$modal.prop("hidden", false);
	}

	function addQuestion(open) {
		var template = $("#tmpl-wsh-quiz-question").html();
		if (!template) {
			return $();
		}

		var index = questionCards().length;
		var html = template
			.replace(/__INDEX__/g, String(index))
			.replace(/__ID__/g, uuid());

		var $card = $(html);
		$(".wsh-quiz-questions").append($card);
		applyType($card);
		refreshNumbers();
		syncQuestionDeleteState();
		if (open !== false) {
			openCard($card);
		}
		return $card;
	}

	function optionText(question, index) {
		if (!question || !question.options || !question.options[index]) {
			return "";
		}
		return question.options[index].text || "";
	}

	function fillQuestionCard($card, question) {
		var allowed = {
			choice: true,
			yes_no: true,
			image_prompt: true,
			video_prompt: true,
		};
		var type = allowed[question.type] ? question.type : "choice";
		var correct = parseInt(question.correct, 10) || 0;
		var mediaType = question.media_type || "";

		if (!mediaType) {
			if (type === "image_prompt") {
				mediaType = "image";
			} else if (type === "video_prompt") {
				mediaType = "video";
			} else {
				mediaType = "none";
			}
		}

		$card.find('input[name$="[id]"]').val(question.id || uuid());
		$card.find(".wsh-quiz-type").val(type);
		$card.find(".wsh-quiz-media-type").val(mediaType);
		$card.find(".wsh-quiz-media-image .wsh-quiz-media-id").val(question.media_id || 0);
		$card.find(".wsh-quiz-media-image .wsh-quiz-media-url").val(question.media_url || "");
		$card.find('input[name*="[media_source]"]').val(question.media_source || "");
		$card.find('textarea[name*="[question]"]').val(question.question || "");
		$card.find('input[name*="[hint]"]').val(question.hint || "");
		$card.find('textarea[name*="[explanation]"]').val(question.explanation || "");
		$card.find('input[name*="[score]"]').val(question.score || 1);
		$card.find('input[name*="[correct]"]').prop("checked", false);
		$card.find('input[name*="[correct]"][value="' + correct + '"]').prop("checked", true);
		$card.find(".wsh-quiz-option").each(function (index) {
			$(this).find(".wsh-quiz-option-text").val(optionText(question, index));
		});
		applyType($card);
		refreshPreview($card);
		setQuestionMediaPreview($card.find(".wsh-quiz-media-image"), question.media_url || "");
	}

	function maybeFillTitle(title) {
		if (!title) {
			return;
		}

		var $title = $("#title");
		if ($title.length && $.trim($title.val() || "") === "") {
			$title.val(title).trigger("input").trigger("change");
			$("#title-prompt-text").hide();
		}

		if (window.wp && wp.data && wp.data.dispatch) {
			try {
				var current = wp.data.select("core/editor").getEditedPostAttribute("title");
				if (!current) {
					wp.data.dispatch("core/editor").editPost({ title: title });
				}
			} catch (error) {
				// Classic editor only.
			}
		}
	}

	function maybeFillDescription(description) {
		var $field = $("#wsh_quiz_description");
		if ($field.length && !$.trim($field.val() || "") && description) {
			$field.val(description);
		}
	}

	function onlyEmptyDraft() {
		var $cards = questionCards();
		if ($cards.length !== 1) {
			return false;
		}
		return $.trim($cards.first().find('textarea[name*="[question]"]').val() || "") === "";
	}

	function hasPlayedGames() {
		return (parseInt(config.playerCount, 10) || 0) > 0;
	}

	function isSavedQuestion($card) {
		return $card.attr("data-saved") === "1";
	}

	function syncQuestionDeleteState() {
		var locked = hasPlayedGames();
		var message = (config.i18n && config.i18n.cannotRemovePlayed) || "";

		$(".wsh-quiz-questions-locked").prop("hidden", !locked);
		$("#wsh-quiz-ai-replace").prop("disabled", locked);
		if (locked) {
			$("#wsh-quiz-ai-replace").prop("checked", false);
		}

		questionCards().each(function () {
			var $card = $(this);
			var $button = $card.find(".wsh-quiz-remove-question");
			var disable = locked && isSavedQuestion($card);
			$button.prop("disabled", disable);
			$button.attr("title", disable ? message : "");
		});
	}

	function applyGeneratedQuiz(payload, replace) {
		var questions = (payload && payload.questions) || [];
		if (!questions.length) {
			return;
		}

		if ((replace && !hasPlayedGames()) || (onlyEmptyDraft() && !hasPlayedGames())) {
			$(".wsh-quiz-questions").empty();
		}

		questions.forEach(function (question) {
			fillQuestionCard(addQuestion(false), question);
		});

		refreshNumbers();
		syncQuestionDeleteState();
		openCard(questionCards().first());
		maybeFillTitle(payload.title || "");
		maybeFillDescription(payload.description || "");
		if (payload.timer > 0) {
			$("#wsh_quiz_timer").val(payload.timer);
		}
		if (payload.difficulty) {
			$("#wsh_quiz_difficulty").val(payload.difficulty);
		}
	}

	function setAiStatus(message, isError) {
		var $status = $(".wsh-quiz-ai-status");
		if (!message) {
			$status.prop("hidden", true).text("");
			return;
		}
		$status
			.prop("hidden", false)
			.toggleClass("is-error", !!isError)
			.text(message);
	}

	function closeModal($modal) {
		($modal && $modal.length ? $modal : $(".wsh-quiz-modal")).prop("hidden", true);
	}

	function clampInt(value, min, max) {
		var number = parseInt(value, 10);
		if (isNaN(number)) {
			number = min;
		}
		return Math.min(max, Math.max(min, number));
	}

	function suggestedTimer(count, difficulty) {
		var per = { easy: 15, medium: 25, hard: 40 };
		return count * (per[difficulty] || 25);
	}

	function syncAiMix(source) {
		var count = clampInt($("#wsh-quiz-ai-count").val(), 1, 20);
		var choice = clampInt($("#wsh-quiz-ai-choice").val(), 0, 20);
		var photo = clampInt($("#wsh-quiz-ai-photo").val(), 0, 20);
		var video = clampInt($("#wsh-quiz-ai-video").val(), 0, 20);

		if (source === "choice" || source === "photo" || source === "video") {
			count = choice + photo + video;
			if (count < 1) {
				count = 1;
				choice = 1;
			}
			if (count > 20) {
				var overflow = count - 20;
				if (source === "choice") {
					choice = Math.max(0, choice - overflow);
				} else if (source === "photo") {
					photo = Math.max(0, photo - overflow);
				} else {
					video = Math.max(0, video - overflow);
				}
				count = choice + photo + video;
			}
		} else {
			if (photo + video > count) {
				var extra = photo + video - count;
				var cutVideo = Math.min(video, extra);
				video -= cutVideo;
				photo = Math.max(0, photo - (extra - cutVideo));
			}
			choice = count - photo - video;
		}

		$("#wsh-quiz-ai-count").val(count);
		$("#wsh-quiz-ai-choice").val(choice);
		$("#wsh-quiz-ai-photo").attr("max", 20).val(photo);
		$("#wsh-quiz-ai-video").attr("max", 20).val(video);
		return count;
	}

	function syncAiTimer() {
		var count = clampInt($("#wsh-quiz-ai-count").val(), 1, 20);
		var difficulty = $("#wsh-quiz-ai-difficulty").val() || "medium";
		$("#wsh-quiz-ai-timer").val(suggestedTimer(count, difficulty));
	}

	function syncAiForm() {
		syncAiMix("count");
		syncAiTimer();
	}

	var lastAutoPrompt = "";

	function currentQuizTitle() {
		var title = $.trim($("#title").val() || "");
		if (!title && window.wp && wp.data && wp.data.select) {
			try {
				title = $.trim(wp.data.select("core/editor").getEditedPostAttribute("title") || "");
			} catch (error) {
				title = "";
			}
		}
		if (title === "Auto Draft") {
			return "";
		}
		return title;
	}

	function currentQuizDescription() {
		return $.trim($("#wsh_quiz_description").val() || "");
	}

	function buildAiPrompt(locale) {
		var templates = config.aiPromptTemplates || {};
		var template = templates[locale] || templates[config.siteLocale] || templates.en_US || "";
		var text = String(template)
			.replace(/%title%/g, currentQuizTitle())
			.replace(/%description%/g, currentQuizDescription());

		return text
			.replace(/^Naslov kviza:\s*$/gm, "")
			.replace(/^Opis kviza:\s*$/gm, "")
			.replace(/^Quiz title:\s*$/gm, "")
			.replace(/^Quiz description:\s*$/gm, "")
			.replace(/\n{3,}/g, "\n\n")
			.trim();
	}

	function fillAiPrompt(force) {
		var locale = $("#wsh-quiz-ai-language").val() || config.siteLocale;
		var next = buildAiPrompt(locale);
		var current = $.trim($("#wsh-quiz-ai-topic").val() || "");
		if (force || !current || current === lastAutoPrompt) {
			$("#wsh-quiz-ai-topic").val(next);
			lastAutoPrompt = next;
		}
	}

	function openAiModal() {
		var $modal = $("#wsh-quiz-ai-modal");
		if (!$modal.length) {
			return;
		}
		setAiStatus("");
		syncAiForm();
		fillAiPrompt(false);
		$modal.prop("hidden", false);
		$("#wsh-quiz-ai-count").trigger("focus");
	}

	function generateWithAi() {
		var i18n = config.i18n || {};
		var topic = $.trim($("#wsh-quiz-ai-topic").val() || "");
		var url = $.trim($("#wsh-quiz-ai-url").val() || "");
		var $button = $(".wsh-quiz-ai-submit");

		syncAiMix("count");

		if (!topic && !url) {
			setAiStatus(i18n.emptyTopic || "Enter a quiz description or an article URL.", true);
			return;
		}

		$button.prop("disabled", true);
		setAiStatus(i18n.generating || "Generating questions…", false);

		$.post(config.ajaxUrl, {
			action: "wsh_quiz_admin_generate",
			nonce: config.adminNonce,
			topic: topic,
			url: url,
			count: $("#wsh-quiz-ai-count").val(),
			difficulty: $("#wsh-quiz-ai-difficulty").val(),
			timer: $("#wsh-quiz-ai-timer").val(),
			photo: $("#wsh-quiz-ai-photo").val(),
			video: $("#wsh-quiz-ai-video").val(),
			language: $("#wsh-quiz-ai-language").val(),
		})
			.done(function (response) {
				if (!response || !response.success || !response.data) {
					setAiStatus((response && response.data && response.data.message) || i18n.error, true);
					return;
				}
				applyGeneratedQuiz(response.data, $("#wsh-quiz-ai-replace").is(":checked"));
				setAiStatus(i18n.generated || "Questions generated.", false);
				closeModal($("#wsh-quiz-ai-modal"));
				window.alert(i18n.generated || "Questions generated. Review them, then save the quiz.");
			})
			.fail(function (xhr) {
				var message = i18n.error || "Error";
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					message = xhr.responseJSON.data.message;
				}
				setAiStatus(message, true);
			})
			.always(function () {
				$button.prop("disabled", false);
			});
	}

	$(function () {
		lastAutoPrompt = $.trim($("#wsh-quiz-ai-topic").val() || "");

		if ($.fn.wpColorPicker) {
			$(".wsh-quiz-color").wpColorPicker();
		}

		questionCards().each(function () {
			applyType($(this));
		});
		refreshNumbers();
		syncQuestionDeleteState();

		$(document).on("change", ".wsh-quiz-type", function () {
			var $select = $(this);
			var $option = $select.find("option:selected");
			if ($option.is(":disabled")) {
				window.alert(config.i18n.proLocked);
				$select.val("choice");
			}
			applyType($select.closest(".wsh-quiz-question"));
		});

		$(document).on("click", ".wsh-quiz-add-question", function (event) {
			event.preventDefault();
			addQuestion();
		});

		$(document).on("click", ".wsh-quiz-question__toggle", function (event) {
			event.preventDefault();
			var $card = $(this).closest(".wsh-quiz-question");
			if ($card.hasClass("is-collapsed")) {
				openCard($card);
			} else {
				$card.addClass("is-collapsed");
			}
		});

		$(document).on("input", '.wsh-quiz-question textarea[name*="[question]"]', function () {
			refreshPreview($(this).closest(".wsh-quiz-question"));
		});

		$(document).on("click", ".wsh-quiz-remove-question", function (event) {
			event.preventDefault();
			event.stopPropagation();

			var $card = $(this).closest(".wsh-quiz-question");
			var i18n = config.i18n || {};

			if ($(this).is(":disabled") || (hasPlayedGames() && isSavedQuestion($card))) {
				window.alert(i18n.cannotRemovePlayed || "");
				return;
			}

			if (!window.confirm(i18n.removeQuestion || "Remove this question?")) {
				return;
			}

			$card.remove();
			refreshNumbers();
		});

		$(document).on("click", ".wsh-quiz-pick-image", function (event) {
			event.preventDefault();
			openMedia($(this));
		});

		$(document).on("click", ".wsh-quiz-clear-image", function (event) {
			event.preventDefault();
			setLogoPreview(mediaWrap($(this)), "", 0);
		});

		$(document).on("click", ".wsh-quiz-media-preview", function (event) {
			event.preventDefault();
			event.stopPropagation();
			openImagePreview($(this).attr("data-full") || $(this).find("img").attr("src") || "");
		});

		$(document).on("input change", ".wsh-quiz-media-image .wsh-quiz-media-url", function () {
			var $wrap = $(this).closest(".wsh-quiz-media-image");
			setQuestionMediaPreview($wrap, $(this).val());
			$wrap.find(".wsh-quiz-clear-image").prop("hidden", !$.trim($(this).val() || ""));
		});

		$(document).on("click", ".wsh-quiz-ai-button", function (event) {
			event.preventDefault();
			if ($(this).data("locked") === 1 || $(this).is(":disabled")) {
				if (config.upgradeUrl) {
					window.location.href = config.upgradeUrl;
				}
				return;
			}
			openAiModal();
		});

		$(document).on("click", ".wsh-quiz-ai-submit", function (event) {
			event.preventDefault();
			generateWithAi();
		});

		$(document).on("input change", "#wsh-quiz-ai-count", function () {
			syncAiForm();
		});

		$(document).on("change", "#wsh-quiz-ai-difficulty", function () {
			syncAiTimer();
		});

		$(document).on("change", "#wsh-quiz-ai-language", function () {
			fillAiPrompt(true);
		});

		$(document).on("input change", "#wsh-quiz-ai-choice, #wsh-quiz-ai-photo, #wsh-quiz-ai-video", function () {
			syncAiMix(this.id.replace("wsh-quiz-ai-", ""));
			syncAiTimer();
		});

		$(document).on("keydown", "#wsh-quiz-ai-modal", function (event) {
			if (event.key === "Enter" && event.target.tagName !== "TEXTAREA") {
				event.preventDefault();
				generateWithAi();
			}
		});

		$(document).on("keydown", function (event) {
			if (event.key === "Escape") {
				closeModal();
			}
		});

		bindQuizActions();
	});

	function adminPost(action, quizId, extra) {
		return $.post(config.ajaxUrl, $.extend({
			action: "wsh_quiz_" + action,
			nonce: config.adminNonce,
			quiz_id: quizId,
		}, extra || {}));
	}

	function quizIdFrom($el) {
		return parseInt($el.closest("[data-quiz-id]").attr("data-quiz-id"), 10) || 0;
	}

	function setPlayerCount(quizId, count, label) {
		$('.wsh-quiz-player-count[data-quiz-id="' + quizId + '"]').text(String(count));
		$('.wsh-quiz-actions[data-quiz-id="' + quizId + '"] .wsh-quiz-player-count').text(label || String(count));
		config.playerCount = parseInt(count, 10) || 0;
		syncQuestionDeleteState();
	}

	function closeResultsModal() {
		closeModal($("#wsh-quiz-results-modal"));
	}

	function renderResultsTable(payload) {
		var i18n = config.i18n || {};
		var rows = (payload && payload.rows) || [];
		var $body = $("#wsh-quiz-results-modal .wsh-quiz-modal__body");

		if (!rows.length) {
			$body.html("<p>" + (i18n.noResults || "No results yet.") + "</p>");
			return;
		}

		var html = "<table><thead><tr>";
		html += "<th>#</th>";
		html += "<th>" + (i18n.name || "Name") + "</th>";
		html += "<th>" + (i18n.email || "Email") + "</th>";
		html += "<th>" + (i18n.score || "Score") + "</th>";
		html += "<th>" + (i18n.time || "Time") + "</th>";
		html += "<th>" + (i18n.date || "Date") + "</th>";
		html += "<th></th>";
		html += "</tr></thead><tbody>";

		rows.forEach(function (row) {
			html += "<tr>";
			html += "<td>" + row.rank + "</td>";
			html += "<td>" + $("<div>").text(row.name || "").html() + "</td>";
			html += "<td>" + $("<div>").text(row.email || "").html() + "</td>";
			html += "<td>" + row.score + "/" + row.max + "</td>";
			html += "<td>" + $("<div>").text(row.duration || "").html() + "</td>";
			html += "<td>" + $("<div>").text(row.created || "").html() + "</td>";
			html += "<td><button type=\"button\" class=\"button-link-delete wsh-quiz-remove-result\" data-result-id=\"" + parseInt(row.id, 10) + "\">" + (i18n.removeGame || "Remove game") + "</button></td>";
			html += "</tr>";
		});

		html += "</tbody></table>";
		$body.html(html);
	}

	function bindQuizActions() {
		$(document).on("click", ".wsh-quiz-view-results", function (event) {
			event.preventDefault();
			var quizId = quizIdFrom($(this));
			var $modal = $("#wsh-quiz-results-modal");
			if (!quizId || !$modal.length) {
				return;
			}

			$modal.attr("data-quiz-id", quizId);
			$modal.prop("hidden", false);
			$modal.find(".wsh-quiz-modal__body").html("<p>…</p>");

			adminPost("admin_list_results", quizId)
				.done(function (response) {
					if (!response || !response.success) {
						throw new Error("bad");
					}
					renderResultsTable(response.data);
					if (response.data) {
						setPlayerCount(quizId, response.data.count, response.data.label);
					}
				})
				.fail(function () {
					$modal.find(".wsh-quiz-modal__body").html("<p>" + (config.i18n.error || "Error") + "</p>");
				});
		});

		$(document).on("click", ".wsh-quiz-remove-result", function (event) {
			event.preventDefault();
			var $button = $(this);
			var quizId = quizIdFrom($button);
			var resultId = parseInt($button.attr("data-result-id"), 10) || 0;
			if (!quizId || !resultId || !window.confirm(config.i18n.removeGameConfirm || "")) {
				return;
			}

			$button.prop("disabled", true);
			adminPost("admin_delete_result", quizId, { result_id: resultId })
				.done(function (response) {
					if (!response || !response.success) {
						window.alert((response && response.data && response.data.message) || config.i18n.error || "Error");
						$button.prop("disabled", false);
						return;
					}
					renderResultsTable(response.data);
					if (response.data) {
						setPlayerCount(quizId, response.data.count, response.data.label);
					}
				})
				.fail(function () {
					window.alert(config.i18n.error || "Error");
					$button.prop("disabled", false);
				});
		});

		$(document).on("click", ".wsh-quiz-clear-results", function (event) {
			event.preventDefault();
			var quizId = quizIdFrom($(this));
			if (!quizId || !window.confirm(config.i18n.clearResults || "")) {
				return;
			}

			adminPost("admin_clear_results", quizId)
				.done(function (response) {
					if (!response || !response.success) {
						window.alert(config.i18n.error || "Error");
						return;
					}
					setPlayerCount(quizId, 0, response.data && response.data.label);
					if (response.data && response.data.message) {
						window.alert(response.data.message);
					}
				})
				.fail(function () {
					window.alert(config.i18n.error || "Error");
				});
		});

		$(document).on("click", ".wsh-quiz-delete-quiz", function (event) {
			event.preventDefault();
			var quizId = quizIdFrom($(this));
			if (!quizId || !window.confirm(config.i18n.deleteQuiz || "")) {
				return;
			}

			adminPost("admin_delete_quiz", quizId)
				.done(function (response) {
					if (response && response.success && response.data && response.data.redirect) {
						window.location.href = response.data.redirect;
						return;
					}
					window.location.reload();
				})
				.fail(function () {
					window.alert(config.i18n.error || "Error");
				});
		});

		$(document).on("click", ".wsh-quiz-modal-close, .wsh-quiz-modal", function (event) {
			if (event.target === this || $(event.target).hasClass("wsh-quiz-modal-close")) {
				event.preventDefault();
				closeModal($(this).closest(".wsh-quiz-modal"));
			}
		});
	}
})(jQuery);
