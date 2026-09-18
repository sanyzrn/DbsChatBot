<?php
/**
 * Pharmaceutical module onboarding (dedicated setup experience).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$configured = isset( $_GET['configured'] ) ? (int) $_GET['configured'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
?>
<div class="ssc-page">
	<header class="ssc-page__head">
		<div>
			<h1><?php esc_html_e( 'Pharmaceutical Extension', 'smart-support-chatbot' ); ?></h1>
			<p class="ssc-page__sub"><?php esc_html_e( 'Structured adverse drug reaction reporting for your pharmacovigilance workflow.', 'smart-support-chatbot' ); ?></p>
		</div>
	</header>

	<?php if ( $configured ) : ?>
		<div class="ssc-notice ssc-notice--success" role="status"><?php esc_html_e( 'Setup complete. You can now receive and manage ADR reports.', 'smart-support-chatbot' ); ?></div>
	<?php endif; ?>

	<form method="post" class="ssc-form">
		<?php wp_nonce_field( 'ssc_pharma_setup' ); ?>
		<input type="hidden" name="ssc_pharma_setup_save" value="1" />

		<section class="ssc-card">
			<h2><?php esc_html_e( 'Before you go live', 'smart-support-chatbot' ); ?></h2>
			<div class="ssc-field">
				<label for="pv_contact"><?php esc_html_e( 'Pharmacovigilance contact email (for internal alerts and case routing)', 'smart-support-chatbot' ); ?></label>
				<input id="pv_contact" name="pv_contact" type="email" dir="ltr" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
			</div>

			<div class="ssc-notice ssc-notice--info" role="note">
				<p><?php esc_html_e( 'How this extension works:', 'smart-support-chatbot' ); ?></p>
				<ul class="ssc-ul">
					<li><?php esc_html_e( 'Visitors can file a structured ADR report: suspected product, batch/lot, reporter details, reaction description, clinical severity, ICH E2A-style seriousness criteria and outcome.', 'smart-support-chatbot' ); ?></li>
					<li><?php esc_html_e( 'Each report becomes a case with a review workflow (triage → assessment → follow-up → closed) and a permanent audit trail of every change.', 'smart-support-chatbot' ); ?></li>
					<li><?php esc_html_e( 'Serious cases (any seriousness criterion, or serious outcome) are flagged and notified immediately — independent of the reported clinical severity.', 'smart-support-chatbot' ); ?></li>
					<li><?php esc_html_e( 'The AI assistant is locked to strict pharmacovigilance answer rules: no invented medical claims, no personalized treatment advice.', 'smart-support-chatbot' ); ?></li>
					<li><?php esc_html_e( 'ADR cases are never auto-deleted by retention rules; deletion is a manual, audited action.', 'smart-support-chatbot' ); ?></li>
				</ul>
			</div>

			<div class="ssc-notice ssc-notice--warn" role="note">
				<p><strong><?php esc_html_e( 'Compliance disclaimer:', 'smart-support-chatbot' ); ?></strong> <?php esc_html_e( 'This tool supports your workflow but does not certify regulatory compliance. Verify your national reporting obligations (e.g., your pharmacovigilance center\'s timelines and formats) before relying on it for submissions.', 'smart-support-chatbot' ); ?></p>
			</div>
		</section>

		<div class="ssc-form__actions">
			<button type="submit" class="ssc-btn ssc-btn--primary"><?php esc_html_e( 'Complete setup', 'smart-support-chatbot' ); ?></button>
		</div>
	</form>
</div>
