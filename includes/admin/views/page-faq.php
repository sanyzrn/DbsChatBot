<?php
/**
 * FAQ bank (faq module): summary tiles, the add form and bulk tools in
 * collapsible cards, and the answers.
 *
 * @package Vazho
 * @var array   $rows  Current page rows.
 * @var int     $total Total rows.
 * @var int     $pages Total pages.
 * @var int     $page  Current page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$added    = isset( $_GET['added'] ) ? (int) $_GET['added'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$deleted  = isset( $_GET['deleted'] ) ? (int) $_GET['deleted'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$imported = isset( $_GET['imported'] ) ? (int) $_GET['imported'] : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$skipped  = isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$err      = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.

$ssc_unanswered = count( SSC_Schema::unanswered_questions( 14, 100 ) );
$ssc_qa_labels  = array(
	'ai_first'   => __( 'AI first', 'vazho' ),
	'bank_first' => __( 'FAQ bank first', 'vazho' ),
	'bank_only'  => __( 'FAQ bank only', 'vazho' ),
);
$ssc_qa_mode    = (string) SSC_Settings::get( 'qa_mode', 'ai_first' );
?>
<div class="ssc-page ssc-faq">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'FAQ Bank', 'vazho' ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( sprintf( _n( '%d answer', '%d answers', $total, 'vazho' ), $total ) ); ?></p>
		</div>
	</header>

	<?php if ( $added ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Answer added.', 'vazho' ); ?></div>
	<?php endif; ?>
	<?php if ( $deleted ) : ?>
		<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'Answer deleted.', 'vazho' ); ?></div>
	<?php endif; ?>
	<?php if ( $imported >= 0 ) : ?>
		<div class="ssc-notice <?php echo '' !== $err ? 'ssc-notice--error' : 'ssc-notice--success'; ?>" role="status">
			<?php
			if ( 'toobig' === $err ) {
				esc_html_e( 'The file is larger than 2 MB and was NOT imported.', 'vazho' );
			} elseif ( 'nofile' === $err ) {
				esc_html_e( 'No file was uploaded.', 'vazho' );
			} elseif ( 'badtype' === $err ) {
				esc_html_e( 'Only .csv, .json and .txt files can be imported.', 'vazho' );
			} elseif ( 'empty' === $err ) {
				esc_html_e( 'The file has no usable rows (a question and an answer), so nothing was replaced.', 'vazho' );
			} else {
				echo esc_html(
					sprintf(
						/* translators: 1: number of imported answers, 2: number of skipped rows. */
						__( 'Imported %1$d answers (%2$d skipped).', 'vazho' ),
						$imported,
						$skipped
					)
				);
			}
			?>
		</div>
	<?php endif; ?>

	<section class="ssc-hstats">
		<?php
		SSC_Admin_UI::stat( 'book', 'violet', number_format_i18n( $total ), _n( 'Answer', 'Answers', $total, 'vazho' ) );
		SSC_Admin_UI::stat( 'search', $ssc_unanswered > 0 ? 'amber' : 'green', number_format_i18n( $ssc_unanswered ), __( 'Unanswered questions (14 days)', 'vazho' ), SSC_Modules::is_active( 'analytics' ) ? admin_url( 'admin.php?page=ssc-analytics' ) : '' );
		SSC_Admin_UI::stat( 'cpu', 'blue', isset( $ssc_qa_labels[ $ssc_qa_mode ] ) ? $ssc_qa_labels[ $ssc_qa_mode ] : $ssc_qa_mode, __( 'Answer priority', 'vazho' ), admin_url( 'admin.php?page=ssc-connection#advanced' ) );
		?>
	</section>

	<?php SSC_Admin_UI::acc_open( 'ssc-faq-add', 'plus', __( 'Add an answer', 'vazho' ), 'violet', ! $rows || $added, '', __( 'A question with the exact answer you want visitors to get.', 'vazho' ) ); ?>
		<form method="post" class="ssc-form">
			<?php wp_nonce_field( 'ssc_faq' ); ?>
			<input type="hidden" name="ssc_faq_add" value="1" />
			<div class="ssc-field">
				<label for="question"><?php esc_html_e( 'Question', 'vazho' ); ?></label>
				<input id="question" name="question" type="text" required />
			</div>
			<div class="ssc-field">
				<label for="keywords"><?php esc_html_e( 'Keywords (comma separated, optional)', 'vazho' ); ?></label>
				<input id="keywords" name="keywords" type="text" placeholder="<?php esc_attr_e( 'price, cost, shipping', 'vazho' ); ?>" />
			</div>
			<div class="ssc-field">
				<label for="answer"><?php esc_html_e( 'Answer', 'vazho' ); ?></label>
				<textarea id="answer" name="answer" rows="3" required></textarea>
			</div>
			<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Add answer', 'vazho' ); ?></button>
		</form>
	<?php SSC_Admin_UI::acc_close(); ?>

	<?php if ( ! $rows ) : ?>
		<?php SSC_Admin_UI::empty_state( 'book', 'violet', __( 'No answers yet', 'vazho' ), __( 'Add the questions customers ask most, with the exact answer you want them to get. They are answered instantly, even without an AI engine.', 'vazho' ) ); ?>
	<?php else : ?>
		<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'vazho' ); ?>"><table class="ssc-table ssc-table--wide">
			<thead><tr><th><?php esc_html_e( 'Question', 'vazho' ); ?></th><th><?php esc_html_e( 'Answer', 'vazho' ); ?></th><th><?php esc_html_e( 'Keywords', 'vazho' ); ?></th><th></th></tr></thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td class="ssc-td-truncate"><?php echo esc_html( mb_substr( (string) $row['question'], 0, 90 ) ); ?></td>
						<td class="ssc-td-truncate"><?php echo esc_html( mb_substr( (string) $row['answer'], 0, 90 ) ); ?></td>
						<td><?php echo esc_html( (string) $row['keywords'] ); ?></td>
						<td><a class="ssc-link--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'ssc-faq', 'ssc_faq_action' => 'delete', 'id' => $row['id'] ), admin_url( 'admin.php' ) ), 'ssc_faq_' . $row['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'vazho' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php if ( $pages > 1 ) : ?>
			<nav class="ssc-pagination">
				<?php for ( $p = 1; $p <= $pages; ++$p ) : ?>
					<?php if ( $p === $page ) : ?>
						<span class="ssc-pagination__cur"><?php echo (int) $p; ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssc-faq', 'paged' => $p ), admin_url( 'admin.php' ) ) ); ?>"><?php echo (int) $p; ?></a>
					<?php endif; ?>
				<?php endfor; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>

	<div class="ssc-mt">
	<?php SSC_Admin_UI::acc_open( 'ssc-faq-bulk', 'download', __( 'Bulk import / export', 'vazho' ), 'slate', false, '', __( 'Move many answers at once with a CSV or JSON file.', 'vazho' ) ); ?>
		<form method="post" enctype="multipart/form-data" class="ssc-form">
			<?php wp_nonce_field( 'ssc_faq' ); ?>
			<input type="file" name="faq_file" accept=".csv,.json" aria-label="<?php esc_attr_e( 'FAQ file', 'vazho' ); ?>" />
			<select name="import_mode" aria-label="<?php esc_attr_e( 'Import mode', 'vazho' ); ?>">
				<option value="append"><?php esc_html_e( 'Add to existing answers', 'vazho' ); ?></option>
				<option value="replace"><?php esc_html_e( 'Replace all answers', 'vazho' ); ?></option>
			</select>
			<button type="submit" name="ssc_faq_import" value="1" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Import', 'vazho' ); ?></button>
			<button type="submit" name="ssc_faq_export" value="1" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Export CSV', 'vazho' ); ?></button>
		</form>
	<?php SSC_Admin_UI::acc_close(); ?>
	</div>
</div>

