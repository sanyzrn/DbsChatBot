<?php
/**
 * FAQ bank view (faq module).
 *
 * @package SmartSupportChatbot
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
?>
<div class="ssc-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'FAQ Bank', 'nexachat-ai' ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( sprintf( _n( '%d answer', '%d answers', $total, 'nexachat-ai' ), $total ) ); ?></p>
		</div>
	</header>

	<?php if ( $added ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Answer added.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>
	<?php if ( $deleted ) : ?>
		<div class="ssc-notice ssc-notice--info" role="status"><?php esc_html_e( 'Answer deleted.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>
	<?php if ( $imported >= 0 ) : ?>
		<div class="ssc-notice <?php echo '' !== $err ? 'ssc-notice--error' : 'ssc-notice--success'; ?>" role="status">
			<?php
			if ( 'toobig' === $err ) {
				esc_html_e( 'The file is larger than 2 MB and was NOT imported.', 'nexachat-ai' );
			} elseif ( 'nofile' === $err ) {
				esc_html_e( 'No file was uploaded.', 'nexachat-ai' );
			} elseif ( 'badtype' === $err ) {
				esc_html_e( 'Only .csv, .json and .txt files can be imported.', 'nexachat-ai' );
			} else {
				echo esc_html(
					sprintf(
						/* translators: 1: number of imported answers, 2: number of skipped rows. */
						__( 'Imported %1$d answers (%2$d skipped).', 'nexachat-ai' ),
						$imported,
						$skipped
					)
				);
			}
			?>
		</div>
	<?php endif; ?>

	<section class="ssc-card">
		<h2><?php esc_html_e( 'Add an answer', 'nexachat-ai' ); ?></h2>
		<form method="post" class="ssc-form">
			<?php wp_nonce_field( 'ssc_faq' ); ?>
			<input type="hidden" name="ssc_faq_add" value="1" />
			<div class="ssc-field">
				<label for="question"><?php esc_html_e( 'Question', 'nexachat-ai' ); ?></label>
				<input id="question" name="question" type="text" required />
			</div>
			<div class="ssc-field">
				<label for="keywords"><?php esc_html_e( 'Keywords (comma separated, optional)', 'nexachat-ai' ); ?></label>
				<input id="keywords" name="keywords" type="text" placeholder="<?php esc_attr_e( 'price, cost, shipping', 'nexachat-ai' ); ?>" />
			</div>
			<div class="ssc-field">
				<label for="answer"><?php esc_html_e( 'Answer', 'nexachat-ai' ); ?></label>
				<textarea id="answer" name="answer" rows="3" required></textarea>
			</div>
			<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Add answer', 'nexachat-ai' ); ?></button>
		</form>
	</section>

	<?php if ( $rows ) : ?>
		<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table ssc-table--wide">
			<thead><tr><th><?php esc_html_e( 'Question', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'Answer', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'Keywords', 'nexachat-ai' ); ?></th><th></th></tr></thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td class="ssc-td-truncate"><?php echo esc_html( mb_substr( (string) $row['question'], 0, 90 ) ); ?></td>
						<td class="ssc-td-truncate"><?php echo esc_html( mb_substr( (string) $row['answer'], 0, 90 ) ); ?></td>
						<td><?php echo esc_html( (string) $row['keywords'] ); ?></td>
						<td><a class="ssc-link--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'ssc-faq', 'ssc_faq_action' => 'delete', 'id' => $row['id'] ), admin_url( 'admin.php' ) ), 'ssc_faq_' . $row['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'nexachat-ai' ); ?></a></td>
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

	<section class="ssc-card ssc-mt">
		<h2><?php esc_html_e( 'Bulk import / export', 'nexachat-ai' ); ?></h2>
		<form method="post" enctype="multipart/form-data" class="ssc-form">
			<?php wp_nonce_field( 'ssc_faq' ); ?>
			<input type="file" name="faq_file" accept=".csv,.json" aria-label="<?php esc_attr_e( 'FAQ file', 'nexachat-ai' ); ?>" />
			<select name="import_mode" aria-label="<?php esc_attr_e( 'Import mode', 'nexachat-ai' ); ?>">
				<option value="append"><?php esc_html_e( 'Add to existing answers', 'nexachat-ai' ); ?></option>
				<option value="replace"><?php esc_html_e( 'Replace all answers', 'nexachat-ai' ); ?></option>
			</select>
			<button type="submit" name="ssc_faq_import" value="1" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Import', 'nexachat-ai' ); ?></button>
			<button type="submit" name="ssc_faq_export" value="1" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Export CSV', 'nexachat-ai' ); ?></button>
		</form>
	</section>
</div>
