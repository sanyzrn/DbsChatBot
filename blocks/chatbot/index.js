/**
 * Gutenberg block editor script (no build step: wp.element.createElement).
 */
(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var Placeholder = wp.components.Placeholder;

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
						label: __('Smart Assistant Chatbot', 'smart-support-chatbot'),
						instructions: __('The assistant is configured in the “Smart Assistant” admin menu. Publishing is controlled there.', 'smart-support-chatbot')
					}
				)
			);
		},
		save: function () {
			return null; // Dynamic block: server-side render.
		}
	});
})(window.wp);
