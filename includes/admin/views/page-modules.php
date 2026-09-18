<?php
/**
 * Module marketplace view. Premium, lightweight module discovery.
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
?>
<div class="ssc-page ssc-modules-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Modules', 'smart-support-chatbot' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Enable exactly what your business needs. Everything is free, included, and safely reversible — data is preserved when you switch a module off.', 'smart-support-chatbot' ); ?></p>
		</div>
		<form method="get" class="ssc-search" role="search">
			<input type="hidden" name="page" value="ssc-modules" />
			<input type="search" name="ssc_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search modules…', 'smart-support-chatbot' ); ?>" aria-label="<?php esc_attr_e( 'Search modules', 'smart-support-chatbot' ); ?>" />
		</form>
	</header>

	<?php if ( $activated ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Module activated.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>
	<?php if ( $deactivated ) : ?>
		<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'Module deactivated. Its data is kept and will be there when you re-enable it.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>

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
		?>
		<section class="ssc-modcat">
			<h2 class="ssc-modcat__title"><?php echo esc_html( $categories[ $cat_id ] ); ?></h2>
			<div class="ssc-modgrid">
				<?php
				foreach ( $cat_modules as $module ) :
					++$rendered;
					$status     = $statuses[ $module->id() ]['status'];
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
					?>
					<article class="ssc-modcard<?php echo 'active' === $status ? ' is-active' : ''; ?> ssc-status--<?php echo esc_attr( $status ); ?>">
						<div class="ssc-modcard__icon" aria-hidden="true"><?php echo $module->icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG from module definition, currentColor only. ?></div>
						<div class="ssc-modcard__body">
							<h3 class="ssc-modcard__title"><?php echo esc_html( $module->title() ); ?></h3>
							<p class="ssc-modcard__desc"><?php echo esc_html( $module->description() ); ?></p>
							<p class="ssc-modcard__benefit"><?php echo esc_html( $module->benefit() ); ?></p>
							<?php if ( $dep_labels ) : ?>
								<p class="ssc-modcard__deps"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> <?php echo esc_html( sprintf( __( 'Requires: %s', 'smart-support-chatbot' ), implode( ', ', $dep_labels ) ) ); ?></p>
							<?php endif; ?>
						</div>
						<div class="ssc-modcard__side">
							<span class="ssc-badge ssc-badge--<?php echo esc_attr( $status ); ?>">
								<?php
								if ( 'active' === $status ) {
									esc_html_e( 'Active', 'smart-support-chatbot' );
								} elseif ( 'setup' === $status ) {
									esc_html_e( 'Setup Required', 'smart-support-chatbot' );
								} else {
									esc_html_e( 'Inactive', 'smart-support-chatbot' );
								}
								?>
							</span>
							<?php if ( 'active' === $status && $module->needs_config() ) : ?>
								<a class="ssc-modcard__configure" href="<?php echo esc_url( $module->is_industry() ? admin_url( 'admin.php?page=ssc-pharma' ) : admin_url( 'admin.php?page=ssc-settings' ) ); ?>"><?php esc_html_e( 'Configure', 'smart-support-chatbot' ); ?></a>
							<?php endif; ?>
							<a class="ssc-btn <?php echo 'inactive' === $status ? 'ssc-btn--primary' : 'ssc-btn--ghost'; ?>" href="<?php echo esc_url( $toggle_url ); ?>">
								<?php if ( 'inactive' === $status ) : ?>
									<?php esc_html_e( 'Activate', 'smart-support-chatbot' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Deactivate', 'smart-support-chatbot' ); ?>
								<?php endif; ?>
							</a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>

	<?php if ( 0 === $rendered ) : ?>
		<div class="ssc-empty">
			<p><?php esc_html_e( 'No modules match your search.', 'smart-support-chatbot' ); ?></p>
		</div>
	<?php endif; ?>
</div>
