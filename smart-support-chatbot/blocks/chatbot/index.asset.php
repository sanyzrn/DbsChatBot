<?php
/**
 * وابستگی‌های اسکریپت ویرایشگر بلوک.
 *
 * وردپرس این فایل را کنار index.js می‌خواند تا هنگام register_block_type
 * اسکریپت با وابستگی‌های درست ثبت شود. بدون این فایل، اسکریپت بدون وابستگی
 * ثبت می‌شود و ممکن است پیش از آمادهٔ شدن wp.blockEditor اجرا شود.
 *
 * (معادل دستیِ خروجی @wordpress/scripts — چون این افزونه عمداً بدون build منتشر می‌شود.)
 *
 * @package SmartSupportChatbot
 */

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-element',
		'wp-block-editor',
		'wp-components',
		'wp-i18n',
	),
	'version'      => SSC_CHATBOT_VERSION,
);
