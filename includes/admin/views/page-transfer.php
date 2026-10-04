<?php
/**
 * Export & Import view.
 *
 * @package SmartSupportChatbot
 * @var string      $state    Screen state: '' | error | review | done | undone | noundo.
 * @var string      $error    Error text.
 * @var array|null  $report   Import report rows.
 * @var array|null  $staged   Parsed upload waiting for confirmation.
 * @var array|null  $summary  What the staged file holds.
 * @var array       $sections Section definitions.
 * @var array       $counts   Live counts per section.
 * @var int         $backup   Timestamp of the undo snapshot (0 = none).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Count text beside a section.
 *
 * @param string   $id    Section id.
 * @param int|null $count Count (null = not a list).
 * @return string
 */
$ssc_count_label = function ( $id, $count ) {
	if ( null === $count ) {
		return '';
	}
	switch ( $id ) {
		case 'modules':
			/* translators: %s: number of modules. */
			return sprintf( _n( '%s module', '%s modules', $count, 'nexachat-ai' ), number_format_i18n( $count ) );
		case 'products':
			/* translators: %s: number of products. */
			return sprintf( _n( '%s product', '%s products', $count, 'nexachat-ai' ), number_format_i18n( $count ) );
		case 'knowledge':
			/* translators: %s: number of entries. */
			return sprintf( _n( '%s entry', '%s entries', $count, 'nexachat-ai' ), number_format_i18n( $count ) );
		case 'faq':
			/* translators: %s: number of questions. */
			return sprintf( _n( '%s question', '%s questions', $count, 'nexachat-ai' ), number_format_i18n( $count ) );
		case 'documents':
			/* translators: %s: number of documents. */
			return sprintf( _n( '%s document', '%s documents', $count, 'nexachat-ai' ), number_format_i18n( $count ) );
		case 'secrets':
			/* translators: %s: number of keys. */
			return sprintf( _n( '%s key', '%s keys', $count, 'nexachat-ai' ), number_format_i18n( $count ) );
	}
	return '';
};
$ssc_when = '';
if ( is_array( $summary ) && '' !== $summary['meta']['exported'] && false !== strtotime( $summary['meta']['exported'] ) ) {
	$ssc_when = SSC_Date::display( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) strtotime( $summary['meta']['exported'] ) ) ) );
}
?>
<div class="ssc-page ssc-transfer">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Export & Import', 'nexachat-ai' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Take everything you set up with you: back it up, move it to another website, or restore it later. One file holds it all.', 'nexachat-ai' ); ?></p>
		</div>
	</header>

	<?php if ( 'error' === $state && '' !== $error ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php echo esc_html( $error ); ?></div>
	<?php elseif ( 'undone' === $state ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'The last import was undone: settings, products, knowledge entries, modules and the FAQ bank are back as they were.', 'nexachat-ai' ); ?></div>
	<?php elseif ( 'noundo' === $state ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><?php esc_html_e( 'There is nothing to undo.', 'nexachat-ai' ); ?></div>
	<?php endif; ?>

	<?php if ( 'done' === $state ) : ?>
		<section class="ssc-card ssc-xdone">
			<h2><?php echo SSC_Icons::get( 'check', 'ssc-xdone__ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Import finished', 'nexachat-ai' ); ?></h2>
			<?php if ( ! is_array( $report ) ) : ?>
				<p class="ssc-card__sub"><?php esc_html_e( 'The report is no longer available.', 'nexachat-ai' ); ?></p>
			<?php else : ?>
				<ul class="ssc-xreport">
					<?php foreach ( $report as $row ) : ?>
						<?php
						$ssc_label = isset( $sections[ $row['id'] ] ) ? $sections[ $row['id'] ]['label'] : $row['id'];
						$ssc_icon  = 'ok' === $row['status'] ? 'check' : 'alert';
						?>
						<li class="ssc-xreport__row is-<?php echo esc_attr( $row['status'] ); ?>">
							<span class="ssc-xreport__mark"><?php echo SSC_Icons::get( $ssc_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
							<span class="ssc-xreport__label"><?php echo esc_html( $ssc_label ); ?></span>
							<span class="ssc-xreport__note">
								<?php
								$ssc_bits = array();
								if ( $row['count'] > 0 ) {
									$ssc_bits[] = $ssc_count_label( $row['id'], (int) $row['count'] );
								} elseif ( 'ok' === $row['status'] && in_array( $row['id'], array( 'modules', 'products', 'knowledge', 'faq', 'documents', 'secrets' ), true ) ) {
									$ssc_bits[] = __( 'Nothing to add', 'nexachat-ai' );
								}
								if ( '' !== $row['note'] ) {
									$ssc_bits[] = $row['note'];
								}
								echo esc_html( implode( ' · ', array_filter( $ssc_bits ) ) );
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="ssc-card__sub"><?php esc_html_e( 'Next: open the Dashboard, test the AI connection on this site and publish. Imported documents are indexed in the background.', 'nexachat-ai' ); ?></p>
			<?php endif; ?>
			<div class="ssc-form__actions ssc-form__actions--start">
				<a class="ssc-btn ssc-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-dashboard' ) ); ?>"><?php esc_html_e( 'Go to the Dashboard', 'nexachat-ai' ); ?></a>
				<a class="ssc-btn ssc-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-knowledge' ) ); ?>"><?php esc_html_e( 'See the knowledge', 'nexachat-ai' ); ?></a>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( 'review' === $state && is_array( $staged ) && is_array( $summary ) ) : ?>
		<form method="post" class="ssc-card ssc-xreview">
			<?php wp_nonce_field( 'ssc_transfer_apply' ); ?>
			<h2><?php echo SSC_Icons::get( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Review before importing', 'nexachat-ai' ); ?></h2>
			<p class="ssc-card__sub">
				<?php
				$ssc_from = '' !== $summary['meta']['site_name'] ? $summary['meta']['site_name'] : $summary['meta']['site'];
				echo esc_html(
					sprintf(
						/* translators: 1: site name, 2: date, 3: plugin version. */
						__( 'File from “%1$s”, exported %2$s with NexaChatAI %3$s. Nothing has changed yet.', 'nexachat-ai' ),
						'' !== $ssc_from ? $ssc_from : __( 'an unknown site', 'nexachat-ai' ),
						'' !== $ssc_when ? $ssc_when : __( 'at an unknown time', 'nexachat-ai' ),
						'' !== $summary['meta']['version'] ? $summary['meta']['version'] : '?'
					)
				);
				?>
			</p>

			<div class="ssc-xgrid">
				<?php foreach ( $sections as $ssc_id => $ssc_def ) : ?>
					<?php
					$ssc_here = in_array( $ssc_id, $summary['present'], true );
					?>
					<label class="ssc-xtile<?php echo $ssc_here ? '' : ' is-absent'; ?>">
						<input type="checkbox" name="sections[]" value="<?php echo esc_attr( $ssc_id ); ?>" <?php checked( $ssc_here ); ?> <?php disabled( ! $ssc_here ); ?> data-ssc-xsection="<?php echo esc_attr( $ssc_id ); ?>" />
						<span class="ssc-xtile__ico"><?php echo SSC_Icons::get( $ssc_def['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
						<span class="ssc-xtile__body">
							<strong><?php echo esc_html( $ssc_def['label'] ); ?></strong>
							<span class="ssc-xtile__meta">
								<?php
								echo esc_html(
									$ssc_here
										? ( '' !== $ssc_count_label( $ssc_id, $summary['counts'][ $ssc_id ] ) ? $ssc_count_label( $ssc_id, $summary['counts'][ $ssc_id ] ) : __( 'In the file', 'nexachat-ai' ) )
										: __( 'Not in the file', 'nexachat-ai' )
								);
								?>
							</span>
						</span>
						<span class="ssc-xtile__tick"><?php echo SSC_Icons::get( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					</label>
				<?php endforeach; ?>
			</div>

			<?php if ( in_array( 'secrets', $summary['present'], true ) ) : ?>
				<div class="ssc-field ssc-xpass" data-ssc-xwhen="secrets">
					<label for="import_password"><?php echo SSC_Icons::get( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Password for the API keys', 'nexachat-ai' ); ?></label>
					<input id="import_password" name="import_password" type="password" autocomplete="off" dir="ltr" />
					<p class="ssc-field__hint"><?php esc_html_e( 'The password chosen when the file was exported. Without it everything else is imported and the keys are skipped.', 'nexachat-ai' ); ?></p>
				</div>
			<?php endif; ?>

			<fieldset class="ssc-xmode">
				<legend><?php esc_html_e( 'How to combine with what is here', 'nexachat-ai' ); ?></legend>
				<label class="ssc-choice"><input type="radio" name="mode" value="merge" checked /> <span><strong><?php esc_html_e( 'Add and update (recommended)', 'nexachat-ai' ); ?></strong><span class="ssc-choice__desc"><?php esc_html_e( 'Keeps your current products, entries and questions; the file adds new ones and updates matching ones.', 'nexachat-ai' ); ?></span></span></label>
				<label class="ssc-choice"><input type="radio" name="mode" value="replace" /> <span><strong><?php esc_html_e( 'Replace', 'nexachat-ai' ); ?></strong><span class="ssc-choice__desc"><?php esc_html_e( 'Makes the chosen lists match the file exactly. Items that are only here are removed.', 'nexachat-ai' ); ?></span></span></label>
			</fieldset>

			<p class="ssc-xsafe"><?php echo SSC_Icons::get( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'The current state is saved first, so you can undo this import afterwards.', 'nexachat-ai' ); ?></p>
			<p class="ssc-xsafe"><?php echo SSC_Icons::get( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Settings that decide where information is sent (the AI endpoint, notification recipients) are imported only together with the API keys, so a file that is not yours cannot redirect your data.', 'nexachat-ai' ); ?></p>

			<div class="ssc-form__actions ssc-form__actions--start">
				<button type="submit" name="ssc_transfer_apply" value="1" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Import the selected', 'nexachat-ai' ); ?></button>
				<button type="submit" name="ssc_transfer_cancel" value="1" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Cancel', 'nexachat-ai' ); ?></button>
			</div>
		</form>
	<?php endif; ?>

	<div class="ssc-xcols"<?php echo 'review' === $state ? ' hidden' : ''; ?>>
		<form method="post" class="ssc-card ssc-xexport">
			<?php wp_nonce_field( 'ssc_transfer_export' ); ?>
			<input type="hidden" name="ssc_transfer_export" value="1" />
			<h2><?php echo SSC_Icons::get( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Export', 'nexachat-ai' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Choose what goes into the file.', 'nexachat-ai' ); ?></p>

			<div class="ssc-xgrid">
				<?php foreach ( $sections as $ssc_id => $ssc_def ) : ?>
					<label class="ssc-xtile">
						<input type="checkbox" name="sections[]" value="<?php echo esc_attr( $ssc_id ); ?>" <?php checked( 'secrets' !== $ssc_id ); ?> data-ssc-xsection="<?php echo esc_attr( $ssc_id ); ?>" />
						<span class="ssc-xtile__ico"><?php echo SSC_Icons::get( $ssc_def['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
						<span class="ssc-xtile__body">
							<strong><?php echo esc_html( $ssc_def['label'] ); ?></strong>
							<span class="ssc-xtile__meta"><?php echo esc_html( '' !== $ssc_count_label( $ssc_id, $counts[ $ssc_id ] ) ? $ssc_count_label( $ssc_id, $counts[ $ssc_id ] ) : $ssc_def['hint'] ); ?></span>
						</span>
						<span class="ssc-xtile__tick"><?php echo SSC_Icons::get( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="ssc-field ssc-xpass" data-ssc-xwhen="secrets" hidden>
				<label for="export_password"><?php echo SSC_Icons::get( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Password to protect the API keys', 'nexachat-ai' ); ?></label>
				<input id="export_password" name="export_password" type="password" autocomplete="new-password" dir="ltr" minlength="<?php echo esc_attr( (string) SSC_Transfer::MIN_PASSWORD ); ?>" />
				<p class="ssc-field__hint"><?php echo esc_html( sprintf( /* translators: %d: minimum password length. */ __( 'At least %d characters. The keys are encrypted with it; you need the same password to import them, and it cannot be recovered.', 'nexachat-ai' ), SSC_Transfer::MIN_PASSWORD ) ); ?></p>
			</div>

			<p class="ssc-xsafe"><?php echo SSC_Icons::get( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Conversations, requests, safety reports and statistics are visitor data and are never exported.', 'nexachat-ai' ); ?></p>

			<div class="ssc-form__actions ssc-form__actions--start">
				<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Download the export file', 'nexachat-ai' ); ?></button>
			</div>
		</form>

		<div class="ssc-xside">
			<form method="post" enctype="multipart/form-data" class="ssc-card ssc-ximport">
				<?php wp_nonce_field( 'ssc_transfer_import' ); ?>
				<input type="hidden" name="ssc_transfer_upload" value="1" />
				<h2><?php echo SSC_Icons::get( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Import from a file', 'nexachat-ai' ); ?></h2>
				<p class="ssc-card__sub"><?php esc_html_e( 'Choose a file made by the export. You will see what it contains and decide what to import before anything changes.', 'nexachat-ai' ); ?></p>
				<div class="ssc-field">
					<label for="transfer_file"><?php esc_html_e( 'Export file (.json)', 'nexachat-ai' ); ?></label>
					<input id="transfer_file" name="transfer_file" type="file" accept=".json,application/json" required />
				</div>
				<div class="ssc-form__actions ssc-form__actions--start">
					<button type="submit" class="ssc-btn ssc-btn--secondary"><?php esc_html_e( 'Read the file', 'nexachat-ai' ); ?></button>
				</div>
			</form>

			<?php if ( $backup ) : ?>
				<form method="post" class="ssc-card ssc-xundo">
					<?php wp_nonce_field( 'ssc_transfer_undo' ); ?>
					<input type="hidden" name="ssc_transfer_undo" value="1" />
					<h2><?php echo SSC_Icons::get( 'undo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?> <?php esc_html_e( 'Undo the last import', 'nexachat-ai' ); ?></h2>
					<p class="ssc-card__sub"><?php echo esc_html( sprintf( /* translators: %s: date and time. */ __( 'Restores the settings, products, knowledge entries, modules and FAQ bank as they were before the import on %s. Imported documents stay.', 'nexachat-ai' ), SSC_Date::display( wp_date( 'Y-m-d H:i:s', $backup ) ) ) ); ?></p>
					<button type="submit" class="ssc-btn ssc-btn--ghost ssc-btn--danger" onclick="return window.confirm(this.getAttribute('data-confirm'));" data-confirm="<?php esc_attr_e( 'Go back to how things were before the last import?', 'nexachat-ai' ); ?>"><?php esc_html_e( 'Undo the import', 'nexachat-ai' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</div>
</div>
