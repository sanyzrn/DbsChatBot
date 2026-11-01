/**
 * Gutenberg block editor script (no build step: wp.element.createElement).
 */
(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var Placeholder = wp.components.Placeholder;
	// Texts come from the server (already translated); the English fallback is for a missing script.
	var text = window.sscBlockI18n || { label: 'Vazho', instructions: 'The assistant is configured in the Vazho admin menu. Publishing is controlled there.' };

	registerBlockType('ssc/chatbot', {
		edit: function () {
			var blockProps = useBlockProps();
			return el(
				'div',
				blockProps,
				el(
					Placeholder,
					{
						icon: 'format-chat',
						label: text.label,
						instructions: text.instructions
					}
				)
			);
		},
		save: function () {
			return null; // Dynamic block: server-side render.
		}
	});
})(window.wp);
