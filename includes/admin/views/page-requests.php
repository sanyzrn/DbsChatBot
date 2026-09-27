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
			<h1><?php esc_html_e( 'Requests', 'nexachat-ai' ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( sprintf( _n( '%d request', '%d requests', $result['total'], 'nexachat-ai' ), $result['total'] ) ); ?></p>
		</div>
		<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'ssc_export_submissions', 'type' => $filters['type'], 'status' => $filters['status'] ), admin_url( 'admin-post.php' ) ), 'ssc_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'nexachat-ai' ); ?></a>
	</header>

	<?php if ( $updated ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Updated.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>

	<form method="get" class="ssc-filterbar">
		<input type="hidden" name="page" value="ssc-requests" />
		<select name="type" aria-label="<?php esc_attr_e( 'Type', 'nexachat-ai' ); ?>">
			<option value=""><?php esc_html_e( 'All types', 'nexachat-ai' ); ?></option>
			<option value="consult" <?php selected( $filters['type'], 'consult' ); ?>><?php esc_html_e( 'Consultation', 'nexachat-ai' ); ?></option>
			<?php if ( ! SSC_Modules::is_active( 'pharma' ) ) : ?>
				<option value="pharma_adr" <?php selected( $filters['type'], 'pharma_adr' ); ?>><?php esc_html_e( 'ADR reports', 'nexachat-ai' ); ?></option>
			<?php endif; ?>
		</select>
		<select name="status" aria-label="<?php esc_attr_e( 'Status', 'nexachat-ai' ); ?>">
			<option value=""><?php esc_html_e( 'All statuses', 'nexachat-ai' ); ?></option>
			<?php foreach ( array( 'new' => __( 'New', 'nexachat-ai' ), 'in_progress' => __( 'In progress', 'nexachat-ai' ), 'done' => __( 'Done', 'nexachat-ai' ), 'archived' => __( 'Archived', 'nexachat-ai' ) ) as $st => $st_label ) : ?>
				<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $filters['status'], $st ); ?>><?php echo esc_html( $st_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search name or text…', 'nexachat-ai' ); ?>" />
		<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Filter', 'nexachat-ai' ); ?></button>
	</form>

	<?php if ( empty( $result['items'] ) ) : ?>
		<div class="ssc-empty"><p><?php esc_html_e( 'No requests yet. When visitors submit the consultation form, they appear here.', 'nexachat-ai' ); ?></p></div>
	<?php else : ?>
		<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Received', 'nexachat-ai' ); ?></th>
					<th><?php esc_html_e( 'Name', 'nexachat-ai' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'nexachat-ai' ); ?></th>
					<th><?php esc_html_e( 'Request', 'nexachat-ai' ); ?></th>
					<th><?php esc_html_e( 'Status', 'nexachat-ai' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['items'] as $row ) : ?>
					<?php
					$statuses = array( 'new' => __( 'New', 'nexachat-ai' ), 'in_progress' => __( 'In progress', 'nexachat-ai' ), 'done' => __( 'Done', 'nexachat-ai' ), 'archived' => __( 'Archived', 'nexachat-ai' ) );
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
						<td><?php echo esc_html( SSC_Date::display( $row['created_at'] ) ); ?></td>
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
								<select name="status_to" aria-label="<?php esc_attr_e( 'Change status', 'nexachat-ai' ); ?>">
									<?php foreach ( $statuses as $st => $st_label ) : ?>
										<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $row['status'], $st ); ?>><?php echo esc_html( $st_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</form>
						</td>
						<td class="ssc-td-actions">
							<a class="ssc-link--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_req_action', 'delete', $row_base ), 'ssc_req_' . $row['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'nexachat-ai' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>

		<?php if ( $result['total_pages'] > 1 ) : ?>
			<nav class="ssc-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'nexachat-ai' ); ?>">
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
