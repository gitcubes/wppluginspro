(function () {
	"use strict";

	var i18n = (window.wshQuizPlayer && window.wshQuizPlayer.i18n) || {};
	var ajaxUrl = (window.wshQuizPlayer && window.wshQuizPlayer.ajaxUrl) || "";
	var nonce = (window.wshQuizPlayer && window.wshQuizPlayer.nonce) || "";

	function parseQuiz(root) {
		try {
			return JSON.parse(root.getAttribute("data-quiz") || "{}");
		} catch (error) {
			return null;
		}
	}

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (typeof text === "string") {
			node.textContent = text;
		}
		return node;
	}

	function formatTime(total) {
		var seconds = Math.max(0, parseInt(total, 10) || 0);
		var minutes = Math.floor(seconds / 60);
		var rest = seconds % 60;
		return (minutes < 10 ? "0" : "") + minutes + ":" + (rest < 10 ? "0" : "") + rest;
	}

	function formatDuration(total) {
		var seconds = Math.max(0, parseInt(total, 10) || 0);
		var minutes = Math.floor(seconds / 60);
		var rest = seconds % 60;
		return minutes + ":" + (rest < 10 ? "0" : "") + rest;
	}

	function Player(root) {
		this.root = root;
		this.data = parseQuiz(root);
		this.index = 0;
		this.score = 0;
		this.max = 0;
		this.answered = false;
		this.started = false;
		this.finished = false;
		this.marks = [];
		this.answers = [];
		this.remaining = 0;
		this.startedAt = 0;
		this.timerId = null;
		this.player = { first_name: "", last_name: "", email: "" };

		if (!this.data || !this.data.questions || !this.data.questions.length) {
			return;
		}

		this.data.questions.forEach(function (question) {
			this.max += question.score || 1;
		}, this);

		if (this.data.player) {
			this.player = this.data.player;
		}

		this.gate = root.querySelector(".wsh-quiz__gate");
		this.play = root.querySelector(".wsh-quiz__play");
		this.progressText = root.querySelector(".wsh-quiz__progress-text");
		this.timerNode = root.querySelector(".wsh-quiz__timer");
		this.bar = root.querySelector(".wsh-quiz__bar");
		this.barFill = root.querySelector(".wsh-quiz__bar-fill");
		this.media = root.querySelector(".wsh-quiz__media");
		this.question = root.querySelector(".wsh-quiz__question");
		this.hint = root.querySelector(".wsh-quiz__hint");
		this.hintBtn = root.querySelector(".wsh-quiz__hint-btn");
		this.options = root.querySelector(".wsh-quiz__options");
		this.feedback = root.querySelector(".wsh-quiz__feedback");
		this.next = root.querySelector(".wsh-quiz__next");
		this.skip = root.querySelector(".wsh-quiz__skip");
		this.result = root.querySelector(".wsh-quiz__result");
		this.scoreNode = root.querySelector(".wsh-quiz__score-num") || root.querySelector(".wsh-quiz__score");
		this.scoreCaption = root.querySelector(".wsh-quiz__score-caption");
		this.marksNode = root.querySelector(".wsh-quiz__marks");
		this.rankNode = root.querySelector(".wsh-quiz__rank");
		this.timeNode = root.querySelector(".wsh-quiz__time");
		this.trophy = root.querySelector(".wsh-quiz__trophy");
		this.board = root.querySelector(".wsh-quiz__board");
		this.boardList = root.querySelector(".wsh-quiz__board-list");
		this.restart = root.querySelector(".wsh-quiz__restart");
		this.giveup = root.querySelector(".wsh-quiz__giveup");
		this.body = root.querySelector(".wsh-quiz__body");
		this.footer = root.querySelector(".wsh-quiz__footer");
		this.form = root.querySelector(".wsh-quiz__player-form");

		if (this.next) {
			this.next.addEventListener("click", this.onNext.bind(this));
		}
		if (this.skip) {
			this.skip.addEventListener("click", this.onSkip.bind(this));
		}
		if (this.hintBtn) {
			this.hintBtn.addEventListener("click", this.toggleHint.bind(this));
		}
		if (this.restart) {
			this.restart.addEventListener("click", this.onRestart.bind(this));
		}
		if (this.giveup) {
			this.giveup.addEventListener("click", this.onGiveUp.bind(this));
		}
		root.querySelectorAll(".wsh-quiz__share").forEach(function (button) {
			button.addEventListener("click", this.onShare.bind(this));
		}, this);
		if (this.form) {
			this.form.addEventListener("submit", this.onGate.bind(this));
		}

		this.deferred = !!this.root.closest(".wsh-quiz-list__embed");
		if (this.data.alreadyPlayed) {
			this.showAlreadyPlayed();
			return;
		}
		if (this.data.available && !this.needsGate() && !this.deferred) {
			this.startPlay();
		}
	}

	Player.prototype.needsGate = function () {
		if (this.data.loggedIn) {
			return false;
		}
		return this.data.entryMode === "guest" || this.data.entryMode === "account";
	};

	Player.prototype.current = function () {
		return this.data.questions[this.index];
	};

	Player.prototype.showAlreadyPlayed = function (message) {
		if (this.gate) {
			this.gate.hidden = true;
		}
		if (this.play) {
			this.play.hidden = true;
		}
		this.root.classList.remove("is-playing", "is-gate");
		this.root.classList.add("is-played");

		var box = this.root.querySelector(".wsh-quiz__played");
		if (!box) {
			box = document.createElement("div");
			box.className = "wsh-quiz__played";
			box.appendChild(el("p", "wsh-quiz__played-title", ""));
			box.appendChild(
				el(
					"p",
					"wsh-quiz__played-rule",
					i18n.alreadyPlayedRule ||
						"This quiz can be played only once. Logged-in players are recognized by their account. Guests are recognized by the email they entered and this browser."
				)
			);
			var stage = this.root.querySelector(".wsh-quiz__stage");
			if (stage) {
				stage.insertBefore(box, stage.firstChild);
			}
		}

		var title = box.querySelector(".wsh-quiz__played-title");
		if (title) {
			title.textContent = message || i18n.alreadyPlayed || "You have already played this quiz.";
		}
		box.hidden = false;
	};

	Player.prototype.onGate = function (event) {
		event.preventDefault();
		var form = event.currentTarget;
		var self = this;
		this.player = {
			first_name: form.first_name.value.trim(),
			last_name: form.last_name.value.trim(),
			email: form.email.value.trim(),
		};
		if (!this.player.first_name || !this.player.last_name || !this.player.email) {
			return;
		}

		if (this.data.allowReplay) {
			if (this.gate) {
				this.gate.hidden = true;
			}
			this.startPlay();
			return;
		}

		var body = new FormData();
		body.append("action", "wsh_quiz_can_play");
		body.append("nonce", nonce);
		body.append("quiz_id", String(this.data.id));
		body.append("first_name", this.player.first_name);
		body.append("last_name", this.player.last_name);
		body.append("email", this.player.email);

		fetch(ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			body: body,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || !payload.success) {
					self.showAlreadyPlayed(payload && payload.data && payload.data.message);
					return;
				}
				if (self.gate) {
					self.gate.hidden = true;
				}
				self.startPlay();
			})
			.catch(function () {
				self.showAlreadyPlayed();
			});
	};

	Player.prototype.startPlay = function () {
		if (!this.play) {
			return;
		}
		this.clearTimer();
		this.started = true;
		this.finished = false;
		this.answered = false;
		this.index = 0;
		this.score = 0;
		this.marks = [];
		this.answers = [];
		this.startedAt = Date.now();
		this.remaining = parseInt(this.data.timer, 10) || 0;
		this.play.hidden = false;
		this.root.classList.add("is-playing");
		this.root.classList.remove("is-finished");
		if (this.trophy) {
			this.trophy.hidden = true;
			this.trophy.classList.remove("is-on", "is-gold", "is-silver", "is-bronze");
		}
		if (this.result) {
			this.result.hidden = true;
		}
		if (this.bar) {
			this.bar.hidden = false;
		}
		if (this.timerNode) {
			if (this.remaining > 0) {
				this.timerNode.hidden = false;
				this.timerNode.textContent = formatTime(this.remaining);
				this.tick();
				this.timerId = window.setInterval(this.tick.bind(this), 1000);
			} else {
				this.timerNode.hidden = true;
				this.timerNode.textContent = "00:00";
			}
		}
		this.showQuestion();
	};

	Player.prototype.restartFresh = function () {
		if (this.data.alreadyPlayed) {
			this.clearTimer();
			this.showAlreadyPlayed();
			return;
		}
		if (this.finished && !this.data.allowReplay) {
			this.clearTimer();
			return;
		}
		this.started = false;
		this.startPlay();
	};

	Player.prototype.tick = function () {
		if (!this.timerNode) {
			return;
		}
		this.timerNode.textContent = formatTime(this.remaining);
		if (this.remaining <= 0) {
			this.finish();
			return;
		}
		this.remaining -= 1;
	};

	Player.prototype.clearTimer = function () {
		if (this.timerId) {
			window.clearInterval(this.timerId);
			this.timerId = null;
		}
	};

	Player.prototype.showQuestion = function () {
		var question = this.current();
		this.answered = false;
		this.root.classList.remove("is-busy");
		if (this.body) {
			this.body.hidden = false;
		}
		if (this.footer) {
			this.footer.hidden = false;
		}
		this.feedback.hidden = true;
		this.feedback.textContent = "";
		this.feedback.classList.remove("is-ok", "is-miss");
		this.next.hidden = true;
		if (this.skip) {
			this.skip.hidden = false;
		}
		this.progressText.textContent = this.index + 1 + "/" + this.data.questions.length;
		if (this.barFill) {
			this.barFill.style.width =
				((this.index + 1) / this.data.questions.length) * 100 + "%";
		}
		this.root.setAttribute("data-qtype", question.type || "choice");
		this.question.textContent = question.question || "";
		this.setHint(question.hint || "");

		this.renderMedia(question);
		this.renderOptions(question);
	};

	Player.prototype.setHint = function (text) {
		var hint = (text || "").trim();
		if (this.hint) {
			this.hint.hidden = true;
			this.hint.textContent = hint;
		}
		if (this.hintBtn) {
			this.hintBtn.hidden = !hint;
			this.hintBtn.setAttribute("aria-expanded", "false");
			this.hintBtn.setAttribute("aria-label", i18n.hint || "Hint");
		}
	};

	Player.prototype.toggleHint = function () {
		if (!this.hint || !this.hint.textContent) {
			return;
		}
		this.hint.hidden = !this.hint.hidden;
		if (this.hintBtn) {
			this.hintBtn.setAttribute("aria-expanded", this.hint.hidden ? "false" : "true");
		}
	};

	Player.prototype.renderMedia = function (question) {
		this.media.innerHTML = "";
		this.media.hidden = true;

		if (question.media_type === "image" && question.media_url) {
			var image = el("img");
			image.src = question.media_url;
			image.alt = "";
			this.media.appendChild(image);
			this.media.hidden = false;
		}

		if (question.media_type === "video" && question.media_url) {
			var iframe = document.createElement("iframe");
			iframe.src = this.youtubeEmbed(question.media_url);
			iframe.setAttribute("allowfullscreen", "allowfullscreen");
			iframe.setAttribute("title", question.question || "Quiz video");
			this.media.appendChild(iframe);
			this.media.hidden = false;
		}

		if (question.media_source && !this.media.hidden) {
			this.media.appendChild(el("span", "wsh-quiz__media-source", question.media_source));
		}
	};

	Player.prototype.youtubeEmbed = function (url) {
		var id = "";
		var shortMatch = url.match(/youtu\.be\/([A-Za-z0-9_-]+)/);
		var queryMatch = url.match(/[?&]v=([A-Za-z0-9_-]+)/);
		var embedMatch = url.match(/youtube\.com\/embed\/([A-Za-z0-9_-]+)/);
		if (shortMatch) {
			id = shortMatch[1];
		} else if (queryMatch) {
			id = queryMatch[1];
		} else if (embedMatch) {
			id = embedMatch[1];
		}
		return id ? "https://www.youtube.com/embed/" + id : url;
	};

	Player.prototype.renderOptions = function (question) {
		var self = this;
		this.options.innerHTML = "";

		(question.options || []).forEach(function (option, index) {
			if (!option.text && !option.media_url) {
				return;
			}

			var button = el("button", "wsh-quiz__option");
			button.type = "button";
			button.setAttribute("data-index", String(index));

			if (question.type === "image_choice" && option.media_url) {
				button.classList.add("is-image");
				var image = el("img");
				image.src = option.media_url;
				image.alt = option.text || "";
				button.appendChild(image);
				if (option.text) {
					button.appendChild(el("span", "", option.text));
				}
			} else {
				button.textContent = option.text || "";
			}

			button.addEventListener("click", function () {
				self.submit(index);
			});

			self.options.appendChild(button);
		});
	};

	Player.prototype.submit = function (optionIndex) {
		if (this.answered || this.finished) {
			return;
		}

		var question = this.current();
		var self = this;
		this.answered = true;
		this.root.classList.add("is-busy");

		var body = new FormData();
		body.append("action", "wsh_quiz_answer");
		body.append("nonce", nonce);
		body.append("quiz_id", String(this.data.id));
		body.append("question_id", question.id);
		body.append("option", String(optionIndex));

		fetch(ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			body: body,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || !payload.success) {
					throw new Error("bad");
				}
				self.mark(optionIndex, payload.data);
			})
			.catch(function () {
				self.answered = false;
				self.root.classList.remove("is-busy");
				self.feedback.hidden = false;
				self.feedback.textContent = i18n.error || "Error";
			});
	};

	Player.prototype.mark = function (selected, result) {
		this.score += result.score || 0;
		this.marks.push(result.correct ? 1 : 0);
		this.answers.push({ id: this.current().id, option: selected });

		var buttons = this.options.querySelectorAll(".wsh-quiz__option");
		buttons.forEach(function (button) {
			var index = parseInt(button.getAttribute("data-index"), 10);
			if (index === result.correctIndex) {
				button.classList.add("is-correct");
			} else if (index === selected) {
				button.classList.add("is-wrong");
			}
		});

		var label = result.correct ? i18n.correct : i18n.wrong;
		this.feedback.hidden = false;
		this.feedback.classList.toggle("is-ok", !!result.correct);
		this.feedback.classList.toggle("is-miss", !result.correct);
		this.feedback.textContent = result.explanation
			? label + ". " + result.explanation
			: label;

		this.next.hidden = false;
		this.next.textContent =
			this.index + 1 >= this.data.questions.length
				? i18n.finish || "See results"
				: i18n.next || "Next";
		if (this.skip) {
			this.skip.hidden = true;
		}
	};

	Player.prototype.onSkip = function () {
		if (this.answered || this.finished) {
			return;
		}

		this.answered = true;
		this.marks.push(0);
		this.answers.push({ id: this.current().id, option: -1 });
		this.onNext();
	};

	Player.prototype.onNext = function () {
		if (this.index + 1 >= this.data.questions.length) {
			this.finish();
			return;
		}
		this.index += 1;
		this.showQuestion();
	};

	Player.prototype.onGiveUp = function () {
		if (window.confirm(i18n.giveUpConfirm || "Give up this quiz?")) {
			this.finish();
		}
	};

	Player.prototype.duration = function () {
		if (!this.startedAt) {
			return 0;
		}
		return Math.max(0, Math.round((Date.now() - this.startedAt) / 1000));
	};

	Player.prototype.finish = function () {
		if (this.finished) {
			return;
		}
		this.finished = true;
		this.root.classList.remove("is-playing");
		this.root.classList.add("is-finished");
		this.clearTimer();
		if (this.play) {
			this.play.hidden = true;
		}

		var self = this;
		var body = new FormData();
		body.append("action", "wsh_quiz_finish");
		body.append("nonce", nonce);
		body.append("quiz_id", String(this.data.id));
		body.append("duration", String(this.duration()));
		body.append("answers", JSON.stringify(this.answers));
		body.append("first_name", this.player.first_name || "");
		body.append("last_name", this.player.last_name || "");
		body.append("email", this.player.email || "");

		fetch(ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			body: body,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				self.showResult(payload && payload.success ? payload.data : null);
			})
			.catch(function () {
				self.showResult(null);
			});
	};

	Player.prototype.placeLabel = function (rank) {
		var place = parseInt(rank, 10);
		if (place === 1) {
			return i18n.place1 || "1st place";
		}
		if (place === 2) {
			return i18n.place2 || "2nd place";
		}
		if (place === 3) {
			return i18n.place3 || "3rd place";
		}
		return (i18n.rank || "Your rank") + " " + place;
	};

	Player.prototype.celebrateRank = function (rank) {
		if (!this.trophy) {
			return;
		}

		this.trophy.hidden = true;
		this.trophy.classList.remove("is-on", "is-gold", "is-silver", "is-bronze");

		var place = parseInt(rank, 10);
		if (!(place >= 1 && place <= 3)) {
			return;
		}

		this.trophy.hidden = false;
		this.trophy.classList.add(place === 1 ? "is-gold" : place === 2 ? "is-silver" : "is-bronze");
		this.trophy.offsetWidth;
		this.trophy.classList.add("is-on");
	};

	Player.prototype.showResult = function (data) {
		var correct = data ? data.correct : this.marks.filter(Boolean).length;
		var total = data ? data.total : this.data.questions.length;
		var marks = data && data.marks ? data.marks : this.marks;

		this.result.hidden = false;
		if (this.scoreNode) {
			this.scoreNode.textContent = correct + "/" + total;
		}
		if (this.scoreCaption) {
			this.scoreCaption.textContent = i18n.correctCount || "correct";
		}

		this.marksNode.innerHTML = "";
		marks.forEach(function (mark) {
			this.marksNode.appendChild(el("span", mark ? "is-ok" : "is-miss", mark ? "✓" : "×"));
		}, this);

		if (data && data.rank && this.rankNode) {
			this.rankNode.hidden = false;
			this.rankNode.textContent = this.placeLabel(data.rank);
		}
		if (this.timeNode) {
			var seconds = data && typeof data.duration !== "undefined" ? data.duration : this.duration();
			this.timeNode.hidden = false;
			this.timeNode.textContent = formatDuration(seconds);
		}
		this.celebrateRank(data && data.rank);

		if (this.restart) {
			this.restart.textContent = i18n.restart || "Play again";
		}

		if (this.board && this.boardList && data && data.leaderboard && data.leaderboard.length) {
			this.board.hidden = false;
			this.boardList.innerHTML = "";
			data.leaderboard.forEach(function (row) {
				var item = el("li");
				var place = parseInt(row.rank, 10);
				var classes = [];
				if (data.rank && Number(row.rank) === Number(data.rank)) {
					classes.push("is-you");
				}
				if (place >= 1 && place <= 3) {
					classes.push("is-place-" + place);
				}
				item.className = classes.join(" ");
				item.appendChild(el("span", "wsh-quiz__board-rank", place + "."));
				item.appendChild(el("span", "wsh-quiz__board-name", row.name));
				item.appendChild(el("span", "wsh-quiz__board-time", formatDuration(row.duration)));
				item.appendChild(el("span", "wsh-quiz__board-score", row.score + "/" + row.max));
				this.boardList.appendChild(item);
			}, this);
		}

		this.lastShareText =
			(this.data.title || "Quiz") +
			" — " +
			correct +
			" / " +
			total +
			" " +
			(i18n.correctCount || "correct");
	};

	Player.prototype.onRestart = function () {
		if (!this.data.allowReplay) {
			return;
		}
		this.restartFresh();
	};

	Player.prototype.onShare = function (event) {
		var network = event.currentTarget.getAttribute("data-network") || "x";
		var text = this.lastShareText || this.data.title || "Quiz";
		var url = window.location.href;

		if (network === "copy") {
			if (navigator.clipboard) {
				navigator.clipboard.writeText(text + " " + url);
			}
			return;
		}

		var shareUrl = url;
		if (network === "facebook") {
			shareUrl = "https://www.facebook.com/sharer/sharer.php?u=" + encodeURIComponent(url);
		} else if (network === "whatsapp") {
			shareUrl = "https://wa.me/?text=" + encodeURIComponent(text + " " + url);
		} else {
			shareUrl =
				"https://twitter.com/intent/tweet?text=" + encodeURIComponent(text + " " + url);
		}

		window.open(shareUrl, "_blank", "noopener");
	};

	Player.prototype.reveal = function () {
		if (this.data.alreadyPlayed) {
			this.showAlreadyPlayed();
			return;
		}
		if (this.data.available && !this.needsGate()) {
			this.restartFresh();
		}
	};

	var players = [];

	document.addEventListener("DOMContentLoaded", function () {
		document.querySelectorAll(".wsh-quiz").forEach(function (root) {
			players.push(new Player(root));
		});

		document.querySelectorAll(".wsh-quiz-list__play").forEach(function (button) {
			button.addEventListener("click", function () {
				var item = button.closest(".wsh-quiz-list__item");
				if (!item) {
					return;
				}
				var embed = item.querySelector(".wsh-quiz-list__embed");
				if (embed) {
					embed.hidden = false;
					embed.scrollIntoView({ behavior: "smooth", block: "start" });
					var root = embed.querySelector(".wsh-quiz");
					players.forEach(function (player) {
						if (player.root === root) {
							player.reveal();
						}
					});
				}
			});
		});
	});

	window.addEventListener("pageshow", function (event) {
		if (!event.persisted) {
			return;
		}
		players.forEach(function (player) {
			player.restartFresh();
		});
	});
})();
