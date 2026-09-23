(function (wp) {
	"use strict";

	if (!wp || !wp.blocks) {
		return;
	}

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var SelectControl = wp.components.SelectControl;
	var PanelBody = wp.components.PanelBody;
	var quizzes = (window.wshQuizBlock && window.wshQuizBlock.quizzes) || [];
	var __ = wp.i18n.__;

	registerBlockType("wsh-quiz/embed", {
		title: __("WSH Quiz", "wsh-quiz"),
		icon: "forms",
		category: "widgets",
		attributes: {
			quizId: {
				type: "number",
				default: 0,
			},
		},
		edit: function (props) {
			var quizId = props.attributes.quizId || 0;
			var selected = quizzes.find(function (item) {
				return Number(item.value) === Number(quizId);
			});

			return el(
				"div",
				useBlockProps({ className: "wsh-quiz-block-editor" }),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __("Quiz", "wsh-quiz"), initialOpen: true },
						el(SelectControl, {
							label: __("Select a quiz", "wsh-quiz"),
							value: quizId,
							options: quizzes,
							onChange: function (value) {
								props.setAttributes({ quizId: parseInt(value, 10) || 0 });
							},
						})
					)
				),
				el(
					"p",
					null,
					quizId
						? __("Embedded quiz:", "wsh-quiz") +
								" " +
								(selected ? selected.label : "#" + quizId)
						: __("Select a quiz in the block settings.", "wsh-quiz")
				)
			);
		},
		save: function () {
			return null;
		},
	});
})(window.wp);
