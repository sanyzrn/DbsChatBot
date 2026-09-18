<?php
/**
 * Requests inbox view (leads module).
 *
 * @package SmartSupportChatbot
 * @var array $result   Paginated rows.
 * @var array $filters  Active filters.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$updated = isset( $_GET['updated'] ) ? (int) $_GET['updated'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$base    = admin_url( 'admin.php' );
?>
<div class="ssc-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Requests', 'smart-support-chatbot' ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( sprintf( _n( '%d request', '%d requests', $result['total'], 'smart-support-chatbot' ), $result['total'] ) ); ?></p>
		</div>
		<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'ssc-requests', 'type' => $filters['type'], 'status' => $filters['status'] ), $base ), 'ssc_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'smart-support-chatbot' ); ?></a>
	</header>

	<?php if ( $updated ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Updated.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>

	<form method="get" class="ssc-filterbar">
		<input type="hidden" name="page" value="ssc-requests" />
		<select name="type" aria-label="<?php esc_attr_e( 'Type', 'smart-support-chatbot' ); ?>">
			<option value=""><?php esc_html_e( 'All types', 'smart-support-chatbot' ); ?></option>
			<option value="consult" <?php selected( $filters['type'], 'consult' ); ?>><?php esc_html_e( 'Consultation', 'smart-support-chatbot' ); ?></option>
			<?php if ( ! SSC_Modules::is_active( 'pharma' ) ) : ?>
				<option value="pharma_adr" <?php selected( $filters['type'], 'pharma_adr' ); ?>><?php esc_html_e( 'ADR reports', 'smart-support-chatbot' ); ?></option>
			<?php endif; ?>
		</select>
		<select name="status" aria-label="<?php esc_attr_e( 'Status', 'smart-support-chatbot' ); ?>">
			<option value=""><?php esc_html_e( 'All statuses', 'smart-support-chatbot' ); ?></option>
			<?php foreach ( array( 'new' => __( 'New', 'smart-support-chatbot' ), 'in_progress' => __( 'In progress', 'smart-support-chatbot' ), 'done' => __( 'Done', 'smart-support-chatbot' ), 'archived' => __( 'Archived', 'smart-support-chatbot' ) ) as $st => $st_label ) : ?>
				<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $filters['status'], $st ); ?>><?php echo esc_html( $st_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search name or text…', 'smart-support-chatbot' ); ?>" />
		<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Filter', 'smart-support-chatbot' ); ?></button>
	</form>

	<?php if ( empty( $result['items'] ) ) : ?>
		<div class="ssc-empty"><p><?php esc_html_e( 'No requests yet. When visitors submit the consultation form, they appear here.', 'smart-support-chatbot' ); ?></p></div>
	<?php else : ?>
		<table class="ssc-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Received', 'smart-support-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Name', 'smart-support-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'smart-support-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Request', 'smart-support-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Status', 'smart-support-chatbot' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['items'] as $row ) : ?>
					<?php
					$statuses = array( 'new' => __( 'New', 'smart-support-chatbot' ), 'in_progress' => __( 'In progress', 'smart-support-chatbot' ), 'done' => __( 'Done', 'smart-support-chatbot' ), 'archived' => __( 'Archived', 'smart-support-chatbot' ) );
					$row_base = add_query_arg(
						array(
							'page'  => 'ssc-requests',
							'type'  => $filters['type'],
							'status' => $filters['status'],
							's'     => $filters['search'],
							'paged' => $filters['page'],
							'id'    => $row['id'],
						),
						$base
					);
					?>
					<tr>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ) ); ?></td>
						<td><?php echo esc_html( $row['name'] ); ?></td>
						<td dir="ltr"><?php echo esc_html( $row['phone'] ); ?></td>
						<td class="ssc-td-truncate"><?php echo esc_html( mb_substr( (string) $row['description'], 0, 90 ) ); ?></td>
						<td>
							<form method="get" class="ssc-statusform">
								<input type="hidden" name="page" value="ssc-requests" />
								<input type="hidden" name="type" value="<?php echo esc_attr( $filters['type'] ); ?>" />
								<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>" />
								<input type="hidden" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" />
								<input type="hidden" name="paged" value="<?php echo esc_attr( (string) $filters['page'] ); ?>" />
								<input type="hidden" name="id" value="<?php echo esc_attr( (string) $row['id'] ); ?>" />
								<?php wp_nonce_field( 'ssc_req_' . $row['id'] ); ?>
								<input type="hidden" name="ssc_req_action" value="status" />
								<select name="status_to" aria-label="<?php esc_attr_e( 'Change status', 'smart-support-chatbot' ); ?>">
									<?php foreach ( $statuses as $st => $st_label ) : ?>
										<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $row['status'], $st ); ?>><?php echo esc_html( $st_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</form>
						</td>
						<td class="ssc-td-actions">
							<a class="ssc-link--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_req_action', 'delete', $row_base ), 'ssc_req_' . $row['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'smart-support-chatbot' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $result['total_pages'] > 1 ) : ?>
			<nav class="ssc-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'smart-support-chatbot' ); ?>">
				<?php
				$paged = (int) $filters['page'];
				for ( $p = 1; $p <= $result['total_pages']; ++$p ) {
					if ( $p === $paged ) {
						printf( '<span class="ssc-pagination__cur">%d</span>', (int) $p );
					} else {
						printf( '<a href="%s">%d</a>', esc_url( add_query_arg( array( 'page' => 'ssc-requests', 'paged' => $p, 'type' => $filters['type'], 'status' => $filters['status'], 's' => $filters['search'] ), $base ) ), (int) $p );
					}
				}
				?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
