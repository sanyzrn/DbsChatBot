<?php
/**
 * ADR cases list (pharma module): summary tiles by triage state, status
 * chips, and the cases with a colour accent per state.
 *
 * @package SmartSupportChatbot
 * @var array $result  Rows.
 * @var array $filters Filters.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$updated = isset( $_GET['updated'] ) ? (int) $_GET['updated'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$counts  = SSC_Schema::counts();
$ssc_by  = isset( $counts['pharma_adr'] ) ? $counts['pharma_adr'] : array();
$ssc_all = array_sum( array_map( 'intval', $ssc_by ) );
$ssc_hue = array(
	'new'         => 'red',
	'in_progress' => 'amber',
	'follow_up'   => 'violet',
	'done'        => 'green',
	'archived'    => 'slate',
);
$ssc_n   = function ( $status ) use ( $ssc_by ) {
	return isset( $ssc_by[ $status ] ) ? (int) $ssc_by[ $status ] : 0;
};
$ssc_filtered = '' !== $filters['status'] || '' !== $filters['search'];
$ssc_link     = function ( $status ) use ( $filters ) {
	return add_query_arg(
		array(
			'page'   => 'ssc-pharma',
			'status' => $status,
			's'      => $filters['search'],
		),
		admin_url( 'admin.php' )
	);
};
?>
<div class="ssc-page ssc-adr">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'ADR Cases', 'nexachat-ai' ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( sprintf( _n( '%d case', '%d cases', $result['total'], 'nexachat-ai' ), $result['total'] ) ); ?></p>
		</div>
		<form method="post">
			<?php wp_nonce_field( 'ssc_pharma_export' ); ?>
			<button type="submit" name="ssc_pharma_export" value="1" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Export CSV', 'nexachat-ai' ); ?></button>
		</form>
	</header>

	<?php if ( $updated ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Case updated.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>

	<section class="ssc-hstats">
		<?php
		SSC_Admin_UI::stat( 'heart', 'rose', number_format_i18n( $ssc_all ), __( 'All cases', 'nexachat-ai' ), $ssc_link( '' ) );
		SSC_Admin_UI::stat( 'alert', 'red', number_format_i18n( $ssc_n( 'new' ) ), __( 'Awaiting triage', 'nexachat-ai' ), $ssc_link( 'new' ) );
		SSC_Admin_UI::stat( 'clock', 'amber', number_format_i18n( $ssc_n( 'in_progress' ) + $ssc_n( 'follow_up' ) ), __( 'Being assessed', 'nexachat-ai' ) );
		SSC_Admin_UI::stat( 'check', 'green', number_format_i18n( $ssc_n( 'done' ) ), __( 'Closed', 'nexachat-ai' ), $ssc_link( 'done' ) );
		?>
	</section>

	<?php
	$ssc_chips = array(
		array(
			'label'  => __( 'All statuses', 'nexachat-ai' ),
			'url'    => $ssc_link( '' ),
			'count'  => $ssc_all,
			'active' => '' === $filters['status'],
			'hue'    => 'indigo',
		),
	);
	foreach ( SSC_Module_Pharma::case_statuses() as $st => $st_label ) {
		$ssc_chips[] = array(
			'label'  => $st_label,
			'url'    => $ssc_link( $st ),
			'count'  => $ssc_n( $st ),
			'active' => $st === $filters['status'],
			'hue'    => isset( $ssc_hue[ $st ] ) ? $ssc_hue[ $st ] : 'slate',
		);
	}
	SSC_Admin_UI::chips( $ssc_chips, __( 'Status', 'nexachat-ai' ) );
	?>

	<form method="get" class="ssc-filterbar">
		<input type="hidden" name="page" value="ssc-pharma" />
		<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>" />
		<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search…', 'nexachat-ai' ); ?>" />
		<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Filter', 'nexachat-ai' ); ?></button>
		<?php if ( $ssc_filtered ) : ?>
			<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( add_query_arg( 'page', 'ssc-pharma', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'nexachat-ai' ); ?></a>
		<?php endif; ?>
	</form>

	<?php if ( empty( $result['items'] ) ) : ?>
		<?php
		if ( $ssc_filtered ) {
			SSC_Admin_UI::empty_state( 'search', 'slate', __( 'Nothing matches these filters', 'nexachat-ai' ), __( 'Try another status or a shorter search.', 'nexachat-ai' ), __( 'Clear filters', 'nexachat-ai' ), add_query_arg( 'page', 'ssc-pharma', admin_url( 'admin.php' ) ) );
		} else {
			SSC_Admin_UI::empty_state( 'heart', 'rose', __( 'No ADR cases yet', 'nexachat-ai' ), __( 'No ADR cases yet. When someone files a report through the chat, it appears here for triage.', 'nexachat-ai' ) );
		}
		?>
	<?php else : ?>
		<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Received', 'nexachat-ai' ); ?></th>
					<th><?php esc_html_e( 'Product / batch', 'nexachat-ai' ); ?></th>
					<th><?php esc_html_e( 'Reporter', 'nexachat-ai' ); ?></th>
					<th><?php esc_html_e( 'Severity', 'nexachat-ai' ); ?></th>
					<th><?php esc_html_e( 'Seriousness', 'nexachat-ai' ); ?></th>
					<th><?php esc_html_e( 'Status', 'nexachat-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['items'] as $row ) : ?>
					<?php $is_serious = SSC_Module_Pharma::is_serious_row( $row ); ?>
					<tr class="ssc-row<?php echo $is_serious ? ' is-serious' : ''; ?>" data-ssc-hue="<?php echo esc_attr( isset( $ssc_hue[ $row['status'] ] ) ? $ssc_hue[ $row['status'] ] : 'slate' ); ?>">
						<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-pharma', 'view' => $row['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( SSC_Date::display( $row['created_at'] ) ); ?></a></td>
						<td><?php echo esc_html( $row['product'] . ( $row['batch_number'] ? ' / ' . $row['batch_number'] : '' ) ); ?></td>
						<td><?php echo esc_html( $row['name'] ); ?></td>
						<td><?php echo esc_html( SSC_Module_Pharma::option_label( 'severity', (string) $row['severity'] ) ); ?></td>
						<td><?php echo $is_serious ? '<span class="ssc-badge ssc-badge--danger">' . esc_html__( 'SERIOUS', 'nexachat-ai' ) . '</span>' : '—'; ?></td>
						<td><span class="ssc-pill" data-ssc-hue="<?php echo esc_attr( isset( $ssc_hue[ $row['status'] ] ) ? $ssc_hue[ $row['status'] ] : 'slate' ); ?>"><?php echo esc_html( SSC_Module_Pharma::case_statuses()[ $row['status'] ] ?? $row['status'] ); ?></span></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php if ( $result['total_pages'] > 1 ) : ?>
			<nav class="ssc-pagination">
				<?php for ( $p = 1; $p <= $result['total_pages']; ++$p ) : ?>
					<?php if ( $p === (int) $filters['page'] ) : ?>
						<span class="ssc-pagination__cur"><?php echo (int) $p; ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-pharma', 'paged' => $p, 'status' => $filters['status'], 's' => $filters['search'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo (int) $p; ?></a>
					<?php endif; ?>
				<?php endfor; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
