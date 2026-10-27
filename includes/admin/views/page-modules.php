<?php
/**
 * Modules: a summary ring, status filters, and the modules in colour-coded
 * groups, each with a switch and a link to its settings.
 *
 * @package SmartSupportChatbot
 * @var array $modules    Modules grouped by category.
 * @var array $categories Category labels.
 * @var array $statuses   Module statuses.
 * @var string $search    Search term.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$activated   = isset( $_GET['activated'] ) ? sanitize_key( wp_unslash( $_GET['activated'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$deactivated = isset( $_GET['deactivated'] ) ? sanitize_key( wp_unslash( $_GET['deactivated'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$error       = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.

/* Look of each group: colour and icon. */
$ssc_cat_look = array(
	'engagement'    => array( 'blue', 'chat' ),
	'communication' => array( 'teal', 'mail' ),
	'analytics'     => array( 'amber', 'star' ),
	'knowledge'     => array( 'violet', 'book' ),
	'business'      => array( 'green', 'building' ),
	'industry'      => array( 'rose', 'heart' ),
);

/* Where a module with settings is configured (an anchor on the Settings screen). */
$ssc_config_anchor = array(
	'history'       => 'mod-history',
	'leads'         => 'mod-leads',
	'notifications' => 'mod-notifications',
	'messenger'     => 'mod-messenger',
	'sms'           => 'mod-sms',
	'woocommerce'   => 'mod-woocommerce',
);

