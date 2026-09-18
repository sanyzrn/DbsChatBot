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
$asst_name = $s['assistant_display_name'] ? $s['assistant_display_name'] : __( 'Nexa', 'smart-support-chatbot' );
$w_title   = $s['welcome_title'] ? $s['welcome_title'] : __( 'Hello! 👋', 'smart-support-chatbot' );
$w_text    = $s['welcome_text'] ? wp_strip_all_tags( $s['welcome_text'] ) : __( 'How can I help you today?', 'smart-support-chatbot' );
$disclaimer = $s['disclaimer'] ? $s['disclaimer'] : __( 'AI can make mistakes.', 'smart-support-chatbot' );
$dir        = in_array( $s['direction'], array( 'rtl', 'ltr' ), true ) ? $s['direction'] : 'rtl';
$theme_mode = in_array( $s['theme_mode'], array( 'light', 'dark', 'auto' ), true ) ? $s['theme_mode'] : 'light';
$pos        = 'left' === $s['position'] ? 'left' : 'right';
?>
<div class="ssc-page" dir="ltr">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Appearance', 'smart-support-chatbot' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Brand the assistant and watch the preview update live. Dark and light themes are fully supported.', 'smart-support-chatbot' ); ?></p>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Appearance saved.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>

	<form method="post" class="ssc-form">
		<?php wp_nonce_field( 'ssc_appearance' ); ?>
		<input type="hidden" name="ssc_appearance_save" value="1" />

		<div class="ssc-appear">
			<div class="ssc-appear__controls">
				<section class="ssc-card">
					<h2><?php esc_html_e( 'Essentials', 'smart-support-chatbot' ); ?></h2>
					<div class="ssc-grid ssc-grid--2">
						<div class="ssc-field">
							<label for="primary_color"><?php esc_html_e( 'Primary brand color', 'smart-support-chatbot' ); ?></label>
							<input id="primary_color" name="primary_color" type="color" value="<?php echo esc_attr( $primary ); ?>" class="ssc-color" />
						</div>
						<div class="ssc-field">
							<label for="theme_mode"><?php esc_html_e( 'Theme', 'smart-support-chatbot' ); ?></label>
							<select id="theme_mode" name="theme_mode">
								<option value="light" <?php selected( $theme_mode, 'light' ); ?>><?php esc_html_e( 'Light', 'smart-support-chatbot' ); ?></option>
								<option value="dark" <?php selected( $theme_mode, 'dark' ); ?>><?php esc_html_e( 'Dark', 'smart-support-chatbot' ); ?></option>
								<option value="auto" <?php selected( $theme_mode, 'auto' ); ?>><?php esc_html_e( 'Match visitor device', 'smart-support-chatbot' ); ?></option>
							</select>
						</div>
						<div class="ssc-field">
							<label for="position"><?php esc_html_e( 'Floating button position', 'smart-support-chatbot' ); ?></label>
							<select id="position" name="position">
								<option value="right" <?php selected( $pos, 'right' ); ?>><?php esc_html_e( 'Bottom right', 'smart-support-chatbot' ); ?></option>
								<option value="left" <?php selected( $pos, 'left' ); ?>><?php esc_html_e( 'Bottom left', 'smart-support-chatbot' ); ?></option>
							</select>
						</div>
						<div class="ssc-field">
							<label for="direction"><?php esc_html_e( 'Chat text direction', 'smart-support-chatbot' ); ?></label>
							<select id="direction" name="direction">
								<option value="rtl" <?php selected( $dir, 'rtl' ); ?>><?php esc_html_e( 'RTL (Persian/Arabic)', 'smart-support-chatbot' ); ?></option>
								<option value="ltr" <?php selected( $dir, 'ltr' ); ?>><?php esc_html_e( 'LTR (Latin)', 'smart-support-chatbot' ); ?></option>
								<option value="auto" <?php selected( $s['direction'], 'auto' ); ?>><?php esc_html_e( 'Auto (follow site language)', 'smart-support-chatbot' ); ?></option>
							</select>
						</div>
					</div>
					<div class="ssc-field">
						<label for="assistant_display_name"><?php esc_html_e( 'Assistant display name', 'smart-support-chatbot' ); ?></label>
						<input id="assistant_display_name" name="assistant_display_name" type="text" value="<?php echo esc_attr( $s['assistant_display_name'] ); ?>" placeholder="<?php echo esc_attr( $asst_name ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="avatar_url"><?php esc_html_e( 'Avatar / custom icon URL', 'smart-support-chatbot' ); ?></label>
						<input id="avatar_url" name="avatar_url" type="url" dir="ltr" value="<?php echo esc_attr( $s['avatar_url'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="welcome_title"><?php esc_html_e( 'Welcome title', 'smart-support-chatbot' ); ?></label>
						<input id="welcome_title" name="welcome_title" type="text" value="<?php echo esc_attr( $s['welcome_title'] ); ?>" placeholder="<?php echo esc_attr( $w_title ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="welcome_text"><?php esc_html_e( 'Welcome message', 'smart-support-chatbot' ); ?></label>
						<textarea id="welcome_text" name="welcome_text" rows="2" placeholder="<?php echo esc_attr( $w_text ); ?>"><?php echo esc_textarea( $s['welcome_text'] ); ?></textarea>
					</div>
					<div class="ssc-field">
						<label for="disclaimer"><?php esc_html_e( 'Disclaimer (shown under the chat)', 'smart-support-chatbot' ); ?></label>
						<input id="disclaimer" name="disclaimer" type="text" value="<?php echo esc_attr( $s['disclaimer'] ); ?>" placeholder="<?php echo esc_attr( $disclaimer ); ?>" />
					</div>
				</section>

				<details class="ssc-card ssc-details">
					<summary><?php esc_html_e( 'Advanced styling', 'smart-support-chatbot' ); ?></summary>
					<div class="ssc-grid ssc-grid--2">
						<div class="ssc-field">
							<label for="font_family"><?php esc_html_e( 'Font', 'smart-support-chatbot' ); ?></label>
							<select id="font_family" name="font_family">
								<option value="vazirmatn" <?php selected( $s['font_family'], 'vazirmatn' ); ?>><?php esc_html_e( 'Vazirmatn (Persian)', 'smart-support-chatbot' ); ?></option>
								<option value="inter" <?php selected( $s['font_family'], 'inter' ); ?>>Inter</option>
								<option value="roboto" <?php selected( $s['font_family'], 'roboto' ); ?>>Roboto</option>
								<option value="system" <?php selected( $s['font_family'], 'system' ); ?>><?php esc_html_e( 'System default', 'smart-support-chatbot' ); ?></option>
								<option value="custom" <?php selected( $s['font_family'], 'custom' ); ?>><?php esc_html_e( 'Custom…', 'smart-support-chatbot' ); ?></option>
							</select>
						</div>
						<div class="ssc-field">
							<label for="font_size"><?php esc_html_e( 'Base font size (px)', 'smart-support-chatbot' ); ?></label>
							<input id="font_size" name="font_size" type="number" min="12" max="20" value="<?php echo esc_attr( (string) $s['font_size'] ); ?>" />
						</div>
						<div class="ssc-field" data-show-when="font_family=custom">
							<label for="font_name"><?php esc_html_e( 'Custom font name', 'smart-support-chatbot' ); ?></label>
							<input id="font_name" name="font_name" type="text" dir="ltr" value="<?php echo esc_attr( $s['font_name'] ); ?>" />
						</div>
						<div class="ssc-field" data-show-when="font_family=custom">
							<label for="font_url"><?php esc_html_e( 'Custom font stylesheet URL', 'smart-support-chatbot' ); ?></label>
							<input id="font_url" name="font_url" type="url" dir="ltr" value="<?php echo esc_attr( $s['font_url'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="window_width"><?php esc_html_e( 'Chat window width (px)', 'smart-support-chatbot' ); ?></label>
							<input id="window_width" name="window_width" type="number" min="320" max="520" value="<?php echo esc_attr( (string) $s['window_width'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="window_radius"><?php esc_html_e( 'Window corner radius (px)', 'smart-support-chatbot' ); ?></label>
							<input id="window_radius" name="window_radius" type="number" min="0" max="32" value="<?php echo esc_attr( (string) $s['window_radius'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="bubble_radius"><?php esc_html_e( 'Message bubble radius (px)', 'smart-support-chatbot' ); ?></label>
							<input id="bubble_radius" name="bubble_radius" type="number" min="0" max="24" value="<?php echo esc_attr( (string) $s['bubble_radius'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="user_bubble_color"><?php esc_html_e( 'Visitor bubble color', 'smart-support-chatbot' ); ?></label>
							<input id="user_bubble_color" name="user_bubble_color" type="color" value="<?php echo esc_attr( $s['user_bubble_color'] ? $s['user_bubble_color'] : $primary ); ?>" class="ssc-color" />
						</div>
						<div class="ssc-field">
							<label for="bot_bubble_color"><?php esc_html_e( 'Assistant bubble color', 'smart-support-chatbot' ); ?></label>
							<input id="bot_bubble_color" name="bot_bubble_color" type="color" value="<?php echo esc_attr( $s['bot_bubble_color'] ? $s['bot_bubble_color'] : '#ffffff' ); ?>" class="ssc-color" />
						</div>
						<div class="ssc-field">
							<label for="launcher_size"><?php esc_html_e( 'Floating button size (px)', 'smart-support-chatbot' ); ?></label>
							<input id="launcher_size" name="launcher_size" type="number" min="48" max="72" value="<?php echo esc_attr( (string) $s['launcher_size'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="launcher_icon_url"><?php esc_html_e( 'Floating button custom image (optional)', 'smart-support-chatbot' ); ?></label>
							<input id="launcher_icon_url" name="launcher_icon_url" type="url" dir="ltr" value="<?php echo esc_attr( $s['launcher_icon_url'] ); ?>" />
						</div>
					</div>
				</details>
			</div>

			<aside class="ssc-preview-panel" id="ssc-preview-panel"
				data-primary="<?php echo esc_attr( $primary ); ?>"
				data-theme-mode="<?php echo esc_attr( $theme_mode ); ?>"
				data-dir="<?php echo esc_attr( $dir ); ?>"
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
					<strong><?php esc_html_e( 'Live preview', 'smart-support-chatbot' ); ?></strong>
					<div class="ssc-preview-toggle" role="group" aria-label="<?php esc_attr_e( 'Preview theme', 'smart-support-chatbot' ); ?>">
						<button type="button" data-pv-theme="light" class="is-on"><?php esc_html_e( 'Light', 'smart-support-chatbot' ); ?></button>
						<button type="button" data-pv-theme="dark"><?php esc_html_e( 'Dark', 'smart-support-chatbot' ); ?></button>
					</div>
				</div>

				<div class="ssc-preview-stage" id="ssc-preview-stage" data-preview-theme="light">
					<div class="ssc-pv" id="ssc-pv" data-theme="light" dir="<?php echo esc_attr( $dir ); ?>">
						<div class="ssc-pv__head">
							<span class="ssc-pv__avatar" id="ssc-pv-avatar"></span>
							<div class="ssc-pv__meta">
								<strong class="ssc-pv__name" id="ssc-pv-name"><?php echo esc_html( $asst_name ); ?></strong>
								<span class="ssc-pv__status" id="ssc-pv-status"><?php echo esc_html( $org_name ); ?></span>
							</div>
						</div>
						<div class="ssc-pv__body">
							<p class="ssc-pv__msg ssc-pv__msg--bot">
								<strong id="ssc-pv-wtitle"><?php echo esc_html( $w_title ); ?></strong>
								<span id="ssc-pv-wtext"><?php echo esc_html( $w_text ); ?></span>
							</p>
							<p class="ssc-pv__msg ssc-pv__msg--user"><?php esc_html_e( 'Hi! Do you ship internationally?', 'smart-support-chatbot' ); ?></p>
							<div class="ssc-pv__chips">
								<span class="ssc-pv__chip"><?php esc_html_e( 'Ask us', 'smart-support-chatbot' ); ?></span>
								<span class="ssc-pv__chip"><?php esc_html_e( 'Products', 'smart-support-chatbot' ); ?></span>
							</div>
						</div>
						<div class="ssc-pv__composer">
							<span class="ssc-pv__input"><?php esc_html_e( 'Write a message…', 'smart-support-chatbot' ); ?></span>
							<span class="ssc-pv__send" aria-hidden="true">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
							</span>
						</div>
						<p class="ssc-pv__foot" id="ssc-pv-foot"><?php echo esc_html( $disclaimer ); ?></p>
					</div>

					<button type="button" class="ssc-preview-launcher" id="ssc-pv-launcher" data-pos="<?php echo esc_attr( $pos ); ?>" tabindex="-1" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
					</button>
				</div>

				<p class="ssc-preview-panel__hint"><?php esc_html_e( 'Toggle Dark to preview night mode. Changes apply instantly — save when you are happy.', 'smart-support-chatbot' ); ?></p>
			</aside>
		</div>

		<div class="ssc-form__actions">
			<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Save appearance', 'smart-support-chatbot' ); ?></button>
		</div>
	</form>
</div>
