<?php
/**
 * Appearance view — controls + live dark/light preview.
 *
 * @package SmartSupportChatbot
 * @var array $s        Settings.
 * @var array $business Business profile.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$saved = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.

$primary   = $s['primary_color'] ? $s['primary_color'] : '#b61615';
$org_name  = isset( $business['org_name'] ) && $business['org_name'] ? $business['org_name'] : get_bloginfo( 'name' );
$asst_name = $s['assistant_display_name'] ? $s['assistant_display_name'] : __( 'Nexa', 'nexachat-ai' );
$w_title   = $s['welcome_title'] ? $s['welcome_title'] : __( 'Hello! 👋', 'nexachat-ai' );
$w_text    = $s['welcome_text'] ? wp_strip_all_tags( $s['welcome_text'] ) : __( 'How can I help you today?', 'nexachat-ai' );
$disclaimer = $s['disclaimer'] ? $s['disclaimer'] : __( 'AI can make mistakes.', 'nexachat-ai' );
$dir        = in_array( $s['direction'], array( 'rtl', 'ltr' ), true ) ? $s['direction'] : 'rtl';
$theme_mode = in_array( $s['theme_mode'], array( 'light', 'dark', 'auto' ), true ) ? $s['theme_mode'] : 'light';
$pos        = 'left' === $s['position'] ? 'left' : 'right';
?>
<div class="ssc-page" dir="ltr">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Appearance', 'nexachat-ai' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Brand the assistant and watch the preview update live. Dark and light themes are fully supported.', 'nexachat-ai' ); ?></p>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Appearance saved.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>

	<form method="post" class="ssc-form">
		<?php wp_nonce_field( 'ssc_appearance' ); ?>
		<input type="hidden" name="ssc_appearance_save" value="1" />

		<div class="ssc-appear">
			<div class="ssc-appear__controls">
				<section class="ssc-card">
					<h2><?php esc_html_e( 'Essentials', 'nexachat-ai' ); ?></h2>
					<div class="ssc-grid ssc-grid--2">
						<div class="ssc-field">
							<label for="primary_color"><?php esc_html_e( 'Primary brand color', 'nexachat-ai' ); ?></label>
							<input id="primary_color" name="primary_color" type="color" value="<?php echo esc_attr( $primary ); ?>" class="ssc-color" />
						</div>
						<div class="ssc-field">
							<label for="theme_mode"><?php esc_html_e( 'Theme', 'nexachat-ai' ); ?></label>
							<select id="theme_mode" name="theme_mode">
								<option value="light" <?php selected( $theme_mode, 'light' ); ?>><?php esc_html_e( 'Light', 'nexachat-ai' ); ?></option>
								<option value="dark" <?php selected( $theme_mode, 'dark' ); ?>><?php esc_html_e( 'Dark', 'nexachat-ai' ); ?></option>
								<option value="auto" <?php selected( $theme_mode, 'auto' ); ?>><?php esc_html_e( 'Match visitor device', 'nexachat-ai' ); ?></option>
							</select>
						</div>
						<div class="ssc-field">
							<label for="position"><?php esc_html_e( 'Floating button position', 'nexachat-ai' ); ?></label>
							<select id="position" name="position">
								<option value="right" <?php selected( $pos, 'right' ); ?>><?php esc_html_e( 'Bottom right', 'nexachat-ai' ); ?></option>
								<option value="left" <?php selected( $pos, 'left' ); ?>><?php esc_html_e( 'Bottom left', 'nexachat-ai' ); ?></option>
							</select>
						</div>
						<div class="ssc-field">
							<label for="launcher_style"><?php esc_html_e( 'Assistant look', 'nexachat-ai' ); ?></label>
							<select id="launcher_style" name="launcher_style">
								<option value="mascot" <?php selected( $s['launcher_style'], 'mascot' ); ?>><?php esc_html_e( 'Animated character (brand colour)', 'nexachat-ai' ); ?></option>
								<option value="icon" <?php selected( $s['launcher_style'], 'icon' ); ?>><?php esc_html_e( 'Simple chat icon', 'nexachat-ai' ); ?></option>
							</select>
							<p class="ssc-field__hint"><?php esc_html_e( 'The character breathes, blinks, follows the pointer and looks up while it thinks. A custom button image or avatar below always wins.', 'nexachat-ai' ); ?></p>
						</div>
						<div class="ssc-field">
							<label for="direction"><?php esc_html_e( 'Chat text direction', 'nexachat-ai' ); ?></label>
							<select id="direction" name="direction">
								<option value="rtl" <?php selected( $dir, 'rtl' ); ?>><?php esc_html_e( 'RTL (Persian/Arabic)', 'nexachat-ai' ); ?></option>
								<option value="ltr" <?php selected( $dir, 'ltr' ); ?>><?php esc_html_e( 'LTR (Latin)', 'nexachat-ai' ); ?></option>
								<option value="auto" <?php selected( $s['direction'], 'auto' ); ?>><?php esc_html_e( 'Auto (follow site language)', 'nexachat-ai' ); ?></option>
							</select>
						</div>
						<div class="ssc-field">
							<label for="widget_language"><?php esc_html_e( 'Chat widget language', 'nexachat-ai' ); ?></label>
							<select id="widget_language" name="widget_language" aria-describedby="widget_language_hint">
								<?php $wl = isset( $s['widget_language'] ) ? $s['widget_language'] : 'auto'; ?>
								<option value="auto" <?php selected( $wl, 'auto' ); ?>><?php esc_html_e( 'Automatic (answer language, else site language)', 'nexachat-ai' ); ?></option>
								<option value="fa_IR" <?php selected( $wl, 'fa_IR' ); ?>>فارسی (Persian)</option>
								<option value="en_US" <?php selected( $wl, 'en_US' ); ?>>English</option>
							</select>
							<p class="ssc-field__hint" id="widget_language_hint"><?php esc_html_e( 'Buttons, forms and messages visitors see in the chat, independent of the WordPress language. Saved changes apply to the preview after reload.', 'nexachat-ai' ); ?></p>
						</div>
					</div>
					<div class="ssc-field">
						<label for="assistant_display_name"><?php esc_html_e( 'Assistant display name', 'nexachat-ai' ); ?></label>
						<input id="assistant_display_name" name="assistant_display_name" type="text" value="<?php echo esc_attr( $s['assistant_display_name'] ); ?>" placeholder="<?php echo esc_attr( $asst_name ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="avatar_url"><?php esc_html_e( 'Avatar / custom icon URL', 'nexachat-ai' ); ?></label>
						<input id="avatar_url" name="avatar_url" type="url" dir="ltr" value="<?php echo esc_attr( $s['avatar_url'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="welcome_title"><?php esc_html_e( 'Welcome title', 'nexachat-ai' ); ?></label>
						<input id="welcome_title" name="welcome_title" type="text" value="<?php echo esc_attr( $s['welcome_title'] ); ?>" placeholder="<?php echo esc_attr( $w_title ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="welcome_text"><?php esc_html_e( 'Welcome message', 'nexachat-ai' ); ?></label>
						<textarea id="welcome_text" name="welcome_text" rows="2" placeholder="<?php echo esc_attr( $w_text ); ?>"><?php echo esc_textarea( $s['welcome_text'] ); ?></textarea>
					</div>
					<div class="ssc-field">
						<label for="disclaimer"><?php esc_html_e( 'Disclaimer (shown under the chat)', 'nexachat-ai' ); ?></label>
						<input id="disclaimer" name="disclaimer" type="text" value="<?php echo esc_attr( $s['disclaimer'] ); ?>" placeholder="<?php echo esc_attr( $disclaimer ); ?>" />
					</div>
				</section>

				<details class="ssc-card ssc-details">
					<summary><?php esc_html_e( 'Advanced styling', 'nexachat-ai' ); ?></summary>
					<div class="ssc-grid ssc-grid--2">
						<div class="ssc-field">
							<label for="font_family"><?php esc_html_e( 'Font', 'nexachat-ai' ); ?></label>
							<select id="font_family" name="font_family">
								<option value="vazirmatn" <?php selected( $s['font_family'], 'vazirmatn' ); ?>><?php esc_html_e( 'Vazirmatn (Persian)', 'nexachat-ai' ); ?></option>
								<option value="inter" <?php selected( $s['font_family'], 'inter' ); ?>>Inter</option>
								<option value="roboto" <?php selected( $s['font_family'], 'roboto' ); ?>>Roboto</option>
								<option value="system" <?php selected( $s['font_family'], 'system' ); ?>><?php esc_html_e( 'System default', 'nexachat-ai' ); ?></option>
								<option value="custom" <?php selected( $s['font_family'], 'custom' ); ?>><?php esc_html_e( 'Custom…', 'nexachat-ai' ); ?></option>
							</select>
						</div>
						<div class="ssc-field">
							<label for="font_size"><?php esc_html_e( 'Base font size (px)', 'nexachat-ai' ); ?></label>
							<input id="font_size" name="font_size" type="number" min="12" max="20" value="<?php echo esc_attr( (string) $s['font_size'] ); ?>" />
						</div>
						<div class="ssc-field" data-show-when="font_family=custom">
							<label for="font_name"><?php esc_html_e( 'Custom font name', 'nexachat-ai' ); ?></label>
							<input id="font_name" name="font_name" type="text" dir="ltr" value="<?php echo esc_attr( $s['font_name'] ); ?>" />
						</div>
						<div class="ssc-field" data-show-when="font_family=custom">
							<label for="font_url"><?php esc_html_e( 'Custom font stylesheet URL', 'nexachat-ai' ); ?></label>
							<input id="font_url" name="font_url" type="url" dir="ltr" value="<?php echo esc_attr( $s['font_url'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="window_width"><?php esc_html_e( 'Chat window width (px)', 'nexachat-ai' ); ?></label>
							<input id="window_width" name="window_width" type="number" min="320" max="520" value="<?php echo esc_attr( (string) $s['window_width'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="window_radius"><?php esc_html_e( 'Window corner radius (px)', 'nexachat-ai' ); ?></label>
							<input id="window_radius" name="window_radius" type="number" min="0" max="32" value="<?php echo esc_attr( (string) $s['window_radius'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="bubble_radius"><?php esc_html_e( 'Message bubble radius (px)', 'nexachat-ai' ); ?></label>
							<input id="bubble_radius" name="bubble_radius" type="number" min="0" max="24" value="<?php echo esc_attr( (string) $s['bubble_radius'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="user_bubble_color"><?php esc_html_e( 'Visitor bubble color', 'nexachat-ai' ); ?></label>
							<input id="user_bubble_color" name="user_bubble_color" type="color" value="<?php echo esc_attr( $s['user_bubble_color'] ? $s['user_bubble_color'] : $primary ); ?>" class="ssc-color" />
						</div>
						<div class="ssc-field">
							<label for="bot_bubble_color"><?php esc_html_e( 'Assistant bubble color', 'nexachat-ai' ); ?></label>
							<input id="bot_bubble_color" name="bot_bubble_color" type="color" value="<?php echo esc_attr( $s['bot_bubble_color'] ? $s['bot_bubble_color'] : '#ffffff' ); ?>" class="ssc-color" />
						</div>
						<div class="ssc-field">
							<label for="launcher_size"><?php esc_html_e( 'Floating button size (px)', 'nexachat-ai' ); ?></label>
							<input id="launcher_size" name="launcher_size" type="number" min="48" max="72" value="<?php echo esc_attr( (string) $s['launcher_size'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="launcher_icon_url"><?php esc_html_e( 'Floating button custom image (optional)', 'nexachat-ai' ); ?></label>
							<input id="launcher_icon_url" name="launcher_icon_url" type="url" dir="ltr" value="<?php echo esc_attr( $s['launcher_icon_url'] ); ?>" />
						</div>
					</div>
				</details>
			</div>

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
		</div>

		<div class="ssc-form__actions">
			<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Save appearance', 'nexachat-ai' ); ?></button>
		</div>
	</form>
</div>
