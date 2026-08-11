/**
 * بلوک گوتنبرگ «دستیار هوشمند گفتگو».
 *
 * عمداً بدون JSX و بدون مرحلهٔ build نوشته شده (wp.element.createElement) تا
 * افزونه همچنان بدون node_modules قابل انتشار باشد؛ همسو با معماری فعلی.
 *
 * چون ویجت یک عنصر شناور در کل صفحه است، در ویرایشگر به‌جای رندر واقعی
 * یک placeholder نمایش داده می‌شود (رندر واقعی سمت سرور انجام می‌گیرد).
 *
 * @package SmartSupportChatbot
 */

( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el            = element.createElement;
	var __            = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType(
		'ssc/chatbot',
		{
			edit: function ( props ) {
				var position = props.attributes.position;

				var inspector = el(
					blockEditor.InspectorControls,
					{ key: 'inspector' },
					el(
						components.PanelBody,
						{ title: __( 'تنظیمات نمایش', 'smart-support-chatbot' ), initialOpen: true },
						el(
							components.SelectControl,
							{
								label: __( 'موقعیت دکمه', 'smart-support-chatbot' ),
								value: position,
								options: [
								{ label: __( 'پیش‌فرض افزونه', 'smart-support-chatbot' ), value: '' },
								{ label: __( 'پایین راست', 'smart-support-chatbot' ), value: 'right' },
								{ label: __( 'پایین چپ', 'smart-support-chatbot' ), value: 'left' }
								],
								onChange: function ( value ) {
									props.setAttributes( { position: value } );
								}
							}
						),
						el(
							'p',
							{ style: { marginTop: '12px', color: '#666', fontSize: '12px', lineHeight: 1.7 } },
							__( 'سایر تنظیمات (متن‌ها، رنگ، فونت، موتور هوش مصنوعی) از پنل «دستیار هوشمند ← تنظیمات» خوانده می‌شود.', 'smart-support-chatbot' )
						)
					)
				);

				var placeholder = el(
					components.Placeholder,
					{
						icon: 'format-chat',
						label: __( 'دستیار هوشمند گفتگو', 'smart-support-chatbot' ),
						instructions: __( 'ویجت چت به‌صورت شناور در صفحهٔ سایت نمایش داده می‌شود. برای دیدن نتیجه، صفحه را در سایت باز کنید.', 'smart-support-chatbot' )
					}
				);

				return el( 'div', useBlockProps(), [ inspector, el( 'div', { key: 'ph' }, placeholder ) ] );
			},

			// رندر سمت سرور انجام می‌شود (بلوک پویا).
			save: function () {
				return null;
			}
		}
	);
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
