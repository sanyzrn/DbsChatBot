<?php
/**
 * About view.
 *
 * @package Vazho
 * @var array $headers   Plugin header fields.
 * @var array $checks    Environment checks.
 * @var int   $passed    Checks that passed.
 * @var string $report   Plain-text support report.
 * @var array $providers Registered AI providers.
 * @var array $modules   Registered modules.
 * @var array $active    Active module ids.
 * @var string $prefix   Table prefix of the plugin's tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ssc_health = count( $checks ) ? (int) round( 100 * $passed / count( $checks ) ) : 100;
$ssc_specs  = array(
	array(
		'icon'  => 'layers',
		'title' => __( 'Three layers in one plugin', 'vazho' ),
		'text'  => __( 'Core engine (chat, identity, knowledge, providers, security), optional modules that stay off until you enable them, and independent industry extensions.', 'vazho' ),
	),
	array(
		'icon'  => 'cpu',
		'title' => __( 'Your own AI account', 'vazho' ),
		'text'  => sprintf(
			/* translators: %s: provider names. */
			__( 'Connects directly to %s. No middleman and no per-message fee.', 'vazho' ),
			implode( ', ', array_map( function ( $provider ) { return $provider->label(); }, array_values( $providers ) ) )
		),
	),
	array(
		'icon'  => 'database',
		'title' => __( 'Storage', 'vazho' ),
		'text'  => sprintf(
			/* translators: 1: number of tables, 2: table prefix. */
			__( 'Settings in the options table; %1$d custom tables with the prefix %2$s for knowledge, answers, conversations and requests.', 'vazho' ),
			count( SSC_Admin_About::tables() ),
			$prefix
		),
	),
	array(
		'icon'  => 'code',
		'title' => __( 'REST API', 'vazho' ),
		'text'  => sprintf(
			/* translators: %s: REST base URL. */
			__( 'Public endpoints under %s, with an admin-ajax fallback for heavily cached pages.', 'vazho' ),
			rest_url( SSC_REST::NS . '/' )
		),
	),
	array(
		'icon'  => 'shield',
		'title' => __( 'Security', 'vazho' ),
		'text'  => __( 'API keys encrypted at rest (AES-256-CBC with HMAC), SSRF-guarded requests, rate limits, honeypot, payload caps, nonces and capability checks on every admin action.', 'vazho' ),
	),
	array(
		'icon'  => 'globe',
		'title' => __( 'Persian first, English ready', 'vazho' ),
		'text'  => __( 'Complete Persian translation, RTL and LTR layouts, Solar Hijri dates and bundled fonts (Vazirmatn, Inter, Roboto) with no external requests.', 'vazho' ),
	),
	array(
		'icon'  => 'puzzle',
		'title' => __( 'Where it appears', 'vazho' ),
		'text'  => __( 'Floating widget, the [ssc_chatbot] shortcode, a Gutenberg block and an Elementor widget, with page, device and login rules.', 'vazho' ),
	),
	array(
		'icon'  => 'zap',
		'title' => __( 'Light on your site', 'vazho' ),
		'text'  => __( 'Nothing loads on pages where the assistant is hidden; the script is deferred and the widget uses plain CSS and vanilla JavaScript.', 'vazho' ),
	),
);
?>
<div class="ssc-page ssc-about">
	<section class="ssc-card ssc-ahero">
		<div class="ssc-ahero__mark" aria-hidden="true"><?php echo SSC_Icons::get( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></div>
		<div class="ssc-ahero__main">
			<h1><?php echo esc_html( defined( 'VAZHO_NAME' ) ? VAZHO_NAME : 'Vazho' ); ?> <span class="ssc-ahero__ver">v<?php echo esc_html( $headers['version'] ); ?></span></h1>
			<p><?php esc_html_e( 'A professional AI assistant for WordPress that actually knows your business. Persian/RTL-first, with full LTR support.', 'vazho' ); ?></p>
			<ul class="ssc-ahero__chips">
				<li><?php echo SSC_Icons::get( 'scale' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php echo esc_html( $headers['license'] ); ?></li>
				<li><?php echo SSC_Icons::get( 'server' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> PHP <?php echo esc_html( $headers['requires_php'] ); ?>+</li>
				<li><?php echo SSC_Icons::get( 'wrench' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> WordPress <?php echo esc_html( $headers['requires_wp'] ); ?>+</li>
				<li><?php echo SSC_Icons::get( 'puzzle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php echo esc_html( sprintf( /* translators: 1: active modules, 2: all modules. */ __( '%1$d of %2$d modules on', 'vazho' ), count( $active ), count( $modules ) ) ); ?></li>
			</ul>
		</div>
		<div class="ssc-ahero__health">
			<?php echo SSC_Icons::ring( $ssc_health, 'ssc-ring--lg' . ( $ssc_health < 100 ? ' is-warn' : '' ), sprintf( /* translators: 1: checks passed, 2: all checks. */ __( 'System health: %1$d of %2$d checks passed', 'vazho' ), $passed, count( $checks ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
			<span class="ssc-ahero__healthlabel"><?php esc_html_e( 'System health', 'vazho' ); ?></span>
		</div>
	</section>

	<div class="ssc-acols">
		<section class="ssc-card ssc-amaker">
			<h2><?php echo SSC_Icons::get( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Made by', 'vazho' ); ?></h2>
			<p class="ssc-amaker__name"><?php echo esc_html( SSC_Admin_About::MAKER ); ?></p>
			<p class="ssc-card__sub"><?php esc_html_e( 'Vazho is designed and developed by DbsStudio. For the latest news, other products, support and feedback, visit the website.', 'vazho' ); ?></p>
			<div class="ssc-form__actions ssc-form__actions--start">
				<a class="ssc-btn ssc-btn--primary" href="<?php echo esc_url( SSC_Admin_About::MAKER_URL ); ?>" target="_blank" rel="noopener"><?php echo SSC_Icons::get( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> DbsStudio.ir</a>
			</div>
		</section>
		<section class="ssc-card ssc-alinks">
			<h2><?php echo SSC_Icons::get( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Links', 'vazho' ); ?></h2>
			<ul class="ssc-alinks__list">
				<li><a href="<?php echo esc_url( SSC_Admin_About::MAKER_URL ); ?>" target="_blank" rel="noopener"><?php echo SSC_Icons::get( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <span><?php esc_html_e( 'Website', 'vazho' ); ?><small>DbsStudio.ir</small></span></a></li>
				<li><a href="#ssc-about-changelog"><?php echo SSC_Icons::get( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <span><?php esc_html_e( 'Releases & changelog', 'vazho' ); ?><small>v<?php echo esc_html( $headers['version'] ); ?></small></span></a></li>
				<li><a href="<?php echo esc_url( SSC_Admin_About::support_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php echo SSC_Icons::get( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <span><?php esc_html_e( 'Report a problem', 'vazho' ); ?><small>DbsStudio.ir</small></span></a></li>
			</ul>
		</section>
	</div>

	<section class="ssc-card">
		<h2><?php esc_html_e( 'Technical details', 'vazho' ); ?></h2>
		<p class="ssc-card__sub"><?php esc_html_e( 'How the plugin is built, for developers and administrators.', 'vazho' ); ?></p>
		<dl class="ssc-aspecs">
			<div><dt><?php esc_html_e( 'Version', 'vazho' ); ?></dt><dd><?php echo esc_html( $headers['version'] ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Plugin folder', 'vazho' ); ?></dt><dd><code>vazho</code></dd></div>
			<div><dt><?php esc_html_e( 'Text domain', 'vazho' ); ?></dt><dd><code>vazho</code></dd></div>
			<div><dt><?php esc_html_e( 'License', 'vazho' ); ?></dt><dd><?php echo esc_html( $headers['license'] ); ?></dd></div>
			<?php if ( 'open' !== SSC_License::state() ) : ?>
				<div><dt><?php esc_html_e( 'Marketplace licence', 'vazho' ); ?></dt><dd><?php echo esc_html( SSC_License::is_limited() ? __( 'Not active', 'vazho' ) : __( 'Active', 'vazho' ) ); ?></dd></div>
			<?php endif; ?>
			<div><dt><?php esc_html_e( 'Requires', 'vazho' ); ?></dt><dd>WordPress <?php echo esc_html( $headers['requires_wp'] ); ?>+ · PHP <?php echo esc_html( $headers['requires_php'] ); ?>+</dd></div>
			<div><dt><?php esc_html_e( 'Database schema', 'vazho' ); ?></dt><dd><?php echo esc_html( (string) get_option( SSC_Schema::DB_VERSION_OPTION, '?' ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'REST namespace', 'vazho' ); ?></dt><dd><code><?php echo esc_html( SSC_REST::NS ); ?></code></dd></div>
			<div><dt><?php esc_html_e( 'Settings option', 'vazho' ); ?></dt><dd><code><?php echo esc_html( SSC_Settings::OPTION_KEY ); ?></code></dd></div>
		</dl>
		<div class="ssc-aspecgrid">
			<?php foreach ( $ssc_specs as $ssc_spec ) : ?>
				<article class="ssc-aspec">
					<span class="ssc-aspec__ico"><?php echo SSC_Icons::get( $ssc_spec['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<div>
						<h3><?php echo esc_html( $ssc_spec['title'] ); ?></h3>
						<p><?php echo esc_html( $ssc_spec['text'] ); ?></p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="ssc-card">
		<h2><?php echo SSC_Icons::get( 'puzzle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Modules', 'vazho' ); ?></h2>
		<ul class="ssc-amods">
			<?php foreach ( $modules as $ssc_id => $ssc_module ) : ?>
				<li class="<?php echo in_array( $ssc_id, $active, true ) ? 'is-on' : ''; ?>"><?php echo esc_html( $ssc_module->title() ); ?></li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="ssc-card">
		<h2><?php echo SSC_Icons::get( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'System check', 'vazho' ); ?></h2>
		<ul class="ssc-achecks">
			<?php foreach ( $checks as $ssc_row ) : ?>
				<li class="is-<?php echo esc_attr( $ssc_row['status'] ); ?>">
					<span class="ssc-achecks__mark"><?php echo SSC_Icons::get( 'ok' === $ssc_row['status'] ? 'check' : 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<strong><?php echo esc_html( $ssc_row['label'] ); ?></strong>
					<span><?php echo esc_html( $ssc_row['detail'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="ssc-card ssc-areport">
		<h2><?php echo SSC_Icons::get( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Support information', 'vazho' ); ?></h2>
		<p class="ssc-card__sub"><?php esc_html_e( 'Paste this when you ask for help. It holds versions and settings only: no API keys, no conversations, no personal data.', 'vazho' ); ?></p>
		<textarea id="ssc-support-report" class="ssc-areport__text" rows="10" readonly dir="ltr"><?php echo esc_textarea( $report ); ?></textarea>
		<div class="ssc-form__actions ssc-form__actions--start">
			<button type="button" class="ssc-btn ssc-btn--secondary" data-ssc-copy="#ssc-support-report" data-copied="<?php esc_attr_e( 'Copied ✓', 'vazho' ); ?>"><?php esc_html_e( 'Copy to clipboard', 'vazho' ); ?></button>
		</div>
	</section>

	<section id="ssc-about-changelog" class="ssc-card ssc-achange">
		<div class="ssc-achange__head">
			<h2><?php esc_html_e( 'Releases & changelog', 'vazho' ); ?></h2>
			<p class="ssc-muted">
				<?php
				printf(
					/* translators: %d: number of versions. */
					esc_html( _n( '%d version, newest first.', '%d versions, newest first.', count( $changelog ), 'vazho' ) ),
					count( $changelog )
				);
				?>
			</p>
		</div>
		<?php
		$ssc_render_release = function ( $ssc_entry, $ssc_open ) {
			$ssc_current = ( SSC_CHATBOT_VERSION === $ssc_entry['version'] );
			?>
			<li class="ssc-achange__item<?php echo $ssc_current ? ' is-current' : ''; ?>">
				<details<?php echo $ssc_open ? ' open' : ''; ?>>
					<summary>
						<strong class="ssc-achange__ver" dir="ltr"><?php echo esc_html( $ssc_entry['version'] ); ?></strong>
						<?php if ( $ssc_current ) : ?>
							<span class="ssc-achange__tag"><?php esc_html_e( 'Installed', 'vazho' ); ?></span>
						<?php endif; ?>
						<span class="ssc-achange__count">
							<?php
							printf(
								/* translators: %d: number of notes. */
								esc_html( _n( '%d change', '%d changes', count( $ssc_entry['notes'] ), 'vazho' ) ),
								count( $ssc_entry['notes'] )
							);
							?>
						</span>
					</summary>
					<ul class="ssc-achange__notes">
						<?php foreach ( $ssc_entry['notes'] as $ssc_note ) : ?>
							<li dir="auto">
								<?php if ( '' !== $ssc_note['type'] ) : ?>
									<span class="ssc-achange__kind ssc-achange__kind--<?php echo esc_attr( $ssc_note['type'] ); ?>"><?php echo esc_html( SSC_Admin_About::note_label( $ssc_note['type'] ) ); ?></span>
								<?php endif; ?>
								<span class="ssc-achange__text"><?php echo SSC_Admin_About::note_html( $ssc_note['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by esc_html() in note_html(). ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			</li>
			<?php
		};
		$ssc_recent = array_slice( $changelog, 0, 5 );
		$ssc_older  = array_slice( $changelog, 5 );
		?>
		<ol class="ssc-achange__list">
			<?php foreach ( $ssc_recent as $ssc_i => $ssc_entry ) : ?>
				<?php $ssc_render_release( $ssc_entry, 0 === $ssc_i ); ?>
			<?php endforeach; ?>
		</ol>
		<?php if ( $ssc_older ) : ?>
			<details class="ssc-achange__older">
				<summary>
					<?php
					printf(
						/* translators: %d: number of older versions. */
						esc_html( _n( 'Show %d older version', 'Show %d older versions', count( $ssc_older ), 'vazho' ) ),
						count( $ssc_older )
					);
					?>
				</summary>
				<ol class="ssc-achange__list">
					<?php foreach ( $ssc_older as $ssc_entry ) : ?>
						<?php $ssc_render_release( $ssc_entry, false ); ?>
					<?php endforeach; ?>
				</ol>
			</details>
		<?php endif; ?>
	</section>

	<section class="ssc-card ssc-acredits">
		<h2><?php esc_html_e( 'Credits', 'vazho' ); ?></h2>
		<ul>
			<li><strong>Vazirmatn</strong> — <?php esc_html_e( 'Saber Rastikerdar, SIL Open Font License 1.1', 'vazho' ); ?></li>
			<li><strong>Inter</strong> — <?php esc_html_e( 'The Inter Project Authors, SIL Open Font License 1.1', 'vazho' ); ?></li>
			<li><strong>Roboto</strong> — <?php esc_html_e( 'Christian Robertson, Apache License 2.0', 'vazho' ); ?></li>
		</ul>
		<p class="ssc-acredits__foot">© <?php echo esc_html( gmdate( 'Y' ) ); ?> <a href="<?php echo esc_url( SSC_Admin_About::MAKER_URL ); ?>" target="_blank" rel="noopener"><?php echo esc_html( SSC_Admin_About::MAKER ); ?></a> · <a href="<?php echo esc_url( SSC_Admin_About::MAKER_URL ); ?>" target="_blank" rel="noopener">DbsStudio.ir</a></p>
	</section>
</div>
