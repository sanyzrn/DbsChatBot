<?php
/**
 * AI Connection view.
 *
 * @package SmartSupportChatbot
 * @var array            $s            Settings.
 * @var SSC_Provider[]   $providers    Provider objects.
 * @var array            $labels       Provider labels.
 * @var bool             $verified     Current verification validity.
 * @var array            $verify_state Verification state.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$saved = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$secret_error = isset( $_GET['secret_error'] ) ? (int) $_GET['secret_error'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$sel  = (string) $s['ai_provider'];
?>
<div class="ssc-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'AI Connection', 'nexachat-ai' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Keys are encrypted at rest and never leave the server. Connection tests perform a real generation, not just a ping.', 'nexachat-ai' ); ?></p>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Connection settings saved.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>
	<?php if ( $secret_error ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The API key could not be encrypted (OpenSSL unavailable or AUTH_KEY missing). It was NOT saved in plaintext. Fix the server crypto setup and try again.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>

	<div class="ssc-verifybar <?php echo $verified ? 'is-ok' : 'is-warn'; ?>">
		<span class="dashicons <?php echo $verified ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
		<div>
			<strong><?php $verified ? esc_html_e( 'Connection verified', 'nexachat-ai' ) : esc_html_e( 'Connection not verified', 'nexachat-ai' ); ?></strong>
			<span>
				<?php
				if ( $verified ) {
					echo esc_html( sprintf( __( 'Verified %s ago for the current provider, key, model and endpoint.', 'nexachat-ai' ), human_time_diff( (int) $verify_state['at'] ) ) );
				} else {
					esc_html_e( 'Changing the provider, key, model or endpoint invalidates previous verification. Run a test after every change.', 'nexachat-ai' );
				}
				?>
			</span>
		</div>
	</div>

	<form method="post" class="ssc-form" id="ssc-connection-form">
		<?php wp_nonce_field( 'ssc_connection' ); ?>
		<input type="hidden" name="ssc_connection_save" value="1" />

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Provider', 'nexachat-ai' ); ?></h2>
			<div class="ssc-field">
				<label for="ai_provider"><?php esc_html_e( 'AI engine', 'nexachat-ai' ); ?></label>
				<select id="ai_provider" name="ai_provider">
					<?php foreach ( $labels as $pid => $plabel ) : ?>
						<option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $sel, $pid ); ?>><?php echo esc_html( $plabel ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="ssc-field__hint"><?php esc_html_e( '“No AI engine” answers from your FAQ bank and knowledge only — nothing leaves your server.', 'nexachat-ai' ); ?></p>
			</div>

			<?php foreach ( $providers as $pid => $provider ) : ?>
				<div class="ssc-provider-fields" data-provider="<?php echo esc_attr( $pid ); ?>" <?php echo ( $sel === $pid ) ? '' : 'hidden'; ?>>
					<?php if ( 'webhook' !== $pid ) : ?>
						<div class="ssc-grid ssc-grid--2">
							<?php if ( $provider->needs_key() ) : ?>
								<div class="ssc-field">
									<label for="<?php echo esc_attr( $pid ); ?>_api_key"><?php esc_html_e( 'API key', 'nexachat-ai' ); ?></label>
									<input id="<?php echo esc_attr( $pid ); ?>_api_key" name="<?php echo esc_attr( $pid ); ?>_api_key" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( $pid . '_api_key' ) ? esc_attr__( 'A key is stored — type to replace', 'nexachat-ai' ) : esc_attr__( 'Paste your API key', 'nexachat-ai' ); ?>" />
									<?php if ( SSC_Settings::has_secret( $pid . '_api_key' ) ) : ?>
										<label class="ssc-check ssc-check--tight"><input type="checkbox" name="<?php echo esc_attr( $pid ); ?>_api_key_clear" value="1" /> <span><?php esc_html_e( 'Remove the stored key', 'nexachat-ai' ); ?></span></label>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<div class="ssc-field">
								<label for="<?php echo esc_attr( $pid ); ?>_model"><?php esc_html_e( 'Model', 'nexachat-ai' ); ?></label>
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
										<option value="__manual__"><?php esc_html_e( 'Enter model ID manually…', 'nexachat-ai' ); ?></option>
									</select>
									<?php // Hidden and disabled until "Enter model ID manually…" is picked, so it never posts a stray empty model. ?>
									<input class="ssc-model-manual" type="text" name="<?php echo esc_attr( $pid ); ?>_model_manual" dir="ltr" placeholder="<?php esc_attr_e( 'Model ID', 'nexachat-ai' ); ?>" hidden disabled />
								<?php else : ?>
									<input id="<?php echo esc_attr( $pid ); ?>_model" name="<?php echo esc_attr( $pid ); ?>_model" type="text" dir="ltr" value="<?php echo esc_attr( (string) SSC_Settings::get( $pid . '_model', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Model ID', 'nexachat-ai' ); ?>" />
								<?php endif; ?>
								<p class="ssc-field__hint"><?php esc_html_e( 'Model IDs evolve — manual entry is always available and lists stay current via your own updates.', 'nexachat-ai' ); ?></p>
							</div>
						</div>
						<?php if ( 'custom' === $pid ) : ?>
							<div class="ssc-field">
								<label for="custom_endpoint"><?php esc_html_e( 'Endpoint URL (OpenAI-compatible)', 'nexachat-ai' ); ?></label>
								<input id="custom_endpoint" name="custom_endpoint" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['custom_endpoint'] ); ?>" placeholder="https://your-gateway.example/v1/chat/completions" />
							</div>
						<?php endif; ?>
					<?php else : ?>
						<div class="ssc-grid ssc-grid--2">
							<div class="ssc-field">
								<label for="ai_webhook_url"><?php esc_html_e( 'Webhook URL', 'nexachat-ai' ); ?></label>
								<input id="ai_webhook_url" name="ai_webhook_url" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['ai_webhook_url'] ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="ai_webhook_secret"><?php esc_html_e( 'Shared secret (HMAC)', 'nexachat-ai' ); ?></label>
								<input id="ai_webhook_secret" name="ai_webhook_secret" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( 'ai_webhook_secret' ) ? esc_attr__( 'A secret is stored — type to replace', 'nexachat-ai' ) : ''; ?>" />
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<div class="ssc-conn-test">
				<button type="button" class="ssc-btn ssc-btn--secondary" id="ssc-test-connection"><?php esc_html_e( 'Test connection', 'nexachat-ai' ); ?></button>
				<span id="ssc-test-result" class="ssc-conn-test__result" role="status" aria-live="polite"></span>
			</div>
		</section>

		<?php $ssc_pharma = SSC_Modules::is_active( 'pharma' ); ?>
		<section class="ssc-card" id="ssc-scope-card">
			<h2><?php esc_html_e( 'What the assistant may answer', 'nexachat-ai' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'In every mode, facts about your organization (prices, policies, specifications) come only from your knowledge — the assistant never invents them.', 'nexachat-ai' ); ?></p>
			<?php if ( $ssc_pharma ) : ?>
				<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'The pharmaceutical module is active: its answer policy (Settings → Privacy) decides the scope, unrelated topics are always declined, and web search is off.', 'nexachat-ai' ); ?></div>
			<?php endif; ?>
			<fieldset class="ssc-choices" <?php disabled( $ssc_pharma ); ?>>
				<legend class="screen-reader-text"><?php esc_html_e( 'Answer scope', 'nexachat-ai' ); ?></legend>
				<?php
				$ssc_scopes = array(
					'knowledge' => array( __( 'Only from my knowledge', 'nexachat-ai' ), __( 'Answers strictly from the business profile, knowledge entries and documents. Anything else gets "I don\'t have that information" and your contact details. Safest; best for regulated content.', 'nexachat-ai' ) ),
					'business'  => array( __( 'My business and its field (recommended)', 'nexachat-ai' ), __( 'Helps with your products, services, orders and support, and can explain general concepts of your industry. Politely declines unrelated requests such as trivia, homework, coding or news.', 'nexachat-ai' ) ),
					'open'      => array( __( 'Any question', 'nexachat-ai' ), __( 'Also answers general questions unrelated to your business, like a general-purpose assistant. Every such answer is billed by your AI provider.', 'nexachat-ai' ) ),
				);
				foreach ( $ssc_scopes as $ssc_value => $ssc_scope ) :
					?>
					<label class="ssc-choice">
						<input type="radio" name="answer_scope" value="<?php echo esc_attr( $ssc_value ); ?>" <?php checked( $s['answer_scope'], $ssc_value ); ?> />
						<span><strong><?php echo esc_html( $ssc_scope[0] ); ?></strong><span class="ssc-choice__desc"><?php echo esc_html( $ssc_scope[1] ); ?></span></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<div class="ssc-field">
				<label for="off_topic_message"><?php esc_html_e( 'Reply to unrelated questions (optional)', 'nexachat-ai' ); ?></label>
				<textarea id="off_topic_message" name="off_topic_message" rows="2" dir="auto" placeholder="<?php esc_attr_e( 'e.g. I can only help with questions about our products and services. For anything else, please contact us.', 'nexachat-ai' ); ?>"><?php echo esc_textarea( (string) $s['off_topic_message'] ); ?></textarea>
				<p class="ssc-field__hint"><?php esc_html_e( 'Used when a question is outside the chosen scope. Empty = a short, friendly reply written by the assistant in the visitor\'s language.', 'nexachat-ai' ); ?></p>
			</div>

			<h3 class="ssc-subhead"><?php esc_html_e( 'Web search', 'nexachat-ai' ); ?></h3>
			<label class="ssc-check"><input type="checkbox" id="web_search" name="web_search" value="yes" <?php checked( 'yes', $s['web_search'] ); ?> <?php disabled( $ssc_pharma ); ?> /> <span><?php esc_html_e( 'Let the assistant search the internet for current, public information', 'nexachat-ai' ); ?></span></label>
			<p class="ssc-field__hint"><?php esc_html_e( 'Uses your provider\'s built-in search (OpenAI, Claude, Gemini, OpenRouter; not available for custom endpoints or webhooks). The provider bills searches separately. Searched answers are not streamed or cached, and the pages used are listed under the answer. Your own knowledge always wins over web results. Not used in "Only from my knowledge" mode.', 'nexachat-ai' ); ?></p>
			<p class="ssc-field__hint ssc-web-unsupported" hidden><?php esc_html_e( 'The selected provider has no web search tool, so this option has no effect.', 'nexachat-ai' ); ?></p>
			<div class="ssc-field">
				<label for="web_search_domains"><?php esc_html_e( 'Search only these sites (optional, one domain per line)', 'nexachat-ai' ); ?></label>
				<textarea id="web_search_domains" name="web_search_domains" rows="2" dir="ltr" placeholder="example.com&#10;docs.example.com"><?php echo esc_textarea( (string) $s['web_search_domains'] ); ?></textarea>
				<p class="ssc-field__hint"><?php esc_html_e( 'For example your own website, to answer from pages you have not imported. Honoured by OpenAI and Claude; Gemini and OpenRouter search the whole web.', 'nexachat-ai' ); ?></p>
			</div>
		</section>

		<details class="ssc-card ssc-details">
			<summary><?php esc_html_e( 'Advanced engine options', 'nexachat-ai' ); ?></summary>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="qa_mode"><?php esc_html_e( 'Answer priority', 'nexachat-ai' ); ?></label>
					<select id="qa_mode" name="qa_mode">
						<option value="ai_first" <?php selected( $s['qa_mode'], 'ai_first' ); ?>><?php esc_html_e( 'AI first, FAQ bank as fallback', 'nexachat-ai' ); ?></option>
						<option value="bank_first" <?php selected( $s['qa_mode'], 'bank_first' ); ?>><?php esc_html_e( 'FAQ bank first, AI as fallback', 'nexachat-ai' ); ?></option>
						<option value="bank_only" <?php selected( $s['qa_mode'], 'bank_only' ); ?>><?php esc_html_e( 'FAQ bank only', 'nexachat-ai' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="ai_temperature"><?php esc_html_e( 'Creativity (temperature)', 'nexachat-ai' ); ?></label>
					<select id="ai_temperature" name="ai_temperature">
						<?php foreach ( array( '0.1' => __( '0.1 — very precise', 'nexachat-ai' ), '0.4' => __( '0.4 — balanced', 'nexachat-ai' ), '0.7' => __( '0.7 — creative', 'nexachat-ai' ), '1' => __( '1 — maximum creativity', 'nexachat-ai' ) ) as $t => $t_label ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>" <?php selected( (string) $s['ai_temperature'], $t ); ?>><?php echo esc_html( $t_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ssc-field">
					<label for="ai_max_tokens"><?php esc_html_e( 'Max answer length', 'nexachat-ai' ); ?></label>
					<input id="ai_max_tokens" name="ai_max_tokens" type="number" min="100" max="4000" step="50" value="<?php echo esc_attr( (string) $s['ai_max_tokens'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="ai_history_limit"><?php esc_html_e( 'Conversation memory (messages)', 'nexachat-ai' ); ?></label>
					<input id="ai_history_limit" name="ai_history_limit" type="number" min="0" max="20" value="<?php echo esc_attr( (string) $s['ai_history_limit'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="kb_max_chunks"><?php esc_html_e( 'Retrieved knowledge chunks per answer', 'nexachat-ai' ); ?></label>
					<input id="kb_max_chunks" name="kb_max_chunks" type="number" min="1" max="8" value="<?php echo esc_attr( (string) $s['kb_max_chunks'] ); ?>" />
				</div>
			</div>
			<label class="ssc-check"><input type="checkbox" name="ai_cache_enabled" value="yes" <?php checked( 'yes', $s['ai_cache_enabled'] ); ?> /> <span><?php esc_html_e( 'Cache identical questions (6 hours, faster + cheaper)', 'nexachat-ai' ); ?></span></label>
			<div class="ssc-field">
				<label for="ai_system_prompt_extra"><?php esc_html_e( 'Extra instructions for the assistant', 'nexachat-ai' ); ?></label>
				<textarea id="ai_system_prompt_extra" name="ai_system_prompt_extra" rows="3" placeholder="<?php esc_attr_e( 'Optional business-specific rules…', 'nexachat-ai' ); ?>"><?php echo esc_textarea( (string) $s['ai_system_prompt_extra'] ); ?></textarea>
			</div>
			<div class="ssc-field">
				<label for="ai_fallback_msg"><?php esc_html_e( 'Offline message (when nothing can answer)', 'nexachat-ai' ); ?></label>
				<textarea id="ai_fallback_msg" name="ai_fallback_msg" rows="2"><?php echo esc_textarea( (string) $s['ai_fallback_msg'] ); ?></textarea>
			</div>
		</details>

		<div class="ssc-form__actions">
			<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Save connection', 'nexachat-ai' ); ?></button>
		</div>
	</form>
</div>
