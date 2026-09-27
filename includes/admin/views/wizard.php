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
				<h1><?php esc_html_e( 'Set up your AI assistant', 'nexachat-ai' ); ?></h1>
				<p><?php esc_html_e( 'Five short steps. Your progress is saved automatically — you can leave and come back anytime.', 'nexachat-ai' ); ?></p>
			</div>
		</div>
		<?php if ( $saved_flag ) : ?>
			<div class="ssc-notice ssc-notice--success" role="status"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Saved', 'nexachat-ai' ); ?></div>
		<?php endif; ?>
	</header>

	<div class="ssc-wizard__body">
		<aside class="ssc-wizard__rail" aria-label="<?php esc_attr_e( 'Setup progress', 'nexachat-ai' ); ?>">
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
				<?php esc_html_e( 'The chatbot stays invisible to visitors until you publish it in the last step.', 'nexachat-ai' ); ?>
			</p>
		</aside>

		<main class="ssc-wizard__main">
			<?php if ( 'identity' === $current ) : ?>

				<form method="post" class="ssc-form">
					<?php wp_nonce_field( 'ssc_wizard_identity' ); ?>
					<input type="hidden" name="ssc_wizard_step" value="identity" />

					<h2><?php esc_html_e( 'Who does the assistant represent?', 'nexachat-ai' ); ?></h2>
					<p class="ssc-form__lede"><?php esc_html_e( 'This is what the AI reads to understand your organization. Only the official name is required.', 'nexachat-ai' ); ?></p>

					<?php if ( 'org_name' === $err ) : ?>
						<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'Please enter the official organization name.', 'nexachat-ai' ); ?></div>
					<?php endif; ?>

					<?php
					$ssc_templates = SSC_Templates::all();
					$ssc_applied   = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
					$ssc_suggested = isset( $_GET['modules'] ) ? array_filter( explode( ',', sanitize_text_field( wp_unslash( $_GET['modules'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
					$ssc_current   = isset( SSC_Setup::state()['template'] ) ? SSC_Setup::state()['template'] : '';
					?>
					<div class="ssc-templates" role="group" aria-labelledby="ssc-templates-title">
						<p class="ssc-templates__title" id="ssc-templates-title"><?php esc_html_e( 'Start from a template for your business (optional)', 'nexachat-ai' ); ?></p>
						<div class="ssc-templates__grid">
							<?php foreach ( $ssc_templates as $ssc_tid => $ssc_t ) : ?>
								<button type="submit" name="ssc_template" value="<?php echo esc_attr( $ssc_tid ); ?>" formnovalidate class="ssc-template<?php echo $ssc_tid === $ssc_current ? ' is-current' : ''; ?>">
									<span class="ssc-template__icon" aria-hidden="true"><?php echo esc_html( $ssc_t['icon'] ); ?></span>
									<span class="ssc-template__label"><?php echo esc_html( $ssc_t['label'] ); ?></span>
									<span class="ssc-template__hint"><?php echo esc_html( $ssc_t['hint'] ); ?></span>
								</button>
							<?php endforeach; ?>
						</div>
						<p class="ssc-field__hint"><?php esc_html_e( 'A template only fills empty fields (role, tone, welcome text, answer rules, request-form fields); your own text is never replaced.', 'nexachat-ai' ); ?></p>
					</div>
					<?php if ( $ssc_applied && isset( $ssc_templates[ $ssc_applied ] ) ) : ?>
						<div class="ssc-notice ssc-notice--success" role="status">
							<?php echo esc_html( sprintf( /* translators: %s: template name. */ __( 'Template "%s" applied to the empty fields.', 'nexachat-ai' ), $ssc_templates[ $ssc_applied ]['label'] ) ); ?>
							<?php
							$ssc_module_names = array();
							foreach ( $ssc_suggested as $ssc_mid ) {
								$ssc_module = SSC_Modules::get( sanitize_key( $ssc_mid ) );
								if ( $ssc_module ) {
									$ssc_module_names[] = $ssc_module->title();
								}
							}
							?>
							<?php if ( $ssc_module_names ) : ?>
								<br /><?php echo esc_html( sprintf( /* translators: %s: module names. */ __( 'Recommended modules (switch them on later in Modules): %s', 'nexachat-ai' ), implode( '، ', $ssc_module_names ) ) ); ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>


					<div class="ssc-grid ssc-grid--2">
						<div class="ssc-field">
							<label for="b_org_name"><?php esc_html_e( 'Official organization name', 'nexachat-ai' ); ?> <span class="ssc-req">*</span></label>
							<input id="b_org_name" name="business[org_name]" type="text" required value="<?php echo esc_attr( $business['org_name'] ? $business['org_name'] : $site_title ); ?>" placeholder="<?php echo esc_attr( $site_title ); ?>" />
							<p class="ssc-field__hint"><?php esc_html_e( 'Suggested from your site title — edit if needed.', 'nexachat-ai' ); ?></p>
						</div>
						<div class="ssc-field">
							<label for="b_brand"><?php esc_html_e( 'Brand / trading name', 'nexachat-ai' ); ?></label>
							<input id="b_brand" name="business[brand_name]" type="text" value="<?php echo esc_attr( $business['brand_name'] ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="b_category"><?php esc_html_e( 'Business category', 'nexachat-ai' ); ?></label>
							<input id="b_category" name="business[category]" type="text" value="<?php echo esc_attr( $business['category'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Retail, Clinic, Manufacturer', 'nexachat-ai' ); ?>" />
						</div>
						<div class="ssc-field">
							<label for="b_industry"><?php esc_html_e( 'Industry', 'nexachat-ai' ); ?></label>
							<input id="b_industry" name="business[industry]" type="text" value="<?php echo esc_attr( $business['industry'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Pharmaceutical, E-commerce', 'nexachat-ai' ); ?>" />
						</div>
					</div>

					<div class="ssc-field">
						<label for="b_desc"><?php esc_html_e( 'What does your organization do?', 'nexachat-ai' ); ?></label>
						<textarea id="b_desc" name="business[description]" rows="4" placeholder="<?php esc_attr_e( 'A short, factual description the assistant can rely on…', 'nexachat-ai' ); ?>"><?php echo esc_textarea( $business['description'] ); ?></textarea>
					</div>

					<details class="ssc-details">
						<summary><?php esc_html_e( 'Optional details (contacts, hours, differentiators)', 'nexachat-ai' ); ?></summary>
						<div class="ssc-grid ssc-grid--2">
							<div class="ssc-field">
								<label for="b_url"><?php esc_html_e( 'Website', 'nexachat-ai' ); ?></label>
								<input id="b_url" name="business[url]" type="url" value="<?php echo esc_attr( $business['url'] ? $business['url'] : $site_url ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_location"><?php esc_html_e( 'Location', 'nexachat-ai' ); ?></label>
								<input id="b_location" name="business[location]" type="text" value="<?php echo esc_attr( $business['location'] ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_phone"><?php esc_html_e( 'Contact phone', 'nexachat-ai' ); ?></label>
								<input id="b_phone" name="business[phone]" type="text" dir="ltr" value="<?php echo esc_attr( $business['phone'] ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_email"><?php esc_html_e( 'Contact email', 'nexachat-ai' ); ?></label>
								<input id="b_email" name="business[email]" type="email" dir="ltr" value="<?php echo esc_attr( $business['email'] ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_sphone"><?php esc_html_e( 'Support phone', 'nexachat-ai' ); ?></label>
								<input id="b_sphone" name="business[support_phone]" type="text" dir="ltr" value="<?php echo esc_attr( $business['support_phone'] ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_hours"><?php esc_html_e( 'Working hours', 'nexachat-ai' ); ?></label>
								<input id="b_hours" name="business[hours]" type="text" value="<?php echo esc_attr( $business['hours'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Sat–Wed, 9:00–17:00', 'nexachat-ai' ); ?>" />
							</div>
						</div>
						<div class="ssc-field">
							<label for="b_products"><?php esc_html_e( 'Products & services overview', 'nexachat-ai' ); ?></label>
							<textarea id="b_products" name="business[products]" rows="3"><?php echo esc_textarea( $business['products'] ); ?></textarea>
						</div>
						<div class="ssc-field">
							<label for="b_diff"><?php esc_html_e( 'What makes you different?', 'nexachat-ai' ); ?></label>
							<textarea id="b_diff" name="business[differentiators]" rows="3"><?php echo esc_textarea( $business['differentiators'] ); ?></textarea>
						</div>
					</details>

					<details class="ssc-details">
						<summary><?php esc_html_e( 'Assistant personality', 'nexachat-ai' ); ?></summary>
						<div class="ssc-grid ssc-grid--2">
							<div class="ssc-field">
								<label for="b_aname"><?php esc_html_e( 'Assistant name', 'nexachat-ai' ); ?></label>
								<input id="b_aname" name="business[assistant_name]" type="text" value="<?php echo esc_attr( $business['assistant_name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Sara', 'nexachat-ai' ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_arole"><?php esc_html_e( 'Assistant role', 'nexachat-ai' ); ?></label>
								<input id="b_arole" name="business[assistant_role]" type="text" value="<?php echo esc_attr( $business['assistant_role'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Customer support specialist', 'nexachat-ai' ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="b_tone"><?php esc_html_e( 'Communication tone', 'nexachat-ai' ); ?></label>
								<select id="b_tone" name="business[tone]">
									<?php
									$tones = array(
										'professional' => __( 'Professional', 'nexachat-ai' ),
										'friendly'     => __( 'Friendly', 'nexachat-ai' ),
										'formal'       => __( 'Formal', 'nexachat-ai' ),
										'casual'       => __( 'Casual', 'nexachat-ai' ),
									);
									foreach ( $tones as $tone_id => $tone_label ) :
										?>
										<option value="<?php echo esc_attr( $tone_id ); ?>" <?php selected( $business['tone'], $tone_id ); ?>><?php echo esc_html( $tone_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="ssc-field">
								<label for="b_lang"><?php esc_html_e( 'Primary answer language', 'nexachat-ai' ); ?></label>
								<input id="b_lang" name="business[language]" type="text" value="<?php echo esc_attr( $business['language'] ? $business['language'] : $site_lang ); ?>" placeholder="<?php echo esc_attr( $site_lang ); ?>" />
							</div>
						</div>
					</details>

					<div class="ssc-form__actions">
						<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-dashboard' ) ); ?>"><?php esc_html_e( 'Do this later', 'nexachat-ai' ); ?></a>
						<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Continue', 'nexachat-ai' ); ?> <span class="ssc-dir-arrow" aria-hidden="true">→</span></button>
					</div>
				</form>

			<?php elseif ( 'knowledge' === $current ) : ?>

				<form method="post" class="ssc-form">
					<?php wp_nonce_field( 'ssc_wizard_knowledge' ); ?>
					<input type="hidden" name="ssc_wizard_step" value="knowledge" />

					<h2><?php esc_html_e( 'What should the assistant know?', 'nexachat-ai' ); ?></h2>
					<p class="ssc-form__lede"><?php esc_html_e( 'Add the facts visitors usually ask about. No AI jargon needed — just type what you would tell a new employee.', 'nexachat-ai' ); ?></p>

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
									<select name="ki[<?php echo esc_attr( (string) $i ); ?>][type]" aria-label="<?php esc_attr_e( 'Knowledge type', 'nexachat-ai' ); ?>">
										<?php
										$types = array(
											'general' => __( 'General', 'nexachat-ai' ),
											'product' => __( 'Products', 'nexachat-ai' ),
											'service' => __( 'Services', 'nexachat-ai' ),
											'faq'     => __( 'FAQ', 'nexachat-ai' ),
											'policy'  => __( 'Policies', 'nexachat-ai' ),
											'support' => __( 'Support', 'nexachat-ai' ),
											'custom'  => __( 'Custom', 'nexachat-ai' ),
										);
										foreach ( $types as $t_id => $t_label ) :
											?>
											<option value="<?php echo esc_attr( $t_id ); ?>" <?php selected( isset( $item['type'] ) ? $item['type'] : 'general', $t_id ); ?>><?php echo esc_html( $t_label ); ?></option>
										<?php endforeach; ?>
									</select>
									<input type="text" name="ki[<?php echo esc_attr( (string) $i ); ?>][title]" value="<?php echo esc_attr( isset( $item['title'] ) ? $item['title'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Title (e.g. Return policy)', 'nexachat-ai' ); ?>" />
									<button type="button" class="ssc-ki__remove" aria-label="<?php esc_attr_e( 'Remove entry', 'nexachat-ai' ); ?>">×</button>
								</div>
								<textarea name="ki[<?php echo esc_attr( (string) $i ); ?>][content]" rows="3" placeholder="<?php esc_attr_e( 'The facts… (prices, policies, answers, anything)', 'nexachat-ai' ); ?>"><?php echo esc_textarea( isset( $item['content'] ) ? $item['content'] : '' ); ?></textarea>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="ssc-btn ssc-btn--ghost ssc-ki__add" data-target="#ssc-ki-list"><?php esc_html_e( '+ Add another entry', 'nexachat-ai' ); ?></button>

					<details class="ssc-details ssc-mt">
						<summary><?php esc_html_e( 'Import from a website page (optional)', 'nexachat-ai' ); ?></summary>
						<div class="ssc-field">
							<label for="kb_url"><?php esc_html_e( 'Page URL', 'nexachat-ai' ); ?></label>
							<input id="kb_url" name="import_url" type="url" dir="ltr" placeholder="https://example.com/about" />
							<p class="ssc-field__hint"><?php esc_html_e( 'The page is fetched once, now, because you asked for it — its readable text becomes reference knowledge.', 'nexachat-ai' ); ?></p>
						</div>
					</details>

					<div class="ssc-form__actions">
						<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-wizard', 'step' => 'identity' ), admin_url( 'admin.php' ) ) ); ?>"><span class="ssc-dir-arrow" aria-hidden="true">←</span> <?php esc_html_e( 'Back', 'nexachat-ai' ); ?></a>
						<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Continue', 'nexachat-ai' ); ?> <span class="ssc-dir-arrow" aria-hidden="true">→</span></button>
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

					<h2><?php esc_html_e( 'Connect an AI provider', 'nexachat-ai' ); ?></h2>
					<p class="ssc-form__lede"><?php esc_html_e( 'Select a provider, paste your API key, choose a model — then run the test. You cannot continue until the connection actually works.', 'nexachat-ai' ); ?></p>

					<?php if ( 'required' === $verify ) : ?>
						<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'This provider is not verified yet. Run the connection test below and wait for the green result before continuing.', 'nexachat-ai' ); ?></div>
					<?php elseif ( isset( $_GET['offline'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
						<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'Saved in offline mode: the assistant will answer from your FAQ bank only. You can connect an AI provider anytime later.', 'nexachat-ai' ); ?></div>
					<?php endif; ?>

					<div class="ssc-field">
						<label for="ai_provider"><?php esc_html_e( 'Provider', 'nexachat-ai' ); ?></label>
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
											<label for="<?php echo esc_attr( $pid ); ?>_api_key"><?php esc_html_e( 'API key', 'nexachat-ai' ); ?></label>
											<input id="<?php echo esc_attr( $pid ); ?>_api_key" name="<?php echo esc_attr( $pid ); ?>_api_key" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( $pid . '_api_key' ) ? esc_attr__( 'A key is stored — type to replace', 'nexachat-ai' ) : esc_attr__( 'Paste your API key', 'nexachat-ai' ); ?>" />
										</div>
									<?php endif; ?>
									<div class="ssc-field">
										<label for="<?php echo esc_attr( $pid ); ?>_model"><?php esc_html_e( 'Model', 'nexachat-ai' ); ?></label>
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
												<option value="__manual__"><?php esc_html_e( 'Enter model ID manually…', 'nexachat-ai' ); ?></option>
											</select>
											<?php // Hidden and disabled until "Enter model ID manually…" is picked, so it never posts a stray empty model. ?>
											<input class="ssc-model-manual" type="text" name="<?php echo esc_attr( $pid ); ?>_model_manual" dir="ltr" placeholder="<?php esc_attr_e( 'Model ID (e.g. my-model-v2)', 'nexachat-ai' ); ?>" hidden disabled />
										<?php else : ?>
											<input id="<?php echo esc_attr( $pid ); ?>_model" name="<?php echo esc_attr( $pid ); ?>_model" type="text" dir="ltr" value="<?php echo esc_attr( (string) SSC_Settings::get( $pid . '_model', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Model ID', 'nexachat-ai' ); ?>" />
										<?php endif; ?>
									</div>
								</div>
								<?php if ( 'custom' === $pid ) : ?>
									<div class="ssc-field">
										<label for="custom_endpoint"><?php esc_html_e( 'Endpoint URL (OpenAI-compatible)', 'nexachat-ai' ); ?></label>
										<input id="custom_endpoint" name="custom_endpoint" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['custom_endpoint'] ); ?>" placeholder="https://your-gateway.example/v1/chat/completions" />
										<p class="ssc-field__hint"><?php esc_html_e( 'Works with OpenRouter, Groq, DeepSeek, Ollama relays, Azure-style proxies and more.', 'nexachat-ai' ); ?></p>
									</div>
								<?php endif; ?>
							<?php else : ?>
								<div class="ssc-field">
									<label for="ai_webhook_url"><?php esc_html_e( 'Webhook URL', 'nexachat-ai' ); ?></label>
									<input id="ai_webhook_url" name="ai_webhook_url" type="url" dir="ltr" value="<?php echo esc_attr( (string) $s['ai_webhook_url'] ); ?>" placeholder="https://your-service.example/chat" />
								</div>
								<div class="ssc-field">
									<label for="ai_webhook_secret"><?php esc_html_e( 'Shared secret (HMAC, recommended)', 'nexachat-ai' ); ?></label>
									<input id="ai_webhook_secret" name="ai_webhook_secret" type="password" autocomplete="off" dir="ltr" value="" placeholder="<?php echo SSC_Settings::has_secret( 'ai_webhook_secret' ) ? esc_attr__( 'A secret is stored — type to replace', 'nexachat-ai' ) : ''; ?>" />
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<div class="ssc-conn-test">
						<button type="button" class="ssc-btn ssc-btn--secondary" id="ssc-test-connection"><?php esc_html_e( 'Test connection', 'nexachat-ai' ); ?></button>
						<span id="ssc-test-result" class="ssc-conn-test__result" role="status" aria-live="polite"></span>
					</div>

					<div class="ssc-form__actions">
						<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-wizard', 'step' => 'knowledge' ), admin_url( 'admin.php' ) ) ); ?>"><span class="ssc-dir-arrow" aria-hidden="true">←</span> <?php esc_html_e( 'Back', 'nexachat-ai' ); ?></a>
						<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Continue', 'nexachat-ai' ); ?> <span class="ssc-dir-arrow" aria-hidden="true">→</span></button>
					</div>
				</form>

			<?php elseif ( 'appearance' === $current ) : ?>

				<form method="post" class="ssc-form">
					<?php wp_nonce_field( 'ssc_wizard_appearance' ); ?>
					<input type="hidden" name="ssc_wizard_step" value="appearance" />

					<h2><?php esc_html_e( 'Make it yours', 'nexachat-ai' ); ?></h2>
					<p class="ssc-form__lede"><?php esc_html_e( 'Brand color, texts and placement — with a live preview. Everything can be fine-tuned later.', 'nexachat-ai' ); ?></p>

					<div class="ssc-appear">
						<div class="ssc-appear__controls">
							<div class="ssc-grid ssc-grid--2">
								<div class="ssc-field">
									<label for="primary_color"><?php esc_html_e( 'Primary brand color', 'nexachat-ai' ); ?></label>
									<input id="primary_color" name="primary_color" type="color" value="<?php echo esc_attr( $s['primary_color'] ? $s['primary_color'] : '#b61615' ); ?>" class="ssc-color" />
								</div>
								<div class="ssc-field">
									<label for="theme_mode"><?php esc_html_e( 'Theme', 'nexachat-ai' ); ?></label>
									<select id="theme_mode" name="theme_mode">
										<option value="light" <?php selected( $s['theme_mode'], 'light' ); ?>><?php esc_html_e( 'Light', 'nexachat-ai' ); ?></option>
										<option value="dark" <?php selected( $s['theme_mode'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'nexachat-ai' ); ?></option>
										<option value="auto" <?php selected( $s['theme_mode'], 'auto' ); ?>><?php esc_html_e( 'Match visitor device', 'nexachat-ai' ); ?></option>
									</select>
								</div>
								<div class="ssc-field">
									<label for="position"><?php esc_html_e( 'Floating button position', 'nexachat-ai' ); ?></label>
									<select id="position" name="position">
										<option value="right" <?php selected( $s['position'], 'right' ); ?>><?php esc_html_e( 'Bottom right', 'nexachat-ai' ); ?></option>
										<option value="left" <?php selected( $s['position'], 'left' ); ?>><?php esc_html_e( 'Bottom left', 'nexachat-ai' ); ?></option>
									</select>
								</div>
								<div class="ssc-field">
									<label for="direction"><?php esc_html_e( 'Text direction', 'nexachat-ai' ); ?></label>
									<select id="direction" name="direction">
										<option value="rtl" <?php selected( $s['direction'], 'rtl' ); ?>><?php esc_html_e( 'RTL (Persian/Arabic)', 'nexachat-ai' ); ?></option>
										<option value="ltr" <?php selected( $s['direction'], 'ltr' ); ?>><?php esc_html_e( 'LTR (Latin)', 'nexachat-ai' ); ?></option>
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
								<input id="assistant_display_name" name="assistant_display_name" type="text" value="<?php echo esc_attr( $s['assistant_display_name'] ); ?>" placeholder="<?php echo esc_attr( $business['assistant_name'] ? $business['assistant_name'] : __( 'Nexa', 'nexachat-ai' ) ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="welcome_title"><?php esc_html_e( 'Welcome title', 'nexachat-ai' ); ?></label>
								<input id="welcome_title" name="welcome_title" type="text" value="<?php echo esc_attr( $s['welcome_title'] ); ?>" placeholder="<?php esc_attr_e( 'Hello! 👋', 'nexachat-ai' ); ?>" />
							</div>
							<div class="ssc-field">
								<label for="welcome_text"><?php esc_html_e( 'Welcome message', 'nexachat-ai' ); ?></label>
								<textarea id="welcome_text" name="welcome_text" rows="2"><?php echo esc_textarea( $s['welcome_text'] ); ?></textarea>
							</div>
						</div>
						<div class="ssc-appear__preview">
							<div class="ssc-preview" id="ssc-preview" data-primary="<?php echo esc_attr( $s['primary_color'] ); ?>">
								<div class="ssc-preview__win">
									<div class="ssc-preview__head">
										<span class="ssc-preview__avatar"></span>
										<div>
											<strong id="ssc-preview-name"><?php echo esc_html( $s['assistant_display_name'] ? $s['assistant_display_name'] : __( 'Nexa', 'nexachat-ai' ) ); ?></strong>
											<em><?php esc_html_e( 'Online', 'nexachat-ai' ); ?></em>
										</div>
									</div>
									<div class="ssc-preview__body">
										<p class="ssc-preview__bot"><strong id="ssc-preview-wtitle"><?php echo esc_html( $s['welcome_title'] ? $s['welcome_title'] : __( 'Hello! 👋', 'nexachat-ai' ) ); ?></strong><span id="ssc-preview-wtext"><?php echo esc_html( $s['welcome_text'] ? wp_strip_all_tags( $s['welcome_text'] ) : __( 'How can I help you today?', 'nexachat-ai' ) ); ?></span></p>
										<p class="ssc-preview__user"><?php esc_html_e( 'Hi! Do you ship internationally?', 'nexachat-ai' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="ssc-form__actions">
						<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-wizard', 'step' => 'connection' ), admin_url( 'admin.php' ) ) ); ?>"><span class="ssc-dir-arrow" aria-hidden="true">←</span> <?php esc_html_e( 'Back', 'nexachat-ai' ); ?></a>
						<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Continue', 'nexachat-ai' ); ?> <span class="ssc-dir-arrow" aria-hidden="true">→</span></button>
					</div>
				</form>

			<?php else : ?>

				<?php $readiness = SSC_Setup::readiness(); $all_ready = true; ?>
				<h2><?php esc_html_e( 'Final check & launch', 'nexachat-ai' ); ?></h2>
				<p class="ssc-form__lede"><?php esc_html_e( 'Review the checklist, try the assistant yourself, then publish it when you are satisfied.', 'nexachat-ai' ); ?></p>

				<?php if ( 'not_ready' === $err ) : ?>
					<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'Some readiness items are incomplete — finish them first (each links to its settings).', 'nexachat-ai' ); ?></div>
				<?php endif; ?>
				<?php if ( $published ) : ?>
					<div class="ssc-notice ssc-notice--success" role="status"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Published! Your assistant is now live on your site.', 'nexachat-ai' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-dashboard' ) ); ?>"><?php esc_html_e( 'Go to dashboard', 'nexachat-ai' ); ?></a></div>
				<?php endif; ?>

				<ul class="ssc-checklist ssc-checklist--launch" id="ssc-launch-checklist">
					<?php
					// Items fixed on another screen link there; the two items completed
					// on THIS screen get in-page actions (they used to be anchor links
					// to sections already in view, so clicking them seemed to do nothing).
					$links = array(
						'identity'   => admin_url( 'admin.php?page=ssc-wizard&step=identity' ),
						'knowledge'  => admin_url( 'admin.php?page=ssc-wizard&step=knowledge' ),
						'connection' => admin_url( 'admin.php?page=ssc-wizard&step=connection' ),
						'appearance' => admin_url( 'admin.php?page=ssc-wizard&step=appearance' ),
					);
					foreach ( $readiness as $item ) :
						if ( ! $item['done'] ) {
							$all_ready = false;
						}
						?>
						<li class="<?php echo $item['done'] ? 'is-done' : ''; ?>" data-item="<?php echo esc_attr( $item['id'] ); ?>" data-done="<?php echo $item['done'] ? '1' : '0'; ?>">
							<span class="ssc-checklist__mark" aria-hidden="true"><?php echo $item['done'] ? '✓' : '○'; ?></span>
							<span class="ssc-checklist__label"><?php echo esc_html( $item['label'] ); ?></span>
							<?php if ( 'identity_test' === $item['id'] ) : ?>
								<button type="button" class="ssc-checklist__action" data-action="identity" <?php echo $item['done'] ? 'hidden' : ''; ?>><?php esc_html_e( 'Run the test', 'nexachat-ai' ); ?></button>
							<?php elseif ( 'privacy' === $item['id'] ) : ?>
								<button type="button" class="ssc-checklist__action" data-action="privacy" <?php echo $item['done'] ? 'hidden' : ''; ?>><?php esc_html_e( 'Confirm below', 'nexachat-ai' ); ?></button>
							<?php elseif ( ! $item['done'] && isset( $links[ $item['id'] ] ) ) : ?>
								<a class="ssc-checklist__action" href="<?php echo esc_url( $links[ $item['id'] ] ); ?>"><?php esc_html_e( 'Complete this step', 'nexachat-ai' ); ?> <span class="ssc-dir-arrow" aria-hidden="true">→</span></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>

				<div class="ssc-identity-test" id="ssc-identity-test">
					<h3><?php esc_html_e( 'Business identity test', 'nexachat-ai' ); ?></h3>
					<p><?php esc_html_e( 'Asks the assistant which organization it represents. It must answer correctly before publishing.', 'nexachat-ai' ); ?></p>
					<button type="button" class="ssc-btn ssc-btn--secondary" id="ssc-run-identity-test"><?php esc_html_e( 'Run identity test', 'nexachat-ai' ); ?></button>
					<div id="ssc-identity-result" class="ssc-identity-result" role="status" aria-live="polite"></div>
				</div>

				<div class="ssc-preview-chat">
					<h3><?php esc_html_e( 'Try the assistant (preview)', 'nexachat-ai' ); ?></h3>
					<p><?php esc_html_e( 'A private preview — visitors cannot see it. Ask anything your customers would ask.', 'nexachat-ai' ); ?></p>
					<div id="ssc-preview-mount" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>"></div>
				</div>

				<form method="post" class="ssc-form ssc-launch">
					<?php wp_nonce_field( 'ssc_wizard_review' ); ?>
					<input type="hidden" name="ssc_wizard_step" value="review" />

					<label class="ssc-check" id="ssc-privacy-ack">
						<input type="checkbox" name="privacy_ack" value="1" <?php checked( 'yes', SSC_Settings::get( 'privacy_acknowledged', 'no' ) ); ?> required />
						<span><?php esc_html_e( 'I understand chat messages are sent to the AI provider I configured (an external service) and form data is stored on my site. I will inform my visitors.', 'nexachat-ai' ); ?></span>
					</label>

					<div class="ssc-form__actions">
						<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-wizard', 'step' => 'appearance' ), admin_url( 'admin.php' ) ) ); ?>"><span class="ssc-dir-arrow" aria-hidden="true">←</span> <?php esc_html_e( 'Back', 'nexachat-ai' ); ?></a>
						<button type="submit" name="publish" value="1" class="ssc-btn ssc-btn--primary ssc-btn--launch" id="ssc-publish" <?php disabled( ! $all_ready ); ?>>
							<?php esc_html_e( 'Publish chatbot', 'nexachat-ai' ); ?>
						</button>
					</div>
					<p class="ssc-launch__hint" id="ssc-launch-hint" role="status" aria-live="polite" data-prefix="<?php esc_attr_e( 'To publish, complete:', 'nexachat-ai' ); ?>" <?php echo $all_ready ? 'hidden' : ''; ?>></p>
				</form>

			<?php
			// Enqueue the real widget for the live preview (admin-only preview endpoint).
			if ( 'review' === $current ) {
				SSC_Plugin::instance()->frontend->enqueue_preview();
			}
			?>
					<?php endif; ?>
			</main>
	</div>
</div>
