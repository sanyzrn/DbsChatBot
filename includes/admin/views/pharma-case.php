<?php
/**
 * ADR case detail view (pharma module): full report, workflow, audit trail.
 *
 * @package Vazho
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
<div class="ssc-page ssc-adr-case">
	<header class="ssc-page__head">
		<div>
			<h1><?php echo esc_html( sprintf( __( 'ADR case #%d', 'vazho' ), (int) $case['id'] ) ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( SSC_Date::display( $case['created_at'] ) ); ?> · <span class="ssc-pill" data-ssc-hue="<?php echo esc_attr( $serious ? 'red' : 'blue' ); ?>"><?php echo esc_html( isset( $statuses[ $case['status'] ] ) ? $statuses[ $case['status'] ] : $case['status'] ); ?></span></p>
		</div>
		<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-pharma' ) ); ?>"><span class="ssc-dir-arrow" aria-hidden="true">←</span> <?php esc_html_e( 'All cases', 'vazho' ); ?></a>
	</header>

	<?php if ( $serious ) : ?>
		<div class="ssc-notice ssc-notice--error" role="alert"><strong><?php esc_html_e( 'SERIOUS case', 'vazho' ); ?></strong> — <?php echo esc_html( implode( ', ', $criteria ) ); ?></div>
	<?php endif; ?>

	<div class="ssc-grid ssc-grid--2">
		<section class="ssc-card">
			<?php SSC_Admin_UI::card_title( 'user', 'blue', __( 'Reporter', 'vazho' ) ); ?>
			<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'vazho' ); ?>"><table class="ssc-table ssc-table--kv">
				<tbody>
					<tr><th><?php esc_html_e( 'Name', 'vazho' ); ?></th><td><?php echo esc_html( $case['name'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Phone', 'vazho' ); ?></th><td dir="ltr"><?php echo esc_html( $case['phone'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Reporter role', 'vazho' ); ?></th><td><?php echo esc_html( SSC_Module_Pharma::option_label( 'reporter_type', (string) $case['reporter_type'] ) ); ?></td></tr>
					<?php if ( isset( $extra['patient_age'] ) && '' !== (string) $extra['patient_age'] ) : ?>
						<tr><th><?php esc_html_e( 'Patient age', 'vazho' ); ?></th><td><?php echo esc_html( (string) $extra['patient_age'] ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $extra['patient_sex'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Patient sex', 'vazho' ); ?></th><td><?php echo esc_html( SSC_Module_Pharma::option_label( 'patient_sex', (string) $extra['patient_sex'] ) ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table></div>
		</section>

		<section class="ssc-card">
			<?php SSC_Admin_UI::card_title( 'box', 'violet', __( 'Suspected product', 'vazho' ) ); ?>
			<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'vazho' ); ?>"><table class="ssc-table ssc-table--kv">
				<tbody>
					<tr><th><?php esc_html_e( 'Product', 'vazho' ); ?></th><td><?php echo esc_html( $case['product'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Batch / lot', 'vazho' ); ?></th><td><?php echo esc_html( $case['batch_number'] ); ?></td></tr>
					<?php if ( ! empty( $extra['dose'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Dose & frequency', 'vazho' ); ?></th><td><?php echo esc_html( (string) $extra['dose'] ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $extra['route'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Route', 'vazho' ); ?></th><td><?php echo esc_html( SSC_Module_Pharma::option_label( 'route', (string) $extra['route'] ) ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table></div>
		</section>

		<section class="ssc-card">
			<?php SSC_Admin_UI::card_title( 'heart', 'rose', __( 'Reaction', 'vazho' ) ); ?>
			<blockquote class="ssc-quote"><?php echo esc_html( (string) $case['description'] ); ?></blockquote>
			<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'vazho' ); ?>"><table class="ssc-table ssc-table--kv">
				<tbody>
					<tr><th><?php esc_html_e( 'Clinical severity', 'vazho' ); ?></th><td><?php echo esc_html( SSC_Module_Pharma::option_label( 'severity', (string) $case['severity'] ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Seriousness criteria', 'vazho' ); ?></th><td><?php echo $criteria ? esc_html( implode( ', ', $criteria ) ) : '—'; ?></td></tr>
					<tr><th><?php esc_html_e( 'Outcome', 'vazho' ); ?></th><td><?php echo esc_html( SSC_Module_Pharma::option_label( 'outcome', (string) $case['outcome'] ) ); ?></td></tr>
					<?php if ( ! empty( $case['concomitant_drugs'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Concomitant drugs', 'vazho' ); ?></th><td><?php echo esc_html( (string) $case['concomitant_drugs'] ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table></div>
		</section>

		<?php foreach ( SSC_Module_Pharma::custom_answers( $extra ) as $ssc_answer_group ) : ?>
			<?php if ( $ssc_answer_group ) : ?>
				<section class="ssc-card">
					<?php SSC_Admin_UI::card_title( 'list', 'slate', __( 'Additional questions', 'vazho' ) ); ?>
					<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'vazho' ); ?>"><table class="ssc-table ssc-table--kv">
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
			<?php SSC_Admin_UI::card_title( 'scale', 'green', __( 'Case workflow', 'vazho' ) ); ?>
			<form method="post" class="ssc-form">
				<?php wp_nonce_field( 'ssc_case_' . (int) $case['id'] ); ?>
				<input type="hidden" name="case_id" value="<?php echo esc_attr( (string) $case['id'] ); ?>" />
				<div class="ssc-field">
					<label for="ssc_case_status"><?php esc_html_e( 'Status', 'vazho' ); ?></label>
					<select id="ssc_case_status" name="ssc_case_status">
						<?php foreach ( $statuses as $st => $st_label ) : ?>
							<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $case['status'], $st ); ?>><?php echo esc_html( $st_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ssc-field">
					<label for="case_note"><?php esc_html_e( 'Follow-up note (recorded in the audit trail)', 'vazho' ); ?></label>
					<textarea id="case_note" name="case_note" rows="3"></textarea>
				</div>
				<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Update case', 'vazho' ); ?></button>
			</form>
		</section>

		<section class="ssc-card">
			<?php SSC_Admin_UI::card_title( 'lock', 'amber', __( 'Privacy actions (per record)', 'vazho' ) ); ?>
			<p class="ssc-card__sub"><?php esc_html_e( 'Export this case, remove direct identifiers, or permanently delete the record. Removing identifiers does not anonymize free-text narratives or audit notes; review those separately under your retention policy.', 'vazho' ); ?></p>
			<form method="post" class="ssc-form" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
				<?php wp_nonce_field( 'ssc_case_privacy_' . (int) $case['id'] ); ?>
				<input type="hidden" name="case_id" value="<?php echo esc_attr( (string) $case['id'] ); ?>" />
				<button type="submit" name="ssc_case_privacy" value="export" class="ssc-btn ssc-btn--ghost"><?php esc_html_e( 'Export JSON', 'vazho' ); ?></button>
				<?php if ( empty( $extra['_anonymized'] ) ) : ?>
					<button type="submit" name="ssc_case_privacy" value="anonymize" class="ssc-btn ssc-btn--ghost" onclick="return confirm('<?php echo esc_js( __( 'Remove reporter name, phone, IP and demographics? Clinical fields stay for PV review.', 'vazho' ) ); ?>');"><?php esc_html_e( 'Remove direct identifiers', 'vazho' ); ?></button>
				<?php else : ?>
					<span class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Direct identifiers removed', 'vazho' ); ?></span>
				<?php endif; ?>
			<button type="submit" name="ssc_case_privacy" value="purge" class="ssc-btn ssc-btn--danger" onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this case? Clinical data is removed; a purge entry stays in the audit trail.', 'vazho' ) ); ?>');"><?php esc_html_e( 'Delete case', 'vazho' ); ?></button>
			</form>
		</section>
	</div>

	<section class="ssc-card ssc-mt">
		<?php SSC_Admin_UI::card_title( 'clock', 'slate', __( 'Audit trail', 'vazho' ) ); ?>
		<?php if ( $audit ) : ?>
			<div class="ssc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable table', 'vazho' ); ?>"><table class="ssc-table">
				<thead><tr><th><?php esc_html_e( 'Time', 'vazho' ); ?></th><th><?php esc_html_e( 'Action', 'vazho' ); ?></th><th><?php esc_html_e( 'From → To', 'vazho' ); ?></th><th><?php esc_html_e( 'By', 'vazho' ); ?></th><th><?php esc_html_e( 'Note', 'vazho' ); ?></th></tr></thead>
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
			<p class="ssc-card__sub"><?php esc_html_e( 'No audit entries yet.', 'vazho' ); ?></p>
		<?php endif; ?>
	</section>
</div>

