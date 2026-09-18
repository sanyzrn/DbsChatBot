<?php
/**
 * Dashboard view.
 *
 * @package SmartSupportChatbot
 * @var bool    $live     Publication status.
 * @var bool    $complete Setup completion.
 * @var array   $readiness Readiness checklist.
 * @var array   $business Business profile.
 * @var SSC_Provider|null $provider Active provider.
 * @var bool    $conn_ok Connection verified.
 * @var array   $modules  Module statuses.
 * @var int     $chats_14 Total chats.
 * @var int     $requests Open requests.
 * @var int     $unanswered_count Unanswered count.
 * @var int     $notify_pending Pending notification retries.
 * @var array   $state    Setup state.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ssc-page ssc-dashboard">
	<header class="ssc-page__head">
		<div>
			<h1><?php echo esc_html( defined( 'NEXACHATAI_NAME' ) ? NEXACHATAI_NAME : 'NexaChatAI' ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( $business['org_name'] ? $business['org_name'] : get_bloginfo( 'name' ) ); ?></p>
		</div>
		<div class="ssc-head-actions">
			<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( SSC_Setup::wizard_url() ); ?>"><?php esc_html_e( 'Setup wizard', 'smart-support-chatbot' ); ?></a>
			<?php if ( $live ) : ?>
				<a class="ssc-btn ssc-btn--ghost ssc-btn--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_dash_action', 'unpublish' ), 'ssc_dash' ) ); ?>"><?php esc_html_e( 'Take offline', 'smart-support-chatbot' ); ?></a>
			<?php elseif ( $complete ) : ?>
				<a class="ssc-btn ssc-btn--primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_dash_action', 'publish' ), 'ssc_dash' ) ); ?>"><?php esc_html_e( 'Publish chatbot', 'smart-support-chatbot' ); ?></a>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( ! $complete ) : ?>
		<div class="ssc-callout ssc-callout--setup">
			<div>
				<h2><?php esc_html_e( 'Setup is not finished', 'smart-support-chatbot' ); ?></h2>
				<p><?php esc_html_e( 'The assistant is invisible to visitors until the wizard is complete and you publish it. Pick up where you left off:', 'smart-support-chatbot' ); ?></p>
			</div>
			<a class="ssc-btn ssc-btn--primary" href="<?php echo esc_url( SSC_Setup::wizard_url() ); ?>"><?php esc_html_e( 'Resume setup', 'smart-support-chatbot' ); ?></a>
		</div>
	<?php endif; ?>

	<div class="ssc-statusbar">
		<div class="ssc-status <?php echo $live ? 'is-live' : 'is-off'; ?>">
			<span class="ssc-status__dot" aria-hidden="true"></span>
			<div>
				<strong><?php $live ? esc_html_e( 'Live on your site', 'smart-support-chatbot' ) : esc_html_e( 'Not published', 'smart-support-chatbot' ); ?></strong>
				<span><?php $live ? esc_html_e( 'Visitors can chat with the assistant.', 'smart-support-chatbot' ) : esc_html_e( 'Invisible to visitors.', 'smart-support-chatbot' ); ?></span>
			</div>
		</div>
		<div class="ssc-status <?php echo $conn_ok ? 'is-ok' : 'is-warn'; ?>">
			<span class="dashicons <?php echo $conn_ok ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
			<div>
				<strong><?php echo esc_html( $provider ? $provider->label() : __( 'No AI engine', 'smart-support-chatbot' ) ); ?></strong>
				<span><?php $conn_ok ? esc_html_e( 'Connection verified', 'smart-support-chatbot' ) : esc_html_e( 'Not verified', 'smart-support-chatbot' ); ?></span>
			</div>
			<a class="ssc-status__link" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-connection' ) ); ?>"><?php esc_html_e( 'Manage', 'smart-support-chatbot' ); ?></a>
		</div>
		<div class="ssc-status <?php echo SSC_Setup::knowledge_ready() ? 'is-ok' : 'is-warn'; ?>">
			<span class="dashicons <?php echo SSC_Setup::knowledge_ready() ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
			<div>
				<strong><?php esc_html_e( 'Business knowledge', 'smart-support-chatbot' ); ?></strong>
				<span><?php echo esc_html( sprintf( __( '%d entries · %d documents', 'smart-support-chatbot' ), count( (array) SSC_Settings::get( 'knowledge_items', array() ) ), count( SSC_Schema::kb_documents() ) ) ); ?></span>
			</div>
			<a class="ssc-status__link" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-knowledge' ) ); ?>"><?php esc_html_e( 'Manage', 'smart-support-chatbot' ); ?></a>
		</div>
	</div>

	<?php
	$warnings = array();
	$notify_failed = SSC_Notification_Queue::failed_count();
	if ( $notify_failed > 0 ) {
		$warnings[] = sprintf( __( '%d notifications exhausted automatic retries. Check delivery settings, then retry them.', 'smart-support-chatbot' ), $notify_failed );
	}
	if ( $live && ! $conn_ok && ! $provider ) {
		$warnings[] = __( 'The assistant is published without an AI engine — it can only answer from your FAQ bank.', 'smart-support-chatbot' );
	}
	if ( $live && 'no' === SSC_Settings::get( 'privacy_acknowledged', 'no' ) ) {
		$warnings[] = __( 'External data transfer disclosures have not been acknowledged.', 'smart-support-chatbot' );
	}
	if ( $notify_pending > 0 ) {
		$last_fail = SSC_Module_Notifications::last_failure();
		$warnings[] = sprintf( __( '%d notification deliveries are pending retry (last error: %s).', 'smart-support-chatbot' ), $notify_pending, $last_fail ? $last_fail['error'] : '' );
	}
	if ( $unanswered_count > 0 ) {
		$warnings[] = sprintf( _n( '%d question went unanswered in the last 14 days — consider adding it to your FAQ bank.', '%d questions went unanswered in the last 14 days — consider adding them to your FAQ bank.', $unanswered_count, 'smart-support-chatbot' ), $unanswered_count );
	}
	?>
	<?php if ( $warnings ) : ?>
		<section class="ssc-warnings" aria-label="<?php esc_attr_e( 'Important notices', 'smart-support-chatbot' ); ?>">
			<?php foreach ( $warnings as $warning ) : ?>
				<div class="ssc-warning"><span class="dashicons dashicons-flag" aria-hidden="true"></span><p><?php echo esc_html( $warning ); ?></p></div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<section class="ssc-tiles">
		<?php if ( $notify_failed > 0 && SSC_Modules::is_active( 'notifications' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ssc_retry_notifications" />
				<?php wp_nonce_field( 'ssc_retry_notifications' ); ?>
				<button class="ssc-btn" type="submit"><?php esc_html_e( 'Retry failed notifications', 'smart-support-chatbot' ); ?></button>
			</form>
		<?php endif; ?>
		<a class="ssc-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-knowledge' ) ); ?>">
			<strong class="ssc-tile__num"><?php echo esc_html( number_format_i18n( count( (array) SSC_Settings::get( 'products', array() ) ) ) ); ?></strong>
			<span><?php esc_html_e( 'Products & services', 'smart-support-chatbot' ); ?></span>
		</a>
		<?php if ( isset( $modules['leads'] ) && 'active' === $modules['leads']['status'] ) : ?>
			<a class="ssc-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-requests' ) ); ?>">
				<strong class="ssc-tile__num"><?php echo esc_html( number_format_i18n( $requests ) ); ?></strong>
				<span><?php esc_html_e( 'Requests', 'smart-support-chatbot' ); ?></span>
			</a>
		<?php endif; ?>
		<?php if ( isset( $modules['analytics'] ) && 'active' === $modules['analytics']['status'] ) : ?>
			<a class="ssc-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-analytics' ) ); ?>">
				<strong class="ssc-tile__num"><?php echo esc_html( number_format_i18n( $chats_14 ) ); ?></strong>
				<span><?php esc_html_e( 'Conversations (all time)', 'smart-support-chatbot' ); ?></span>
			</a>
		<?php endif; ?>
		<a class="ssc-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-modules' ) ); ?>">
			<strong class="ssc-tile__num"><?php echo esc_html( number_format_i18n( count( array_filter( array_column( $modules, 'status' ), function ( $s ) { return 'active' === $s; } ) ) ) ); ?></strong>
			<span><?php esc_html_e( 'Active modules', 'smart-support-chatbot' ); ?></span>
		</a>
	</section>

	<section class="ssc-readiness">
		<h2><?php esc_html_e( 'Readiness', 'smart-support-chatbot' ); ?></h2>
		<ul class="ssc-checklist">
			<?php foreach ( $readiness as $item ) : ?>
				<li class="<?php echo $item['done'] ? 'is-done' : ''; ?>">
					<span class="ssc-checklist__mark" aria-hidden="true"><?php echo $item['done'] ? '✓' : '○'; ?></span>
					<span><?php echo esc_html( $item['label'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>
