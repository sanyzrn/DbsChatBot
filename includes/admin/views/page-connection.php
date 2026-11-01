<?php
/**
 * AI Connection view: a status hero, four sections (engine, answers, web
 * search, advanced) and one save bar.
 *
 * @package Vazho
 * @var array            $s            Settings.
 * @var SSC_Provider[]   $providers    Provider objects.
 * @var array            $labels       Provider labels.
 * @var bool             $verified     Current verification validity.
 * @var array            $conn_status  SSC_Setup::connection_status().
 * @var array            $verify_state Verification state.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$saved        = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$secret_error = isset( $_GET['secret_error'] ) ? (int) $_GET['secret_error'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$sel          = (string) $s['ai_provider'];
$ssc_pharma   = SSC_Modules::is_active( 'pharma' );

/* How far the connection is: an engine, its key, a passing test. "No engine" is a complete offline setup. */
$ssc_needs_key = isset( $providers[ $sel ] ) && $providers[ $sel ]->needs_key();
$ssc_has_key   = ! $ssc_needs_key || SSC_Settings::has_secret( $sel . '_api_key' );
if ( 'none' === $sel ) {
	$ssc_percent = 100;
} else {
	$ssc_percent = (int) round( 100 * ( 1 + ( $ssc_has_key ? 1 : 0 ) + ( $verified ? 1 : 0 ) ) / 3 );
}
$ssc_label = isset( $labels[ $sel ] ) ? $labels[ $sel ] : $sel;
$ssc_model = 'none' !== $sel ? (string) SSC_Settings::get( $sel . '_model', '' ) : '';