$ssc_total   = 0;
$ssc_counts  = array(
	'active'   => 0,
	'setup'    => 0,
	'inactive' => 0,
);
foreach ( $modules as $ssc_group ) {
	foreach ( $ssc_group as $ssc_m ) {
		++$ssc_total;
		$ssc_st = isset( $statuses[ $ssc_m->id() ]['status'] ) ? $statuses[ $ssc_m->id() ]['status'] : 'inactive';
		if ( isset( $ssc_counts[ $ssc_st ] ) ) {
			++$ssc_counts[ $ssc_st ];
		}
	}
}
$ssc_on      = $ssc_counts['active'] + $ssc_counts['setup'];
$ssc_percent = $ssc_total > 0 ? (int) round( 100 * $ssc_on / $ssc_total ) : 0;
?>
<div class="ssc-page ssc-modules-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Modules', 'nexachat-ai' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Enable exactly what your business needs. Everything is free, included, and safely reversible — data is preserved when you switch a module off.', 'nexachat-ai' ); ?></p>
		</div>
		<form method="get" class="ssc-search" role="search">
			<input type="hidden" name="page" value="ssc-modules" />
			<input type="search" name="ssc_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search modules…', 'nexachat-ai' ); ?>" aria-label="<?php esc_attr_e( 'Search modules', 'nexachat-ai' ); ?>" />
		</form>
	</header>

	<?php if ( SSC_License::is_limited() ) : ?>
		<div class="ssc-notice ssc-notice--info" role="status"><?php echo esc_html( SSC_License::notice() ); ?></div>
	<?php endif; ?>
	<?php if ( $activated ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Module activated.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>
	<?php if ( $deactivated ) : ?>
		<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'Module deactivated. Its data is kept and will be there when you re-enable it.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>

	<section class="ssc-card ssc-hhero ssc-hhero--compact ssc-mhero">
		<div class="ssc-hhero__ring">
			<?php echo SSC_Icons::ring( $ssc_percent, 'ssc-ring--lg' . ( $ssc_percent >= 100 && 0 === $ssc_counts['setup'] ? ' is-ok' : '' ), __( 'Share of modules that are on', 'nexachat-ai' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
		</div>
		<div class="ssc-hhero__main">
			<h2>
				<?php
				/* translators: 1: modules on, 2: modules in total. */
				echo esc_html( sprintf( __( '%1$d of %2$d modules are on', 'nexachat-ai' ), $ssc_on, $ssc_total ) );
				?>
			</h2>
			<p class="ssc-hhero__sub">
				<?php
				if ( $ssc_counts['setup'] > 0 ) {
					/* translators: %d: number of modules. */
					echo esc_html( sprintf( _n( '%d module is on but still needs a little setup. Use Configure on its card.', '%d modules are on but still need a little setup. Use Configure on their cards.', $ssc_counts['setup'], 'nexachat-ai' ), $ssc_counts['setup'] ) );
				} else {
					esc_html_e( 'Switch on only what you need. Each module adds its own page or setting, and nothing is deleted when you turn one off.', 'nexachat-ai' );
				}
				?>
			</p>
		</div>
		<div class="ssc-mfilter" role="group" aria-label="<?php esc_attr_e( 'Show modules', 'nexachat-ai' ); ?>" hidden data-ssc-modfilter>
			<button type="button" class="ssc-chip is-on" data-ssc-modshow="all"><?php esc_html_e( 'All', 'nexachat-ai' ); ?> <b><?php echo esc_html( number_format_i18n( $ssc_total ) ); ?></b></button>
			<button type="button" class="ssc-chip" data-ssc-modshow="active"><?php esc_html_e( 'Active', 'nexachat-ai' ); ?> <b><?php echo esc_html( number_format_i18n( $ssc_counts['active'] ) ); ?></b></button>
			<?php if ( $ssc_counts['setup'] > 0 ) : ?>
				<button type="button" class="ssc-chip" data-ssc-modshow="setup"><?php esc_html_e( 'Setup Required', 'nexachat-ai' ); ?> <b><?php echo esc_html( number_format_i18n( $ssc_counts['setup'] ) ); ?></b></button>
			<?php endif; ?>
			<button type="button" class="ssc-chip" data-ssc-modshow="inactive"><?php esc_html_e( 'Inactive', 'nexachat-ai' ); ?> <b><?php echo esc_html( number_format_i18n( $ssc_counts['inactive'] ) ); ?></b></button>
		</div>
	</section>

	<?php
	$rendered = 0;
	foreach ( $modules as $cat_id => $cat_modules ) :
		// Search filter.
		if ( '' !== $search ) {
			$filtered = array();
			foreach ( $cat_modules as $module ) {
				$hay = $module->title() . ' ' . $module->description() . ' ' . $module->benefit();
				if ( false !== stripos( $hay, $search ) ) {
					$filtered[] = $module;
				}
			}
			if ( empty( $filtered ) ) {
				continue;
			}
			$cat_modules = $filtered;
		}
		$ssc_look   = isset( $ssc_cat_look[ $cat_id ] ) ? $ssc_cat_look[ $cat_id ] : array( 'slate', 'puzzle' );
		$ssc_cat_on = 0;
		foreach ( $cat_modules as $module ) {
			if ( 'inactive' !== $statuses[ $module->id() ]['status'] ) {
				++$ssc_cat_on;
			}
		}
		?>
		<section class="ssc-modcat" data-ssc-hue="<?php echo esc_attr( $ssc_look[0] ); ?>" data-ssc-modcat>
			<h2 class="ssc-modcat__title">
				<span class="ssc-modcat__ico"><?php echo SSC_Icons::get( $ssc_look[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
				<span class="ssc-modcat__name"><?php echo esc_html( $categories[ $cat_id ] ); ?></span>
				<span class="ssc-modcat__count"><?php echo esc_html( sprintf( /* translators: 1: modules on, 2: modules in the group. */ __( '%1$d of %2$d on', 'nexachat-ai' ), $ssc_cat_on, count( $cat_modules ) ) ); ?></span>
			</h2>
			<div class="ssc-modgrid">
				<?php
				foreach ( $cat_modules as $module ) :
					++$rendered;
					$status     = $statuses[ $module->id() ]['status'];
					$ssc_locked = SSC_License::module_locked( $module->id() );
					$deps       = $module->dependencies();
					$dep_labels = array();
					foreach ( $deps as $dep ) {
						$dep_module  = SSC_Modules::get( $dep );
						$dep_labels[] = $dep_module ? $dep_module->title() : $dep;
					}
					$toggle_url = wp_nonce_url(
						add_query_arg(
							array(
								'page' => 'ssc-modules',
								'ssc_module' => $module->id(),
								'ssc_module_action' => ( 'inactive' === $status ) ? 'activate' : 'deactivate',
							),
							admin_url( 'admin.php' )
						),
						'ssc_module_' . $module->id()
					);
					$ssc_settings_url = $module->is_industry()
						? admin_url( 'admin.php?page=ssc-pharma' )
						: admin_url( 'admin.php?page=ssc-settings' . ( isset( $ssc_config_anchor[ $module->id() ] ) ? '#' . $ssc_config_anchor[ $module->id() ] : '' ) );
					?>
					<article class="ssc-modcard<?php echo 'active' === $status ? ' is-active' : ''; ?> ssc-status--<?php echo esc_attr( $status ); ?>" data-ssc-modstatus="<?php echo esc_attr( $status ); ?>">
						<div class="ssc-modcard__icon" aria-hidden="true"><?php echo $module->icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG from module definition, currentColor only. ?></div>
						<div class="ssc-modcard__body">
							<h3 class="ssc-modcard__title"><?php echo esc_html( $module->title() ); ?>
								<span class="ssc-badge ssc-badge--<?php echo esc_attr( $status ); ?>">
									<?php
									if ( 'active' === $status ) {
										esc_html_e( 'Active', 'nexachat-ai' );
									} elseif ( 'setup' === $status ) {
										esc_html_e( 'Setup Required', 'nexachat-ai' );
									} elseif ( $ssc_locked ) {
										esc_html_e( 'Needs licence', 'nexachat-ai' );
									} else {
										esc_html_e( 'Inactive', 'nexachat-ai' );
									}
									?>
								</span>
							</h3>
							<p class="ssc-modcard__desc"><?php echo esc_html( $module->description() ); ?></p>
							<p class="ssc-modcard__benefit"><?php echo SSC_Icons::get( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?><span><?php echo esc_html( $module->benefit() ); ?></span></p>
							<?php if ( $dep_labels ) : ?>
								<p class="ssc-modcard__deps"><?php echo SSC_Icons::get( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php echo esc_html( sprintf( __( 'Requires: %s', 'nexachat-ai' ), implode( ', ', $dep_labels ) ) ); ?></p>
							<?php endif; ?>
						</div>
						<div class="ssc-modcard__side">
							<?php if ( 'inactive' !== $status && $module->needs_config() ) : ?>
								<a class="ssc-modcard__configure" href="<?php echo esc_url( $ssc_settings_url ); ?>"><?php echo SSC_Icons::get( 'sliders' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Configure', 'nexachat-ai' ); ?></a>
							<?php endif; ?>
							<?php if ( $ssc_locked ) : ?>
								<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( SSC_License::PRODUCT_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get a licence', 'nexachat-ai' ); ?></a>
							<?php else : ?>
							<a class="ssc-btn <?php echo 'inactive' === $status ? 'ssc-btn--primary' : 'ssc-btn--ghost'; ?>" href="<?php echo esc_url( $toggle_url ); ?>">
								<?php if ( 'inactive' === $status ) : ?>
									<?php esc_html_e( 'Activate', 'nexachat-ai' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Deactivate', 'nexachat-ai' ); ?>
								<?php endif; ?>
							</a>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>

	<?php if ( 0 === $rendered ) : ?>
		<div class="ssc-empty">
			<p><?php esc_html_e( 'No modules match your search.', 'nexachat-ai' ); ?></p>
		</div>
	<?php endif; ?>
	<div class="ssc-empty" data-ssc-modempty hidden>
		<p><?php esc_html_e( 'No modules in this view.', 'nexachat-ai' ); ?></p>
	</div>
</div>
