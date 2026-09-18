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
			<h1><?php esc_html_e( 'AI Connection', 'smart-support-chatbot' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Keys are encrypted at rest and never leave the server. Connection tests perform a real generation, not just a ping.', 'smart-support-chatbot' ); ?></p>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Connection settings saved.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>
	<?php if ( $secret_error ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'The API key could not be encrypted (OpenSSL unavailable or AUTH_KEY missing). It was NOT saved in plaintext. Fix the server crypto setup and try again.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>

	<div class="ssc-verifybar <?php echo $verified ? 'is-ok' : 'is-warn'; ?>">
		<span class="dashicons <?php echo $verified ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
		<div>
			<strong><?php $verified ? esc_html_e( 'Connection verified', 'smart-support-chatbot' ) : esc_html_e( 'Connection not verified', 'smart-support-chatbot' ); ?></strong>
			<span>
				<?php
				if ( $verified ) {
					echo esc_html( sprintf( __( 'Verified %s ago for the current provider, key, model and endpoint.', 'smart-support-chatbot' ), human_time_diff( (int) $verify_state['at'] ) ) );
				} else {
					esc_html_e( 'Changing the provider, key, model or endpoint invalidates previous verification. Run a test after every change.', 'smart-support-chatbot' );
				}
				?>
			</span>
		</div>
	</div>

	<form method="post" class="ssc-form" id="ssc-connection-form">
		<?php wp_nonce_field( 'ssc_connection' ); ?>
		<input type="hidden" name="ssc_connection_save" value="1" />

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Provider', 'smart-support-chatbot' ); ?></h2>
			<div class="ssc-field">
				<label for="ai_provider"><?php esc_html_e( 'AI engine', 'smart-support-chatbot' ); ?></label>
				<select id="ai_provider" name="ai_provider">
					<?php foreach ( $labels as $pid => $plabel ) : ?>
						<option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $sel, $pid ); ?>><?php echo esc_html( $plabel ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="ssc-field__hint"><?php esc_html_e( '“No AI engine” answers from your FAQ bank and knowledge only — nothing leaves your server.', 'smart-support-chatbot' ); ?></p>
			</div>

			<?php foreach ( $providers as $pid => $provider ) : ?>
				<div class="ssc-provider-fields" data-provider="<?php echo esc_attr( $pid ); ?>" <?php echo ( $sel === $pid ) ? '' : 'hidden'; ?>>
					<?php if ( 'webhook' !== $pid ) : ?>
						<div class="ssc-grid ssc-grid--2">
							<?php if ( $provider->needs_key() ) : ?>
								<div class="ssc-field">
									<label for="<?php echo esc_attr( $pid ); ?>_api_key"><?php esc_html_e( 'API key', 'smart-support-chatbot' ); ?></label>
									<input id="<?php echo esc_attr( $pid ); ?>_api_key" name="<?php echo esc_attr( $pid ); ?>_api_key" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( $pid . '_api_key' ) ? esc_attr__( 'A key is stored — type to replace', 'smart-support-chatbot' ) : esc_attr__( 'Paste your API key', 'smart-support-chatbot' ); ?>" />
									<?php if ( SSC_Settings::has_secret( $pid . '_api_key' ) ) : ?>
										<label class="ssc-check ssc-check--tight"><input type="checkbox" name="<?php echo esc_attr( $pid ); ?>_api_key_clear" value="1" /> <span><?php esc_html_e( 'Remove the stored key', 'smart-support-chatbot' ); ?></span></label>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<div class="ssc-field">
								<label for="<?php echo esc_attr( $pid ); ?>_model"><?php esc_html_e( 'Model', 'smart-support-chatbot' ); ?></label>
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
										<option value="__manual__"><?php esc_html_e( 'Enter model ID manually…', 'smart-support-chatbot' ); ?></option>
									</select>
									<input class="ssc-model-manual" type="text" name="<?php echo esc_attr( $pid ); ?>_model_manual" dir="ltr" placeholder="<?php esc_attr_e( 'Model ID', 'smart-support-chatbot' ); ?>" <?php echo '' !== $saved_model && ! isset( $models[ $saved_model ] ) ? '' : 'hidden'; ?> />
								<?php else : ?>
									<input id="<?php echo esc_attr( $pid ); ?>_model" name="<?php echo esc_attr( $pid ); ?>_model" type="text" dir="ltr" value="<?php echo esc_attr( (string) SSC_Settings::get( $pid . '_model', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Model ID', 'smart-support-chatbot' ); ?>" />
								<?php endif; ?>
								<p class="ssc-field__hint"><?php esc_html_e( 'Model IDs evolve — manual entry is always available and lists stay current via your own updates.', 'smart-support-chatbot' ); ?></p>
							</div>
						</div>
						<?php if ( 'custom' === $pid ) : ?>
							<div class="ssc-field">
								<label for="custom_endpoint"><?php esc_html_e( 'Endpoint URL (OpenAI-compatible)', 'smart-support-chatbot' ); ?></label>
								<input id="custom_endpoint" name="custom_endpoint" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['custom_endpoint'] ); ?>" placeholder="https://your-gateway.example/v1/chat/completions" />
							</div>
						<?php endif; ?>
					<?php else : ?>
						<div class="ssc-grid ssc-grid--2">
							<div class="ssc-field">
								<label for="ai_webhook_url"><?php esc_html_e( 'Webhook URL', 'smart-support-chatbot' ); ?></label>
								<input id="ai_webhook_url" name="ai_webhook_url" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['ai_webhook_url'] ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="ai_webhook_secret"><?php esc_html_e( 'Shared secret (HMAC)', 'smart-support-chatbot' ); ?></label>
								<input id="ai_webhook_secret" name="ai_webhook_secret" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( 'ai_webhook_secret' ) ? esc_attr__( 'A secret is stored — type to replace', 'smart-support-chatbot' ) : ''; ?>" />
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<div class="ssc-conn-test">
				<button type="button" class="ssc-btn ssc-btn--secondary" id="ssc-test-connection"><?php esc_html_e( 'Test connection', 'smart-support-chatbot' ); ?></button>
				<span id="ssc-test-result" class="ssc-conn-test__result" role="status" aria-live="polite"></span>
			</div>
		</section>

		<details class="ssc-card ssc-details">
			<summary><?php esc_html_e( 'Advanced engine options', 'smart-support-chatbot' ); ?></summary>
			<div class="ssc-grid ssc-grid--2">
				<div class="ssc-field">
					<label for="qa_mode"><?php esc_html_e( 'Answer priority', 'smart-support-chatbot' ); ?></label>
					<select id="qa_mode" name="qa_mode">
						<option value="ai_first" <?php selected( $s['qa_mode'], 'ai_first' ); ?>><?php esc_html_e( 'AI first, FAQ bank as fallback', 'smart-support-chatbot' ); ?></option>
						<option value="bank_first" <?php selected( $s['qa_mode'], 'bank_first' ); ?>><?php esc_html_e( 'FAQ bank first, AI as fallback', 'smart-support-chatbot' ); ?></option>
						<option value="bank_only" <?php selected( $s['qa_mode'], 'bank_only' ); ?>><?php esc_html_e( 'FAQ bank only', 'smart-support-chatbot' ); ?></option>
					</select>
				</div>
				<div class="ssc-field">
					<label for="ai_temperature"><?php esc_html_e( 'Creativity (temperature)', 'smart-support-chatbot' ); ?></label>
					<select id="ai_temperature" name="ai_temperature">
						<?php foreach ( array( '0.1' => __( '0.1 — very precise', 'smart-support-chatbot' ), '0.4' => __( '0.4 — balanced', 'smart-support-chatbot' ), '0.7' => __( '0.7 — creative', 'smart-support-chatbot' ), '1' => __( '1 — maximum creativity', 'smart-support-chatbot' ) ) as $t => $t_label ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>" <?php selected( (string) $s['ai_temperature'], $t ); ?>><?php echo esc_html( $t_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ssc-field">
					<label for="ai_max_tokens"><?php esc_html_e( 'Max answer length', 'smart-support-chatbot' ); ?></label>
					<input id="ai_max_tokens" name="ai_max_tokens" type="number" min="100" max="4000" step="50" value="<?php echo esc_attr( (string) $s['ai_max_tokens'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="ai_history_limit"><?php esc_html_e( 'Conversation memory (messages)', 'smart-support-chatbot' ); ?></label>
					<input id="ai_history_limit" name="ai_history_limit" type="number" min="0" max="20" value="<?php echo esc_attr( (string) $s['ai_history_limit'] ); ?>" />
				</div>
				<div class="ssc-field">
					<label for="kb_max_chunks"><?php esc_html_e( 'Retrieved knowledge chunks per answer', 'smart-support-chatbot' ); ?></label>
					<input id="kb_max_chunks" name="kb_max_chunks" type="number" min="1" max="8" value="<?php echo esc_attr( (string) $s['kb_max_chunks'] ); ?>" />
				</div>
			</div>
			<label class="ssc-check"><input type="checkbox" name="ai_strict_knowledge" value="yes" <?php checked( 'yes', $s['ai_strict_knowledge'] ); ?> /> <span><?php esc_html_e( 'Strict mode — answer only from your knowledge', 'smart-support-chatbot' ); ?></span></label>
			<label class="ssc-check"><input type="checkbox" name="ai_cache_enabled" value="yes" <?php checked( 'yes', $s['ai_cache_enabled'] ); ?> /> <span><?php esc_html_e( 'Cache identical questions (6 hours, faster + cheaper)', 'smart-support-chatbot' ); ?></span></label>
			<div class="ssc-field">
				<label for="ai_system_prompt_extra"><?php esc_html_e( 'Extra instructions for the assistant', 'smart-support-chatbot' ); ?></label>
				<textarea id="ai_system_prompt_extra" name="ai_system_prompt_extra" rows="3" placeholder="<?php esc_attr_e( 'Optional business-specific rules…', 'smart-support-chatbot' ); ?>"><?php echo esc_textarea( (string) $s['ai_system_prompt_extra'] ); ?></textarea>
			</div>
			<div class="ssc-field">
				<label for="ai_fallback_msg"><?php esc_html_e( 'Offline message (when nothing can answer)', 'smart-support-chatbot' ); ?></label>
				<textarea id="ai_fallback_msg" name="ai_fallback_msg" rows="2"><?php echo esc_textarea( (string) $s['ai_fallback_msg'] ); ?></textarea>
			</div>
		</details>

		<div class="ssc-form__actions">
			<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Save connection', 'smart-support-chatbot' ); ?></button>
		</div>
	</form>
</div>
