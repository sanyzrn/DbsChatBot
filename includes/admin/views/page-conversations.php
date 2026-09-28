<?php
/**
 * Conversations view (history module).
 *
 * @package SmartSupportChatbot
 * @var array $result  Rows.
 * @var array $filters Filters.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ssc-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Conversations', 'nexachat-ai' ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( sprintf( _n( '%d exchange', '%d exchanges', $result['total'], 'nexachat-ai' ), $result['total'] ) ); ?> · <?php esc_html_e( 'retention is configured in Settings', 'nexachat-ai' ); ?></p>
		</div>
	</header>

	<?php if ( '' !== $filters['conv'] ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'Showing one whole conversation, oldest message first.', 'nexachat-ai' ); ?> <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-conversations' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Back to all conversations', 'nexachat-ai' ); ?></a></p></div>
	<?php endif; ?>

	<form method="get" class="ssc-filterbar">
		<input type="hidden" name="page" value="ssc-conversations" />
		<select name="source" aria-label="<?php esc_attr_e( 'Answer source', 'nexachat-ai' ); ?>">
			<option value=""><?php esc_html_e( 'All sources', 'nexachat-ai' ); ?></option>
			<?php foreach ( array( 'ai' => __( 'AI', 'nexachat-ai' ), 'bank' => __( 'FAQ bank', 'nexachat-ai' ), 'cache' => __( 'Cache', 'nexachat-ai' ), 'unanswered' => __( 'Unanswered', 'nexachat-ai' ) ) as $src => $src_label ) : ?>
				<option value="<?php echo esc_attr( $src ); ?>" <?php selected( $filters['source'], $src ); ?>><?php echo esc_html( $src_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="rating" aria-label="<?php esc_attr_e( 'Rating', 'nexachat-ai' ); ?>">
			<option value=""><?php esc_html_e( 'Any rating', 'nexachat-ai' ); ?></option>
			<option value="1" <?php selected( $filters['rating'], '1' ); ?>>👍</option>
			<option value="-1" <?php selected( $filters['rating'], '-1' ); ?>>👎</option>
		</select>
		<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Filter', 'nexachat-ai' ); ?></button>
	</form>

	<?php if ( empty( $result['items'] ) ) : ?>
		<div class="ssc-empty"><p><?php esc_html_e( 'No conversations stored yet.', 'nexachat-ai' ); ?></p></div>
	<?php else : ?>
		<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table ssc-table--wide">
			<thead>
				<tr><th><?php esc_html_e( 'Time', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'Question', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'Answer', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'Source', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'Rating', 'nexachat-ai' ); ?></th><th></th></tr>
			</thead>
			<tbody>
				<?php
				// One conversation is read in full; the overview stays one line per row.
				$cut        = '' !== $filters['conv'] ? 4000 : 80;
				$cell_class = '' !== $filters['conv'] ? 'ssc-td-full' : 'ssc-td-truncate';
				?>
				<?php foreach ( $result['items'] as $row ) : ?>
					<?php
					$row_base = add_query_arg( array( 'page' => 'ssc-conversations', 'id' => $row['id'], 'source' => $filters['source'], 'rating' => $filters['rating'] ), admin_url( 'admin.php' ) );
					?>
					<tr>
						<td><?php echo esc_html( SSC_Date::display( $row['created_at'] ) ); ?></td>
						<td class="<?php echo esc_attr( $cell_class ); ?>"><?php echo esc_html( mb_substr( (string) $row['question'], 0, $cut ) ); ?></td>
						<td class="<?php echo esc_attr( $cell_class ); ?>"><?php echo esc_html( mb_substr( (string) $row['answer'], 0, $cut ) ); ?></td>
						<td><span class="ssc-badge ssc-badge--src-<?php echo esc_attr( $row['source'] ); ?>"><?php echo esc_html( $row['source'] ); ?></span></td>
						<td><?php echo (int) $row['rating'] > 0 ? '👍' : ( (int) $row['rating'] < 0 ? '👎' : '—' ); // phpcs:ignore WordPress.Security.EscapeOutput.EmojiNotSupported -- admin UI. ?></td>
						<td class="ssc-td-actions">
							<?php if ( ! empty( $row['conv_hash'] ) && '' === $filters['conv'] ) : ?>
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-conversations', 'conv' => $row['conv_hash'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Whole conversation', 'nexachat-ai' ); ?></a>
							<?php endif; ?>
							<?php if ( 'unanswered' === $row['source'] ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_log_action', 'tobank', $row_base ), 'ssc_log_' . $row['id'] ) ); ?>"><?php esc_html_e( 'Add to FAQ', 'nexachat-ai' ); ?></a>
							<?php endif; ?>
							<a class="ssc-link--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_log_action', 'delete', $row_base ), 'ssc_log_' . $row['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'nexachat-ai' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php if ( $result['total_pages'] > 1 ) : ?>
			<nav class="ssc-pagination">
				<?php for ( $p = 1; $p <= $result['total_pages']; ++$p ) : ?>
					<?php if ( $p === (int) $result['page'] ) : ?>
						<span class="ssc-pagination__cur"><?php echo (int) $p; ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-conversations', 'paged' => $p, 'source' => $filters['source'], 'rating' => $filters['rating'], 'conv' => $filters['conv'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo (int) $p; ?></a>
					<?php endif; ?>
				<?php endfor; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