$ssc_provider_meta = array(
	'none'       => array( 'shield', __( 'Answers from your knowledge only. Nothing leaves your server.', 'vazho' ) ),
	'openai'     => array( 'cpu', __( 'GPT models. Your own API key.', 'vazho' ) ),
	'gemini'     => array( 'sparkles', __( 'Google Gemini. Your own API key.', 'vazho' ) ),
	'claude'     => array( 'chat', __( 'Anthropic Claude. Your own API key.', 'vazho' ) ),
	'openrouter' => array( 'layers', __( 'Many models through one key.', 'vazho' ) ),
	'custom'     => array( 'code', __( 'Any OpenAI-compatible endpoint, such as a local gateway.', 'vazho' ) ),
	'webhook'    => array( 'link', __( 'Your own service, called with a signed request.', 'vazho' ) ),
);
$ssc_scope_labels = array(
	'knowledge' => __( 'Only from my knowledge', 'vazho' ),
	'business'  => __( 'My business and its field', 'vazho' ),
	'open'      => __( 'Any question', 'vazho' ),
);
$ssc_domains = count( array_filter( preg_split( '/\s+/', (string) $s['web_search_domains'] ) ) );
$ssc_qa      = array(
	'ai_first'   => __( 'AI first', 'vazho' ),
	'bank_first' => __( 'FAQ bank first', 'vazho' ),
	'bank_only'  => __( 'FAQ bank only', 'vazho' ),
);
$ssc_steps = array();
if ( 'none' !== $sel && ! $ssc_has_key ) {
	$ssc_steps[] = array( 'engine', __( 'Paste your API key', 'vazho' ), 'key' );
}
if ( 'none' !== $sel && ! $verified ) {
	$ssc_steps[] = array( 'engine', __( 'Run a connection test', 'vazho' ), 'zap' );
}
?>
<div class="ssc-page ssc-conn">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'AI Connection', 'vazho' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Keys are stored encrypted and are never shown in the browser. They are sent only to the provider you choose, when a request is made. Connection tests perform a real generation, not just a ping.', 'vazho' ); ?></p>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Connection settings saved.', 'vazho' ); ?></div>
	<?php endif; ?>
	<?php if ( $secret_error ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The API key could not be encrypted (OpenSSL unavailable or AUTH_KEY missing). It was NOT saved in plaintext. Fix the server crypto setup and try again.', 'vazho' ); ?></div>
	<?php endif; ?>

	<div class="ssc-hub-wrap" data-ssc-hub="connection" data-initial="engine">
	<section class="ssc-card ssc-hhero ssc-hhero--compact">
		<div class="ssc-hhero__ring">
			<?php echo SSC_Icons::ring( $ssc_percent, 'ssc-ring--lg' . ( $ssc_percent >= 100 ? ' is-ok' : '' ), __( 'How far the connection is set up', 'vazho' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
		</div>
		<div class="ssc-hhero__main" data-ssc-connhero data-ok-title="<?php esc_attr_e( 'Connected and verified', 'vazho' ); ?>" data-ok-sub="<?php esc_attr_e( 'Verified just now for the current provider, key, model and endpoint.', 'vazho' ); ?>" data-fail-title="<?php esc_attr_e( 'The last connection test failed', 'vazho' ); ?>">
			<p class="ssc-hhero__level"><span><?php echo esc_html( $ssc_label . ( '' !== $ssc_model ? ' · ' . $ssc_model : '' ) ); ?></span></p>
			<h2>
				<?php
				if ( 'none' === $sel ) {
					esc_html_e( 'Running without an AI engine', 'vazho' );
				} elseif ( $verified ) {
					esc_html_e( 'Connected and verified', 'vazho' );
				} elseif ( ! empty( $conn_status['attempt'] ) && empty( $conn_status['attempt']['ok'] ) ) {
					esc_html_e( 'The last connection test failed', 'vazho' );
				} else {
					esc_html_e( 'Almost there: verify the connection', 'vazho' );
				}
				?>
			</h2>
			<p class="ssc-hhero__sub">
				<?php
				if ( 'none' === $sel ) {
					esc_html_e( 'Answers come from your FAQ bank and knowledge. You can connect an AI engine whenever you like.', 'vazho' );
				} elseif ( $verified ) {
					echo esc_html( sprintf( /* translators: %s: time span such as "3 hours". */ __( 'Verified %s ago for the current provider, key, model and endpoint.', 'vazho' ), human_time_diff( (int) $verify_state['at'] ) ) );
				} elseif ( ! empty( $conn_status['attempt'] ) && empty( $conn_status['attempt']['ok'] ) ) {
					$ssc_attempt = $conn_status['attempt'];
					echo esc_html( sprintf( /* translators: 1: time span such as "3 hours", 2: the reason. */ __( 'Failed %1$s ago: %2$s', 'vazho' ), human_time_diff( (int) $ssc_attempt['at'] ), SSC_HTTP::friendly_error( (string) $ssc_attempt['code'] ) ) );
					if ( ! empty( $conn_status['verified_at'] ) ) {
						echo ' ' . esc_html( sprintf( /* translators: %s: time span such as "3 hours". */ __( 'It last worked %s ago.', 'vazho' ), human_time_diff( (int) $conn_status['verified_at'] ) ) );
					}
					if ( ! empty( $ssc_attempt['request_id'] ) ) {
						echo ' ' . esc_html( sprintf( /* translators: %s: the provider's request id. */ __( 'Request id: %s', 'vazho' ), $ssc_attempt['request_id'] ) );
					}
				} else {
					esc_html_e( 'Changing the provider, key, model or endpoint invalidates previous verification. Run a test after every change.', 'vazho' );
				}
				?>
			</p>
		</div>
		<?php if ( $ssc_steps ) : ?>
			<div class="ssc-hhero__steps">
				<h3><?php esc_html_e( 'Next', 'vazho' ); ?></h3>
				<ul>
					<?php foreach ( $ssc_steps as $ssc_step ) : ?>
						<li><a href="#<?php echo esc_attr( $ssc_step[0] ); ?>" data-ssc-hubgo="<?php echo esc_attr( $ssc_step[0] ); ?>"><span class="ssc-hhero__stepico"><?php echo SSC_Icons::get( $ssc_step[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span><span><?php echo esc_html( $ssc_step[1] ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</section>

	<nav class="<?php echo esc_attr( SSC_Admin_UI::tiles_class( 4 ) ); ?>" role="tablist" aria-label="<?php esc_attr_e( 'Connection sections', 'vazho' ); ?>" hidden>
		<?php
		SSC_Admin_UI::tile( array( 'id' => 'engine', 'hue' => 'blue', 'icon' => 'cpu', 'title' => __( 'AI engine', 'vazho' ), 'status' => $ssc_label . ( '' !== $ssc_model ? ' · ' . $ssc_model : '' ) ) );
		SSC_Admin_UI::tile( array( 'id' => 'answers', 'hue' => 'violet', 'icon' => 'chat', 'title' => __( 'What it may answer', 'vazho' ), 'status' => $ssc_pharma ? __( 'Set by the pharmaceutical policy', 'vazho' ) : ( isset( $ssc_scope_labels[ $s['answer_scope'] ] ) ? $ssc_scope_labels[ $s['answer_scope'] ] : '' ) ) );
		SSC_Admin_UI::tile( array( 'id' => 'web', 'hue' => 'teal', 'icon' => 'globe', 'title' => __( 'Web search', 'vazho' ), 'status' => ( 'yes' === $s['web_search'] && ! $ssc_pharma ) ? ( $ssc_domains ? sprintf( /* translators: %d: number of sites. */ _n( 'On · %d site', 'On · %d sites', $ssc_domains, 'vazho' ), $ssc_domains ) : __( 'On', 'vazho' ) ) : __( 'Off', 'vazho' ) ) );
		SSC_Admin_UI::tile( array( 'id' => 'advanced', 'hue' => 'slate', 'icon' => 'sliders', 'title' => __( 'Advanced', 'vazho' ), 'status' => isset( $ssc_qa[ $s['qa_mode'] ] ) ? $ssc_qa[ $s['qa_mode'] ] : '' ) );
		?>
	</nav>

	<div class="ssc-hpanels">
	<form method="post" class="ssc-form" id="ssc-connection-form" data-ssc-hubform>
		<?php wp_nonce_field( 'ssc_connection' ); ?>
		<input type="hidden" name="ssc_connection_save" value="1" />

		<?php SSC_Admin_UI::panel_open( 'engine', 'blue', 'cpu', __( 'AI engine', 'vazho' ), __( 'Who writes the answers. Pick an engine, add its key, then test it.', 'vazho' ) ); ?>
		<section class="ssc-card">
			<div class="ssc-provider-cards" role="radiogroup" aria-label="<?php esc_attr_e( 'AI engine', 'vazho' ); ?>" hidden>
				<?php foreach ( $labels as $pid => $plabel ) : ?>
					<?php $ssc_meta = isset( $ssc_provider_meta[ $pid ] ) ? $ssc_provider_meta[ $pid ] : array( 'cpu', '' ); ?>
					<button type="button" role="radio" class="ssc-pcard<?php echo $sel === $pid ? ' is-on' : ''; ?>" data-ssc-provider="<?php echo esc_attr( $pid ); ?>" aria-checked="<?php echo $sel === $pid ? 'true' : 'false'; ?>">
						<span class="ssc-pcard__ico"><?php echo SSC_Icons::get( $ssc_meta[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
						<span class="ssc-pcard__txt"><strong><?php echo esc_html( $plabel ); ?></strong><small><?php echo esc_html( $ssc_meta[1] ); ?></small></span>
						<span class="ssc-pcard__tick"><?php echo SSC_Icons::get( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="ssc-field ssc-provider-select">
				<label for="ai_provider"><?php esc_html_e( 'AI engine', 'vazho' ); ?></label>
				<select id="ai_provider" name="ai_provider">
					<?php foreach ( $labels as $pid => $plabel ) : ?>
						<option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $sel, $pid ); ?>><?php echo esc_html( $plabel ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="ssc-field__hint"><?php esc_html_e( '“No AI engine” answers from your FAQ bank and knowledge only — nothing leaves your server.', 'vazho' ); ?></p>
				<p class="ssc-field__hint"><?php esc_html_e( 'The server must be able to reach the provider. Some providers do not serve every country, and servers inside Iran may be refused or blocked. If the connection test fails with an access or network error, host the site outside Iran or use the OpenAI-compatible endpoint with a gateway you can reach.', 'vazho' ); ?></p>
			</div>

			<?php foreach ( $providers as $pid => $provider ) : ?>
				<div class="ssc-provider-fields" data-provider="<?php echo esc_attr( $pid ); ?>" <?php echo ( $sel === $pid ) ? '' : 'hidden'; ?>>
					<?php if ( 'webhook' !== $pid ) : ?>
						<div class="ssc-grid ssc-grid--2">
							<?php if ( $provider->accepts_key() ) : ?>
								<div class="ssc-field">
									<label for="<?php echo esc_attr( $pid ); ?>_api_key"><?php echo $provider->needs_key() ? esc_html__( 'API key', 'vazho' ) : esc_html__( 'API key (optional)', 'vazho' ); ?></label>
									<input id="<?php echo esc_attr( $pid ); ?>_api_key" name="<?php echo esc_attr( $pid ); ?>_api_key" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( $pid . '_api_key' ) ? esc_attr__( 'A key is stored — type to replace', 'vazho' ) : esc_attr__( 'Paste your API key', 'vazho' ); ?>" />
									<?php if ( SSC_Settings::has_secret( $pid . '_api_key' ) ) : ?>
										<label class="ssc-check ssc-check--tight"><input type="checkbox" name="<?php echo esc_attr( $pid ); ?>_api_key_clear" value="1" /> <span><?php esc_html_e( 'Remove the stored key', 'vazho' ); ?></span></label>
									<?php endif; ?>
									<?php if ( ! $provider->needs_key() ) : ?>
										<p class="ssc-field__hint"><?php esc_html_e( 'Most hosted services need a key; it is sent as a Bearer token. Leave it empty for a local gateway that needs none.', 'vazho' ); ?></p>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<div class="ssc-field">
								<label for="<?php echo esc_attr( $pid ); ?>_model"><?php esc_html_e( 'Model', 'vazho' ); ?></label>
								<?php $models = $provider->models(); ?>
								<?php if ( $models ) : ?>
									<?php $saved_model = (string) SSC_Settings::get( $pid . '_model', '' ); ?>
									<select id="<?php echo esc_attr( $pid ); ?>_model" name="<?php echo esc_attr( $pid ); ?>_model" data-manual="1">
										<?php if ( '' !== $saved_model && ! isset( $models[ $saved_model ] ) ) : ?>
											<option value="<?php echo esc_attr( $saved_model ); ?>" selected><?php echo esc_html( $saved_model ); ?></option>
										<?php endif; ?>
										<?php foreach ( $models as $m_id => $m_label ) : ?>
											<option value="<?php echo esc_attr( $m_id ); ?>" <?php selected( $saved_model, $m_id ); ?>><?php echo esc_html( $m_label ); ?></option>
										<?php endforeach; ?>
										<option value="__manual__"><?php esc_html_e( 'Enter model ID manually…', 'vazho' ); ?></option>
									</select>
									<?php // Hidden and disabled until "Enter model ID manually…" is picked, so it never posts a stray empty model. ?>
									<input class="ssc-model-manual" type="text" name="<?php echo esc_attr( $pid ); ?>_model_manual" dir="ltr" placeholder="<?php esc_attr_e( 'Model ID', 'vazho' ); ?>" hidden disabled />
								<?php else : ?>
									<input id="<?php echo esc_attr( $pid ); ?>_model" name="<?php echo esc_attr( $pid ); ?>_model" type="text" dir="ltr" value="<?php echo esc_attr( (string) SSC_Settings::get( $pid . '_model', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Model ID', 'vazho' ); ?>" />
								<?php endif; ?>
								<p class="ssc-field__hint"><?php esc_html_e( 'Model IDs evolve — manual entry is always available and lists stay current via your own updates.', 'vazho' ); ?></p>
							</div>
						</div>
						<?php if ( 'custom' === $pid ) : ?>
							<div class="ssc-field">
								<label for="custom_endpoint"><?php esc_html_e( 'Endpoint URL (OpenAI-compatible)', 'vazho' ); ?></label>
								<input id="custom_endpoint" name="custom_endpoint" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['custom_endpoint'] ); ?>" placeholder="https://your-gateway.example/v1/chat/completions" />
							</div>
						<?php endif; ?>
					<?php else : ?>
						<div class="ssc-grid ssc-grid--2">
							<div class="ssc-field">
								<label for="ai_webhook_url"><?php esc_html_e( 'Webhook URL', 'vazho' ); ?></label>
								<input id="ai_webhook_url" name="ai_webhook_url" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['ai_webhook_url'] ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="ai_webhook_secret"><?php esc_html_e( 'Shared secret (HMAC)', 'vazho' ); ?></label>
								<input id="ai_webhook_secret" name="ai_webhook_secret" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( 'ai_webhook_secret' ) ? esc_attr__( 'A secret is stored — type to replace', 'vazho' ) : ''; ?>" />
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<div class="ssc-conn-test">
				<button type="button" class="ssc-btn ssc-btn--secondary" id="ssc-test-connection"><?php echo SSC_Icons::get( 'zap' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Test connection', 'vazho' ); ?></button>
				<span id="ssc-test-result" class="ssc-conn-test__result" role="status" aria-live="polite"></span>
			</div>
		</section>
		<?php SSC_Admin_UI::panel_close(); ?>

		<div id="ssc-scope-card">
		<?php SSC_Admin_UI::panel_open( 'answers', 'violet', 'chat', __( 'What the assistant may answer', 'vazho' ), __( 'In every mode, facts about your organization (prices, policies, specifications) come only from your knowledge. The assistant never invents them.', 'vazho' ) ); ?>
		<section class="ssc-card">
			<?php if ( $ssc_pharma ) : ?>
				<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'The pharmaceutical module is active: its answer policy (Settings → Privacy) decides the scope, unrelated topics are always declined, and web search is off.', 'vazho' ); ?></div>
			<?php endif; ?>
			<fieldset class="ssc-choices ssc-choices--cards" <?php disabled( $ssc_pharma ); ?>>
				<legend class="screen-reader-text"><?php esc_html_e( 'Answer scope', 'vazho' ); ?></legend>
				<?php
				$ssc_scopes = array(
					'knowledge' => array( 'shield', __( 'Only from my knowledge', 'vazho' ), __( 'Answers strictly from the business profile, knowledge entries and documents. Anything else gets "I don\'t have that information" and your contact details. Safest; best for regulated content.', 'vazho' ) ),
					'business'  => array( 'building', __( 'My business and its field (recommended)', 'vazho' ), __( 'Helps with your products, services, orders and support, and can explain general concepts of your industry. Politely declines unrelated requests such as trivia, homework, coding or news.', 'vazho' ) ),
					'open'      => array( 'globe', __( 'Any question', 'vazho' ), __( 'Also answers general questions unrelated to your business, like a general-purpose assistant. Every such answer is billed by your AI provider.', 'vazho' ) ),
				);
				foreach ( $ssc_scopes as $ssc_value => $ssc_scope ) :
					?>
					<label class="ssc-choice ssc-choice--card">
						<input type="radio" name="answer_scope" value="<?php echo esc_attr( $ssc_value ); ?>" <?php checked( $s['answer_scope'], $ssc_value ); ?> />
						<span class="ssc-choice__ico"><?php echo SSC_Icons::get( $ssc_scope[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
						<span><strong><?php echo esc_html( $ssc_scope[1] ); ?></strong><span class="ssc-choice__desc"><?php echo esc_html( $ssc_scope[2] ); ?></span></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<div class="ssc-field">
				<label for="off_topic_message"><?php esc_html_e( 'Reply to unrelated questions (optional)', 'vazho' ); ?></label>
				<textarea id="off_topic_message" name="off_topic_message" rows="2" dir="auto" placeholder="<?php esc_attr_e( 'e.g. I can only help with questions about our products and services. For anything else, please contact us.', 'vazho' ); ?>"><?php echo esc_textarea( (string) $s['off_topic_message'] ); ?></textarea>
				<p class="ssc-field__hint"><?php esc_html_e( 'Used when a question is outside the chosen scope. Empty = a short, friendly reply written by the assistant in the visitor\'s language.', 'vazho' ); ?></p>
			</div>
		</section>
		<?php SSC_Admin_UI::panel_close(); ?>

		<?php SSC_Admin_UI::panel_open( 'web', 'teal', 'globe', __( 'Web search', 'vazho' ), __( 'Let the assistant look up current, public information when your knowledge is not enough.', 'vazho' ) ); ?>
		<section class="ssc-card">
			<label class="ssc-check"><input type="checkbox" id="web_search" name="web_search" value="yes" <?php checked( 'yes', $s['web_search'] ); ?> <?php disabled( $ssc_pharma ); ?> /> <span><?php esc_html_e( 'Let the assistant search the internet for current, public information', 'vazho' ); ?></span></label>
			<p class="ssc-field__hint"><?php esc_html_e( 'Uses your provider\'s built-in search (OpenAI, Claude, Gemini, OpenRouter; not available for custom endpoints or webhooks). The provider bills searches separately. Searched answers are not streamed or cached, and the pages used are listed under the answer. Your own knowledge always wins over web results. Not used in "Only from my knowledge" mode.', 'vazho' ); ?></p>
			<p class="ssc-field__hint ssc-web-unsupported" hidden><?php esc_html_e( 'The selected provider has no web search tool, so this option has no effect.', 'vazho' ); ?></p>
			<div class="ssc-field">
				<label for="web_search_domains"><?php esc_html_e( 'Search only these sites (optional, one domain per line)', 'vazho' ); ?></label>
				<textarea id="web_search_domains" name="web_search_domains" rows="2" dir="ltr" placeholder="example.com&#10;docs.example.com"><?php echo esc_textarea( (string) $s['web_search_domains'] ); ?></textarea>
				<p class="ssc-field__hint"><?php esc_html_e( 'For example your own website, to answer from pages you have not imported. Honoured by OpenAI and Claude; Gemini and OpenRouter search the whole web.', 'vazho' ); ?></p>
			</div>
		</section>
		<?php SSC_Admin_UI::panel_close(); ?>
		</div>

		<?php SSC_Admin_UI::panel_open( 'advanced', 'slate', 'sliders', __( 'Advanced engine options', 'vazho' ), __( 'Fine tuning. The defaults suit most sites.', 'vazho' ) ); ?>
		<section class="ssc-card">
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="qa_mode"><?php esc_html_e( 'Answer priority', 'vazho' ); ?></label>
					<select id="qa_mode" name="qa_mode">
						<option value="ai_first" <?php selected( $s['qa_mode'], 'ai_first' ); ?>><?php esc_html_e( 'AI first, FAQ bank as fallback', 'vazho' ); ?></option>
						<option value="bank_first" <?php selected( $s['qa_mode'], 'bank_first' ); ?>><?php esc_html_e( 'FAQ bank first, AI as fallback', 'vazho' ); ?></option>
						<option value="bank_only" <?php selected( $s['qa_mode'], 'bank_only' ); ?>><?php esc_html_e( 'FAQ bank only', 'vazho' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="ai_temperature"><?php esc_html_e( 'Creativity (temperature)', 'vazho' ); ?></label>
					<select id="ai_temperature" name="ai_temperature">
						<?php foreach ( array( '0.1' => __( '0.1 — very precise', 'vazho' ), '0.4' => __( '0.4 — balanced', 'vazho' ), '0.7' => __( '0.7 — creative', 'vazho' ), '1' => __( '1 — maximum creativity', 'vazho' ) ) as $t => $t_label ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>" <?php selected( (string) $s['ai_temperature'], $t ); ?>><?php echo esc_html( $t_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ssc-field">
					<label for="ai_max_tokens"><?php esc_html_e( 'Max answer length', 'vazho' ); ?></label>
					<input id="ai_max_tokens" name="ai_max_tokens" type="number" min="100" max="4000" step="50" value="<?php echo esc_attr( (string) $s['ai_max_tokens'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="ai_history_limit"><?php esc_html_e( 'Conversation memory (messages)', 'vazho' ); ?></label>
					<input id="ai_history_limit" name="ai_history_limit" type="number" min="0" max="20" value="<?php echo esc_attr( (string) $s['ai_history_limit'] ); ?>" />
					<p class="ssc-field__hint"><?php esc_html_e( 'The latest messages the AI reads word for word. Older messages are not forgotten: they are kept as a short summary.', 'vazho' ); ?></p>
				</div>
				<div class="ssc-field">
					<label for="kb_max_chunks"><?php esc_html_e( 'Retrieved knowledge chunks per answer', 'vazho' ); ?></label>
					<input id="kb_max_chunks" name="kb_max_chunks" type="number" min="1" max="8" value="<?php echo esc_attr( (string) $s['kb_max_chunks'] ); ?>" />
				</div>
			</div>
			<label class="ssc-check"><input type="checkbox" name="ai_cache_enabled" value="yes" <?php checked( 'yes', $s['ai_cache_enabled'] ); ?> /> <span><?php esc_html_e( 'Cache identical questions (6 hours, faster + cheaper)', 'vazho' ); ?></span></label>
			<div class="ssc-field">
				<label for="ai_system_prompt_extra"><?php esc_html_e( 'Extra instructions for the assistant', 'vazho' ); ?></label>
				<textarea id="ai_system_prompt_extra" name="ai_system_prompt_extra" rows="3" placeholder="<?php esc_attr_e( 'Optional business-specific rules…', 'vazho' ); ?>"><?php echo esc_textarea( (string) $s['ai_system_prompt_extra'] ); ?></textarea>
			</div>
			<div class="ssc-field">
				<label for="ai_fallback_msg"><?php esc_html_e( 'Offline message (when nothing can answer)', 'vazho' ); ?></label>
				<textarea id="ai_fallback_msg" name="ai_fallback_msg" rows="2"><?php echo esc_textarea( (string) $s['ai_fallback_msg'] ); ?></textarea>
			</div>
		</section>
		<?php SSC_Admin_UI::panel_close(); ?>

		<?php SSC_Admin_UI::save_bar( __( 'Save connection', 'vazho' ) ); ?>
	</form>
	</div>
	</div>
</div>
