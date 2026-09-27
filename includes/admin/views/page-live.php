<?php
/**
 * Live chat inbox shell (filled by assets/js/live-inbox.js).
 *
 * @package SmartSupportChatbot
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ssc-page ssc-page--wide">
		<header class="ssc-page__head">
			<div>
				<h1><?php esc_html_e( 'Live chat', 'nexachat-ai' ); ?></h1>
				<p class="ssc-page__sub"><?php esc_html_e( 'Conversations appear here as they happen. Take over from the assistant at any time; while you are in the chat, the assistant stays quiet.', 'nexachat-ai' ); ?></p>
			</div>
			<div class="ssc-live__tools">
				<label class="ssc-switch">
					<input type="checkbox" id="ssc-live-available" checked />
					<span class="ssc-switch__track" aria-hidden="true"></span>
					<span class="ssc-switch__label" id="ssc-live-available-label"><?php esc_html_e( 'Available', 'nexachat-ai' ); ?></span>
				</label>
				<div id="ssc-live-link"></div>
			</div>
		</header>
		<noscript><div class="ssc-notice ssc-notice--error"><?php esc_html_e( 'The live inbox needs JavaScript.', 'nexachat-ai' ); ?></div></noscript>
		<div class="ssc-live" id="ssc-live">
			<aside class="ssc-live__list" aria-label="<?php esc_attr_e( 'Conversations', 'nexachat-ai' ); ?>">
				<div class="ssc-live__tabs" role="tablist">
					<button type="button" role="tab" class="is-active" data-scope="open" aria-selected="true"><?php esc_html_e( 'Open', 'nexachat-ai' ); ?></button>
					<button type="button" role="tab" data-scope="closed" aria-selected="false"><?php esc_html_e( 'Closed', 'nexachat-ai' ); ?></button>
				</div>
				<ul class="ssc-live__threads" id="ssc-live-threads" aria-live="polite"></ul>
			</aside>
			<section class="ssc-live__chat" id="ssc-live-chat" aria-live="polite">
				<p class="ssc-live__empty"><?php esc_html_e( 'Choose a conversation on the left.', 'nexachat-ai' ); ?></p>
			</section>
		</div>
</div>
