<?php
/**
 * ADR case detail view (pharma module): full report, workflow, audit trail.
 *
 * @package SmartSupportChatbot
 * @var array $case Case row.
 * @var array $audit Audit rows.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$extra     = json_decode( (string) $case['extra_fields'], true );
$extra     = is_array( $extra ) ? $extra : array();
$serious   = SSC_Module_Pharma::is_serious_row( $case );
$criteria  = SSC_Module_Pharma::seriousness_criteria( $case );
$statuses  = SSC_Module_Pharma::case_statuses();
?>
<div class="ssc-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php echo esc_html( sprintf( __( 'ADR case #%d', 'nexachat-ai' ), (int) $case['id'] ) ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( SSC_Date::display( $case['created_at'] ) ); ?></p>
		</div>
		<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-pharma' ) ); ?>"><span class="ssc-dir-arrow" aria-hidden="true">←</span> <?php esc_html_e( 'All cases', 'nexachat-ai' ); ?></a>
	</header>

	<?php if ( $serious ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><strong><?php esc_html_e( 'SERIOUS case', 'nexachat-ai' ); ?></strong> — <?php echo esc_html( implode( ', ', $criteria ) ); ?></div>
	<?php endif; ?>

	<div class="ssc-grid ssc-grid--2">
		<section class="ssc-card">
			<h2><?php esc_html_e( 'Reporter', 'nexachat-ai' ); ?></h2>
			<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table ssc-table--kv">
				<tbody>
					<tr><th><?php esc_html_e( 'Name', 'nexachat-ai' ); ?></th><td><?php echo esc_html( $case['name'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Phone', 'nexachat-ai' ); ?></th><td dir="ltr"><?php echo esc_html( $case['phone'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Reporter role', 'nexachat-ai' ); ?></th><td><?php echo esc_html( SSC_Module_Pharma::option_label( 'reporter_type', (string) $case['reporter_type'] ) ); ?></td></tr>
					<?php if ( isset( $extra['patient_age'] ) && '' !== (string) $extra['patient_age'] ) : ?>
						<tr><th><?php esc_html_e( 'Patient age', 'nexachat-ai' ); ?></th><td><?php echo esc_html( (string) $extra['patient_age'] ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $extra['patient_sex'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Patient sex', 'nexachat-ai' ); ?></th><td><?php echo esc_html( SSC_Module_Pharma::option_label( 'patient_sex', (string) $extra['patient_sex'] ) ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table></div>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Suspected product', 'nexachat-ai' ); ?></h2>
			<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table ssc-table--kv">
				<tbody>
					<tr><th><?php esc_html_e( 'Product', 'nexachat-ai' ); ?></th><td><?php echo esc_html( $case['product'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Batch / lot', 'nexachat-ai' ); ?></th><td><?php echo esc_html( $case['batch_number'] ); ?></td></tr>
					<?php if ( ! empty( $extra['dose'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Dose & frequency', 'nexachat-ai' ); ?></th><td><?php echo esc_html( (string) $extra['dose'] ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $extra['route'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Route', 'nexachat-ai' ); ?></th><td><?php echo esc_html( SSC_Module_Pharma::option_label( 'route', (string) $extra['route'] ) ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table></div>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Reaction', 'nexachat-ai' ); ?></h2>
			<blockquote class="ssc-quote"><?php echo esc_html( (string) $case['description'] ); ?></blockquote>
			<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table ssc-table--kv">
				<tbody>
					<tr><th><?php esc_html_e( 'Clinical severity', 'nexachat-ai' ); ?></th><td><?php echo esc_html( SSC_Module_Pharma::option_label( 'severity', (string) $case['severity'] ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Seriousness criteria', 'nexachat-ai' ); ?></th><td><?php echo $criteria ? esc_html( implode( ', ', $criteria ) ) : '—'; ?></td></tr>
					<tr><th><?php esc_html_e( 'Outcome', 'nexachat-ai' ); ?></th><td><?php echo esc_html( SSC_Module_Pharma::option_label( 'outcome', (string) $case['outcome'] ) ); ?></td></tr>
					<?php if ( ! empty( $case['concomitant_drugs'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Concomitant drugs', 'nexachat-ai' ); ?></th><td><?php echo esc_html( (string) $case['concomitant_drugs'] ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table></div>
		</section>

		<?php foreach ( SSC_Module_Pharma::custom_answers( $extra ) as $ssc_answer_group ) : ?>
			<?php if ( $ssc_answer_group ) : ?>
				<section class="ssc-card">
					<h2><?php esc_html_e( 'Additional questions', 'nexachat-ai' ); ?></h2>
					<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table ssc-table--kv">
						<tbody>
							<?php foreach ( $ssc_answer_group as $ssc_question => $ssc_answer ) : ?>
								<tr><th dir="auto"><?php echo esc_html( $ssc_question ); ?></th><td dir="auto"><?php echo esc_html( $ssc_answer ); ?></td></tr>
							<?php endforeach; ?>
						</tbody>
					</table></div>
				</section>
			<?php endif; ?>
		<?php endforeach; ?>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Case workflow', 'nexachat-ai' ); ?></h2>
			<form method="post" class="ssc-form">
				<?php wp_nonce_field( 'ssc_case_' . (int) $case['id'] ); ?>
				<input type="hidden" name="case_id" value="<?php echo esc_attr( (string) $case['id'] ); ?>" />
				<div class="ssc-field">
					<label for="ssc_case_status"><?php esc_html_e( 'Status', 'nexachat-ai' ); ?></label>
					<select id="ssc_case_status" name="ssc_case_status">
						<?php foreach ( $statuses as $st => $st_label ) : ?>
							<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $case['status'], $st ); ?>><?php echo esc_html( $st_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ssc-field">
					<label for="case_note"><?php esc_html_e( 'Follow-up note (recorded in the audit trail)', 'nexachat-ai' ); ?></label>
					<textarea id="case_note" name="case_note" rows="3"></textarea>
				</div>
				<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Update case', 'nexachat-ai' ); ?></button>
			</form>
		</section>

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Privacy actions (per record)', 'nexachat-ai' ); ?></h2>
			<p class="ssc-card__sub"><?php esc_html_e( 'Export this case, remove direct identifiers, or permanently delete the record. Removing identifiers does not anonymize free-text narratives or audit notes; review those separately under your retention policy.', 'nexachat-ai' ); ?></p>
			<form method="post" class="ssc-form" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
				<?php wp_nonce_field( 'ssc_case_privacy_' . (int) $case['id'] ); ?>
				<input type="hidden" name="case_id" value="<?php echo esc_attr( (string) $case['id'] ); ?>" />
				<button type="submit" name="ssc_case_privacy" value="export" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Export JSON', 'nexachat-ai' ); ?></button>
				<?php if ( empty( $extra['_anonymized'] ) ) : ?>
					<button type="submit" name="ssc_case_privacy" value="anonymize" class="ssc-btn ssc-btn--ghost" onclick="return confirm('<?php echo esc_js( __( 'Remove reporter name, phone, IP and demographics? Clinical fields stay for PV review.', 'nexachat-ai' ) ); ?>');"><?php esc_html_e( 'Remove direct identifiers', 'nexachat-ai' ); ?></button>
				<?php else : ?>
					<span class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Direct identifiers removed', 'nexachat-ai' ); ?></span>
				<?php endif; ?>
			<button type="submit" name="ssc_case_privacy" value="purge" class="ssc-btn ssc-btn--danger" onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this case? Clinical data is removed; a purge entry stays in the audit trail.', 'nexachat-ai' ) ); ?>');"><?php esc_html_e( 'Delete case', 'nexachat-ai' ); ?></button>
			</form>
		</section>
	</div>

	<section class="ssc-card ssc-mt">
		<h2><?php esc_html_e( 'Audit trail', 'nexachat-ai' ); ?></h2>
		<?php if ( $audit ) : ?>
			<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'nexachat-ai' ); ?>"><table class="ssc-table">
				<thead><tr><th><?php esc_html_e( 'Time', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'Action', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'From → To', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'By', 'nexachat-ai' ); ?></th><th><?php esc_html_e( 'Note', 'nexachat-ai' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $audit as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( SSC_Date::display( $entry['created_at'] ) ); ?></td>
							<td><?php echo esc_html( $entry['action'] ); ?></td>
							<td><?php echo esc_html( $entry['from_status'] . ( $entry['to_status'] ? ' → ' . $entry['to_status'] : '' ) ); ?></td>
							<td><?php echo esc_html( $entry['actor'] ); ?></td>
							<td class="ssc-td-truncate"><?php echo esc_html( mb_substr( (string) $entry['note'], 0, 80 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table></div>
		<?php else : ?>
			<p class="ssc-card__sub"><?php esc_html_e( 'No audit entries yet.', 'nexachat-ai' ); ?></p>
		<?php endif; ?>
	</section>
</div>
