<?php
/**
 * Setup Wizard view. Progressive, resumable, RTL/LTR-safe.
 *
 * @package SmartSupportChatbot
 * @var array  $state      Setup state.
 * @var string $current    Current step id.
 * @var int    $step_index Current step index.
 * @var array  $steps      Step ids.
 * @var array  $labels     Step labels + icons.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s          = SSC_Settings::all();
$business   = SSC_Settings::business();
$saved_flag = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$err        = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$verify     = isset( $_GET['verify'] ) ? sanitize_key( wp_unslash( $_GET['verify'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$published  = isset( $_GET['published'] ) ? (int) $_GET['published'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.

// Site suggestions (never fabricated - real WordPress data offered as defaults).
$site_title = get_bloginfo( 'name' );
$site_url   = home_url( '/' );
$site_lang  = get_locale();

$dir = 'ltr'; // Admin UI is always LTR regardless of site locale.
?>
<div class="ssc-wizard" dir="ltr">

	<header class="ssc-wizard__head">
		<div class="ssc-wizard__brand">
			<span class="ssc-wizard__logo" aria-hidden="true"></span>
			<div>
				<h1><?php esc_html_e( 'Set up your AI assistant', 'smart-support-chatbot' ); ?></h1>
				<p><?php esc_html_e( 'Five short steps. Your progress is saved automatically — you can leave and come back anytime.', 'smart-support-chatbot' ); ?></p>
			</div>
		</div>
		<?php if ( $saved_flag ) : ?>
			<div class="ssc-notice ssc-notice--success" role="status"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Saved', 'smart-support-chatbot' ); ?></div>
		<?php endif; ?>
	</header>

	<div class="ssc-wizard__body">
		<aside class="ssc-wizard__rail" aria-label="<?php esc_attr_e( 'Setup progress', 'smart-support-chatbot' ); ?>">
			<ol class="ssc-wizard__steps">
				<?php foreach ( $steps as $i => $step ) : ?>
					<?php
					$done      = ! empty( $state['steps'][ $step ] );
					$is_active = ( $step === $current );
					$reachable = $done || $i <= $step_index; // Backwards navigation always allowed.
					?>
					<li class="ssc-wizard__step<?php echo $is_active ? ' is-active' : ''; ?><?php echo $done ? ' is-done' : ''; ?>">
						<?php if ( $reachable ) : ?>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-wizard', 'step' => $step ), admin_url( 'admin.php' ) ) ); ?>">
						<?php else : ?>
							<span class="ssc-wizard__locked">
						<?php endif; ?>
							<span class="ssc-wizard__stepnum"><?php echo $done ? '<span class="dashicons dashicons-yes" aria-hidden="true"></span>' : esc_html( (string) ( $i + 1 ) ); ?></span>
							<span class="ssc-wizard__steplabel"><?php echo esc_html( $labels[ $step ][0] ); ?></span>
						<?php if ( $reachable ) : ?>
							</a>
						<?php else : ?>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
			<p class="ssc-wizard__railnote">
				<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				<?php esc_html_e( 'The chatbot stays invisible to visitors until you publish it in the last step.', 'smart-support-chatbot' ); ?>
			</p>
		</aside>

		<main class="ssc-wizard__main">
			<?php if ( 'identity' === $current ) : ?>

				<form method="post" class="ssc-form">
					<?php wp_nonce_field( 'ssc_wizard_identity' ); ?>
					<input type="hidden" name="ssc_wizard_step" value="identity" />

					<h2><?php esc_html_e( 'Who does the assistant represent?', 'smart-support-chatbot' ); ?></h2>
					<p class="ssc-form__lede"><?php esc_html_e( 'This is what the AI reads to understand your organization. Only the official name is required.', 'smart-support-chatbot' ); ?></p>

					<?php if ( 'org_name' === $err ) : ?>
						<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'Please enter the official organization name.', 'smart-support-chatbot' ); ?></div>
					<?php endif; ?>

					<div class="ssc-grid ssc-grid--2">
						<div class="ssc-field">
							<label for="b_org_name"><?php esc_html_e( 'Official organization name', 'smart-support-chatbot' ); ?> <span class="ssc-req">*</span></label>
							<input id="b_org_name" name="business[org_name]" type="text" required value="<?php echo esc_attr( $business['org_name'] ? $business['org_name'] : $site_title ); ?>" placeholder="<?php echo esc_attr( $site_title ); ?>" />
							<p class="ssc-field__hint"><?php esc_html_e( 'Suggested from your site title — edit if needed.', 'smart-support-chatbot' ); ?></p>
						</div>
						<div class="ssc-field">
							<label for="b_brand"><?php esc_html_e( 'Brand / trading name', 'smart-support-chatbot' ); ?></label>
							<input id="b_brand" name="business[brand_name]" type="text" value="<?php echo esc_attr( $business['brand_name'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="b_category"><?php esc_html_e( 'Business category', 'smart-support-chatbot' ); ?></label>
							<input id="b_category" name="business[category]" type="text" value="<?php echo esc_attr( $business['category'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Retail, Clinic, Manufacturer', 'smart-support-chatbot' ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="b_industry"><?php esc_html_e( 'Industry', 'smart-support-chatbot' ); ?></label>
							<input id="b_industry" name="business[industry]" type="text" value="<?php echo esc_attr( $business['industry'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Pharmaceutical, E-commerce', 'smart-support-chatbot' ); ?>" />
						</div>
					</div>

					<div class="ssc-field">
						<label for="b_desc"><?php esc_html_e( 'What does your organization do?', 'smart-support-chatbot' ); ?></label>
						<textarea id="b_desc" name="business[description]" rows="4" placeholder="<?php esc_attr_e( 'A short, factual description the assistant can rely on…', 'smart-support-chatbot' ); ?>"><?php echo esc_textarea( $business['description'] ); ?></textarea>
					</div>

					<details class="ssc-details">
						<summary><?php esc_html_e( 'Optional details (contacts, hours, differentiators)', 'smart-support-chatbot' ); ?></summary>
						<div class="ssc-grid ssc-grid--2">
							<div class="ssc-field">
								<label for="b_url"><?php esc_html_e( 'Website', 'smart-support-chatbot' ); ?></label>
								<input id="b_url" name="business[url]" type="url" value="<?php echo esc_attr( $business['url'] ? $business['url'] : $site_url ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_location"><?php esc_html_e( 'Location', 'smart-support-chatbot' ); ?></label>
								<input id="b_location" name="business[location]" type="text" value="<?php echo esc_attr( $business['location'] ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_phone"><?php esc_html_e( 'Contact phone', 'smart-support-chatbot' ); ?></label>
								<input id="b_phone" name="business[phone]" type="text" dir="ltr" value="<?php echo esc_attr( $business['phone'] ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_email"><?php esc_html_e( 'Contact email', 'smart-support-chatbot' ); ?></label>
								<input id="b_email" name="business[email]" type="email" dir="ltr" value="<?php echo esc_attr( $business['email'] ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_sphone"><?php esc_html_e( 'Support phone', 'smart-support-chatbot' ); ?></label>
								<input id="b_sphone" name="business[support_phone]" type="text" dir="ltr" value="<?php echo esc_attr( $business['support_phone'] ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_hours"><?php esc_html_e( 'Working hours', 'smart-support-chatbot' ); ?></label>
								<input id="b_hours" name="business[hours]" type="text" value="<?php echo esc_attr( $business['hours'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Sat–Wed, 9:00–17:00', 'smart-support-chatbot' ); ?>" />
							</div>
						</div>
						<div class="ssc-field">
							<label for="b_products"><?php esc_html_e( 'Products & services overview', 'smart-support-chatbot' ); ?></label>
							<textarea id="b_products" name="business[products]" rows="3"><?php echo esc_textarea( $business['products'] ); ?></textarea>
						</div>
						<div class="ssc-field">
							<label for="b_diff"><?php esc_html_e( 'What makes you different?', 'smart-support-chatbot' ); ?></label>
							<textarea id="b_diff" name="business[differentiators]" rows="3"><?php echo esc_textarea( $business['differentiators'] ); ?></textarea>
						</div>
					</details>

					<details class="ssc-details">
						<summary><?php esc_html_e( 'Assistant personality', 'smart-support-chatbot' ); ?></summary>
						<div class="ssc-grid ssc-grid--2">
							<div class="ssc-field">
								<label for="b_aname"><?php esc_html_e( 'Assistant name', 'smart-support-chatbot' ); ?></label>
								<input id="b_aname" name="business[assistant_name]" type="text" value="<?php echo esc_attr( $business['assistant_name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Sara', 'smart-support-chatbot' ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_arole"><?php esc_html_e( 'Assistant role', 'smart-support-chatbot' ); ?></label>
								<input id="b_arole" name="business[assistant_role]" type="text" value="<?php echo esc_attr( $business['assistant_role'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Customer support specialist', 'smart-support-chatbot' ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_tone"><?php esc_html_e( 'Communication tone', 'smart-support-chatbot' ); ?></label>
								<select id="b_tone" name="business[tone]">
									<?php
									$tones = array(
										'professional' => __( 'Professional', 'smart-support-chatbot' ),
										'friendly'     => __( 'Friendly', 'smart-support-chatbot' ),
										'formal'       => __( 'Formal', 'smart-support-chatbot' ),
										'casual'       => __( 'Casual', 'smart-support-chatbot' ),
									);
									foreach ( $tones as $tone_id => $tone_label ) :
										?>
										<option value="<?php echo esc_attr( $tone_id ); ?>" <?php selected( $business['tone'], $tone_id ); ?>><?php echo esc_html( $tone_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="ssc-field">
								<label for="b_lang"><?php esc_html_e( 'Primary answer language', 'smart-support-chatbot' ); ?></label>
								<input id="b_lang" name="business[language]" type="text" value="<?php echo esc_attr( $business['language'] ? $business['language'] : $site_lang ); ?>" placeholder="<?php echo esc_attr( $site_lang ); ?>" />
							</div>
						</div>
					</details>

					<div class="ssc-form__actions">
						<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-dashboard' ) ); ?>"><?php esc_html_e( 'Do this later', 'smart-support-chatbot' ); ?></a>
						<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Continue', 'smart-support-chatbot' ); ?> <span aria-hidden="true">→</span></button>
					</div>
				</form>

			<?php elseif ( 'knowledge' === $current ) : ?>

				<form method="post" class="ssc-form">
					<?php wp_nonce_field( 'ssc_wizard_knowledge' ); ?>
					<input type="hidden" name="ssc_wizard_step" value="knowledge" />

					<h2><?php esc_html_e( 'What should the assistant know?', 'smart-support-chatbot' ); ?></h2>
					<p class="ssc-form__lede"><?php esc_html_e( 'Add the facts visitors usually ask about. No AI jargon needed — just type what you would tell a new employee.', 'smart-support-chatbot' ); ?></p>

					<div id="ssc-ki-list" class="ssc-ki-list">
						<?php
						$existing = (array) SSC_Settings::get( 'knowledge_items', array() );
						$rows     = $existing ? $existing : array(
							array( 'type' => 'general', 'title' => '', 'content' => '' ),
						);
						foreach ( $rows as $i => $item ) :
							?>
							<div class="ssc-ki">
								<div class="ssc-ki__row">
									<select name="ki[<?php echo esc_attr( (string) $i ); ?>][type]" aria-label="<?php esc_attr_e( 'Knowledge type', 'smart-support-chatbot' ); ?>">
										<?php
										$types = array(
											'general' => __( 'General', 'smart-support-chatbot' ),
											'product' => __( 'Products', 'smart-support-chatbot' ),
											'service' => __( 'Services', 'smart-support-chatbot' ),
											'faq'     => __( 'FAQ', 'smart-support-chatbot' ),
											'policy'  => __( 'Policies', 'smart-support-chatbot' ),
											'support' => __( 'Support', 'smart-support-chatbot' ),
											'custom'  => __( 'Custom', 'smart-support-chatbot' ),
										);
										foreach ( $types as $t_id => $t_label ) :
											?>
											<option value="<?php echo esc_attr( $t_id ); ?>" <?php selected( isset( $item['type'] ) ? $item['type'] : 'general', $t_id ); ?>><?php echo esc_html( $t_label ); ?></option>
										<?php endforeach; ?>
									</select>
									<input type="text" name="ki[<?php echo esc_attr( (string) $i ); ?>][title]" value="<?php echo esc_attr( isset( $item['title'] ) ? $item['title'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Title (e.g. Return policy)', 'smart-support-chatbot' ); ?>" />
									<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove entry', 'smart-support-chatbot' ); ?>">×</button>
								</div>
								<textarea name="ki[<?php echo esc_attr( (string) $i ); ?>][content]" rows="3" placeholder="<?php esc_attr_e( 'The facts… (prices, policies, answers, anything)', 'smart-support-chatbot' ); ?>"><?php echo esc_textarea( isset( $item['content'] ) ? $item['content'] : '' ); ?></textarea>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="ssc-btn ssc-btn--ghost ssc-ki__add" data-target="#ssc-ki-list"><?php esc_html_e( '+ Add another entry', 'smart-support-chatbot' ); ?></button>

					<details class="ssc-details ssc-mt">
						<summary><?php esc_html_e( 'Import from a website page (optional)', 'smart-support-chatbot' ); ?></summary>
						<div class="ssc-field">
							<label for="kb_url"><?php esc_html_e( 'Page URL', 'smart-support-chatbot' ); ?></label>
							<input id="kb_url" name="import_url" type="url" dir="ltr" placeholder="https://example.com/about" />
							<p class="ssc-field__hint"><?php esc_html_e( 'The page is fetched once, now, because you asked for it — its readable text becomes reference knowledge.', 'smart-support-chatbot' ); ?></p>
						</div>
					</details>

					<div class="ssc-form__actions">
						<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-wizard', 'step' => 'identity' ), admin_url( 'admin.php' ) ) ); ?>">← <?php esc_html_e( 'Back', 'smart-support-chatbot' ); ?></a>
						<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Continue', 'smart-support-chatbot' ); ?> <span aria-hidden="true">→</span></button>
					</div>
				</form>

			<?php elseif ( 'connection' === $current ) : ?>

				<?php
				$providers = SSC_Providers::all();
				$labels_p  = SSC_Providers::labels();
				$sel       = (string) $s['ai_provider'];
				?>
				<form method="post" class="ssc-form" id="ssc-connection-form">
					<?php wp_nonce_field( 'ssc_wizard_connection' ); ?>
					<input type="hidden" name="ssc_wizard_step" value="connection" />

					<h2><?php esc_html_e( 'Connect an AI provider', 'smart-support-chatbot' ); ?></h2>
					<p class="ssc-form__lede"><?php esc_html_e( 'Select a provider, paste your API key, choose a model — then run the test. You cannot continue until the connection actually works.', 'smart-support-chatbot' ); ?></p>

					<?php if ( 'required' === $verify ) : ?>
						<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'This provider is not verified yet. Run the connection test below and wait for the green result before continuing.', 'smart-support-chatbot' ); ?></div>
					<?php elseif ( isset( $_GET['offline'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
						<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'Saved in offline mode: the assistant will answer from your FAQ bank only. You can connect an AI provider anytime later.', 'smart-support-chatbot' ); ?></div>
					<?php endif; ?>

					<div class="ssc-field">
						<label for="ai_provider"><?php esc_html_e( 'Provider', 'smart-support-chatbot' ); ?></label>
						<select id="ai_provider" name="ai_provider">
							<?php foreach ( $labels_p as $pid => $plabel ) : ?>
								<option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $sel, $pid ); ?>><?php echo esc_html( $plabel ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<?php foreach ( $providers as $pid => $provider ) : ?>
						<div class="ssc-provider-fields" data-provider="<?php echo esc_attr( $pid ); ?>" <?php echo ( $sel === $pid ) ? '' : 'hidden'; ?>>
							<?php if ( 'webhook' !== $pid ) : ?>
								<div class="ssc-grid ssc-grid--2">
									<?php if ( $provider->needs_key() ) : ?>
										<div class="ssc-field">
											<label for="<?php echo esc_attr( $pid ); ?>_api_key"><?php esc_html_e( 'API key', 'smart-support-chatbot' ); ?></label>
											<input id="<?php echo esc_attr( $pid ); ?>_api_key" name="<?php echo esc_attr( $pid ); ?>_api_key" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( $pid . '_api_key' ) ? esc_attr__( 'A key is stored — type to replace', 'smart-support-chatbot' ) : esc_attr__( 'Paste your API key', 'smart-support-chatbot' ); ?>" />
										</div>
									<?php endif; ?>
									<div class="ssc-field">
										<label for="<?php echo esc_attr( $pid ); ?>_model"><?php esc_html_e( 'Model', 'smart-support-chatbot' ); ?></label>
										<?php $models = $provider->models(); ?>
										<?php if ( $models ) : ?>
											<select id="<?php echo esc_attr( $pid ); ?>_model" name="<?php echo esc_attr( $pid ); ?>_model" data-manual="1">
												<?php
												$saved_model = (string) SSC_Settings::get( $pid . '_model', '' );
												$has_saved   = '' !== $saved_model;
												?>
												<?php if ( $has_saved && ! isset( $models[ $saved_model ] ) ) : ?>
													<option value="<?php echo esc_attr( $saved_model ); ?>" selected><?php echo esc_html( $saved_model ); ?></option>
												<?php endif; ?>
												<?php foreach ( $models as $m_id => $m_label ) : ?>
													<option value="<?php echo esc_attr( $m_id ); ?>" <?php selected( $saved_model, $m_id ); ?>><?php echo esc_html( $m_label ); ?></option>
												<?php endforeach; ?>
												<option value="__manual__"><?php esc_html_e( 'Enter model ID manually…', 'smart-support-chatbot' ); ?></option>
											</select>
											<?php // Hidden and disabled until "Enter model ID manually…" is picked, so it never posts a stray empty model. ?>
											<input class="ssc-model-manual" type="text" name="<?php echo esc_attr( $pid ); ?>_model_manual" dir="ltr" placeholder="<?php esc_attr_e( 'Model ID (e.g. my-model-v2)', 'smart-support-chatbot' ); ?>" hidden disabled />
										<?php else : ?>
											<input id="<?php echo esc_attr( $pid ); ?>_model" name="<?php echo esc_attr( $pid ); ?>_model" type="text" dir="ltr" value="<?php echo esc_attr( (string) SSC_Settings::get( $pid . '_model', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Model ID', 'smart-support-chatbot' ); ?>" />
										<?php endif; ?>
									</div>
								</div>
								<?php if ( 'custom' === $pid ) : ?>
									<div class="ssc-field">
										<label for="custom_endpoint"><?php esc_html_e( 'Endpoint URL (OpenAI-compatible)', 'smart-support-chatbot' ); ?></label>
										<input id="custom_endpoint" name="custom_endpoint" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['custom_endpoint'] ); ?>" placeholder="https://your-gateway.example/v1/chat/completions" />
										<p class="ssc-field__hint"><?php esc_html_e( 'Works with OpenRouter, Groq, DeepSeek, Ollama relays, Azure-style proxies and more.', 'smart-support-chatbot' ); ?></p>
									</div>
								<?php endif; ?>
							<?php else : ?>
								<div class="ssc-field">
									<label for="ai_webhook_url"><?php esc_html_e( 'Webhook URL', 'smart-support-chatbot' ); ?></label>
									<input id="ai_webhook_url" name="ai_webhook_url" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['ai_webhook_url'] ); ?>" placeholder="https://your-service.example/chat" />
								</div>
								<div class="ssc-field">
									<label for="ai_webhook_secret"><?php esc_html_e( 'Shared secret (HMAC, recommended)', 'smart-support-chatbot' ); ?></label>
									<input id="ai_webhook_secret" name="ai_webhook_secret" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( 'ai_webhook_secret' ) ? esc_attr__( 'A secret is stored — type to replace', 'smart-support-chatbot' ) : ''; ?>" />
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<div class="ssc-conn-test">
						<button type="button" class="ssc-btn ssc-btn--secondary" id="ssc-test-connection"><?php esc_html_e( 'Test connection', 'smart-support-chatbot' ); ?></button>
						<span id="ssc-test-result" class="ssc-conn-test__result" role="status" aria-live="polite"></span>
					</div>

					<div class="ssc-form__actions">
						<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-wizard', 'step' => 'knowledge' ), admin_url( 'admin.php' ) ) ); ?>">← <?php esc_html_e( 'Back', 'smart-support-chatbot' ); ?></a>
						<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Continue', 'smart-support-chatbot' ); ?> <span aria-hidden="true">→</span></button>
					</div>
				</form>

			<?php elseif ( 'appearance' === $current ) : ?>

				<form method="post" class="ssc-form">
					<?php wp_nonce_field( 'ssc_wizard_appearance' ); ?>
					<input type="hidden" name="ssc_wizard_step" value="appearance" />

					<h2><?php esc_html_e( 'Make it yours', 'smart-support-chatbot' ); ?></h2>
					<p class="ssc-form__lede"><?php esc_html_e( 'Brand color, texts and placement — with a live preview. Everything can be fine-tuned later.', 'smart-support-chatbot' ); ?></p>

					<div class="ssc-appear">
						<div class="ssc-appear__controls">
							<div class="ssc-grid ssc-grid--2">
								<div class="ssc-field">
									<label for="primary_color"><?php esc_html_e( 'Primary brand color', 'smart-support-chatbot' ); ?></label>
									<input id="primary_color" name="primary_color" type="color" value="<?php echo esc_attr( $s['primary_color'] ? $s['primary_color'] : '#b61615' ); ?>" class="ssc-color" />
								</div>
								<div class="ssc-field">
									<label for="theme_mode"><?php esc_html_e( 'Theme', 'smart-support-chatbot' ); ?></label>
									<select id="theme_mode" name="theme_mode">
										<option value="light" <?php selected( $s['theme_mode'], 'light' ); ?>><?php esc_html_e( 'Light', 'smart-support-chatbot' ); ?></option>
										<option value="dark" <?php selected( $s['theme_mode'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'smart-support-chatbot' ); ?></option>
										<option value="auto" <?php selected( $s['theme_mode'], 'auto' ); ?>><?php esc_html_e( 'Match visitor device', 'smart-support-chatbot' ); ?></option>
									</select>
								</div>
								<div class="ssc-field">
									<label for="position"><?php esc_html_e( 'Floating button position', 'smart-support-chatbot' ); ?></label>
									<select id="position" name="position">
										<option value="right" <?php selected( $s['position'], 'right' ); ?>><?php esc_html_e( 'Bottom right', 'smart-support-chatbot' ); ?></option>
										<option value="left" <?php selected( $s['position'], 'left' ); ?>><?php esc_html_e( 'Bottom left', 'smart-support-chatbot' ); ?></option>
									</select>
								</div>
								<div class="ssc-field">
									<label for="direction"><?php esc_html_e( 'Text direction', 'smart-support-chatbot' ); ?></label>
									<select id="direction" name="direction">
										<option value="rtl" <?php selected( $s['direction'], 'rtl' ); ?>><?php esc_html_e( 'RTL (Persian/Arabic)', 'smart-support-chatbot' ); ?></option>
										<option value="ltr" <?php selected( $s['direction'], 'ltr' ); ?>><?php esc_html_e( 'LTR (Latin)', 'smart-support-chatbot' ); ?></option>
										<option value="auto" <?php selected( $s['direction'], 'auto' ); ?>><?php esc_html_e( 'Auto (follow site language)', 'smart-support-chatbot' ); ?></option>
									</select>
								</div>
							</div>
							<div class="ssc-field">
								<label for="assistant_display_name"><?php esc_html_e( 'Assistant display name', 'smart-support-chatbot' ); ?></label>
								<input id="assistant_display_name" name="assistant_display_name" type="text" value="<?php echo esc_attr( $s['assistant_display_name'] ); ?>" placeholder="<?php echo esc_attr( $business['assistant_name'] ? $business['assistant_name'] : __( 'Nexa', 'smart-support-chatbot' ) ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="welcome_title"><?php esc_html_e( 'Welcome title', 'smart-support-chatbot' ); ?></label>
								<input id="welcome_title" name="welcome_title" type="text" value="<?php echo esc_attr( $s['welcome_title'] ); ?>" placeholder="<?php esc_attr_e( 'Hello! 👋', 'smart-support-chatbot' ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="welcome_text"><?php esc_html_e( 'Welcome message', 'smart-support-chatbot' ); ?></label>
								<textarea id="welcome_text" name="welcome_text" rows="2"><?php echo esc_textarea( $s['welcome_text'] ); ?></textarea>
							</div>
						</div>
						<div class="ssc-appear__preview">
							<div class="ssc-preview" id="ssc-preview" data-primary="<?php echo esc_attr( $s['primary_color'] ); ?>">
								<div class="ssc-preview__win">
									<div class="ssc-preview__head">
										<span class="ssc-preview__avatar"></span>
										<div>
											<strong id="ssc-preview-name"><?php echo esc_html( $s['assistant_display_name'] ? $s['assistant_display_name'] : __( 'Nexa', 'smart-support-chatbot' ) ); ?></strong>
											<em><?php esc_html_e( 'Online', 'smart-support-chatbot' ); ?></em>
										</div>
									</div>
									<div class="ssc-preview__body">
										<p class="ssc-preview__bot"><strong id="ssc-preview-wtitle"><?php echo esc_html( $s['welcome_title'] ? $s['welcome_title'] : __( 'Hello! 👋', 'smart-support-chatbot' ) ); ?></strong><span id="ssc-preview-wtext"><?php echo esc_html( $s['welcome_text'] ? wp_strip_all_tags( $s['welcome_text'] ) : __( 'How can I help you today?', 'smart-support-chatbot' ) ); ?></span></p>
										<p class="ssc-preview__user"><?php esc_html_e( 'Hi! Do you ship internationally?', 'smart-support-chatbot' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="ssc-form__actions">
						<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-wizard', 'step' => 'connection' ), admin_url( 'admin.php' ) ) ); ?>">← <?php esc_html_e( 'Back', 'smart-support-chatbot' ); ?></a>
						<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Continue', 'smart-support-chatbot' ); ?> <span aria-hidden="true">→</span></button>
					</div>
				</form>

			<?php else : ?>

				<?php $readiness = SSC_Setup::readiness(); $all_ready = true; ?>
				<h2><?php esc_html_e( 'Final check & launch', 'smart-support-chatbot' ); ?></h2>
				<p class="ssc-form__lede"><?php esc_html_e( 'Review the checklist, try the assistant yourself, then publish it when you are satisfied.', 'smart-support-chatbot' ); ?></p>

				<?php if ( 'not_ready' === $err ) : ?>
					<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'Some readiness items are incomplete — finish them first (each links to its settings).', 'smart-support-chatbot' ); ?></div>
				<?php endif; ?>
				<?php if ( $published ) : ?>
					<div class="ssc-notice ssc-notice--success" role="status"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Published! Your assistant is now live on your site.', 'smart-support-chatbot' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-dashboard' ) ); ?>"><?php esc_html_e( 'Go to dashboard', 'smart-support-chatbot' ); ?></a></div>
				<?php endif; ?>

				<ul class="ssc-checklist">
					<?php
					$links = array(
						'identity'       => admin_url( 'admin.php?page=ssc-wizard&step=identity' ),
						'knowledge'      => admin_url( 'admin.php?page=ssc-knowledge' ),
						'connection'     => admin_url( 'admin.php?page=ssc-connection' ),
						'identity_test'  => '#ssc-identity-test',
						'appearance'     => admin_url( 'admin.php?page=ssc-appearance' ),
						'privacy'        => '#ssc-privacy-ack',
					);
					foreach ( $readiness as $item ) :
						if ( ! $item['done'] ) {
							$all_ready = false;
						}
						?>
						<li class="<?php echo $item['done'] ? 'is-done' : ''; ?>">
							<span class="ssc-checklist__mark" aria-hidden="true"><?php echo $item['done'] ? '✓' : '○'; ?></span>
							<span><?php echo esc_html( $item['label'] ); ?></span>
							<?php if ( ! $item['done'] ) : ?>
								<a href="<?php echo esc_attr( $links[ $item['id'] ] ); ?>"><?php esc_html_e( 'Fix', 'smart-support-chatbot' ); ?></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>

				<div class="ssc-identity-test" id="ssc-identity-test">
					<h3><?php esc_html_e( 'Business identity test', 'smart-support-chatbot' ); ?></h3>
					<p><?php esc_html_e( 'Asks the assistant which organization it represents. It must answer correctly before publishing.', 'smart-support-chatbot' ); ?></p>
					<button type="button" class="ssc-btn ssc-btn--secondary" id="ssc-run-identity-test"><?php esc_html_e( 'Run identity test', 'smart-support-chatbot' ); ?></button>
					<div id="ssc-identity-result" class="ssc-identity-result" role="status" aria-live="polite"></div>
				</div>

				<div class="ssc-preview-chat">
					<h3><?php esc_html_e( 'Try the assistant (preview)', 'smart-support-chatbot' ); ?></h3>
					<p><?php esc_html_e( 'A private preview — visitors cannot see it. Ask anything your customers would ask.', 'smart-support-chatbot' ); ?></p>
					<div id="ssc-preview-mount" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>"></div>
				</div>

				<form method="post" class="ssc-form ssc-launch">
					<?php wp_nonce_field( 'ssc_wizard_review' ); ?>
					<input type="hidden" name="ssc_wizard_step" value="review" />

					<label class="ssc-check" id="ssc-privacy-ack">
						<input type="checkbox" name="privacy_ack" value="1" <?php checked( 'yes', SSC_Settings::get( 'privacy_acknowledged', 'no' ) ); ?> required />
						<span><?php esc_html_e( 'I understand chat messages are sent to the AI provider I configured (an external service) and form data is stored on my site. I will inform my visitors.', 'smart-support-chatbot' ); ?></span>
					</label>

					<div class="ssc-form__actions">
						<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-wizard', 'step' => 'appearance' ), admin_url( 'admin.php' ) ) ); ?>">← <?php esc_html_e( 'Back', 'smart-support-chatbot' ); ?></a>
						<button type="submit" name="publish" value="1" class="ssc-btn ssc-btn--primary ssc-btn--launch" <?php disabled( ! $all_ready ); ?>>
							<?php esc_html_e( 'Publish chatbot', 'smart-support-chatbot' ); ?>
						</button>
					</div>
				</form>

			<?php
			// Enqueue the real widget for the live preview (admin-only preview endpoint).
			if ( 'review' === $current ) {
				wp_enqueue_style( 'smart-support-chatbot' );
				wp_enqueue_script( 'smart-support-chatbot' );
				$preview_config = array(
					'preview'         => true,
					'restUrl'         => esc_url_raw( rest_url( SSC_REST::NS . '/' ) ),
					'previewRoute'    => 'preview-chat',
					'nonce'           => wp_create_nonce( 'wp_rest' ),
					'assistantName'   => '' !== trim( (string) $s['assistant_display_name'] ) ? $s['assistant_display_name'] : __( 'Nexa', 'smart-support-chatbot' ),
					'orgName'         => '' !== trim( (string) $business['org_name'] ) ? $business['org_name'] : get_bloginfo( 'name' ),
					'welcomeTitle'    => '' !== trim( (string) $s['welcome_title'] ) ? $s['welcome_title'] : __( 'Hello! 👋', 'smart-support-chatbot' ),
					'welcomeText'     => '' !== trim( (string) $s['welcome_text'] ) ? $s['welcome_text'] : __( 'How can I help you today?', 'smart-support-chatbot' ),
					'disclaimer'      => (string) $s['disclaimer'],
					'direction'       => is_rtl() ? 'rtl' : 'ltr',
					'themeMode'       => $s['theme_mode'],
					'position'        => $s['position'],
					'primaryColor'    => $s['primary_color'],
					'fontSize'        => (int) $s['font_size'],
					'windowWidth'     => (int) $s['window_width'],
					'windowRadius'    => (int) $s['window_radius'],
					'bubbleRadius'    => (int) $s['bubble_radius'],
					'userBubble'      => $s['user_bubble_color'],
					'botBubble'       => $s['bot_bubble_color'],
					'fontStack'       => '',
					'avatarUrl'       => $s['avatar_url'],
					'launcherSize'    => (int) $s['launcher_size'],
					'launcherIconUrl' => $s['launcher_icon_url'],
					'supportPhone'    => $business['support_phone'] ? $business['support_phone'] : $business['phone'],
					'products'        => array(),
					'features'        => array(
						'leads'       => false,
						'faq'         => false,
						'voice'       => false,
						'voiceInput'  => false,
						'voiceOutput' => false,
						'csat'        => false,
						'handoff'     => false,
						'proactive'   => false,
						'pharma'      => false,
						'feedback'    => false,
					),
					'handoffText'     => '',
					'proactiveDelay'  => 0,
					'proactiveText'   => '',
					'voiceLanguage'   => get_locale(),
					'formFields'      => array(),
					'consent'         => array( 'enabled' => false, 'text' => '', 'link' => '' ),
					'adrOptions'      => null,
					'i18n'            => array(
						'open'          => __( 'Open chat', 'smart-support-chatbot' ),
						'close'         => __( 'Close chat', 'smart-support-chatbot' ),
						'send'          => __( 'Send message', 'smart-support-chatbot' ),
						'inputLabel'    => __( 'Message text', 'smart-support-chatbot' ),
						'placeholder'   => __( 'Write your message…', 'smart-support-chatbot' ),
						'sessionExpired' => __( 'Your session expired. Please refresh the page and try again.', 'smart-support-chatbot' ),
						'connectionError' => __( 'Connection error. Please check your internet and try again.', 'smart-support-chatbot' ),
						'rateLimited'   => __( 'Rate limit reached.', 'smart-support-chatbot' ),
						'mainMenu'      => __( 'Main menu', 'smart-support-chatbot' ),
						'typing'        => __( 'Typing…', 'smart-support-chatbot' ),
					),
				);
				wp_localize_script( 'smart-support-chatbot', 'SSCChatbotConfig', $preview_config );
			}
			?>
					<?php endif; ?>
			</main>
	</div>
</div>
