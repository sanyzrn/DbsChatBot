<?php
/**
 * The live preview panel: the real widget, repainted as the form changes
 * (assets/js/admin.js bindPreview reads the data attributes and the form
 * fields by id). Shared by the Appearance screen and the setup wizard.
 *
 * @package SmartSupportChatbot
 * @var array $s        Settings.
 * @var array $business Business profile.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$primary    = $s['primary_color'] ? $s['primary_color'] : '#b61615';
$org_name   = isset( $business['org_name'] ) && $business['org_name'] ? $business['org_name'] : get_bloginfo( 'name' );
$asst_name  = SSC_Frontend::display_name( $s );
$w_title    = $s['welcome_title'] ? $s['welcome_title'] : __( 'Hello! 👋', 'nexachat-ai' );
$w_text     = $s['welcome_text'] ? wp_strip_all_tags( $s['welcome_text'] ) : __( 'How can I help you today?', 'nexachat-ai' );
$disclaimer = $s['disclaimer'] ? $s['disclaimer'] : __( 'AI can make mistakes.', 'nexachat-ai' );
$dir        = in_array( $s['direction'], array( 'rtl', 'ltr' ), true ) ? $s['direction'] : 'rtl';
$theme_mode = in_array( $s['theme_mode'], array( 'light', 'dark', 'auto' ), true ) ? $s['theme_mode'] : 'light';
$pos        = 'left' === $s['position'] ? 'left' : 'right';
?>
<aside class="ssc-preview-panel" id="ssc-preview-panel"
				data-primary="<?php echo esc_attr( $primary ); ?>"
				data-theme-mode="<?php echo esc_attr( $theme_mode ); ?>"
				data-dir="<?php echo esc_attr( $dir ); ?>"
				data-dir-auto="<?php echo esc_attr( is_rtl() || in_array( substr( get_locale(), 0, 2 ), array( 'fa', 'ar', 'he', 'ur' ), true ) ? 'rtl' : 'ltr' ); ?>"
				data-pos="<?php echo esc_attr( $pos ); ?>"
				data-asst-name="<?php echo esc_attr( $asst_name ); ?>"
				data-org-name="<?php echo esc_attr( $org_name ); ?>"
				data-w-title="<?php echo esc_attr( $w_title ); ?>"
				data-w-text="<?php echo esc_attr( $w_text ); ?>"
				data-disclaimer="<?php echo esc_attr( $disclaimer ); ?>"
				data-avatar="<?php echo esc_attr( $s['avatar_url'] ); ?>"
				data-font-size="<?php echo esc_attr( (string) $s['font_size'] ); ?>"
				data-win-radius="<?php echo esc_attr( (string) $s['window_radius'] ); ?>"
				data-bubble-radius="<?php echo esc_attr( (string) $s['bubble_radius'] ); ?>"
				data-user-bubble="<?php echo esc_attr( $s['user_bubble_color'] ); ?>"
				data-bot-bubble="<?php echo esc_attr( $s['bot_bubble_color'] ); ?>"
				data-launcher-size="<?php echo esc_attr( (string) $s['launcher_size'] ); ?>"
			>
				<div class="ssc-preview-panel__bar">
					<strong><?php esc_html_e( 'Live preview', 'nexachat-ai' ); ?></strong>
					<div class="ssc-preview-toggle" role="group" aria-label="<?php esc_attr_e( 'Preview theme', 'nexachat-ai' ); ?>">
						<button type="button" data-pv-theme="light" class="is-on"><?php esc_html_e( 'Light', 'nexachat-ai' ); ?></button>
						<button type="button" data-pv-theme="dark"><?php esc_html_e( 'Dark', 'nexachat-ai' ); ?></button>
					</div>
				</div>

				<div class="ssc-preview-stage" id="ssc-preview-stage">
					<?php // The real widget mounts here (chatbot.js preview mode, admin-only preview endpoint). ?>
					<div id="ssc-live-preview"></div>
				</div>

				<p class="ssc-preview-panel__hint"><?php esc_html_e( 'This is the real assistant: changes apply instantly and you can chat with it (answers use the saved AI connection). Save when you are happy.', 'nexachat-ai' ); ?></p>
			</aside>
