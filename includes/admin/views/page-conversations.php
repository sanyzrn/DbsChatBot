<?php
/**
 * Conversations (history module): summary tiles, source and rating chips,
 * and the exchanges. One whole conversation is read without the summary.
 *
 * @package Vazho
 * @var array $result  Rows.
 * @var array $filters Filters.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ssc_whole   = '' !== $filters['conv'];
$ssc_totals  = $ssc_whole ? array( 'total' => 0, 'sources' => array(), 'up' => 0, 'down' => 0 ) : SSC_Schema::chatlog_counts();
$ssc_sources = array(
	'ai'         => array( __( 'AI', 'vazho' ), 'blue' ),
	'bank'       => array( __( 'FAQ bank', 'vazho' ), 'violet' ),
	'cache'      => array( __( 'Cache', 'vazho' ), 'slate' ),
	'unanswered' => array( __( 'Unanswered', 'vazho' ), 'amber' ),
);
$ssc_unanswered = isset( $ssc_totals['sources']['unanswered'] ) ? (int) $ssc_totals['sources']['unanswered'] : 0;
$ssc_answered   = $ssc_totals['total'] > 0 ? (int) round( 100 * ( $ssc_totals['total'] - $ssc_unanswered ) / $ssc_totals['total'] ) : 0;
$ssc_filtered   = '' !== $filters['source'] || '' !== $filters['rating'];
$ssc_url        = function ( $source, $rating ) {
	return add_query_arg(
		array(
			'page'   => 'ssc-conversations',
			'source' => $source,
			'rating' => $rating,
		),
		admin_url( 'admin.php' )
	);
};
?>
<div class="ssc-page ssc-conversations">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Conversations', 'vazho' ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( sprintf( _n( '%d exchange', '%d exchanges', $result['total'], 'vazho' ), $result['total'] ) ); ?> · <?php esc_html_e( 'retention is configured in Settings', 'vazho' ); ?></p>
		</div>
	</header>

	<?php if ( '' !== $filters['conv'] ) : ?>
		<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'Showing one whole conversation, oldest message first.', 'vazho' ); ?> <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-conversations' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Back to all conversations', 'vazho' ); ?></a></div>
	<?php endif; ?>

	<?php if ( ! $ssc_whole ) : ?>
		<section class="ssc-hstats">
			<?php
			SSC_Admin_UI::stat( 'chat', 'cyan', number_format_i18n( $ssc_totals['total'] ), __( 'Exchanges', 'vazho' ) );
			SSC_Admin_UI::stat( 'check', 'violet', $ssc_answered . '%', __( 'Answered', 'vazho' ), $ssc_url( '', '' ), $ssc_answered );
			SSC_Admin_UI::stat( 'heart', 'green', number_format_i18n( $ssc_totals['up'] ), __( 'Helpful', 'vazho' ), $ssc_url( '', '1' ) );
			SSC_Admin_UI::stat( 'alert', 'red', number_format_i18n( $ssc_totals['down'] ), __( 'Not helpful', 'vazho' ), $ssc_url( '', '-1' ) );
			?>
		</section>

		<?php
		$ssc_chips = array(
			array(
				'label'  => __( 'All sources', 'vazho' ),
				'url'    => $ssc_url( '', $filters['rating'] ),
				'count'  => $ssc_totals['total'],
				'active' => '' === $filters['source'],
				'hue'    => 'indigo',
			),
		);
		foreach ( $ssc_sources as $ssc_key => $ssc_src ) {
			$ssc_chips[] = array(
				'label'  => $ssc_src[0],
				'url'    => $ssc_url( $ssc_key, $filters['rating'] ),
				'count'  => isset( $ssc_totals['sources'][ $ssc_key ] ) ? $ssc_totals['sources'][ $ssc_key ] : 0,
				'active' => $ssc_key === $filters['source'],
				'hue'    => $ssc_src[1],
			);
		}
		SSC_Admin_UI::chips( $ssc_chips, __( 'Answer source', 'vazho' ) );
		SSC_Admin_UI::chips(
			array(
				array(
					'label'  => __( 'Any rating', 'vazho' ),
					'url'    => $ssc_url( $filters['source'], '' ),
					'count'  => null,
					'active' => '' === $filters['rating'],
					'hue'    => 'slate',
				),
				array(
					'label'  => '👍', // phpcs:ignore WordPress.Security.EscapeOutput.EmojiNotSupported -- admin UI.
					'url'    => $ssc_url( $filters['source'], '1' ),
					'count'  => null,
					'active' => '1' === $filters['rating'],
					'hue'    => 'green',
				),
				array(
					'label'  => '👎', // phpcs:ignore WordPress.Security.EscapeOutput.EmojiNotSupported -- admin UI.
					'url'    => $ssc_url( $filters['source'], '-1' ),
					'count'  => null,
					'active' => '-1' === $filters['rating'],
					'hue'    => 'red',
				),
			),
			__( 'Rating', 'vazho' )
		);
		?>
	<?php endif; ?>

	<?php if ( empty( $result['items'] ) ) : ?>
		<?php
		if ( $ssc_filtered ) {
			SSC_Admin_UI::empty_state( 'search', 'slate', __( 'Nothing matches these filters', 'vazho' ), __( 'Try another source or rating.', 'vazho' ), __( 'Show all conversations', 'vazho' ), add_query_arg( 'page', 'ssc-conversations', admin_url( 'admin.php' ) ) );
		} else {
			SSC_Admin_UI::empty_state( 'chat', 'cyan', __( 'No conversations stored yet', 'vazho' ), __( 'Once visitors chat with the assistant, every exchange is listed here for you to review.', 'vazho' ) );
		}
		?>
	<?php else : ?>
		<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'vazho' ); ?>"><table class="ssc-table ssc-table--wide">
			<thead>
				<tr><th><?php esc_html_e( 'Time', 'vazho' ); ?></th><th><?php esc_html_e( 'Question', 'vazho' ); ?></th><th><?php esc_html_e( 'Answer', 'vazho' ); ?></th><th><?php esc_html_e( 'Source', 'vazho' ); ?></th><th><?php esc_html_e( 'Rating', 'vazho' ); ?></th><th></th></tr>
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
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-conversations', 'conv' => $row['conv_hash'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Whole conversation', 'vazho' ); ?></a>
							<?php endif; ?>
							<?php if ( 'unanswered' === $row['source'] ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_log_action', 'tobank', $row_base ), 'ssc_log_' . $row['id'] ) ); ?>"><?php esc_html_e( 'Add to FAQ', 'vazho' ); ?></a>
							<?php endif; ?>
							<a class="ssc-link--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_log_action', 'delete', $row_base ), 'ssc_log_' . $row['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'vazho' ); ?></a>
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

