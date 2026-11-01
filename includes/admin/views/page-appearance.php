<?php
/**
 * Appearance view: a brand strip, five collapsible sections of controls and
 * a sticky live dark/light preview, with one save bar.
 *
 * @package Vazho
 * @var array $s        Settings.
 * @var array $business Business profile.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$saved = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.

$primary   = $s['primary_color'] ? $s['primary_color'] : '#b61615';
$org_name  = isset( $business['org_name'] ) && $business['org_name'] ? $business['org_name'] : get_bloginfo( 'name' );
$asst_name = SSC_Frontend::display_name( $s );
$w_title   = $s['welcome_title'] ? $s['welcome_title'] : __( 'Hello! 👋', 'vazho' );
$w_text    = $s['welcome_text'] ? wp_strip_all_tags( $s['welcome_text'] ) : __( 'How can I help you today?', 'vazho' );
$disclaimer = $s['disclaimer'] ? $s['disclaimer'] : __( 'AI can make mistakes.', 'vazho' );
$dir        = in_array( $s['direction'], array( 'rtl', 'ltr' ), true ) ? $s['direction'] : 'rtl';
$theme_mode = in_array( $s['theme_mode'], array( 'light', 'dark', 'auto' ), true ) ? $s['theme_mode'] : 'light';
$pos        = 'left' === $s['position'] ? 'left' : 'right';

$ssc_theme_labels = array(
	'light' => __( 'Light', 'vazho' ),
	'dark'  => __( 'Dark', 'vazho' ),
	'auto'  => __( 'Match visitor device', 'vazho' ),
);
$ssc_dir_labels   = array(
	'rtl'  => __( 'RTL (Persian/Arabic)', 'vazho' ),
	'ltr'  => __( 'LTR (Latin)', 'vazho' ),
	'auto' => __( 'Auto (follow site language)', 'vazho' ),
);
?>
<div class="ssc-page ssc-appearance">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Appearance', 'vazho' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Brand the assistant and watch the preview update live. Dark and light themes are fully supported.', 'vazho' ); ?></p>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Appearance saved.', 'vazho' ); ?></div>
	<?php endif; ?>

	<div class="ssc-hub-wrap" data-ssc-hub="appearance">
	<form method="post" class="ssc-form" data-ssc-hubform>
		<?php wp_nonce_field( 'ssc_appearance' ); ?>
		<input type="hidden" name="ssc_appearance_save" value="1" />

		<section class="ssc-card ssc-abrand" id="ssc-abrand" style="--brand: <?php echo esc_attr( $primary ); ?>">
			<span class="ssc-abrand__orb" aria-hidden="true"><?php echo SSC_Icons::get( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
			<div class="ssc-abrand__who">
				<strong data-ssc-abrand="name"><?php echo esc_html( $asst_name ); ?></strong>
				<span><?php echo esc_html( $org_name ); ?></span>
			</div>
			<ul class="ssc-abrand__chips">
				<li data-ssc-hue="rose"><?php echo SSC_Icons::get( 'sparkles' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?><span><?php echo esc_html( $ssc_theme_labels[ $theme_mode ] ); ?></span></li>
				<li data-ssc-hue="teal"><?php echo SSC_Icons::get( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?><span><?php echo esc_html( $ssc_dir_labels[ in_array( $s['direction'], array( 'rtl', 'ltr', 'auto' ), true ) ? $s['direction'] : 'rtl' ] ); ?></span></li>
				<li data-ssc-hue="amber"><?php echo SSC_Icons::get( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?><span><?php echo 'left' === $pos ? esc_html__( 'Bottom left', 'vazho' ) : esc_html__( 'Bottom right', 'vazho' ); ?></span></li>
			</ul>
		</section>

		<div class="ssc-appear">
			<div class="ssc-appear__controls">
				<?php SSC_Admin_UI::acc_open( 'ssc-colors', 'sparkles', __( 'Colours & style', 'vazho' ), 'rose', true, '', __( 'The brand colour, the theme and how the button looks.', 'vazho' ) ); ?>
					<div class="ssc-grid ssc-grid--2">
						<div class="ssc-field">
							<label for="primary_color"><?php esc_html_e( 'Primary brand color', 'vazho' ); ?></label>
							<input id="primary_color" name="primary_color" type="color" value="<?php echo esc_attr( $primary ); ?>" class="ssc-color" />
						</div>
						<div class="ssc-field">
							<label for="theme_mode"><?php esc_html_e( 'Theme', 'vazho' ); ?></label>
							<select id="theme_mode" name="theme_mode">
								<option value="light" <?php selected( $theme_mode, 'light' ); ?>><?php esc_html_e( 'Light', 'vazho' ); ?></option>
								<option value="dark" <?php selected( $theme_mode, 'dark' ); ?>><?php esc_html_e( 'Dark', 'vazho' ); ?></option>
								<option value="auto" <?php selected( $theme_mode, 'auto' ); ?>><?php esc_html_e( 'Match visitor device', 'vazho' ); ?></option>
							</select>
						</div>
						<div class="ssc-field">
							<label for="launcher_style"><?php esc_html_e( 'Assistant look', 'vazho' ); ?></label>
							<select id="launcher_style" name="launcher_style">
								<option value="mascot" <?php selected( $s['launcher_style'], 'mascot' ); ?>><?php esc_html_e( 'Animated character (brand colour)', 'vazho' ); ?></option>
								<option value="icon" <?php selected( $s['launcher_style'], 'icon' ); ?>><?php esc_html_e( 'Simple chat icon', 'vazho' ); ?></option>
							</select>
							<p class="ssc-field__hint"><?php esc_html_e( 'The character breathes, blinks, follows the pointer and looks up while it thinks. A custom button image or avatar below always wins.', 'vazho' ); ?></p>
						</div>
					</div>
				<?php SSC_Admin_UI::acc_close(); ?>

				<?php SSC_Admin_UI::acc_open( 'ssc-words', 'chat', __( 'Name & messages', 'vazho' ), 'blue', true, '', __( 'What the assistant is called and what visitors read first.', 'vazho' ) ); ?>
					<div class="ssc-field">
						<label for="assistant_display_name"><?php esc_html_e( 'Assistant display name', 'vazho' ); ?></label>
						<input id="assistant_display_name" name="assistant_display_name" type="text" value="<?php echo esc_attr( $s['assistant_display_name'] ); ?>" placeholder="<?php echo esc_attr( $asst_name ); ?>" />
						<p class="ssc-field__hint"><?php esc_html_e( 'Leave it empty to use the assistant name from Business Knowledge, so the chat header and the way the assistant introduces itself match.', 'vazho' ); ?></p>
					</div>
					<div class="ssc-field">
						<label for="avatar_url"><?php esc_html_e( 'Avatar / custom icon URL', 'vazho' ); ?></label>
						<input id="avatar_url" name="avatar_url" type="url" dir="ltr" value="<?php echo esc_attr( $s['avatar_url'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="welcome_title"><?php esc_html_e( 'Welcome title', 'vazho' ); ?></label>
						<input id="welcome_title" name="welcome_title" type="text" value="<?php echo esc_attr( $s['welcome_title'] ); ?>" placeholder="<?php echo esc_attr( $w_title ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="welcome_text"><?php esc_html_e( 'Welcome message', 'vazho' ); ?></label>
						<textarea id="welcome_text" name="welcome_text" rows="2" placeholder="<?php echo esc_attr( $w_text ); ?>"><?php echo esc_textarea( $s['welcome_text'] ); ?></textarea>
					</div>
					<div class="ssc-field">
						<label for="disclaimer"><?php esc_html_e( 'Disclaimer (shown under the chat)', 'vazho' ); ?></label>
						<input id="disclaimer" name="disclaimer" type="text" value="<?php echo esc_attr( $s['disclaimer'] ); ?>" placeholder="<?php echo esc_attr( $disclaimer ); ?>" />
					</div>
				<?php SSC_Admin_UI::acc_close(); ?>

				<?php SSC_Admin_UI::acc_open( 'ssc-lang', 'globe', __( 'Language & direction', 'vazho' ), 'teal', false, '', __( 'Which way the chat reads and which language its buttons use.', 'vazho' ) ); ?>
					<div class="ssc-grid ssc-grid--2">
						<div class="ssc-field">
							<label for="direction"><?php esc_html_e( 'Chat text direction', 'vazho' ); ?></label>
							<select id="direction" name="direction">
								<option value="rtl" <?php selected( $dir, 'rtl' ); ?>><?php esc_html_e( 'RTL (Persian/Arabic)', 'vazho' ); ?></option>
								<option value="ltr" <?php selected( $dir, 'ltr' ); ?>><?php esc_html_e( 'LTR (Latin)', 'vazho' ); ?></option>
								<option value="auto" <?php selected( $s['direction'], 'auto' ); ?>><?php esc_html_e( 'Auto (follow site language)', 'vazho' ); ?></option>
							</select>
						</div>
						<div class="ssc-field">
							<label for="widget_language"><?php esc_html_e( 'Chat widget language', 'vazho' ); ?></label>
							<select id="widget_language" name="widget_language" aria-describedby="widget_language_hint">
								<?php $wl = isset( $s['widget_language'] ) ? $s['widget_language'] : 'auto'; ?>
								<option value="auto" <?php selected( $wl, 'auto' ); ?>><?php esc_html_e( 'Automatic (answer language, else site language)', 'vazho' ); ?></option>
								<option value="fa_IR" <?php selected( $wl, 'fa_IR' ); ?>>فارسی (Persian)</option>
								<option value="en_US" <?php selected( $wl, 'en_US' ); ?>>English</option>
							</select>
							<p class="ssc-field__hint" id="widget_language_hint"><?php esc_html_e( 'Buttons, forms and messages visitors see in the chat, independent of the WordPress language. Saved changes apply to the preview after reload.', 'vazho' ); ?></p>
						</div>
					</div>
				<?php SSC_Admin_UI::acc_close(); ?>

				<?php SSC_Admin_UI::acc_open( 'ssc-posbox', 'pin', __( 'Button position & spacing', 'vazho' ), 'amber', false, '', __( 'Keep the button clear of your menu, such as a bottom bar on phones.', 'vazho' ) ); ?>
					<div class="ssc-grid ssc-grid--2">
						<div class="ssc-field">
							<label for="position"><?php esc_html_e( 'Floating button position', 'vazho' ); ?></label>
							<select id="position" name="position">
								<option value="right" <?php selected( $pos, 'right' ); ?>><?php esc_html_e( 'Bottom right', 'vazho' ); ?></option>
								<option value="left" <?php selected( $pos, 'left' ); ?>><?php esc_html_e( 'Bottom left', 'vazho' ); ?></option>
							</select>
						</div>
					</div>
					<p class="ssc-card__sub"><?php esc_html_e( 'Move the floating button so it never covers your menu, for example a bottom navigation bar on phones. The drawings on the right show where it will sit.', 'vazho' ); ?></p>
										<div class="ssc-posbox__grid">
											<div class="ssc-posbox__fields">
												<fieldset class="ssc-posbox__set">
													<legend><span class="dashicons dashicons-smartphone" aria-hidden="true"></span> <?php esc_html_e( 'Phones', 'vazho' ); ?></legend>
													<div class="ssc-grid ssc-grid--2">
														<div class="ssc-field">
															<label for="launcher_offset_y_mobile"><?php esc_html_e( 'Distance from the bottom (px)', 'vazho' ); ?></label>
															<input id="launcher_offset_y_mobile" name="launcher_offset_y_mobile" type="number" min="0" max="300" step="2" value="<?php echo esc_attr( (string) $s['launcher_offset_y_mobile'] ); ?>" />
														</div>
														<div class="ssc-field">
															<label for="launcher_offset_x_mobile"><?php esc_html_e( 'Distance from the side (px)', 'vazho' ); ?></label>
															<input id="launcher_offset_x_mobile" name="launcher_offset_x_mobile" type="number" min="0" max="120" step="2" value="<?php echo esc_attr( (string) $s['launcher_offset_x_mobile'] ); ?>" />
														</div>
													</div>
													<div class="ssc-posbox__presets" role="group" aria-label="<?php esc_attr_e( 'Quick choices for phones', 'vazho' ); ?>">
														<button type="button" class="ssc-chip" data-ssc-pos-preset="18"><?php esc_html_e( 'Corner (default)', 'vazho' ); ?></button>
														<button type="button" class="ssc-chip" data-ssc-pos-preset="84"><?php esc_html_e( 'Above a bottom menu', 'vazho' ); ?></button>
													</div>
													<label class="ssc-check ssc-check--tight"><input type="checkbox" id="launcher_avoid_bars" name="launcher_avoid_bars" value="yes" <?php checked( 'yes', $s['launcher_avoid_bars'] ); ?> /> <span><?php esc_html_e( 'Lift the button automatically above a fixed bar at the bottom of the page (menu, cookie notice)', 'vazho' ); ?></span></label>
												</fieldset>
												<fieldset class="ssc-posbox__set">
													<legend><span class="dashicons dashicons-desktop" aria-hidden="true"></span> <?php esc_html_e( 'Desktop & tablet', 'vazho' ); ?></legend>
													<div class="ssc-grid ssc-grid--2">
														<div class="ssc-field">
															<label for="launcher_offset_y"><?php esc_html_e( 'Distance from the bottom (px)', 'vazho' ); ?></label>
															<input id="launcher_offset_y" name="launcher_offset_y" type="number" min="0" max="300" step="2" value="<?php echo esc_attr( (string) $s['launcher_offset_y'] ); ?>" />
														</div>
														<div class="ssc-field">
															<label for="launcher_offset_x"><?php esc_html_e( 'Distance from the side (px)', 'vazho' ); ?></label>
															<input id="launcher_offset_x" name="launcher_offset_x" type="number" min="0" max="200" step="2" value="<?php echo esc_attr( (string) $s['launcher_offset_x'] ); ?>" />
														</div>
													</div>
												</fieldset>
											</div>
											<div class="ssc-posbox__mocks" aria-hidden="true">
												<figure class="ssc-posmock ssc-posmock--phone" data-ssc-posmock="phone" data-vw="390" data-vh="760">
													<div class="ssc-posmock__screen">
														<div class="ssc-posmock__bar"><i></i><i></i><i></i></div>
														<span class="ssc-posmock__dot"></span>
													</div>
													<figcaption><?php esc_html_e( 'Phone', 'vazho' ); ?></figcaption>
												</figure>
												<figure class="ssc-posmock ssc-posmock--desktop" data-ssc-posmock="desktop" data-vw="1280" data-vh="720">
													<div class="ssc-posmock__screen">
														<span class="ssc-posmock__dot"></span>
													</div>
													<figcaption><?php esc_html_e( 'Desktop', 'vazho' ); ?></figcaption>
												</figure>
											</div>
										</div>
				<?php SSC_Admin_UI::acc_close(); ?>

				<?php SSC_Admin_UI::acc_open( 'ssc-advstyle', 'sliders', __( 'Advanced styling', 'vazho' ), 'slate', false, '', __( 'Fonts, sizes and corner radii. The defaults suit most sites.', 'vazho' ) ); ?>
					<div class="ssc-grid ssc-grid--2">
											<div class="ssc-field">
												<label for="font_family"><?php esc_html_e( 'Font', 'vazho' ); ?></label>
												<select id="font_family" name="font_family">
													<option value="vazirmatn" <?php selected( $s['font_family'], 'vazirmatn' ); ?>><?php esc_html_e( 'Vazirmatn (Persian)', 'vazho' ); ?></option>
													<option value="inter" <?php selected( $s['font_family'], 'inter' ); ?>>Inter</option>
													<option value="roboto" <?php selected( $s['font_family'], 'roboto' ); ?>>Roboto</option>
													<option value="system" <?php selected( $s['font_family'], 'system' ); ?>><?php esc_html_e( 'System default', 'vazho' ); ?></option>
													<option value="custom" <?php selected( $s['font_family'], 'custom' ); ?>><?php esc_html_e( 'Custom…', 'vazho' ); ?></option>
												</select>
											</div>
											<div class="ssc-field">
												<label for="font_size"><?php esc_html_e( 'Base font size (px)', 'vazho' ); ?></label>
												<input id="font_size" name="font_size" type="number" min="12" max="20" value="<?php echo esc_attr( (string) $s['font_size'] ); ?>" />
											</div>
											<div class="ssc-field" data-show-when="font_family=custom">
												<label for="font_name"><?php esc_html_e( 'Custom font name', 'vazho' ); ?></label>
												<input id="font_name" name="font_name" type="text" dir="ltr" value="<?php echo esc_attr( $s['font_name'] ); ?>" />
											</div>
											<div class="ssc-field" data-show-when="font_family=custom">
												<label for="font_url"><?php esc_html_e( 'Custom font stylesheet URL', 'vazho' ); ?></label>
												<input id="font_url" name="font_url" type="url" dir="ltr" value="<?php echo esc_attr( $s['font_url'] ); ?>" />
											</div>
											<div class="ssc-field">
												<label for="window_width"><?php esc_html_e( 'Chat window width (px)', 'vazho' ); ?></label>
												<input id="window_width" name="window_width" type="number" min="320" max="520" value="<?php echo esc_attr( (string) $s['window_width'] ); ?>" />
											</div>
											<div class="ssc-field">
												<label for="window_radius"><?php esc_html_e( 'Window corner radius (px)', 'vazho' ); ?></label>
												<input id="window_radius" name="window_radius" type="number" min="0" max="32" value="<?php echo esc_attr( (string) $s['window_radius'] ); ?>" />
											</div>
											<div class="ssc-field">
												<label for="bubble_radius"><?php esc_html_e( 'Message bubble radius (px)', 'vazho' ); ?></label>
												<input id="bubble_radius" name="bubble_radius" type="number" min="0" max="24" value="<?php echo esc_attr( (string) $s['bubble_radius'] ); ?>" />
											</div>
											<div class="ssc-field">
												<label for="user_bubble_color"><?php esc_html_e( 'Visitor bubble color', 'vazho' ); ?></label>
												<input id="user_bubble_color" name="user_bubble_color" type="color" value="<?php echo esc_attr( $s['user_bubble_color'] ? $s['user_bubble_color'] : $primary ); ?>" class="ssc-color" />
											</div>
											<div class="ssc-field">
												<label for="bot_bubble_color"><?php esc_html_e( 'Assistant bubble color', 'vazho' ); ?></label>
												<input id="bot_bubble_color" name="bot_bubble_color" type="color" value="<?php echo esc_attr( $s['bot_bubble_color'] ? $s['bot_bubble_color'] : '#ffffff' ); ?>" class="ssc-color" />
											</div>
											<div class="ssc-field">
												<label for="launcher_size"><?php esc_html_e( 'Floating button size (px)', 'vazho' ); ?></label>
												<input id="launcher_size" name="launcher_size" type="number" min="48" max="72" value="<?php echo esc_attr( (string) $s['launcher_size'] ); ?>" />
											</div>
											<div class="ssc-field">
												<label for="launcher_icon_url"><?php esc_html_e( 'Floating button custom image (optional)', 'vazho' ); ?></label>
												<input id="launcher_icon_url" name="launcher_icon_url" type="url" dir="ltr" value="<?php echo esc_attr( $s['launcher_icon_url'] ); ?>" />
											</div>
										</div>
				<?php SSC_Admin_UI::acc_close(); ?>
			</div>

			<?php require SSC_CHATBOT_DIR . 'includes/admin/views/partial-preview-panel.php'; ?>
		</div>

		<?php SSC_Admin_UI::save_bar( __( 'Save appearance', 'vazho' ) ); ?>
	</form>
	</div>
</div>
