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
			<h1><?php esc_html_e( 'Settings', 'nexachat-ai' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Privacy, protection and module options. Security and privacy controls are always active — they are not modules.', 'nexachat-ai' ); ?></p>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Settings saved.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>
	<?php if ( $secret_error ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The secret could not be encrypted (OpenSSL unavailable or AUTH_KEY missing). It was NOT saved in plaintext. Fix the server crypto setup and try again.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>
	<?php if ( $flushed ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Cached AI answers cleared.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>

	<?php
	$ssc_tabs = array(
		'privacy'    => __( 'Privacy', 'nexachat-ai' ),
		'display'    => __( 'Display & hours', 'nexachat-ai' ),
		'protection' => __( 'Protection', 'nexachat-ai' ),
		'modules'    => __( 'Modules', 'nexachat-ai' ),
		'data'       => __( 'Data tools', 'nexachat-ai' ),
	);
	?>
	<nav class="ssc-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'nexachat-ai' ); ?>" hidden>
		<?php foreach ( $ssc_tabs as $ssc_tab_id => $ssc_tab_label ) : ?>
			<button type="button" role="tab" class="ssc-tabs__tab" id="ssc-tab-<?php echo esc_attr( $ssc_tab_id ); ?>" data-tab="<?php echo esc_attr( $ssc_tab_id ); ?>" aria-selected="false"><?php echo esc_html( $ssc_tab_label ); ?></button>
		<?php endforeach; ?>
	</nav>

	<form method="post" class="ssc-form">
		<?php wp_nonce_field( 'ssc_settings' ); ?>
		<input type="hidden" name="ssc_settings_save" value="1" />

		<?php if ( SSC_Modules::is_active( 'pharma' ) ) : ?>
		<section data-ssc-tab="privacy" class="ssc-card">
			<h2><?php esc_html_e( 'Pharmaceutical answer policy', 'nexachat-ai' ); ?></h2>
			<div class="ssc-field">
				<label for="pharma_answer_mode"><?php esc_html_e( 'Allowed information sources', 'nexachat-ai' ); ?></label>
				<select id="pharma_answer_mode" name="pharma_answer_mode">
					<option value="approved_only" <?php selected( $s['pharma_answer_mode'], 'approved_only' ); ?>><?php esc_html_e( 'Approved company sources only', 'nexachat-ai' ); ?></option>
					<option value="general_education" <?php selected( $s['pharma_answer_mode'], 'general_education' ); ?>><?php esc_html_e( 'Company sources and general educational information', 'nexachat-ai' ); ?></option>
				</select>
				<p class="ssc-help"><?php esc_html_e( 'Both modes refer personal treatment questions to a healthcare professional. Product-specific claims require approved company sources. Test answers with your medical team before publication.', 'nexachat-ai' ); ?></p>
			</div>
		</section>
		<?php endif; ?>

		<section data-ssc-tab="privacy" class="ssc-card">
			<h2><?php esc_html_e( 'Privacy & consent', 'nexachat-ai' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Applies to every form. Server transcript logging is opt-in; browser transcripts and shared answer caching are disabled in pharmaceutical mode. Submitted forms are stored independently of chat history.', 'nexachat-ai' ); ?></p>
			<label class="ssc-check"><input type="checkbox" name="consent_enabled" value="yes" <?php checked( 'yes', $s['consent_enabled'] ); ?> /> <span><?php esc_html_e( 'Require explicit consent before storing contact data', 'nexachat-ai' ); ?></span></label>
			<div class="ssc-field">
				<label for="consent_text"><?php esc_html_e( 'Consent text', 'nexachat-ai' ); ?></label>
				<textarea id="consent_text" name="consent_text" rows="2"><?php echo esc_textarea( (string) $s['consent_text'] ); ?></textarea>
			</div>
			<div class="ssc-field">
				<label for="consent_link"><?php esc_html_e( 'Privacy policy link', 'nexachat-ai' ); ?></label>
				<input id="consent_link" name="consent_link" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['consent_link'] ); ?>" />
			</div>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="chatlog_retention_days"><?php esc_html_e( 'Conversation retention (days, 0 = keep forever)', 'nexachat-ai' ); ?></label>
					<input id="chatlog_retention_days" name="chatlog_retention_days" type="number" min="0" max="3650" value="<?php echo esc_attr( (string) $s['chatlog_retention_days'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="submissions_retention_days"><?php esc_html_e( 'Request retention (days, 0 = keep forever; ADR cases are never auto-deleted)', 'nexachat-ai' ); ?></label>
					<input id="submissions_retention_days" name="submissions_retention_days" type="number" min="0" max="3650" value="<?php echo esc_attr( (string) $s['submissions_retention_days'] ); ?>" />
				</div>
			</div>
			<div class="ssc-field">
				<label for="ip_storage"><?php esc_html_e( 'Visitor IP addresses in logs and requests', 'nexachat-ai' ); ?></label>
				<select id="ip_storage" name="ip_storage">
					<option value="anonymize" <?php selected( $s['ip_storage'], 'anonymize' ); ?>><?php esc_html_e( 'Anonymized (network part only — recommended)', 'nexachat-ai' ); ?></option>
					<option value="full" <?php selected( $s['ip_storage'], 'full' ); ?>><?php esc_html_e( 'Full address', 'nexachat-ai' ); ?></option>
					<option value="none" <?php selected( $s['ip_storage'], 'none' ); ?>><?php esc_html_e( 'Do not store', 'nexachat-ai' ); ?></option>
				</select>
				<p class="ssc-field__hint"><?php esc_html_e( 'Rate limiting still works in every mode: it uses a one-way hash that is never stored with the conversation.', 'nexachat-ai' ); ?></p>
			</div>
		</section>

		<section data-ssc-tab="display" class="ssc-card">
			<h2><?php esc_html_e( 'Where the widget appears', 'nexachat-ai' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Pages that fail these rules never load the chatbot CSS or JS — better performance and cleaner analytics.', 'nexachat-ai' ); ?></p>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="display_mode"><?php esc_html_e( 'Page targeting', 'nexachat-ai' ); ?></label>
					<select id="display_mode" name="display_mode">
						<option value="all" <?php selected( $s['display_mode'], 'all' ); ?>><?php esc_html_e( 'All pages', 'nexachat-ai' ); ?></option>
						<option value="include" <?php selected( $s['display_mode'], 'include' ); ?>><?php esc_html_e( 'Only these paths', 'nexachat-ai' ); ?></option>
						<option value="exclude" <?php selected( $s['display_mode'], 'exclude' ); ?>><?php esc_html_e( 'Everywhere except these paths', 'nexachat-ai' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="display_devices"><?php esc_html_e( 'Devices', 'nexachat-ai' ); ?></label>
					<select id="display_devices" name="display_devices">
						<option value="all" <?php selected( $s['display_devices'], 'all' ); ?>><?php esc_html_e( 'All devices', 'nexachat-ai' ); ?></option>
						<option value="desktop" <?php selected( $s['display_devices'], 'desktop' ); ?>><?php esc_html_e( 'Desktop only', 'nexachat-ai' ); ?></option>
						<option value="mobile" <?php selected( $s['display_devices'], 'mobile' ); ?>><?php esc_html_e( 'Mobile only', 'nexachat-ai' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="display_users"><?php esc_html_e( 'Visitors', 'nexachat-ai' ); ?></label>
					<select id="display_users" name="display_users">
						<option value="all" <?php selected( $s['display_users'], 'all' ); ?>><?php esc_html_e( 'Everyone', 'nexachat-ai' ); ?></option>
						<option value="guest" <?php selected( $s['display_users'], 'guest' ); ?>><?php esc_html_e( 'Logged-out visitors only', 'nexachat-ai' ); ?></option>
						<option value="logged_in" <?php selected( $s['display_users'], 'logged_in' ); ?>><?php esc_html_e( 'Logged-in users only', 'nexachat-ai' ); ?></option>
					</select>
				</div>
			</div>
			<div class="ssc-field">
				<label for="display_paths"><?php esc_html_e( 'Path patterns (one per line; * = wildcard)', 'nexachat-ai' ); ?></label>
				<textarea id="display_paths" name="display_paths" rows="3" dir="ltr" placeholder="/shop/*&#10;/contact&#10;/blog/*"><?php echo esc_textarea( (string) $s['display_paths'] ); ?></textarea>
			</div>
		</section>

		<section data-ssc-tab="display" class="ssc-card">
			<h2><?php esc_html_e( 'Business hours', 'nexachat-ai' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Outside these hours the widget shows an offline notice. Forms still work so visitors can leave a message.', 'nexachat-ai' ); ?></p>
			<label class="ssc-check"><input type="checkbox" name="business_hours_enabled" value="yes" <?php checked( 'yes', $s['business_hours_enabled'] ); ?> /> <span><?php esc_html_e( 'Limit availability to business hours', 'nexachat-ai' ); ?></span></label>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="business_hours_start"><?php esc_html_e( 'Opens at', 'nexachat-ai' ); ?></label>
					<input id="business_hours_start" name="business_hours_start" type="time" dir="ltr" value="<?php echo esc_attr( (string) $s['business_hours_start'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="business_hours_end"><?php esc_html_e( 'Closes at', 'nexachat-ai' ); ?></label>
					<input id="business_hours_end" name="business_hours_end" type="time" dir="ltr" value="<?php echo esc_attr( (string) $s['business_hours_end'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="business_hours_days"><?php esc_html_e( 'Days (Mon=1 … Sun=7)', 'nexachat-ai' ); ?></label>
					<input id="business_hours_days" name="business_hours_days" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['business_hours_days'] ); ?>" placeholder="1,2,3,4,5" />
				</div>
				<div class="ssc-field">
					<label for="business_timezone"><?php esc_html_e( 'Timezone (empty = site timezone)', 'nexachat-ai' ); ?></label>
					<input id="business_timezone" name="business_timezone" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['business_timezone'] ); ?>" placeholder="Asia/Tehran" />
				</div>
			</div>
			<div class="ssc-field">
				<label for="offline_message"><?php esc_html_e( 'Offline message', 'nexachat-ai' ); ?></label>
				<textarea id="offline_message" name="offline_message" rows="2"><?php echo esc_textarea( (string) $s['offline_message'] ); ?></textarea>
			</div>
		</section>

		<section data-ssc-tab="display" class="ssc-card">
			<h2><?php esc_html_e( 'Widget behaviour', 'nexachat-ai' ); ?></h2>
			<label class="ssc-check"><input type="checkbox" name="streaming_enabled" value="yes" <?php checked( 'yes', $s['streaming_enabled'] ); ?> /> <span><?php esc_html_e( 'Stream AI answers as they are generated (faster first word; OpenAI, Claude, Gemini and compatible providers)', 'nexachat-ai' ); ?></span></label>
			<label class="ssc-check"><input type="checkbox" name="sound_enabled" value="yes" <?php checked( 'yes', $s['sound_enabled'] ); ?> /> <span><?php esc_html_e( 'Play a soft ping when a reply arrives while the chat is closed', 'nexachat-ai' ); ?></span></label>
		</section>

		<section data-ssc-tab="protection" class="ssc-card">
			<h2><?php esc_html_e( 'Protection & limits', 'nexachat-ai' ); ?></h2>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="rate_limit_mode"><?php esc_html_e( 'Limit by', 'nexachat-ai' ); ?></label>
					<select id="rate_limit_mode" name="rate_limit_mode">
						<option value="ip" <?php selected( $s['rate_limit_mode'], 'ip' ); ?>><?php esc_html_e( 'IP address', 'nexachat-ai' ); ?></option>
						<option value="session" <?php selected( $s['rate_limit_mode'], 'session' ); ?>><?php esc_html_e( 'Browser session', 'nexachat-ai' ); ?></option>
						<option value="both" <?php selected( $s['rate_limit_mode'], 'both' ); ?>><?php esc_html_e( 'Both', 'nexachat-ai' ); ?></option>
						<option value="off" <?php selected( $s['rate_limit_mode'], 'off' ); ?>><?php esc_html_e( 'Off (not recommended)', 'nexachat-ai' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="chat_rate_limit"><?php esc_html_e( 'Chat messages / day / visitor', 'nexachat-ai' ); ?></label>
					<input id="chat_rate_limit" name="chat_rate_limit" type="number" min="0" value="<?php echo esc_attr( (string) $s['chat_rate_limit'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="submit_rate_limit"><?php esc_html_e( 'Form submissions / day / visitor', 'nexachat-ai' ); ?></label>
					<input id="submit_rate_limit" name="submit_rate_limit" type="number" min="0" value="<?php echo esc_attr( (string) $s['submit_rate_limit'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="session_rate_limit"><?php esc_html_e( 'Chat messages / day / session', 'nexachat-ai' ); ?></label>
					<input id="session_rate_limit" name="session_rate_limit" type="number" min="0" value="<?php echo esc_attr( (string) $s['session_rate_limit'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="trusted_proxy_header"><?php esc_html_e( 'Trusted proxy header (behind CDN / reverse proxy)', 'nexachat-ai' ); ?></label>
					<select id="trusted_proxy_header" name="trusted_proxy_header">
						<option value="" <?php selected( $s['trusted_proxy_header'], '' ); ?>><?php esc_html_e( 'None — use REMOTE_ADDR (direct connection)', 'nexachat-ai' ); ?></option>
						<option value="HTTP_CF_CONNECTING_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_CF_CONNECTING_IP' ); ?>>Cloudflare — CF-Connecting-IP</option>
						<option value="HTTP_X_FORWARDED_FOR" <?php selected( $s['trusted_proxy_header'], 'HTTP_X_FORWARDED_FOR' ); ?>>X-Forwarded-For</option>
						<option value="HTTP_X_REAL_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_X_REAL_IP' ); ?>>X-Real-IP (nginx)</option>
						<option value="HTTP_TRUE_CLIENT_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_TRUE_CLIENT_IP' ); ?>>True-Client-IP (Akamai)</option>
						<option value="HTTP_FASTLY_CLIENT_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_FASTLY_CLIENT_IP' ); ?>>Fastly-Client-IP</option>
						<option value="HTTP_X_CLIENT_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_X_CLIENT_IP' ); ?>>X-Client-IP</option>
					</select>
					<p class="ssc-field__hint"><?php esc_html_e( 'Required when the site sits behind Cloudflare or any reverse proxy — otherwise every visitor shares one IP and one rate-limit quota. Only enable the header your proxy actually sets and strips from client requests.', 'nexachat-ai' ); ?></p>
				</div>
			</div>
		</section>

		<?php if ( SSC_Modules::is_active( 'pharma' ) ) : ?>
			<?php
			$adr_config  = SSC_Module_Pharma::form_config();
			$adr_presets = SSC_Module_Pharma::presets();
			?>
			<section data-ssc-tab="modules" class="ssc-card" id="ssc-adr-form">
				<h2><?php esc_html_e( 'Side-effect report form', 'nexachat-ai' ); ?></h2>
				<p class="ssc-card__sub"><?php esc_html_e( 'Choose how long the form visitors fill in is. Reporter name, contact phone, product and the reaction description are always asked: without them a report is not a valid safety case.', 'nexachat-ai' ); ?></p>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="adr_preset"><?php esc_html_e( 'Form length', 'nexachat-ai' ); ?></label>
						<select id="adr_preset" name="adr_form[preset]">
							<?php foreach ( SSC_Module_Pharma::preset_labels() as $adr_pid => $adr_plabel ) : ?>
								<option value="<?php echo esc_attr( $adr_pid ); ?>" <?php selected( $adr_config['preset'], $adr_pid ); ?>><?php echo esc_html( $adr_plabel ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="ssc-field">
						<span class="ssc-field__label"><?php esc_html_e( 'Optional questions', 'nexachat-ai' ); ?></span>
						<input type="hidden" name="adr_form[collapse_optional]" value="no" />
						<label class="ssc-check"><input type="checkbox" name="adr_form[collapse_optional]" value="yes" <?php checked( 'yes', $adr_config['collapse_optional'] ); ?> /> <span><?php esc_html_e( 'Fold them under "More details (optional)" so the form looks short', 'nexachat-ai' ); ?></span></label>
					</div>
				</div>
				<div class="ssc-field">
					<label for="adr_intro"><?php esc_html_e( 'Short introduction above the form (optional)', 'nexachat-ai' ); ?></label>
					<textarea id="adr_intro" name="adr_form[intro]" rows="2" dir="auto" maxlength="400"><?php echo esc_textarea( $adr_config['intro'] ); ?></textarea>
				</div>

				<div class="ssc-table-scroll">
				<table class="widefat striped ssc-table ssc-adr-fields" data-presets="<?php echo esc_attr( wp_json_encode( $adr_presets ) ); ?>">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Question', 'nexachat-ai' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Ask', 'nexachat-ai' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Required', 'nexachat-ai' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Custom wording (optional)', 'nexachat-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( SSC_Module_Pharma::form_schema() as $adr_section ) :
							foreach ( $adr_section['fields'] as $adr_key => $adr_field ) :
								$adr_locked = in_array( $adr_key, SSC_Module_Pharma::LOCKED_FIELDS, true );
								$adr_saved  = isset( $adr_config['fields'][ $adr_key ] ) ? $adr_config['fields'][ $adr_key ] : null;
								$adr_on     = $adr_locked || null === $adr_saved || ! empty( $adr_saved['on'] );
								$adr_req    = $adr_locked || ( null === $adr_saved ? ! empty( $adr_field['required'] ) : ! empty( $adr_saved['req'] ) );
								?>
								<tr data-key="<?php echo esc_attr( $adr_key ); ?>" <?php echo $adr_locked ? 'data-locked="1"' : ''; ?>>
									<th scope="row"><?php echo esc_html( $adr_field['label'] ); ?><?php echo $adr_locked ? ' <span class="ssc-badge">' . esc_html__( 'always', 'nexachat-ai' ) . '</span>' : ''; ?></th>
									<td><input type="checkbox" class="ssc-adr-on" name="adr_form[fields][<?php echo esc_attr( $adr_key ); ?>][on]" value="1" <?php checked( $adr_on ); ?> <?php disabled( $adr_locked ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: question */ __( 'Ask: %s', 'nexachat-ai' ), $adr_field['label'] ) ); ?>" /></td>
									<td><input type="checkbox" class="ssc-adr-req" name="adr_form[fields][<?php echo esc_attr( $adr_key ); ?>][req]" value="1" <?php checked( $adr_req ); ?> <?php disabled( $adr_locked ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: question */ __( 'Required: %s', 'nexachat-ai' ), $adr_field['label'] ) ); ?>" /></td>
									<td><input type="text" name="adr_form[fields][<?php echo esc_attr( $adr_key ); ?>][label]" value="<?php echo esc_attr( $adr_saved ? $adr_saved['label'] : '' ); ?>" dir="auto" placeholder="<?php echo esc_attr( $adr_field['label'] ); ?>" /></td>
								</tr>
								<?php
							endforeach;
						endforeach;
						?>
					</tbody>
				</table>
				</div>
				<p class="ssc-field__hint" id="ssc-adr-hint"><?php esc_html_e( '"Ask" and "Required" are editable in Custom mode; the other lengths use a fixed set. If the seriousness question is off, serious cases cannot be flagged for immediate notification.', 'nexachat-ai' ); ?></p>

				<h3><?php esc_html_e( 'Your own questions', 'nexachat-ai' ); ?></h3>
				<p class="ssc-card__sub"><?php esc_html_e( 'Added at the end of the form, whatever the length. Answers are saved with the question text, so editing a question later never changes old reports.', 'nexachat-ai' ); ?></p>
				<div id="ssc-adr-custom" class="ssc-fields">
					<?php
					$adr_custom = $adr_config['custom'] ? $adr_config['custom'] : array(
						array(
							'key'      => '',
							'label'    => '',
							'type'     => 'text',
							'options'  => array(),
							'required' => false,
						),
					);
					foreach ( $adr_custom as $adr_i => $adr_q ) :
						?>
						<div class="ssc-fieldrow">
							<input type="hidden" name="adr_form[custom][<?php echo esc_attr( (string) $adr_i ); ?>][key]" value="<?php echo esc_attr( $adr_q['key'] ); ?>" />
							<input type="text" name="adr_form[custom][<?php echo esc_attr( (string) $adr_i ); ?>][label]" value="<?php echo esc_attr( $adr_q['label'] ); ?>" dir="auto" placeholder="<?php esc_attr_e( 'Question text', 'nexachat-ai' ); ?>" />
							<select name="adr_form[custom][<?php echo esc_attr( (string) $adr_i ); ?>][type]" aria-label="<?php esc_attr_e( 'Answer type', 'nexachat-ai' ); ?>">
								<?php foreach ( SSC_Module_Pharma::custom_type_labels() as $adr_t => $adr_tl ) : ?>
									<option value="<?php echo esc_attr( $adr_t ); ?>" <?php selected( $adr_q['type'], $adr_t ); ?>><?php echo esc_html( $adr_tl ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" class="ssc-fieldrow__options" name="adr_form[custom][<?php echo esc_attr( (string) $adr_i ); ?>][options]" value="<?php echo esc_attr( implode( ', ', $adr_q['options'] ) ); ?>" dir="auto" placeholder="<?php esc_attr_e( 'Choices, comma separated', 'nexachat-ai' ); ?>" aria-label="<?php esc_attr_e( 'Choices', 'nexachat-ai' ); ?>" />
							<label class="ssc-check ssc-check--tight"><input type="checkbox" name="adr_form[custom][<?php echo esc_attr( (string) $adr_i ); ?>][required]" value="1" <?php checked( ! empty( $adr_q['required'] ) ); ?> /> <?php esc_html_e( 'Required', 'nexachat-ai' ); ?></label>
							<span></span>
							<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove question', 'nexachat-ai' ); ?>">×</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="ssc-btn ssc-btn--ghost ssc-field__add" data-target="#ssc-adr-custom"><?php esc_html_e( '+ Add question', 'nexachat-ai' ); ?></button>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'voice' ) ) : ?>
			<section data-ssc-tab="modules" class="ssc-card">
				<h2><?php esc_html_e( 'Voice module', 'nexachat-ai' ); ?></h2>
				<label class="ssc-check"><input type="checkbox" name="voice_input" value="yes" <?php checked( 'yes', $s['voice_input'] ); ?> /> <span><?php esc_html_e( 'Microphone input', 'nexachat-ai' ); ?></span></label>
				<label class="ssc-check"><input type="checkbox" name="voice_output" value="yes" <?php checked( 'yes', $s['voice_output'] ); ?> /> <span><?php esc_html_e( 'Read answers aloud', 'nexachat-ai' ); ?></span></label>
				<div class="ssc-field">
					<label for="voice_language"><?php esc_html_e( 'Recognition language (or "auto")', 'nexachat-ai' ); ?></label>
					<input id="voice_language" name="voice_language" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['voice_language'] ); ?>" placeholder="auto / fa-IR / en-US" />
				</div>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'history' ) ) : ?>
			<section data-ssc-tab="modules" class="ssc-card">
				<h2><?php esc_html_e( 'Conversation history module', 'nexachat-ai' ); ?></h2>
				<label class="ssc-check"><input type="checkbox" name="chatlog_enabled" value="yes" <?php checked( 'yes', $s['chatlog_enabled'] ); ?> /> <span><?php esc_html_e( 'Store conversations (needed for feedback, unanswered radar and quality review)', 'nexachat-ai' ); ?></span></label>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'csat' ) ) : ?>
			<section data-ssc-tab="modules" class="ssc-card">
				<h2><?php esc_html_e( 'Satisfaction survey module', 'nexachat-ai' ); ?></h2>
				<label class="ssc-check"><input type="checkbox" name="csat_enabled" value="yes" <?php checked( 'yes', $s['csat_enabled'] ); ?> /> <span><?php esc_html_e( 'Show the end-of-conversation survey', 'nexachat-ai' ); ?></span></label>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'handoff' ) ) : ?>
			<section data-ssc-tab="modules" class="ssc-card">
				<h2><?php esc_html_e( 'Human handoff module', 'nexachat-ai' ); ?></h2>
				<div class="ssc-field">
					<label for="handoff_text"><?php esc_html_e( 'Handoff message', 'nexachat-ai' ); ?></label>
					<textarea id="handoff_text" name="handoff_text" rows="2"><?php echo esc_textarea( (string) $s['handoff_text'] ); ?></textarea>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'proactive' ) ) : ?>
			<section data-ssc-tab="modules" class="ssc-card">
				<h2><?php esc_html_e( 'Proactive invitation module', 'nexachat-ai' ); ?></h2>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="proactive_delay"><?php esc_html_e( 'Delay before showing (seconds)', 'nexachat-ai' ); ?></label>
						<input id="proactive_delay" name="proactive_delay" type="number" min="2" max="120" value="<?php echo esc_attr( (string) $s['proactive_delay'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="proactive_text"><?php esc_html_e( 'Invitation text', 'nexachat-ai' ); ?></label>
						<input id="proactive_text" name="proactive_text" type="text" value="<?php echo esc_attr( (string) $s['proactive_text'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="proactive_trigger"><?php esc_html_e( 'Show the invitation', 'nexachat-ai' ); ?></label>
						<select id="proactive_trigger" name="proactive_trigger">
							<option value="delay" <?php selected( $s['proactive_trigger'], 'delay' ); ?>><?php esc_html_e( 'After the delay', 'nexachat-ai' ); ?></option>
							<option value="scroll" <?php selected( $s['proactive_trigger'], 'scroll' ); ?>><?php esc_html_e( 'After scrolling part of the page', 'nexachat-ai' ); ?></option>
							<option value="exit" <?php selected( $s['proactive_trigger'], 'exit' ); ?>><?php esc_html_e( 'When the visitor is about to leave (desktop; delay on phones)', 'nexachat-ai' ); ?></option>
						</select>
					</div>
					<div class="ssc-field">
						<label for="proactive_scroll"><?php esc_html_e( 'Scroll depth (%)', 'nexachat-ai' ); ?></label>
						<input id="proactive_scroll" name="proactive_scroll" type="number" min="10" max="100" value="<?php echo esc_attr( (string) $s['proactive_scroll'] ); ?>" />
					</div>
				</div>
				<div class="ssc-field">
					<label for="proactive_rules"><?php esc_html_e( 'Page-specific messages (one per line: path | message)', 'nexachat-ai' ); ?></label>
					<textarea id="proactive_rules" name="proactive_rules" rows="3" dir="auto" placeholder="/pricing* | Questions about plans? Ask me!&#10;/product/* | Need help choosing?"><?php echo esc_textarea( (string) $s['proactive_rules'] ); ?></textarea>
					<p class="ssc-field__hint"><?php esc_html_e( 'The first matching path wins; * matches anything. Pages without a match use the invitation text above. Use "-" as the message to show no invitation on those pages.', 'nexachat-ai' ); ?></p>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'leads' ) ) : ?>
			<section data-ssc-tab="modules" class="ssc-card">
				<h2><?php esc_html_e( 'Request form module', 'nexachat-ai' ); ?></h2>
				<p class="ssc-card__sub"><?php esc_html_e( 'Custom fields for the consultation form. Server-side validation is generated from this definition.', 'nexachat-ai' ); ?></p>
				<div id="ssc-fields-list" class="ssc-fields">
					<?php $fields = SSC_Settings::form_fields(); ?>
					<?php $fields = $fields ? $fields : array( array( 'label' => '', 'type' => 'text', 'key' => '', 'required' => false, 'options' => array(), 'placeholder' => '' ) ); ?>
					<?php foreach ( $fields as $i => $f ) : ?>
						<div class="ssc-fieldrow">
							<input type="hidden" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][key]" value="<?php echo esc_attr( $f['key'] ); ?>" />
							<input type="text" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][label]" value="<?php echo esc_attr( $f['label'] ); ?>" placeholder="<?php esc_attr_e( 'Field label', 'nexachat-ai' ); ?>" />
							<select name="form_fields[<?php echo esc_attr( (string) $i ); ?>][type]" aria-label="<?php esc_attr_e( 'Field type', 'nexachat-ai' ); ?>">
								<?php foreach ( SSC_Settings::form_field_type_labels() as $ft => $ft_label ) : ?>
									<option value="<?php echo esc_attr( $ft ); ?>" <?php selected( $f['type'], $ft ); ?>><?php echo esc_html( $ft_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" class="ssc-fieldrow__options" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][options]" value="<?php echo esc_attr( implode( ', ', (array) $f['options'] ) ); ?>" placeholder="<?php esc_attr_e( 'Choices, comma separated (dropdown / radio only)', 'nexachat-ai' ); ?>" aria-label="<?php esc_attr_e( 'Choices', 'nexachat-ai' ); ?>" />
							<label class="ssc-check ssc-check--tight"><input type="checkbox" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][required]" value="1" <?php checked( ! empty( $f['required'] ) ); ?> /> <?php esc_html_e( 'Required', 'nexachat-ai' ); ?></label>
							<input type="text" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][placeholder]" value="<?php echo esc_attr( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Placeholder', 'nexachat-ai' ); ?>" />
							<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove field', 'nexachat-ai' ); ?>">×</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="ssc-btn ssc-btn--ghost ssc-field__add" data-target="#ssc-fields-list"><?php esc_html_e( '+ Add field', 'nexachat-ai' ); ?></button>
			</section>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'notifications' ) ) : ?>
			<section data-ssc-tab="modules" class="ssc-card">
				<h2><?php esc_html_e( 'Notifications module', 'nexachat-ai' ); ?></h2>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="notify_platform"><?php esc_html_e( 'Messenger platform', 'nexachat-ai' ); ?></label>
						<select id="notify_platform" name="notify_platform">
							<option value="bale" <?php selected( $s['notify_platform'], 'bale' ); ?>><?php esc_html_e( 'Bale', 'nexachat-ai' ); ?></option>
							<option value="telegram" <?php selected( $s['notify_platform'], 'telegram' ); ?>><?php esc_html_e( 'Telegram', 'nexachat-ai' ); ?></option>
						</select>
					</div>
					<div class="ssc-field">
						<label for="notify_chat_id"><?php esc_html_e( 'Chat / channel ID', 'nexachat-ai' ); ?></label>
						<input id="notify_chat_id" name="notify_chat_id" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['notify_chat_id'] ); ?>" />
					</div>
				</div>
				<div class="ssc-field">
					<label for="notify_token"><?php esc_html_e( 'Bot token', 'nexachat-ai' ); ?></label>
					<input id="notify_token" name="notify_token" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( 'notify_token' ) ? esc_attr__( 'A token is stored — type to replace', 'nexachat-ai' ) : ''; ?>" />
					<?php if ( SSC_Settings::has_secret( 'notify_token' ) ) : ?>
						<label class="ssc-check ssc-check--tight"><input type="checkbox" name="notify_token_clear" value="1" /> <span><?php esc_html_e( 'Remove the stored token', 'nexachat-ai' ); ?></span></label>
					<?php endif; ?>
				</div>
				<label class="ssc-check"><input type="checkbox" name="notify_email_enabled" value="yes" <?php checked( 'yes', $s['notify_email_enabled'] ); ?> /> <span><?php esc_html_e( 'Also send email notifications', 'nexachat-ai' ); ?></span></label>
				<div class="ssc-field">
					<label for="notify_email_to"><?php esc_html_e( 'Email recipient (empty = site admin)', 'nexachat-ai' ); ?></label>
					<input id="notify_email_to" name="notify_email_to" type="email" dir="ltr" value="<?php echo esc_attr( (string) $s['notify_email_to'] ); ?>" />
				</div>
			</section>
		<?php endif; ?>

		<div class="ssc-form__actions">
			<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Save settings', 'nexachat-ai' ); ?></button>
		</div>
	</form>

	<section data-ssc-tab="data" class="ssc-card ssc-mt">
		<h2><?php esc_html_e( 'Data tools', 'nexachat-ai' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'ssc_tools' ); ?>
			<button type="submit" name="ssc_flush_cache" value="1" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Clear cached AI answers', 'nexachat-ai' ); ?></button>
		</form>
		<form method="post" class="ssc-mt">
			<?php wp_nonce_field( 'ssc_tools' ); ?>
			<input type="hidden" name="ssc_delete_policy" value="1" />
			<label class="ssc-check">
				<input type="checkbox" name="delete_on_uninstall" value="yes" <?php checked( 'yes', get_option( 'ssc_chatbot_delete_on_uninstall', 'no' ) ); ?> />
				<span><?php esc_html_e( 'Remove ALL plugin data (settings, knowledge, requests, ADR cases, logs) when the plugin is deleted. Set this policy before deactivation; an inactive plugin cannot display a deletion prompt. Keep this off to preserve your data.', 'nexachat-ai' ); ?></span>
			</label>
			<button type="submit" class="ssc-btn ssc-btn--ghost ssc-btn--danger"><?php esc_html_e( 'Save data policy', 'nexachat-ai' ); ?></button>
		</form>
	</section>
</div>
