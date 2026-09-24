<?php
/**
 * Settings view: privacy, security, module settings, data tools.
 *
 * @package SmartSupportChatbot
 * @var array $s       Settings.
 * @var array $modules Module statuses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$saved   = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$flushed = isset( $_GET['flushed'] ) ? (int) $_GET['flushed'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$secret_error = isset( $_GET['secret_error'] ) ? (int) $_GET['secret_error'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
?>
<div class="ssc-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Settings', 'smart-support-chatbot' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Privacy, protection and module options. Security and privacy controls are always active — they are not modules.', 'smart-support-chatbot' ); ?></p>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Settings saved.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>
	<?php if ( $secret_error ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The secret could not be encrypted (OpenSSL unavailable or AUTH_KEY missing). It was NOT saved in plaintext. Fix the server crypto setup and try again.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>
	<?php if ( $flushed ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Cached AI answers cleared.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>

	<form method="post" class="ssc-form">
		<?php wp_nonce_field( 'ssc_settings' ); ?>
		<input type="hidden" name="ssc_settings_save" value="1" />

		<?php if ( SSC_Modules::is_active( 'pharma' ) ) : ?>
		<section class="ssc-card">
			<h2><?php esc_html_e( 'Pharmaceutical answer policy', 'smart-support-chatbot' ); ?></h2>
			<div class="ssc-field">
				<label for="pharma_answer_mode"><?php esc_html_e( 'Allowed information sources', 'smart-support-chatbot' ); ?></label>
				<select id="pharma_answer_mode" name="pharma_answer_mode">
					<option value="approved_only" <?php selected( $s['pharma_answer_mode'], 'approved_only' ); ?>><?php esc_html_e( 'Approved company sources only', 'smart-support-chatbot' ); ?></option>
					<option value="general_education" <?php selected( $s['pharma_answer_mode'], 'general_education' ); ?>><?php esc_html_e( 'Company sources and general educational information', 'smart-support-chatbot' ); ?></option>
				</select>
				<p class="ssc-help"><?php esc_html_e( 'Both modes refer personal treatment questions to a healthcare professional. Product-specific claims require approved company sources. Test answers with your medical team before publication.', 'smart-support-chatbot' ); ?></p>
			</div>
		</section>
		<?php endif; ?>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Privacy & consent', 'smart-support-chatbot' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Applies to every form. Server transcript logging is opt-in; browser transcripts and shared answer caching are disabled in pharmaceutical mode. Submitted forms are stored independently of chat history.', 'smart-support-chatbot' ); ?></p>
			<label class="ssc-check"><input type="checkbox" name="consent_enabled" value="yes" <?php checked( 'yes', $s['consent_enabled'] ); ?> /> <span><?php esc_html_e( 'Require explicit consent before storing contact data', 'smart-support-chatbot' ); ?></span></label>
			<div class="ssc-field">
				<label for="consent_text"><?php esc_html_e( 'Consent text', 'smart-support-chatbot' ); ?></label>
				<textarea id="consent_text" name="consent_text" rows="2"><?php echo esc_textarea( (string) $s['consent_text'] ); ?></textarea>
			</div>
			<div class="ssc-field">
				<label for="consent_link"><?php esc_html_e( 'Privacy policy link', 'smart-support-chatbot' ); ?></label>
				<input id="consent_link" name="consent_link" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['consent_link'] ); ?>" />
			</div>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="chatlog_retention_days"><?php esc_html_e( 'Conversation retention (days, 0 = keep forever)', 'smart-support-chatbot' ); ?></label>
					<input id="chatlog_retention_days" name="chatlog_retention_days" type="number" min="0" max="3650" value="<?php echo esc_attr( (string) $s['chatlog_retention_days'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="submissions_retention_days"><?php esc_html_e( 'Request retention (days, 0 = keep forever; ADR cases are never auto-deleted)', 'smart-support-chatbot' ); ?></label>
					<input id="submissions_retention_days" name="submissions_retention_days" type="number" min="0" max="3650" value="<?php echo esc_attr( (string) $s['submissions_retention_days'] ); ?>" />
				</div>
			</div>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Where the widget appears', 'smart-support-chatbot' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Pages that fail these rules never load the chatbot CSS or JS — better performance and cleaner analytics.', 'smart-support-chatbot' ); ?></p>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="display_mode"><?php esc_html_e( 'Page targeting', 'smart-support-chatbot' ); ?></label>
					<select id="display_mode" name="display_mode">
						<option value="all" <?php selected( $s['display_mode'], 'all' ); ?>><?php esc_html_e( 'All pages', 'smart-support-chatbot' ); ?></option>
						<option value="include" <?php selected( $s['display_mode'], 'include' ); ?>><?php esc_html_e( 'Only these paths', 'smart-support-chatbot' ); ?></option>
						<option value="exclude" <?php selected( $s['display_mode'], 'exclude' ); ?>><?php esc_html_e( 'Everywhere except these paths', 'smart-support-chatbot' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="display_devices"><?php esc_html_e( 'Devices', 'smart-support-chatbot' ); ?></label>
					<select id="display_devices" name="display_devices">
						<option value="all" <?php selected( $s['display_devices'], 'all' ); ?>><?php esc_html_e( 'All devices', 'smart-support-chatbot' ); ?></option>
						<option value="desktop" <?php selected( $s['display_devices'], 'desktop' ); ?>><?php esc_html_e( 'Desktop only', 'smart-support-chatbot' ); ?></option>
						<option value="mobile" <?php selected( $s['display_devices'], 'mobile' ); ?>><?php esc_html_e( 'Mobile only', 'smart-support-chatbot' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="display_users"><?php esc_html_e( 'Visitors', 'smart-support-chatbot' ); ?></label>
					<select id="display_users" name="display_users">
						<option value="all" <?php selected( $s['display_users'], 'all' ); ?>><?php esc_html_e( 'Everyone', 'smart-support-chatbot' ); ?></option>
						<option value="guest" <?php selected( $s['display_users'], 'guest' ); ?>><?php esc_html_e( 'Logged-out visitors only', 'smart-support-chatbot' ); ?></option>
						<option value="logged_in" <?php selected( $s['display_users'], 'logged_in' ); ?>><?php esc_html_e( 'Logged-in users only', 'smart-support-chatbot' ); ?></option>
					</select>
				</div>
			</div>
			<div class="ssc-field">
				<label for="display_paths"><?php esc_html_e( 'Path patterns (one per line; * = wildcard)', 'smart-support-chatbot' ); ?></label>
				<textarea id="display_paths" name="display_paths" rows="3" dir="ltr" placeholder="/shop/*&#10;/contact&#10;/blog/*"><?php echo esc_textarea( (string) $s['display_paths'] ); ?></textarea>
			</div>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Business hours', 'smart-support-chatbot' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Outside these hours the widget shows an offline notice. Forms still work so visitors can leave a message.', 'smart-support-chatbot' ); ?></p>
			<label class="ssc-check"><input type="checkbox" name="business_hours_enabled" value="yes" <?php checked( 'yes', $s['business_hours_enabled'] ); ?> /> <span><?php esc_html_e( 'Limit availability to business hours', 'smart-support-chatbot' ); ?></span></label>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="business_hours_start"><?php esc_html_e( 'Opens at', 'smart-support-chatbot' ); ?></label>
					<input id="business_hours_start" name="business_hours_start" type="time" dir="ltr" value="<?php echo esc_attr( (string) $s['business_hours_start'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="business_hours_end"><?php esc_html_e( 'Closes at', 'smart-support-chatbot' ); ?></label>
					<input id="business_hours_end" name="business_hours_end" type="time" dir="ltr" value="<?php echo esc_attr( (string) $s['business_hours_end'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="business_hours_days"><?php esc_html_e( 'Days (Mon=1 … Sun=7)', 'smart-support-chatbot' ); ?></label>
					<input id="business_hours_days" name="business_hours_days" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['business_hours_days'] ); ?>" placeholder="1,2,3,4,5" />
				</div>
				<div class="ssc-field">
					<label for="business_timezone"><?php esc_html_e( 'Timezone (empty = site timezone)', 'smart-support-chatbot' ); ?></label>
					<input id="business_timezone" name="business_timezone" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['business_timezone'] ); ?>" placeholder="Asia/Tehran" />
				</div>
			</div>
			<div class="ssc-field">
				<label for="offline_message"><?php esc_html_e( 'Offline message', 'smart-support-chatbot' ); ?></label>
				<textarea id="offline_message" name="offline_message" rows="2"><?php echo esc_textarea( (string) $s['offline_message'] ); ?></textarea>
			</div>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Widget behaviour', 'smart-support-chatbot' ); ?></h2>
			<label class="ssc-check"><input type="checkbox" name="streaming_enabled" value="yes" <?php checked( 'yes', $s['streaming_enabled'] ); ?> /> <span><?php esc_html_e( 'Stream AI answers as they are generated (faster first word; OpenAI-compatible providers)', 'smart-support-chatbot' ); ?></span></label>
			<label class="ssc-check"><input type="checkbox" name="sound_enabled" value="yes" <?php checked( 'yes', $s['sound_enabled'] ); ?> /> <span><?php esc_html_e( 'Play a soft ping when a reply arrives while the chat is closed', 'smart-support-chatbot' ); ?></span></label>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Protection & limits', 'smart-support-chatbot' ); ?></h2>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="rate_limit_mode"><?php esc_html_e( 'Limit by', 'smart-support-chatbot' ); ?></label>
					<select id="rate_limit_mode" name="rate_limit_mode">
						<option value="ip" <?php selected( $s['rate_limit_mode'], 'ip' ); ?>><?php esc_html_e( 'IP address', 'smart-support-chatbot' ); ?></option>
						<option value="session" <?php selected( $s['rate_limit_mode'], 'session' ); ?>><?php esc_html_e( 'Browser session', 'smart-support-chatbot' ); ?></option>
						<option value="both" <?php selected( $s['rate_limit_mode'], 'both' ); ?>><?php esc_html_e( 'Both', 'smart-support-chatbot' ); ?></option>
						<option value="off" <?php selected( $s['rate_limit_mode'], 'off' ); ?>><?php esc_html_e( 'Off (not recommended)', 'smart-support-chatbot' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="chat_rate_limit"><?php esc_html_e( 'Chat messages / day / visitor', 'smart-support-chatbot' ); ?></label>
					<input id="chat_rate_limit" name="chat_rate_limit" type="number" min="0" value="<?php echo esc_attr( (string) $s['chat_rate_limit'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="submit_rate_limit"><?php esc_html_e( 'Form submissions / day / visitor', 'smart-support-chatbot' ); ?></label>
					<input id="submit_rate_limit" name="submit_rate_limit" type="number" min="0" value="<?php echo esc_attr( (string) $s['submit_rate_limit'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="session_rate_limit"><?php esc_html_e( 'Chat messages / day / session', 'smart-support-chatbot' ); ?></label>
					<input id="session_rate_limit" name="session_rate_limit" type="number" min="0" value="<?php echo esc_attr( (string) $s['session_rate_limit'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="trusted_proxy_header"><?php esc_html_e( 'Trusted proxy header (behind CDN / reverse proxy)', 'smart-support-chatbot' ); ?></label>
					<select id="trusted_proxy_header" name="trusted_proxy_header">
						<option value="" <?php selected( $s['trusted_proxy_header'], '' ); ?>><?php esc_html_e( 'None — use REMOTE_ADDR (direct connection)', 'smart-support-chatbot' ); ?></option>
						<option value="HTTP_CF_CONNECTING_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_CF_CONNECTING_IP' ); ?>>Cloudflare — CF-Connecting-IP</option>
						<option value="HTTP_X_FORWARDED_FOR" <?php selected( $s['trusted_proxy_header'], 'HTTP_X_FORWARDED_FOR' ); ?>>X-Forwarded-For</option>
						<option value="HTTP_X_REAL_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_X_REAL_IP' ); ?>>X-Real-IP (nginx)</option>
						<option value="HTTP_TRUE_CLIENT_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_TRUE_CLIENT_IP' ); ?>>True-Client-IP (Akamai)</option>
						<option value="HTTP_FASTLY_CLIENT_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_FASTLY_CLIENT_IP' ); ?>>Fastly-Client-IP</option>
						<option value="HTTP_X_CLIENT_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_X_CLIENT_IP' ); ?>>X-Client-IP</option>
					</select>
					<p class="ssc-field__hint"><?php esc_html_e( 'Required when the site sits behind Cloudflare or any reverse proxy — otherwise every visitor shares one IP and one rate-limit quota. Only enable the header your proxy actually sets and strips from client requests.', 'smart-support-chatbot' ); ?></p>
				</div>
			</div>
		</section>

		<?php if ( SSC_Modules::is_active( 'voice' ) ) : ?>
			<section class="ssc-card">
				<h2><?php esc_html_e( 'Voice module', 'smart-support-chatbot' ); ?></h2>
				<label class="ssc-check"><input type="checkbox" name="voice_input" value="yes" <?php checked( 'yes', $s['voice_input'] ); ?> /> <span><?php esc_html_e( 'Microphone input', 'smart-support-chatbot' ); ?></span></label>
				<label class="ssc-check"><input type="checkbox" name="voice_output" value="yes" <?php checked( 'yes', $s['voice_output'] ); ?> /> <span><?php esc_html_e( 'Read answers aloud', 'smart-support-chatbot' ); ?></span></label>
				<div class="ssc-field">
					<label for="voice_language"><?php esc_html_e( 'Recognition language (or "auto")', 'smart-support-chatbot' ); ?></label>
					<input id="voice_language" name="voice_language" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['voice_language'] ); ?>" placeholder="auto / fa-IR / en-US" />
				</div>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'history' ) ) : ?>
			<section class="ssc-card">
				<h2><?php esc_html_e( 'Conversation history module', 'smart-support-chatbot' ); ?></h2>
				<label class="ssc-check"><input type="checkbox" name="chatlog_enabled" value="yes" <?php checked( 'yes', $s['chatlog_enabled'] ); ?> /> <span><?php esc_html_e( 'Store conversations (needed for feedback, unanswered radar and quality review)', 'smart-support-chatbot' ); ?></span></label>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'csat' ) ) : ?>
			<section class="ssc-card">
				<h2><?php esc_html_e( 'Satisfaction survey module', 'smart-support-chatbot' ); ?></h2>
				<label class="ssc-check"><input type="checkbox" name="csat_enabled" value="yes" <?php checked( 'yes', $s['csat_enabled'] ); ?> /> <span><?php esc_html_e( 'Show the end-of-conversation survey', 'smart-support-chatbot' ); ?></span></label>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'handoff' ) ) : ?>
			<section class="ssc-card">
				<h2><?php esc_html_e( 'Human handoff module', 'smart-support-chatbot' ); ?></h2>
				<div class="ssc-field">
					<label for="handoff_text"><?php esc_html_e( 'Handoff message', 'smart-support-chatbot' ); ?></label>
					<textarea id="handoff_text" name="handoff_text" rows="2"><?php echo esc_textarea( (string) $s['handoff_text'] ); ?></textarea>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'proactive' ) ) : ?>
			<section class="ssc-card">
				<h2><?php esc_html_e( 'Proactive invitation module', 'smart-support-chatbot' ); ?></h2>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="proactive_delay"><?php esc_html_e( 'Delay before showing (seconds)', 'smart-support-chatbot' ); ?></label>
						<input id="proactive_delay" name="proactive_delay" type="number" min="2" max="120" value="<?php echo esc_attr( (string) $s['proactive_delay'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="proactive_text"><?php esc_html_e( 'Invitation text', 'smart-support-chatbot' ); ?></label>
						<input id="proactive_text" name="proactive_text" type="text" value="<?php echo esc_attr( (string) $s['proactive_text'] ); ?>" />
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'leads' ) ) : ?>
			<section class="ssc-card">
				<h2><?php esc_html_e( 'Request form module', 'smart-support-chatbot' ); ?></h2>
				<p class="ssc-card__sub"><?php esc_html_e( 'Custom fields for the consultation form. Server-side validation is generated from this definition.', 'smart-support-chatbot' ); ?></p>
				<div id="ssc-fields-list" class="ssc-fields">
					<?php $fields = SSC_Settings::form_fields(); ?>
					<?php $fields = $fields ? $fields : array( array( 'label' => '', 'type' => 'text', 'key' => '', 'required' => false, 'options' => array(), 'placeholder' => '' ) ); ?>
					<?php foreach ( $fields as $i => $f ) : ?>
						<div class="ssc-fieldrow">
							<input type="hidden" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][key]" value="<?php echo esc_attr( $f['key'] ); ?>" />
							<input type="text" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][label]" value="<?php echo esc_attr( $f['label'] ); ?>" placeholder="<?php esc_attr_e( 'Field label', 'smart-support-chatbot' ); ?>" />
							<select name="form_fields[<?php echo esc_attr( (string) $i ); ?>][type]" aria-label="<?php esc_attr_e( 'Field type', 'smart-support-chatbot' ); ?>">
								<?php foreach ( SSC_Settings::form_field_types() as $ft ) : ?>
									<option value="<?php echo esc_attr( $ft ); ?>" <?php selected( $f['type'], $ft ); ?>><?php echo esc_html( $ft ); ?></option>
								<?php endforeach; ?>
							</select>
							<label class="ssc-check ssc-check--tight"><input type="checkbox" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][required]" value="1" <?php checked( ! empty( $f['required'] ) ); ?> /> <?php esc_html_e( 'Required', 'smart-support-chatbot' ); ?></label>
							<input type="text" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][placeholder]" value="<?php echo esc_attr( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Placeholder', 'smart-support-chatbot' ); ?>" />
							<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove field', 'smart-support-chatbot' ); ?>">×</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="ssc-btn ssc-btn--ghost ssc-field__add" data-target="#ssc-fields-list"><?php esc_html_e( '+ Add field', 'smart-support-chatbot' ); ?></button>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'notifications' ) ) : ?>
			<section class="ssc-card">
				<h2><?php esc_html_e( 'Notifications module', 'smart-support-chatbot' ); ?></h2>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="notify_platform"><?php esc_html_e( 'Messenger platform', 'smart-support-chatbot' ); ?></label>
						<select id="notify_platform" name="notify_platform">
							<option value="bale" <?php selected( $s['notify_platform'], 'bale' ); ?>><?php esc_html_e( 'Bale', 'smart-support-chatbot' ); ?></option>
							<option value="telegram" <?php selected( $s['notify_platform'], 'telegram' ); ?>><?php esc_html_e( 'Telegram', 'smart-support-chatbot' ); ?></option>
						</select>
					</div>
					<div class="ssc-field">
						<label for="notify_chat_id"><?php esc_html_e( 'Chat / channel ID', 'smart-support-chatbot' ); ?></label>
						<input id="notify_chat_id" name="notify_chat_id" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['notify_chat_id'] ); ?>" />
					</div>
				</div>
				<div class="ssc-field">
					<label for="notify_token"><?php esc_html_e( 'Bot token', 'smart-support-chatbot' ); ?></label>
					<input id="notify_token" name="notify_token" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( 'notify_token' ) ? esc_attr__( 'A token is stored — type to replace', 'smart-support-chatbot' ) : ''; ?>" />
					<?php if ( SSC_Settings::has_secret( 'notify_token' ) ) : ?>
						<label class="ssc-check ssc-check--tight"><input type="checkbox" name="notify_token_clear" value="1" /> <span><?php esc_html_e( 'Remove the stored token', 'smart-support-chatbot' ); ?></span></label>
					<?php endif; ?>
				</div>
				<label class="ssc-check"><input type="checkbox" name="notify_email_enabled" value="yes" <?php checked( 'yes', $s['notify_email_enabled'] ); ?> /> <span><?php esc_html_e( 'Also send email notifications', 'smart-support-chatbot' ); ?></span></label>
				<div class="ssc-field">
					<label for="notify_email_to"><?php esc_html_e( 'Email recipient (empty = site admin)', 'smart-support-chatbot' ); ?></label>
					<input id="notify_email_to" name="notify_email_to" type="email" dir="ltr" value="<?php echo esc_attr( (string) $s['notify_email_to'] ); ?>" />
				</div>
			</section>
		<?php endif; ?>

		<div class="ssc-form__actions">
			<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Save settings', 'smart-support-chatbot' ); ?></button>
		</div>
	</form>

	<section class="ssc-card ssc-mt">
		<h2><?php esc_html_e( 'Data tools', 'smart-support-chatbot' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'ssc_tools' ); ?>
			<button type="submit" name="ssc_flush_cache" value="1" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Clear cached AI answers', 'smart-support-chatbot' ); ?></button>
		</form>
		<form method="post" class="ssc-mt">
			<?php wp_nonce_field( 'ssc_tools' ); ?>
			<input type="hidden" name="ssc_delete_policy" value="1" />
			<label class="ssc-check">
				<input type="checkbox" name="delete_on_uninstall" value="yes" <?php checked( 'yes', get_option( 'ssc_chatbot_delete_on_uninstall', 'no' ) ); ?> />
				<span><?php esc_html_e( 'Remove ALL plugin data (settings, knowledge, requests, ADR cases, logs) when the plugin is deleted. Set this policy before deactivation; an inactive plugin cannot display a deletion prompt. Keep this off to preserve your data.', 'smart-support-chatbot' ); ?></span>
			</label>
			<button type="submit" class="ssc-btn ssc-btn--ghost ssc-btn--danger"><?php esc_html_e( 'Save data policy', 'smart-support-chatbot' ); ?></button>
		</form>
	</section>
</div>
