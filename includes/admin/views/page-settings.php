<?php
/**
 * Settings view: privacy, security, module settings, data tools.
 *
 * @package Vazho
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
			<h1><?php esc_html_e( 'Settings', 'vazho' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Privacy, protection and module options. Security and privacy controls are always active — they are not modules.', 'vazho' ); ?></p>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Settings saved.', 'vazho' ); ?></div>
	<?php endif; ?>
	<?php if ( $secret_error ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The secret could not be encrypted (OpenSSL unavailable or AUTH_KEY missing). It was NOT saved in plaintext. Fix the server crypto setup and try again.', 'vazho' ); ?></div>
	<?php endif; ?>
	<?php if ( $flushed ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Cached AI answers cleared.', 'vazho' ); ?></div>
	<?php endif; ?>

	<?php
	$ssc_levels_txt = array(
		'ip'        => __( 'Anonymize visitor IP addresses', 'vazho' ),
		'policy'    => __( 'Add a link to your privacy policy', 'vazho' ),
		'consent'   => __( 'Require consent before storing contact data', 'vazho' ),
		'retention' => __( 'Limit how long conversations are kept', 'vazho' ),
		'ratelimit' => __( 'Turn rate limiting back on', 'vazho' ),
		'proxy'     => __( 'Choose the proxy header and list your proxy’s addresses: your site is behind a proxy', 'vazho' ),
		'https'     => __( 'Serve your site over HTTPS', 'vazho' ),
		'crypto'    => __( 'Fix server encryption (OpenSSL and AUTH_KEY)', 'vazho' ),
	);
	$ssc_has_mod_settings = $mod_count > 0;
	?>
	<div class="ssc-hub-wrap" data-ssc-hub="settings" data-initial="privacy">
	<section class="ssc-card ssc-hhero ssc-hhero--compact">
		<div class="ssc-hhero__ring">
			<?php echo SSC_Icons::ring( $health['percent'], 'ssc-ring--lg' . ( $health['percent'] >= 100 ? ' is-ok' : '' ), __( 'Privacy and protection health', 'vazho' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
		</div>
		<div class="ssc-hhero__main">
			<p class="ssc-hhero__level"><span><?php esc_html_e( 'Privacy & protection', 'vazho' ); ?></span></p>
			<h2><?php echo esc_html( $health['percent'] >= 100 ? __( 'Everything recommended is in place.', 'vazho' ) : __( 'A few settings are worth a look.', 'vazho' ) ); ?></h2>
			<p class="ssc-hhero__sub"><?php esc_html_e( 'Security and privacy controls are always active. This only checks the choices that are yours.', 'vazho' ); ?></p>
		</div>
		<?php if ( $health['steps'] ) : ?>
			<div class="ssc-hhero__steps">
				<h3><?php esc_html_e( 'Worth a look', 'vazho' ); ?></h3>
				<ul>
					<?php foreach ( $health['steps'] as $ssc_step ) : ?>
						<li><a href="#<?php echo esc_attr( $ssc_step['panel'] ); ?>" data-ssc-hubgo="<?php echo esc_attr( $ssc_step['panel'] ); ?>"><span class="ssc-hhero__stepico"><?php echo SSC_Icons::get( 'privacy' === $ssc_step['panel'] ? 'shield' : 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span><span><?php echo esc_html( $ssc_levels_txt[ $ssc_step['id'] ] ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</section>

	<nav class="<?php echo esc_attr( SSC_Admin_UI::tiles_class( $ssc_has_mod_settings ? 5 : 4 ) ); ?>" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'vazho' ); ?>" hidden>
		<?php
		SSC_Admin_UI::tile( array( 'id' => 'privacy', 'hue' => 'blue', 'icon' => 'shield', 'title' => __( 'Privacy', 'vazho' ), 'status' => $tiles['privacy'] ) );
		SSC_Admin_UI::tile( array( 'id' => 'display', 'hue' => 'green', 'icon' => 'eye', 'title' => __( 'Display & hours', 'vazho' ), 'status' => $tiles['display'] ) );
		SSC_Admin_UI::tile( array( 'id' => 'protection', 'hue' => 'red', 'icon' => 'lock', 'title' => __( 'Protection', 'vazho' ), 'status' => $tiles['protection'] ) );
		if ( $ssc_has_mod_settings ) {
			SSC_Admin_UI::tile( array( 'id' => 'modules', 'hue' => 'violet', 'icon' => 'puzzle', 'title' => __( 'Modules', 'vazho' ), 'status' => $tiles['modules'] ) );
		}
		SSC_Admin_UI::tile( array( 'id' => 'data', 'hue' => 'slate', 'icon' => 'database', 'title' => __( 'Data tools', 'vazho' ), 'status' => $tiles['data'] ) );
		?>
	</nav>

	<div class="ssc-hpanels">
	<form method="post" class="ssc-form" id="ssc-sform" data-ssc-hubform>
		<?php wp_nonce_field( 'ssc_settings' ); ?>
		<input type="hidden" name="ssc_settings_save" value="1" />

		<?php SSC_Admin_UI::panel_open( 'privacy', 'blue', 'shield', __( 'Privacy', 'vazho' ), __( 'What is stored about visitors, for how long, and what they are told.', 'vazho' ) ); ?>
		<?php if ( SSC_Modules::is_active( 'pharma' ) ) : ?>
		<section class="ssc-card">
			<h2><?php esc_html_e( 'Pharmaceutical answer policy', 'vazho' ); ?></h2>
			<div class="ssc-field">
				<label for="pharma_answer_mode"><?php esc_html_e( 'Allowed information sources', 'vazho' ); ?></label>
				<select id="pharma_answer_mode" name="pharma_answer_mode">
					<option value="approved_only" <?php selected( $s['pharma_answer_mode'], 'approved_only' ); ?>><?php esc_html_e( 'Approved company sources only', 'vazho' ); ?></option>
					<option value="general_education" <?php selected( $s['pharma_answer_mode'], 'general_education' ); ?>><?php esc_html_e( 'Company sources and general educational information', 'vazho' ); ?></option>
				</select>
				<p class="ssc-help"><?php esc_html_e( 'Both modes refer personal treatment questions to a healthcare professional. Product-specific claims require approved company sources. Test answers with your medical team before publication.', 'vazho' ); ?></p>
			</div>
		</section>
		<?php endif; ?>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Privacy & consent', 'vazho' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Applies to every form. Conversations are logged for quality review while the History module is on (the widget says so); browser transcripts and shared answer caching are disabled in pharmaceutical mode. Submitted forms are stored independently of chat history.', 'vazho' ); ?></p>
			<label class="ssc-check"><input type="checkbox" name="consent_enabled" value="yes" <?php checked( 'yes', $s['consent_enabled'] ); ?> /> <span><?php esc_html_e( 'Require explicit consent before storing contact data', 'vazho' ); ?></span></label>
			<div class="ssc-field">
				<label for="consent_text"><?php esc_html_e( 'Consent text', 'vazho' ); ?></label>
				<textarea id="consent_text" name="consent_text" rows="2"><?php echo esc_textarea( (string) $s['consent_text'] ); ?></textarea>
			</div>
			<div class="ssc-field">
				<label for="consent_link"><?php esc_html_e( 'Privacy policy link', 'vazho' ); ?></label>
				<input id="consent_link" name="consent_link" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['consent_link'] ); ?>" />
			</div>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="chatlog_retention_days"><?php esc_html_e( 'Conversation retention (days, 0 = keep forever)', 'vazho' ); ?></label>
					<input id="chatlog_retention_days" name="chatlog_retention_days" type="number" min="0" max="3650" value="<?php echo esc_attr( (string) $s['chatlog_retention_days'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="memory_days"><?php esc_html_e( 'Remember a visitor\'s conversation for (days)', 'vazho' ); ?></label>
					<input id="memory_days" name="memory_days" type="number" min="1" max="90" value="<?php echo esc_attr( (string) $s['memory_days'] ); ?>" />
					<p class="ssc-field__hint"><?php esc_html_e( 'A visitor who comes back within this time continues the same conversation, and the assistant still knows what was said. Pharmaceutical mode always uses one day and never keeps the chat in the browser.', 'vazho' ); ?></p>
				</div>
				<div class="ssc-field">
					<label for="submissions_retention_days"><?php esc_html_e( 'Request retention (days, 0 = keep forever; ADR cases are never auto-deleted)', 'vazho' ); ?></label>
					<input id="submissions_retention_days" name="submissions_retention_days" type="number" min="0" max="3650" value="<?php echo esc_attr( (string) $s['submissions_retention_days'] ); ?>" />
				</div>
			</div>
			<div class="ssc-field">
				<label for="ip_storage"><?php esc_html_e( 'Visitor IP addresses in logs and requests', 'vazho' ); ?></label>
				<select id="ip_storage" name="ip_storage">
					<option value="anonymize" <?php selected( $s['ip_storage'], 'anonymize' ); ?>><?php esc_html_e( 'Anonymized (network part only — recommended)', 'vazho' ); ?></option>
					<option value="full" <?php selected( $s['ip_storage'], 'full' ); ?>><?php esc_html_e( 'Full address', 'vazho' ); ?></option>
					<option value="none" <?php selected( $s['ip_storage'], 'none' ); ?>><?php esc_html_e( 'Do not store', 'vazho' ); ?></option>
				</select>
				<p class="ssc-field__hint"><?php esc_html_e( 'Rate limiting still works in every mode: it uses a one-way hash that is never stored with the conversation.', 'vazho' ); ?></p>
			</div>
			<label class="ssc-check"><input type="checkbox" name="chat_threads" value="yes" <?php checked( 'yes', $s['chat_threads'] ); ?> /> <span><?php esc_html_e( 'Signed-in users can reopen their previous conversations on any device', 'vazho' ); ?></span></label>
		</section>

		<?php SSC_Admin_UI::panel_close(); ?>

		<?php SSC_Admin_UI::panel_open( 'display', 'green', 'eye', __( 'Display & hours', 'vazho' ), __( 'Where and when the assistant appears, and how it behaves.', 'vazho' ) ); ?>
		<section class="ssc-card">
			<h2><?php esc_html_e( 'Where the widget appears', 'vazho' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Pages that fail these rules never load the chatbot CSS or JS — better performance and cleaner analytics.', 'vazho' ); ?></p>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="display_mode"><?php esc_html_e( 'Page targeting', 'vazho' ); ?></label>
					<select id="display_mode" name="display_mode">
						<option value="all" <?php selected( $s['display_mode'], 'all' ); ?>><?php esc_html_e( 'All pages', 'vazho' ); ?></option>
						<option value="include" <?php selected( $s['display_mode'], 'include' ); ?>><?php esc_html_e( 'Only these paths', 'vazho' ); ?></option>
						<option value="exclude" <?php selected( $s['display_mode'], 'exclude' ); ?>><?php esc_html_e( 'Everywhere except these paths', 'vazho' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="display_devices"><?php esc_html_e( 'Devices', 'vazho' ); ?></label>
					<select id="display_devices" name="display_devices">
						<option value="all" <?php selected( $s['display_devices'], 'all' ); ?>><?php esc_html_e( 'All devices', 'vazho' ); ?></option>
						<option value="desktop" <?php selected( $s['display_devices'], 'desktop' ); ?>><?php esc_html_e( 'Desktop only', 'vazho' ); ?></option>
						<option value="mobile" <?php selected( $s['display_devices'], 'mobile' ); ?>><?php esc_html_e( 'Mobile only', 'vazho' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="display_users"><?php esc_html_e( 'Visitors', 'vazho' ); ?></label>
					<select id="display_users" name="display_users">
						<option value="all" <?php selected( $s['display_users'], 'all' ); ?>><?php esc_html_e( 'Everyone', 'vazho' ); ?></option>
						<option value="guest" <?php selected( $s['display_users'], 'guest' ); ?>><?php esc_html_e( 'Logged-out visitors only', 'vazho' ); ?></option>
						<option value="logged_in" <?php selected( $s['display_users'], 'logged_in' ); ?>><?php esc_html_e( 'Logged-in users only', 'vazho' ); ?></option>
					</select>
				</div>
			</div>
			<div class="ssc-field">
				<label for="display_paths"><?php esc_html_e( 'Path patterns (one per line; * = wildcard)', 'vazho' ); ?></label>
				<textarea id="display_paths" name="display_paths" rows="3" dir="ltr" placeholder="/shop/*&#10;/contact&#10;/blog/*"><?php echo esc_textarea( (string) $s['display_paths'] ); ?></textarea>
			</div>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Business hours', 'vazho' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Outside these hours the widget shows an offline notice. Forms still work so visitors can leave a message.', 'vazho' ); ?></p>
			<?php $ssc_profile_hours = trim( (string) SSC_Settings::business()['hours'] ); ?>
			<p class="ssc-field__hint ssc-hoursnote">
				<?php
				if ( '' !== $ssc_profile_hours ) {
					/* translators: %s: the working hours written in the business profile. */
					echo esc_html( sprintf( __( 'This schedule only decides when the chat shows as online. What the assistant tells visitors about your hours is written in Business Knowledge: “%s”.', 'vazho' ), $ssc_profile_hours ) );
				} else {
					esc_html_e( 'This schedule only decides when the chat shows as online. You have not written your hours in Business Knowledge, so the assistant will quote this schedule.', 'vazho' );
				}
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-knowledge#contact' ) ); ?>"><?php esc_html_e( 'Open Contact & hours', 'vazho' ); ?></a>
			</p>
			<label class="ssc-check"><input type="checkbox" name="business_hours_enabled" value="yes" <?php checked( 'yes', $s['business_hours_enabled'] ); ?> /> <span><?php esc_html_e( 'Limit availability to business hours', 'vazho' ); ?></span></label>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="business_hours_start"><?php esc_html_e( 'Opens at', 'vazho' ); ?></label>
					<input id="business_hours_start" name="business_hours_start" type="time" dir="ltr" value="<?php echo esc_attr( (string) $s['business_hours_start'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="business_hours_end"><?php esc_html_e( 'Closes at', 'vazho' ); ?></label>
					<input id="business_hours_end" name="business_hours_end" type="time" dir="ltr" value="<?php echo esc_attr( (string) $s['business_hours_end'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="business_hours_days"><?php esc_html_e( 'Days (Mon=1 … Sun=7)', 'vazho' ); ?></label>
					<input id="business_hours_days" name="business_hours_days" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['business_hours_days'] ); ?>" placeholder="1,2,3,4,5" />
				</div>
				<div class="ssc-field">
					<label for="business_timezone"><?php esc_html_e( 'Timezone (empty = site timezone)', 'vazho' ); ?></label>
					<input id="business_timezone" name="business_timezone" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['business_timezone'] ); ?>" placeholder="Asia/Tehran" />
				</div>
			</div>
			<div class="ssc-field">
				<label for="offline_message"><?php esc_html_e( 'Offline message', 'vazho' ); ?></label>
				<textarea id="offline_message" name="offline_message" rows="2"><?php echo esc_textarea( (string) $s['offline_message'] ); ?></textarea>
			</div>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Widget behaviour', 'vazho' ); ?></h2>
			<label class="ssc-check"><input type="checkbox" name="streaming_enabled" value="yes" <?php checked( 'yes', $s['streaming_enabled'] ); ?> /> <span><?php esc_html_e( 'Stream AI answers as they are generated (faster first word; OpenAI, Claude, Gemini and compatible providers)', 'vazho' ); ?></span></label>
			<label class="ssc-check"><input type="checkbox" name="sound_enabled" value="yes" <?php checked( 'yes', $s['sound_enabled'] ); ?> /> <span><?php esc_html_e( 'Play a soft ping when a reply arrives while the chat is closed', 'vazho' ); ?></span></label>
		</section>

		<?php SSC_Admin_UI::panel_close(); ?>

		<?php SSC_Admin_UI::panel_open( 'protection', 'red', 'lock', __( 'Protection', 'vazho' ), __( 'Limits that keep bots and heavy use from costing you money.', 'vazho' ) ); ?>
		<section class="ssc-card">
			<h2><?php esc_html_e( 'Protection & limits', 'vazho' ); ?></h2>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="rate_limit_mode"><?php esc_html_e( 'Limit by', 'vazho' ); ?></label>
					<select id="rate_limit_mode" name="rate_limit_mode">
						<option value="ip" <?php selected( $s['rate_limit_mode'], 'ip' ); ?>><?php esc_html_e( 'IP address', 'vazho' ); ?></option>
						<option value="session" <?php selected( $s['rate_limit_mode'], 'session' ); ?>><?php esc_html_e( 'Browser session', 'vazho' ); ?></option>
						<option value="both" <?php selected( $s['rate_limit_mode'], 'both' ); ?>><?php esc_html_e( 'Both', 'vazho' ); ?></option>
						<option value="off" <?php selected( $s['rate_limit_mode'], 'off' ); ?>><?php esc_html_e( 'Off (not recommended)', 'vazho' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="chat_rate_limit"><?php esc_html_e( 'Chat messages / day / visitor', 'vazho' ); ?></label>
					<input id="chat_rate_limit" name="chat_rate_limit" type="number" min="0" value="<?php echo esc_attr( (string) $s['chat_rate_limit'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="submit_rate_limit"><?php esc_html_e( 'Form submissions / day / visitor', 'vazho' ); ?></label>
					<input id="submit_rate_limit" name="submit_rate_limit" type="number" min="0" value="<?php echo esc_attr( (string) $s['submit_rate_limit'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="session_rate_limit"><?php esc_html_e( 'Chat messages / day / session', 'vazho' ); ?></label>
					<input id="session_rate_limit" name="session_rate_limit" type="number" min="0" value="<?php echo esc_attr( (string) $s['session_rate_limit'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="trusted_proxy_header"><?php esc_html_e( 'Trusted proxy header (behind CDN / reverse proxy)', 'vazho' ); ?></label>
					<select id="trusted_proxy_header" name="trusted_proxy_header">
						<option value="" <?php selected( $s['trusted_proxy_header'], '' ); ?>><?php esc_html_e( 'None — use REMOTE_ADDR (direct connection)', 'vazho' ); ?></option>
						<option value="HTTP_CF_CONNECTING_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_CF_CONNECTING_IP' ); ?>>Cloudflare — CF-Connecting-IP</option>
						<option value="HTTP_X_FORWARDED_FOR" <?php selected( $s['trusted_proxy_header'], 'HTTP_X_FORWARDED_FOR' ); ?>>X-Forwarded-For</option>
						<option value="HTTP_X_REAL_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_X_REAL_IP' ); ?>>X-Real-IP (nginx)</option>
						<option value="HTTP_TRUE_CLIENT_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_TRUE_CLIENT_IP' ); ?>>True-Client-IP (Akamai)</option>
						<option value="HTTP_FASTLY_CLIENT_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_FASTLY_CLIENT_IP' ); ?>>Fastly-Client-IP</option>
						<option value="HTTP_X_CLIENT_IP" <?php selected( $s['trusted_proxy_header'], 'HTTP_X_CLIENT_IP' ); ?>>X-Client-IP</option>
					</select>
					<p class="ssc-field__hint"><?php esc_html_e( 'Required when the site sits behind Cloudflare or any reverse proxy — otherwise every visitor shares one IP and one rate-limit quota. Only enable the header your proxy actually sets and strips from client requests.', 'vazho' ); ?></p>
				</div>
				<div class="ssc-field">
					<label for="trusted_proxies"><?php esc_html_e( 'Trusted proxies (addresses or CIDR blocks, one per line)', 'vazho' ); ?></label>
					<textarea id="trusted_proxies" name="trusted_proxies" rows="3" dir="ltr" placeholder="203.0.113.0/24&#10;2001:db8::/32"><?php echo esc_textarea( (string) $s['trusted_proxies'] ); ?></textarea>
					<p class="ssc-field__hint"><?php esc_html_e( 'The header above is believed only when the connection comes from one of these addresses or from this server itself (for example nginx on the same machine). A visitor who reaches the server directly cannot choose his own address and slip past the limits. Cloudflare publishes its ranges at cloudflare.com/ips.', 'vazho' ); ?></p>
					<label class="ssc-check ssc-check--tight"><input type="checkbox" name="trust_private_proxies" value="yes" <?php checked( 'yes', $s['trust_private_proxies'] ); ?> /> <span><?php esc_html_e( 'Also believe a proxy on a private network (10.x, 172.16–31.x, 192.168.x)', 'vazho' ); ?></span></label>
					<p class="ssc-field__hint"><?php esc_html_e( 'Tick it only if your server can be reached from that network solely through your proxy, and the proxy removes any address a visitor sends. Shared carrier-grade addresses (100.64.x) are never included: list them above if they are your proxy.', 'vazho' ); ?></p>
					<button type="button" class="ssc-btn ssc-btn--ghost ssc-btn--sm" data-ssc-fill="trusted_proxies" data-text="<?php echo esc_attr( implode( "\n", SSC_Client_IP::cloudflare_ranges() ) ); ?>"><?php esc_html_e( 'Use Cloudflare’s ranges', 'vazho' ); ?></button>
					<?php
					$ssc_remote = isset( $_SERVER['REMOTE_ADDR'] ) ? SSC_Client_IP::clean( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
					if ( '' !== $ssc_remote ) :
						$ssc_listed = SSC_Client_IP::is_trusted( $ssc_remote, SSC_Client_IP::parse_list( (string) $s['trusted_proxies'] ), 'yes' === $s['trust_private_proxies'] );
						$ssc_seen   = ( new SSC_Chat_Engine() )->client_ip();
						?>
						<p class="ssc-field__hint ssc-proxynote">
							<?php
							if ( '' === (string) $s['trusted_proxy_header'] ) {
								/* translators: %s: the address of this connection. */
								echo esc_html( sprintf( __( 'Right now: no header is chosen, so the connecting address (%s) is used for visitors.', 'vazho' ), $ssc_remote ) );
							} elseif ( $ssc_listed ) {
								/* translators: 1: the address of this connection, 2: the visitor address read from the header. */
								echo esc_html( sprintf( __( 'Right now: your connection comes from %1$s, a proxy this site believes. The visitor address read from the header is %2$s.', 'vazho' ), $ssc_remote, $ssc_seen ) );
							} else {
								/* translators: %s: the address of this connection. */
								echo esc_html( sprintf( __( 'Right now: your connection comes from %s, which is not a listed proxy, so the header is ignored and that address is used for visitors.', 'vazho' ), $ssc_remote ) );
								if ( SSC_Client_IP::is_trusted( $ssc_remote, array(), true ) ) {
									echo ' ' . esc_html__( 'It is on a private network: if it is your proxy, tick the private-network option above.', 'vazho' );
								}
							}
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<?php SSC_Admin_UI::panel_close(); ?>

		<?php if ( $ssc_has_mod_settings ) : ?>
		<?php SSC_Admin_UI::panel_open( 'modules', 'violet', 'puzzle', __( 'Modules', 'vazho' ), __( 'The options of the modules you switched on. Open one to change it.', 'vazho' ) ); ?>
		<?php if ( SSC_Modules::is_active( 'pharma' ) ) : ?>
			<?php
			$adr_config  = SSC_Module_Pharma::form_config();
			$adr_presets = SSC_Module_Pharma::presets();
			?>
			<?php SSC_Admin_UI::acc_open( 'mod-pharma', SSC_Modules::get( 'pharma' )->icon(), __( 'Side-effect report form', 'vazho' ), 'rose' ); ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'Choose how long the form visitors fill in is. Reporter name, contact phone, product and the reaction description are always asked: without them a report is not a valid safety case.', 'vazho' ); ?></p>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="adr_preset"><?php esc_html_e( 'Form length', 'vazho' ); ?></label>
						<select id="adr_preset" name="adr_form[preset]">
							<?php foreach ( SSC_Module_Pharma::preset_labels() as $adr_pid => $adr_plabel ) : ?>
								<option value="<?php echo esc_attr( $adr_pid ); ?>" <?php selected( $adr_config['preset'], $adr_pid ); ?>><?php echo esc_html( $adr_plabel ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="ssc-field">
						<span class="ssc-field__label"><?php esc_html_e( 'Optional questions', 'vazho' ); ?></span>
						<input type="hidden" name="adr_form[collapse_optional]" value="no" />
						<label class="ssc-check"><input type="checkbox" name="adr_form[collapse_optional]" value="yes" <?php checked( 'yes', $adr_config['collapse_optional'] ); ?> /> <span><?php esc_html_e( 'Fold them under "More details (optional)" so the form looks short', 'vazho' ); ?></span></label>
					</div>
				</div>
				<div class="ssc-field">
					<label for="adr_intro"><?php esc_html_e( 'Short introduction above the form (optional)', 'vazho' ); ?></label>
					<textarea id="adr_intro" name="adr_form[intro]" rows="2" dir="auto" maxlength="400"><?php echo esc_textarea( $adr_config['intro'] ); ?></textarea>
				</div>

				<div class="ssc-table-scroll">
				<table class="widefat striped ssc-table ssc-adr-fields" data-presets="<?php echo esc_attr( wp_json_encode( $adr_presets ) ); ?>">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Question', 'vazho' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Ask', 'vazho' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Required', 'vazho' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Custom wording (optional)', 'vazho' ); ?></th>
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
									<th scope="row"><?php echo esc_html( $adr_field['label'] ); ?><?php echo $adr_locked ? ' <span class="ssc-badge">' . esc_html__( 'always', 'vazho' ) . '</span>' : ''; ?></th>
									<td><input type="checkbox" class="ssc-adr-on" name="adr_form[fields][<?php echo esc_attr( $adr_key ); ?>][on]" value="1" <?php checked( $adr_on ); ?> <?php disabled( $adr_locked ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: question */ __( 'Ask: %s', 'vazho' ), $adr_field['label'] ) ); ?>" /></td>
									<td><input type="checkbox" class="ssc-adr-req" name="adr_form[fields][<?php echo esc_attr( $adr_key ); ?>][req]" value="1" <?php checked( $adr_req ); ?> <?php disabled( $adr_locked ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: question */ __( 'Required: %s', 'vazho' ), $adr_field['label'] ) ); ?>" /></td>
									<td><input type="text" name="adr_form[fields][<?php echo esc_attr( $adr_key ); ?>][label]" value="<?php echo esc_attr( $adr_saved ? $adr_saved['label'] : '' ); ?>" dir="auto" placeholder="<?php echo esc_attr( $adr_field['label'] ); ?>" /></td>
								</tr>
								<?php
							endforeach;
						endforeach;
						?>
					</tbody>
				</table>
				</div>
				<p class="ssc-field__hint" id="ssc-adr-hint"><?php esc_html_e( '"Ask" and "Required" are editable in Custom mode; the other lengths use a fixed set. If the seriousness question is off, serious cases cannot be flagged for immediate notification.', 'vazho' ); ?></p>

				<h3><?php esc_html_e( 'Your own questions', 'vazho' ); ?></h3>
				<p class="ssc-card__sub"><?php esc_html_e( 'Added at the end of the form, whatever the length. Answers are saved with the question text, so editing a question later never changes old reports.', 'vazho' ); ?></p>
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
							<input type="text" name="adr_form[custom][<?php echo esc_attr( (string) $adr_i ); ?>][label]" value="<?php echo esc_attr( $adr_q['label'] ); ?>" dir="auto" placeholder="<?php esc_attr_e( 'Question text', 'vazho' ); ?>" />
							<select name="adr_form[custom][<?php echo esc_attr( (string) $adr_i ); ?>][type]" aria-label="<?php esc_attr_e( 'Answer type', 'vazho' ); ?>">
								<?php foreach ( SSC_Module_Pharma::custom_type_labels() as $adr_t => $adr_tl ) : ?>
									<option value="<?php echo esc_attr( $adr_t ); ?>" <?php selected( $adr_q['type'], $adr_t ); ?>><?php echo esc_html( $adr_tl ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" class="ssc-fieldrow__options" name="adr_form[custom][<?php echo esc_attr( (string) $adr_i ); ?>][options]" value="<?php echo esc_attr( implode( ', ', $adr_q['options'] ) ); ?>" dir="auto" placeholder="<?php esc_attr_e( 'Choices, comma separated', 'vazho' ); ?>" aria-label="<?php esc_attr_e( 'Choices', 'vazho' ); ?>" />
							<label class="ssc-check ssc-check--tight"><input type="checkbox" name="adr_form[custom][<?php echo esc_attr( (string) $adr_i ); ?>][required]" value="1" <?php checked( ! empty( $adr_q['required'] ) ); ?> /> <?php esc_html_e( 'Required', 'vazho' ); ?></label>
							<span></span>
							<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove question', 'vazho' ); ?>">×</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="ssc-btn ssc-btn--ghost ssc-field__add" data-target="#ssc-adr-custom"><?php esc_html_e( '+ Add question', 'vazho' ); ?></button>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'voice' ) ) : ?>
			<?php SSC_Admin_UI::acc_open( 'mod-voice', SSC_Modules::get( 'voice' )->icon(), __( 'Voice module', 'vazho' ), 'blue' ); ?>
				<label class="ssc-check"><input type="checkbox" name="voice_input" value="yes" <?php checked( 'yes', $s['voice_input'] ); ?> /> <span><?php esc_html_e( 'Microphone input', 'vazho' ); ?></span></label>
				<label class="ssc-check"><input type="checkbox" name="voice_output" value="yes" <?php checked( 'yes', $s['voice_output'] ); ?> /> <span><?php esc_html_e( 'Read answers aloud', 'vazho' ); ?></span></label>
				<div class="ssc-field">
					<label for="voice_language"><?php esc_html_e( 'Recognition language (or "auto")', 'vazho' ); ?></label>
					<input id="voice_language" name="voice_language" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['voice_language'] ); ?>" placeholder="auto / fa-IR / en-US" />
				</div>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'history' ) ) : ?>
			<?php SSC_Admin_UI::acc_open( 'mod-history', SSC_Modules::get( 'history' )->icon(), __( 'Conversation history module', 'vazho' ), 'amber' ); ?>
				<label class="ssc-check"><input type="checkbox" name="chatlog_enabled" value="yes" <?php checked( 'yes', $s['chatlog_enabled'] ); ?> /> <span><?php esc_html_e( 'Store conversations (needed for feedback, unanswered radar and quality review)', 'vazho' ); ?></span></label>
				<label class="ssc-check"><input type="checkbox" name="chatlog_notice" value="yes" <?php checked( 'yes', $s['chatlog_notice'] ); ?> /> <span><?php esc_html_e( 'Tell visitors in the chat window that conversations are saved', 'vazho' ); ?></span></label>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'csat' ) ) : ?>
			<?php SSC_Admin_UI::acc_open( 'mod-csat', SSC_Modules::get( 'csat' )->icon(), __( 'Satisfaction survey module', 'vazho' ), 'amber' ); ?>
				<label class="ssc-check"><input type="checkbox" name="csat_enabled" value="yes" <?php checked( 'yes', $s['csat_enabled'] ); ?> /> <span><?php esc_html_e( 'Show the end-of-conversation survey', 'vazho' ); ?></span></label>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'handoff' ) ) : ?>
			<?php SSC_Admin_UI::acc_open( 'mod-handoff', SSC_Modules::get( 'handoff' )->icon(), __( 'Human handoff module', 'vazho' ), 'violet' ); ?>
				<div class="ssc-field">
					<label for="handoff_text"><?php esc_html_e( 'Handoff message', 'vazho' ); ?></label>
					<textarea id="handoff_text" name="handoff_text" rows="2"><?php echo esc_textarea( (string) $s['handoff_text'] ); ?></textarea>
				</div>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'proactive' ) ) : ?>
			<?php SSC_Admin_UI::acc_open( 'mod-proactive', SSC_Modules::get( 'proactive' )->icon(), __( 'Proactive invitation module', 'vazho' ), 'blue' ); ?>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="proactive_delay"><?php esc_html_e( 'Delay before showing (seconds)', 'vazho' ); ?></label>
						<input id="proactive_delay" name="proactive_delay" type="number" min="2" max="120" value="<?php echo esc_attr( (string) $s['proactive_delay'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="proactive_text"><?php esc_html_e( 'Invitation text', 'vazho' ); ?></label>
						<input id="proactive_text" name="proactive_text" type="text" value="<?php echo esc_attr( (string) $s['proactive_text'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="proactive_trigger"><?php esc_html_e( 'Show the invitation', 'vazho' ); ?></label>
						<select id="proactive_trigger" name="proactive_trigger">
							<option value="delay" <?php selected( $s['proactive_trigger'], 'delay' ); ?>><?php esc_html_e( 'After the delay', 'vazho' ); ?></option>
							<option value="scroll" <?php selected( $s['proactive_trigger'], 'scroll' ); ?>><?php esc_html_e( 'After scrolling part of the page', 'vazho' ); ?></option>
							<option value="exit" <?php selected( $s['proactive_trigger'], 'exit' ); ?>><?php esc_html_e( 'When the visitor is about to leave (desktop; delay on phones)', 'vazho' ); ?></option>
						</select>
					</div>
					<div class="ssc-field">
						<label for="proactive_scroll"><?php esc_html_e( 'Scroll depth (%)', 'vazho' ); ?></label>
						<input id="proactive_scroll" name="proactive_scroll" type="number" min="10" max="100" value="<?php echo esc_attr( (string) $s['proactive_scroll'] ); ?>" />
					</div>
				</div>
				<div class="ssc-field">
					<label for="proactive_rules"><?php esc_html_e( 'Page-specific messages (one per line: path | message)', 'vazho' ); ?></label>
					<textarea id="proactive_rules" name="proactive_rules" rows="3" dir="auto" placeholder="/pricing* | Questions about plans? Ask me!&#10;/product/* | Need help choosing?"><?php echo esc_textarea( (string) $s['proactive_rules'] ); ?></textarea>
					<p class="ssc-field__hint"><?php esc_html_e( 'The first matching path wins; * matches anything. Pages without a match use the invitation text above. Use "-" as the message to show no invitation on those pages.', 'vazho' ); ?></p>
				</div>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'leads' ) ) : ?>
			<?php SSC_Admin_UI::acc_open( 'mod-leads', SSC_Modules::get( 'leads' )->icon(), __( 'Request form module', 'vazho' ), 'green' ); ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'The consultation form asks for a name, a phone number and a short description. Choose whether it also offers an email field, and add any custom fields you need. Server-side validation is generated from these definitions.', 'vazho' ); ?></p>
				<div class="ssc-field">
					<label for="consult_email"><?php esc_html_e( 'Email field', 'vazho' ); ?></label>
					<select id="consult_email" name="consult_email">
						<option value="optional" <?php selected( $s['consult_email'], 'optional' ); ?>><?php esc_html_e( 'Ask for an email address (optional for the visitor)', 'vazho' ); ?></option>
						<option value="off" <?php selected( $s['consult_email'], 'off' ); ?>><?php esc_html_e( 'Do not ask for an email address', 'vazho' ); ?></option>
					</select>
				</div>
				<div id="ssc-fields-list" class="ssc-fields">
					<?php $fields = SSC_Settings::form_fields(); ?>
					<?php $fields = $fields ? $fields : array( array( 'label' => '', 'type' => 'text', 'key' => '', 'required' => false, 'options' => array(), 'placeholder' => '' ) ); ?>
					<?php foreach ( $fields as $i => $f ) : ?>
						<div class="ssc-fieldrow">
							<input type="hidden" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][key]" value="<?php echo esc_attr( $f['key'] ); ?>" />
							<input type="text" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][label]" value="<?php echo esc_attr( $f['label'] ); ?>" placeholder="<?php esc_attr_e( 'Field label', 'vazho' ); ?>" />
							<select name="form_fields[<?php echo esc_attr( (string) $i ); ?>][type]" aria-label="<?php esc_attr_e( 'Field type', 'vazho' ); ?>">
								<?php foreach ( SSC_Settings::form_field_type_labels() as $ft => $ft_label ) : ?>
									<option value="<?php echo esc_attr( $ft ); ?>" <?php selected( $f['type'], $ft ); ?>><?php echo esc_html( $ft_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" class="ssc-fieldrow__options" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][options]" value="<?php echo esc_attr( implode( ', ', (array) $f['options'] ) ); ?>" placeholder="<?php esc_attr_e( 'Choices, comma separated (dropdown / radio only)', 'vazho' ); ?>" aria-label="<?php esc_attr_e( 'Choices', 'vazho' ); ?>" />
							<label class="ssc-check ssc-check--tight"><input type="checkbox" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][required]" value="1" <?php checked( ! empty( $f['required'] ) ); ?> /> <?php esc_html_e( 'Required', 'vazho' ); ?></label>
							<input type="text" name="form_fields[<?php echo esc_attr( (string) $i ); ?>][placeholder]" value="<?php echo esc_attr( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Placeholder', 'vazho' ); ?>" />
							<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove field', 'vazho' ); ?>">×</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="ssc-btn ssc-btn--ghost ssc-field__add" data-target="#ssc-fields-list"><?php esc_html_e( '+ Add field', 'vazho' ); ?></button>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'notifications' ) || SSC_Modules::is_active( 'live' ) || SSC_Modules::is_active( 'messenger' ) ) : ?>
			<?php $ssc_bot_status = get_transient( 'ssc_messenger_status' ); ?>
			<?php SSC_Admin_UI::acc_open( 'mod-bot', SSC_Modules::get( 'notifications' )->icon(), __( 'Bale / Telegram bot', 'vazho' ), 'violet' ); ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'One bot serves alerts, operator replies and customer chats. Create it with @BotFather (Telegram) or @botfather in Bale, then paste its token here. On servers inside Iran use Bale: Telegram is not reachable from there.', 'vazho' ); ?></p>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="notify_platform"><?php esc_html_e( 'Messenger platform', 'vazho' ); ?></label>
						<select id="notify_platform" name="notify_platform">
							<option value="bale" <?php selected( $s['notify_platform'], 'bale' ); ?>><?php esc_html_e( 'Bale', 'vazho' ); ?></option>
							<option value="telegram" <?php selected( $s['notify_platform'], 'telegram' ); ?>><?php esc_html_e( 'Telegram', 'vazho' ); ?></option>
						</select>
					</div>
					<div class="ssc-field">
						<label for="notify_token"><?php esc_html_e( 'Bot token', 'vazho' ); ?></label>
						<input id="notify_token" name="notify_token" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( 'notify_token' ) ? esc_attr__( 'A token is stored — type to replace', 'vazho' ) : ''; ?>" />
						<?php if ( SSC_Settings::has_secret( 'notify_token' ) ) : ?>
							<label class="ssc-check ssc-check--tight"><input type="checkbox" name="notify_token_clear" value="1" /> <span><?php esc_html_e( 'Remove the stored token', 'vazho' ); ?></span></label>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( SSC_Modules::is_active( 'live' ) || SSC_Modules::is_active( 'messenger' ) ) : ?>
					<div class="ssc-field">
						<label for="messenger_mode"><?php esc_html_e( 'How messages reach this site', 'vazho' ); ?></label>
						<select id="messenger_mode" name="messenger_mode">
							<option value="webhook" <?php selected( $s['messenger_mode'], 'webhook' ); ?>><?php esc_html_e( 'Instantly (webhook) — recommended for a public HTTPS site', 'vazho' ); ?></option>
							<option value="polling" <?php selected( $s['messenger_mode'], 'polling' ); ?>><?php esc_html_e( 'Checked every minute (polling) — for sites the messenger cannot reach', 'vazho' ); ?></option>
						</select>
						<p class="ssc-field__hint"><?php esc_html_e( 'With polling, replies also arrive within seconds while the Live chat screen is open.', 'vazho' ); ?></p>
					</div>
					<?php if ( is_array( $ssc_bot_status ) ) : ?>
						<p class="ssc-notice <?php echo empty( $ssc_bot_status['ok'] ) ? 'ssc-notice--error' : 'ssc-notice--success'; ?>" dir="auto"><?php echo esc_html( (string) $ssc_bot_status['text'] ); ?></p>
					<?php endif; ?>
					<button type="submit" name="ssc_messenger_connect" value="1" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Save and connect the bot', 'vazho' ); ?></button>
				<?php endif; ?>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'live' ) ) : ?>
			<?php SSC_Admin_UI::acc_open( 'mod-live', SSC_Modules::get( 'live' )->icon(), __( 'Live chat module', 'vazho' ), 'violet' ); ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'Administrators can always answer. Choose other team members who may answer chats, too; each operator can connect their own Bale/Telegram account on the Live chat screen.', 'vazho' ); ?></p>
				<?php
				$ssc_candidates = get_users(
					array(
						'role__not_in' => array( 'administrator', 'subscriber', 'customer' ),
						'number'       => 200,
						'orderby'      => 'display_name',
					)
				);
				$ssc_ops        = array_map( 'intval', (array) $s['live_operators'] );
				?>
				<fieldset class="ssc-field">
					<legend class="ssc-field__label"><?php esc_html_e( 'Operators', 'vazho' ); ?></legend>
					<input type="hidden" name="live_operators[]" value="0" />
					<?php if ( ! $ssc_candidates ) : ?>
						<p class="ssc-field__hint"><?php esc_html_e( 'No other team members yet (editors, shop managers…). Administrators answer chats.', 'vazho' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $ssc_candidates as $ssc_user ) : ?>
						<label class="ssc-check ssc-check--tight"><input type="checkbox" name="live_operators[]" value="<?php echo esc_attr( (string) $ssc_user->ID ); ?>" <?php checked( in_array( (int) $ssc_user->ID, $ssc_ops, true ) ); ?> /> <span><?php echo esc_html( $ssc_user->display_name ); ?></span></label>
					<?php endforeach; ?>
				</fieldset>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="live_assign"><?php esc_html_e( 'Who gets a waiting chat', 'vazho' ); ?></label>
						<select id="live_assign" name="live_assign">
							<option value="auto" <?php selected( $s['live_assign'], 'auto' ); ?>><?php esc_html_e( 'The available operator with the fewest open chats', 'vazho' ); ?></option>
							<option value="manual" <?php selected( $s['live_assign'], 'manual' ); ?>><?php esc_html_e( 'Everyone is notified; the first to take it answers', 'vazho' ); ?></option>
						</select>
					</div>
					<div class="ssc-field">
						<label for="live_wait_minutes"><?php esc_html_e( 'Offer the request form after (minutes without an answer)', 'vazho' ); ?></label>
						<input id="live_wait_minutes" name="live_wait_minutes" type="number" min="1" max="30" value="<?php echo esc_attr( (string) $s['live_wait_minutes'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="live_retention_days"><?php esc_html_e( 'Keep chat transcripts for (days)', 'vazho' ); ?></label>
						<input id="live_retention_days" name="live_retention_days" type="number" min="1" max="365" value="<?php echo esc_attr( (string) $s['live_retention_days'] ); ?>" />
					</div>
				</div>
				<div class="ssc-field">
					<label for="live_join_text"><?php esc_html_e( 'Greeting when an operator joins ({name} = operator name)', 'vazho' ); ?></label>
					<input id="live_join_text" name="live_join_text" type="text" dir="auto" value="<?php echo esc_attr( (string) $s['live_join_text'] ); ?>" placeholder="<?php echo esc_attr( SSC_Module_Live::join_text( get_current_user_id() ) ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="live_offline_text"><?php esc_html_e( 'Message when nobody can answer', 'vazho' ); ?></label>
					<input id="live_offline_text" name="live_offline_text" type="text" dir="auto" value="<?php echo esc_attr( (string) $s['live_offline_text'] ); ?>" placeholder="<?php echo esc_attr( SSC_Module_Live::offline_text() ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="live_canned"><?php esc_html_e( 'Saved replies (one per line)', 'vazho' ); ?></label>
					<textarea id="live_canned" name="live_canned" rows="4" dir="auto"><?php echo esc_textarea( (string) $s['live_canned'] ); ?></textarea>
				</div>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'woocommerce' ) ) : ?>
			<?php SSC_Admin_UI::acc_open( 'mod-woocommerce', SSC_Modules::get( 'woocommerce' )->icon(), __( 'WooCommerce sales assistant', 'vazho' ), 'green' ); ?>
				<?php if ( ! SSC_Module_Woo::wc() ) : ?>
					<p class="ssc-notice ssc-notice--warn"><?php esc_html_e( 'WooCommerce is not active on this site, so this module has nothing to do yet.', 'vazho' ); ?></p>
				<?php endif; ?>
				<label class="ssc-check"><input type="checkbox" name="woo_product_search" value="yes" <?php checked( 'yes', $s['woo_product_search'] ); ?> /> <span><?php esc_html_e( 'Answer with real prices and stock, and show product cards with "Add to cart"', 'vazho' ); ?></span></label>
				<div class="ssc-field">
					<label for="woo_cards"><?php esc_html_e( 'Products per answer', 'vazho' ); ?></label>
					<input id="woo_cards" name="woo_cards" type="number" min="1" max="6" value="<?php echo esc_attr( (string) $s['woo_cards'] ); ?>" />
				</div>
				<label class="ssc-check"><input type="checkbox" name="woo_order_tracking" value="yes" <?php checked( 'yes', $s['woo_order_tracking'] ); ?> /> <span><?php esc_html_e( 'Order tracking: customers check an order with its number and their phone or email', 'vazho' ); ?></span></label>

				<h3><?php esc_html_e( 'Smart discount for hesitating visitors', 'vazho' ); ?></h3>
				<label class="ssc-check"><input type="checkbox" name="woo_coupon_enabled" value="yes" <?php checked( 'yes', $s['woo_coupon_enabled'] ); ?> /> <span><?php esc_html_e( 'Offer a single-use discount code', 'vazho' ); ?></span></label>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="woo_coupon_trigger"><?php esc_html_e( 'When to offer it', 'vazho' ); ?></label>
						<select id="woo_coupon_trigger" name="woo_coupon_trigger">
							<option value="both" <?php selected( $s['woo_coupon_trigger'], 'both' ); ?>><?php esc_html_e( 'About to leave, or lingering on a product or cart page', 'vazho' ); ?></option>
							<option value="exit" <?php selected( $s['woo_coupon_trigger'], 'exit' ); ?>><?php esc_html_e( 'Only when about to leave (desktop)', 'vazho' ); ?></option>
							<option value="idle" <?php selected( $s['woo_coupon_trigger'], 'idle' ); ?>><?php esc_html_e( 'Only when lingering on a product or cart page', 'vazho' ); ?></option>
						</select>
					</div>
					<div class="ssc-field">
						<label for="woo_coupon_idle"><?php esc_html_e( 'Lingering means (seconds)', 'vazho' ); ?></label>
						<input id="woo_coupon_idle" name="woo_coupon_idle" type="number" min="10" max="600" value="<?php echo esc_attr( (string) $s['woo_coupon_idle'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="woo_coupon_type"><?php esc_html_e( 'Discount', 'vazho' ); ?></label>
						<select id="woo_coupon_type" name="woo_coupon_type">
							<option value="percent" <?php selected( $s['woo_coupon_type'], 'percent' ); ?>><?php esc_html_e( 'Percent of the cart', 'vazho' ); ?></option>
							<option value="fixed_cart" <?php selected( $s['woo_coupon_type'], 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed amount off the cart', 'vazho' ); ?></option>
						</select>
					</div>
					<div class="ssc-field">
						<label for="woo_coupon_amount"><?php esc_html_e( 'Amount (percent, or in the shop currency)', 'vazho' ); ?></label>
						<input id="woo_coupon_amount" name="woo_coupon_amount" type="number" min="1" value="<?php echo esc_attr( (string) $s['woo_coupon_amount'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="woo_coupon_min"><?php esc_html_e( 'Minimum cart total (0 = none)', 'vazho' ); ?></label>
						<input id="woo_coupon_min" name="woo_coupon_min" type="number" min="0" value="<?php echo esc_attr( (string) $s['woo_coupon_min'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="woo_coupon_hours"><?php esc_html_e( 'Code valid for (hours)', 'vazho' ); ?></label>
						<input id="woo_coupon_hours" name="woo_coupon_hours" type="number" min="1" max="720" value="<?php echo esc_attr( (string) $s['woo_coupon_hours'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="woo_coupon_daily"><?php esc_html_e( 'Most codes per day', 'vazho' ); ?></label>
						<input id="woo_coupon_daily" name="woo_coupon_daily" type="number" min="1" max="10000" value="<?php echo esc_attr( (string) $s['woo_coupon_daily'] ); ?>" />
					</div>
				</div>
				<div class="ssc-field">
					<label for="woo_coupon_text"><?php esc_html_e( 'Offer text (empty = automatic)', 'vazho' ); ?></label>
					<input id="woo_coupon_text" name="woo_coupon_text" type="text" dir="auto" value="<?php echo esc_attr( (string) $s['woo_coupon_text'] ); ?>" placeholder="<?php echo esc_attr( SSC_Module_Woo::wc() ? SSC_Module_Woo::coupon_teaser() : '' ); ?>" />
					<p class="ssc-field__hint"><?php esc_html_e( 'The code is created only when the visitor clicks the offer; each visitor gets one code, each code works once.', 'vazho' ); ?></p>
				</div>

				<h3><?php esc_html_e( 'Cart reminder by SMS', 'vazho' ); ?></h3>
				<label class="ssc-check"><input type="checkbox" name="woo_abandoned_enabled" value="yes" <?php checked( 'yes', $s['woo_abandoned_enabled'] ); ?> /> <span><?php esc_html_e( 'Let visitors with items in their cart ask for one SMS reminder', 'vazho' ); ?></span></label>
				<?php if ( ! SSC_Modules::is_active( 'sms' ) ) : ?>
					<p class="ssc-field__hint"><?php esc_html_e( 'Needs the SMS module.', 'vazho' ); ?></p>
				<?php endif; ?>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="woo_abandoned_hours"><?php esc_html_e( 'Send the reminder after (hours without an order)', 'vazho' ); ?></label>
						<input id="woo_abandoned_hours" name="woo_abandoned_hours" type="number" min="1" max="72" value="<?php echo esc_attr( (string) $s['woo_abandoned_hours'] ); ?>" />
					</div>
				</div>
				<div class="ssc-field">
					<label for="woo_abandoned_text"><?php esc_html_e( 'Reminder text ({site}, {items}, {total}, {cart_url})', 'vazho' ); ?></label>
					<textarea id="woo_abandoned_text" name="woo_abandoned_text" rows="2" dir="auto" placeholder="<?php echo esc_attr( SSC_Module_Woo::reminder_text( array( 'cart' => '[]', 'total' => '' ) ) ); ?>"><?php echo esc_textarea( (string) $s['woo_abandoned_text'] ); ?></textarea>
				</div>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'sms' ) ) : ?>
			<?php $ssc_sms_status = get_transient( 'ssc_sms_status' ); ?>
			<?php SSC_Admin_UI::acc_open( 'mod-sms', SSC_Modules::get( 'sms' )->icon(), __( 'SMS module', 'vazho' ), 'violet' ); ?>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="sms_provider"><?php esc_html_e( 'SMS panel', 'vazho' ); ?></label>
						<select id="sms_provider" name="sms_provider">
							<?php foreach ( SSC_Module_Sms::providers() as $ssc_pid => $ssc_plabel ) : ?>
								<option value="<?php echo esc_attr( $ssc_pid ); ?>" <?php selected( $s['sms_provider'], $ssc_pid ); ?>><?php echo esc_html( $ssc_plabel ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="ssc-field">
						<label for="sms_sender"><?php esc_html_e( 'Sender line number', 'vazho' ); ?></label>
						<input id="sms_sender" name="sms_sender" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['sms_sender'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="sms_username"><?php esc_html_e( 'Username (Melipayamak only)', 'vazho' ); ?></label>
						<input id="sms_username" name="sms_username" type="text" dir="ltr" autocomplete="off" value="<?php echo esc_attr( (string) $s['sms_username'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="sms_api_key"><?php esc_html_e( 'API key (Melipayamak: password)', 'vazho' ); ?></label>
						<input id="sms_api_key" name="sms_api_key" type="password" dir="ltr" autocomplete="off" value="" placeholder="<?php echo SSC_Settings::has_secret( 'sms_api_key' ) ? esc_attr__( 'A key is stored — type to replace', 'vazho' ) : ''; ?>" />
					</div>
				</div>
				<label class="ssc-check"><input type="checkbox" name="sms_notify_admin" value="yes" <?php checked( 'yes', $s['sms_notify_admin'] ); ?> /> <span><?php esc_html_e( 'Text me when a new request or report arrives', 'vazho' ); ?></span></label>
				<div class="ssc-grid ssc-grid--2">
					<div class="ssc-field">
						<label for="sms_admin_phone"><?php esc_html_e( 'My mobile number', 'vazho' ); ?></label>
						<input id="sms_admin_phone" name="sms_admin_phone" type="tel" dir="ltr" value="<?php echo esc_attr( (string) $s['sms_admin_phone'] ); ?>" />
					</div>
					<div class="ssc-field">
						<label for="sms_test_phone"><?php esc_html_e( 'Send a test SMS to', 'vazho' ); ?></label>
						<input id="sms_test_phone" name="sms_test_phone" type="tel" dir="ltr" value="" placeholder="09…" />
					</div>
				</div>
				<?php if ( is_array( $ssc_sms_status ) ) : ?>
					<p class="ssc-notice <?php echo empty( $ssc_sms_status['ok'] ) ? 'ssc-notice--error' : 'ssc-notice--success'; ?>" dir="auto"><?php echo esc_html( (string) $ssc_sms_status['text'] ); ?></p>
				<?php endif; ?>
				<button type="submit" name="ssc_sms_test" value="1" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Save and send a test SMS', 'vazho' ); ?></button>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'messenger' ) ) : ?>
			<?php SSC_Admin_UI::acc_open( 'mod-messenger', SSC_Modules::get( 'messenger' )->icon(), __( 'Messenger bot module', 'vazho' ), 'violet' ); ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'Customers open your bot and chat with the assistant: same knowledge, same rules and limits as the website chat. Share the bot link on your site, Instagram bio and invoices.', 'vazho' ); ?></p>
				<?php $ssc_me = SSC_Messenger::ready() ? SSC_Messenger::bot_info() : array(); ?>
				<?php if ( ! empty( $ssc_me['username'] ) ) : ?>
					<?php $ssc_bot_link = ( 'telegram' === SSC_Messenger::platform() ? 'https://t.me/' : 'https://ble.ir/' ) . $ssc_me['username']; ?>
					<p><?php esc_html_e( 'Your bot:', 'vazho' ); ?> <a href="<?php echo esc_url( $ssc_bot_link ); ?>" target="_blank" rel="noopener noreferrer" dir="ltr"><?php echo esc_html( $ssc_bot_link ); ?></a></p>
				<?php elseif ( ! SSC_Messenger::ready() ) : ?>
					<p class="ssc-notice ssc-notice--warn"><?php esc_html_e( 'Add the bot token in the "Bale / Telegram bot" card, then press "Save and connect the bot".', 'vazho' ); ?></p>
				<?php endif; ?>
				<div class="ssc-field">
					<label for="messenger_welcome"><?php esc_html_e( 'Welcome message for /start (empty = the website welcome text)', 'vazho' ); ?></label>
					<textarea id="messenger_welcome" name="messenger_welcome" rows="3" dir="auto"><?php echo esc_textarea( (string) $s['messenger_welcome'] ); ?></textarea>
				</div>
				<?php if ( SSC_Modules::is_active( 'live' ) ) : ?>
					<p class="ssc-field__hint"><?php esc_html_e( 'With Live chat on, these chats appear in the Live chat inbox and customers can ask for a person with the button or /human.', 'vazho' ); ?></p>
				<?php endif; ?>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>

		<?php if ( SSC_Modules::is_active( 'notifications' ) ) : ?>
			<?php SSC_Admin_UI::acc_open( 'mod-notifications', SSC_Modules::get( 'notifications' )->icon(), __( 'Notifications module', 'vazho' ), 'violet' ); ?>
				<div class="ssc-field">
					<label for="notify_chat_id"><?php esc_html_e( 'Chat / channel ID for alerts', 'vazho' ); ?></label>
					<input id="notify_chat_id" name="notify_chat_id" type="text" dir="ltr" value="<?php echo esc_attr( (string) $s['notify_chat_id'] ); ?>" />
					<p class="ssc-field__hint"><?php esc_html_e( 'New requests are posted here through the bot configured in the "Bale / Telegram bot" card.', 'vazho' ); ?></p>
				</div>
				<label class="ssc-check"><input type="checkbox" name="notify_email_enabled" value="yes" <?php checked( 'yes', $s['notify_email_enabled'] ); ?> /> <span><?php esc_html_e( 'Also send email notifications', 'vazho' ); ?></span></label>
				<div class="ssc-field">
					<label for="notify_email_to"><?php esc_html_e( 'Email recipient (empty = site admin)', 'vazho' ); ?></label>
					<input id="notify_email_to" name="notify_email_to" type="email" dir="ltr" value="<?php echo esc_attr( (string) $s['notify_email_to'] ); ?>" />
				</div>
			<?php SSC_Admin_UI::acc_close(); ?>
		<?php endif; ?>
		<?php SSC_Admin_UI::panel_close(); ?>
		<?php endif; ?>

		<?php SSC_Admin_UI::save_bar( __( 'Save settings', 'vazho' ) ); ?>
	</form>

	<?php SSC_Admin_UI::panel_open( 'data', 'slate', 'database', __( 'Data tools', 'vazho' ), __( 'Clear caches and decide what happens to your data when the plugin is deleted.', 'vazho' ), false ); ?>
	<section class="ssc-card">
		<p class="ssc-card__sub"><?php esc_html_e( 'To back up everything you set up, or to move it to another website, use Export & Import.', 'vazho' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-transfer' ) ); ?>"><?php esc_html_e( 'Open Export & Import', 'vazho' ); ?></a></p>
		<form method="post">
			<?php wp_nonce_field( 'ssc_tools' ); ?>
			<button type="submit" name="ssc_flush_cache" value="1" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Clear cached AI answers', 'vazho' ); ?></button>
		</form>
		<form method="post" class="ssc-mt">
			<?php wp_nonce_field( 'ssc_tools' ); ?>
			<input type="hidden" name="ssc_delete_policy" value="1" />
			<label class="ssc-check">
				<input type="checkbox" name="delete_on_uninstall" value="yes" <?php checked( 'yes', get_option( 'ssc_chatbot_delete_on_uninstall', 'no' ) ); ?> />
				<span><?php esc_html_e( 'Remove ALL plugin data (settings, knowledge, requests, ADR cases, logs) when the plugin is deleted. Set this policy before deactivation; an inactive plugin cannot display a deletion prompt. Keep this off to preserve your data.', 'vazho' ); ?></span>
			</label>
			<button type="submit" class="ssc-btn ssc-btn--ghost ssc-btn--danger"><?php esc_html_e( 'Save data policy', 'vazho' ); ?></button>
		</form>
	</section>
	<?php SSC_Admin_UI::panel_close(); ?>
	</div>
	</div>
</div>

