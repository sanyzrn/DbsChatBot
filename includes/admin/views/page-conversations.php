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
			<h1><?php esc_html_e( 'Conversations', 'smart-support-chatbot' ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( sprintf( _n( '%d exchange', '%d exchanges', $result['total'], 'smart-support-chatbot' ), $result['total'] ) ); ?> · <?php esc_html_e( 'retention is configured in Settings', 'smart-support-chatbot' ); ?></p>
		</div>
	</header>

	<form method="get" class="ssc-filterbar">
		<input type="hidden" name="page" value="ssc-conversations" />
		<select name="source" aria-label="<?php esc_attr_e( 'Answer source', 'smart-support-chatbot' ); ?>">
			<option value=""><?php esc_html_e( 'All sources', 'smart-support-chatbot' ); ?></option>
			<?php foreach ( array( 'ai' => __( 'AI', 'smart-support-chatbot' ), 'bank' => __( 'FAQ bank', 'smart-support-chatbot' ), 'cache' => __( 'Cache', 'smart-support-chatbot' ), 'unanswered' => __( 'Unanswered', 'smart-support-chatbot' ) ) as $src => $src_label ) : ?>
				<option value="<?php echo esc_attr( $src ); ?>" <?php selected( $filters['source'], $src ); ?>><?php echo esc_html( $src_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="rating" aria-label="<?php esc_attr_e( 'Rating', 'smart-support-chatbot' ); ?>">
			<option value=""><?php esc_html_e( 'Any rating', 'smart-support-chatbot' ); ?></option>
			<option value="1" <?php selected( $filters['rating'], '1' ); ?>>👍</option>
			<option value="-1" <?php selected( $filters['rating'], '-1' ); ?>>👎</option>
		</select>
		<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Filter', 'smart-support-chatbot' ); ?></button>
	</form>

	<?php if ( empty( $result['items'] ) ) : ?>
		<div class="ssc-empty"><p><?php esc_html_e( 'No conversations stored yet.', 'smart-support-chatbot' ); ?></p></div>
	<?php else : ?>
		<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'smart-support-chatbot' ); ?>"><table class="ssc-table ssc-table--wide">
			<thead>
				<tr><th><?php esc_html_e( 'Time', 'smart-support-chatbot' ); ?></th><th><?php esc_html_e( 'Question', 'smart-support-chatbot' ); ?></th><th><?php esc_html_e( 'Answer', 'smart-support-chatbot' ); ?></th><th><?php esc_html_e( 'Source', 'smart-support-chatbot' ); ?></th><th><?php esc_html_e( 'Rating', 'smart-support-chatbot' ); ?></th><th></th></tr>
			</thead>
			<tbody>
				<?php foreach ( $result['items'] as $row ) : ?>
					<?php
					$row_base = add_query_arg( array( 'page' => 'ssc-conversations', 'id' => $row['id'], 'source' => $filters['source'], 'rating' => $filters['rating'] ), admin_url( 'admin.php' ) );
					?>
					<tr>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ) ); ?></td>
						<td class="ssc-td-truncate"><?php echo esc_html( mb_substr( (string) $row['question'], 0, 80 ) ); ?></td>
						<td class="ssc-td-truncate"><?php echo esc_html( mb_substr( (string) $row['answer'], 0, 80 ) ); ?></td>
						<td><span class="ssc-badge ssc-badge--src-<?php echo esc_attr( $row['source'] ); ?>"><?php echo esc_html( $row['source'] ); ?></span></td>
						<td><?php echo (int) $row['rating'] > 0 ? '👍' : ( (int) $row['rating'] < 0 ? '👎' : '—' ); // phpcs:ignore WordPress.Security.EscapeOutput.EmojiNotSupported -- admin UI. ?></td>
						<td class="ssc-td-actions">
							<?php if ( 'unanswered' === $row['source'] ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_log_action', 'tobank', $row_base ), 'ssc_log_' . $row['id'] ) ); ?>"><?php esc_html_e( 'Add to FAQ', 'smart-support-chatbot' ); ?></a>
							<?php endif; ?>
							<a class="ssc-link--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_log_action', 'delete', $row_base ), 'ssc_log_' . $row['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'smart-support-chatbot' ); ?></a>
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
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-conversations', 'paged' => $p, 'source' => $filters['source'], 'rating' => $filters['rating'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo (int) $p; ?></a>
					<?php endif; ?>
				<?php endfor; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
