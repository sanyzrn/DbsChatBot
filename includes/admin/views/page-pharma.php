<?php
/**
 * ADR cases list view (pharma module).
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
$serious = 0;
if ( isset( $counts['pharma_adr'] ) ) {
	foreach ( $counts['pharma_adr'] as $status => $n ) {
		$serious += (int) $n;
	}
}
?>
<div class="ssc-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'ADR Cases', 'smart-support-chatbot' ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( sprintf( _n( '%d case', '%d cases', $result['total'], 'smart-support-chatbot' ), $result['total'] ) ); ?></p>
		</div>
		<form method="post">
			<?php wp_nonce_field( 'ssc_pharma_export' ); ?>
			<button type="submit" name="ssc_pharma_export" value="1" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Export CSV', 'smart-support-chatbot' ); ?></button>
		</form>
	</header>

	<?php if ( $updated ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Case updated.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>

	<form method="get" class="ssc-filterbar">
		<input type="hidden" name="page" value="ssc-pharma" />
		<select name="status" aria-label="<?php esc_attr_e( 'Status', 'smart-support-chatbot' ); ?>">
			<option value=""><?php esc_html_e( 'All statuses', 'smart-support-chatbot' ); ?></option>
			<?php foreach ( SSC_Module_Pharma::case_statuses() as $st => $st_label ) : ?>
				<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $filters['status'], $st ); ?>><?php echo esc_html( $st_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search…', 'smart-support-chatbot' ); ?>" />
		<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Filter', 'smart-support-chatbot' ); ?></button>
	</form>

	<?php if ( empty( $result['items'] ) ) : ?>
		<div class="ssc-empty"><p><?php esc_html_e( 'No ADR cases yet. When someone files a report through the chat, it appears here for triage.', 'smart-support-chatbot' ); ?></p></div>
	<?php else : ?>
		<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'smart-support-chatbot' ); ?>"><table class="ssc-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Received', 'smart-support-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Product / batch', 'smart-support-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Reporter', 'smart-support-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Severity', 'smart-support-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Seriousness', 'smart-support-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Status', 'smart-support-chatbot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['items'] as $row ) : ?>
					<?php $is_serious = SSC_Module_Pharma::is_serious_row( $row ); ?>
					<tr class="<?php echo $is_serious ? 'is-serious' : ''; ?>">
						<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-pharma', 'view' => $row['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ) ); ?></a></td>
						<td><?php echo esc_html( $row['product'] . ( $row['batch_number'] ? ' / ' . $row['batch_number'] : '' ) ); ?></td>
						<td><?php echo esc_html( $row['name'] ); ?></td>
						<td><?php echo esc_html( SSC_Module_Pharma::option_label( 'severity', (string) $row['severity'] ) ); ?></td>
						<td><?php echo $is_serious ? '<span class="ssc-badge ssc-badge--danger">' . esc_html__( 'SERIOUS', 'smart-support-chatbot' ) . '</span>' : '—'; ?></td>
						<td><?php echo esc_html( SSC_Module_Pharma::case_statuses()[ $row['status'] ] ?? $row['status'] ); ?></td>
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
