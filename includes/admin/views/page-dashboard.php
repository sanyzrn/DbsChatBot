<?php
/**
 * Dashboard view: a readiness ring with the next steps, three status cards,
 * notices that need attention, icon tiles with the numbers, and the
 * readiness list with a link to where each item is fixed.
 *
 * @package SmartSupportChatbot
 * @var bool    $live     Publication status.
 * @var bool    $complete Setup completion.
 * @var array   $readiness Readiness checklist.
 * @var array   $business Business profile.
 * @var SSC_Provider|null $provider Active provider.
 * @var bool    $conn_ok Connection verified.
 * @var array   $conn_info SSC_Setup::connection_status().
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

$ssc_done_count = count( array_filter( wp_list_pluck( $readiness, 'done' ) ) );
$ssc_total      = max( 1, count( $readiness ) );
$ssc_percent    = (int) round( 100 * $ssc_done_count / $ssc_total );
$ssc_knowledge  = SSC_Setup::knowledge_ready();
$ssc_entries    = count( (array) SSC_Settings::get( 'knowledge_items', array() ) );
$ssc_documents  = count( SSC_Schema::kb_documents() );

/* Where each readiness item is fixed: icon, colour and the screen. */
$ssc_where = array(
	'identity'      => array( 'building', 'blue', admin_url( 'admin.php?page=ssc-knowledge#identity' ) ),
	'knowledge'     => array( 'book', 'violet', admin_url( 'admin.php?page=ssc-knowledge#facts' ) ),
	'connection'    => array( 'zap', 'cyan', admin_url( 'admin.php?page=ssc-connection#engine' ) ),
	'identity_test' => array( 'shield', 'green', SSC_Setup::wizard_url() ),
	'appearance'    => array( 'sliders', 'rose', admin_url( 'admin.php?page=ssc-appearance' ) ),
	'privacy'       => array( 'lock', 'amber', SSC_Setup::wizard_url() ),
);

$ssc_next = array();
foreach ( $readiness as $ssc_item ) {
	if ( ! $ssc_item['done'] && count( $ssc_next ) < 3 ) {
		$ssc_next[] = $ssc_item;
	}
}

/* Notices: what needs attention, each with a colour and an optional action. */
$ssc_notes     = array();
$notify_failed = SSC_Notification_Queue::failed_count();
if ( $notify_failed > 0 ) {
	/* translators: %d: number of notifications. */
	$ssc_notes[] = array( 'red', sprintf( __( '%d notifications exhausted automatic retries. Check delivery settings, then retry them.', 'nexachat-ai' ), $notify_failed ), SSC_Modules::is_active( 'notifications' ) ? 'retry' : '' );
}
if ( $live && ! $conn_ok && ! $provider ) {
	$ssc_notes[] = array( 'amber', __( 'The assistant is published without an AI engine — it can only answer from your FAQ bank.', 'nexachat-ai' ), '' );
}
if ( $live && 'no' === SSC_Settings::get( 'privacy_acknowledged', 'no' ) ) {
	$ssc_notes[] = array( 'amber', __( 'External data transfer disclosures have not been acknowledged.', 'nexachat-ai' ), '' );
}
if ( $notify_pending > 0 ) {
	$ssc_last_fail = SSC_Module_Notifications::last_failure();
	$ssc_notes[] = array( 'amber', sprintf( __( '%d notification deliveries are pending retry (last error: %s).', 'nexachat-ai' ), $notify_pending, $ssc_last_fail ? $ssc_last_fail['error'] : '' ), '' );
}
$ssc_ai_failures = SSC_Chat_Engine::recent_failures();
if ( $ssc_ai_failures ) {
	/* translators: 1: number of failed answers, 2: the reason in plain words. */
	$ssc_notes[] = array( 'red', sprintf( _n( 'The AI engine failed to answer %1$d time in the last day: %2$s', 'The AI engine failed to answer %1$d times in the last day: %2$s', (int) $ssc_ai_failures['count'], 'nexachat-ai' ), (int) $ssc_ai_failures['count'], '' !== $ssc_ai_failures['code'] ? SSC_HTTP::friendly_error( $ssc_ai_failures['code'] ) : $ssc_ai_failures['message'] ), '' );
}
if ( $unanswered_count > 0 ) {
	/* translators: %d: number of questions. */
	$ssc_notes[] = array( 'blue', sprintf( _n( '%d question went unanswered in the last 14 days — consider adding it to your FAQ bank.', '%d questions went unanswered in the last 14 days — consider adding them to your FAQ bank.', $unanswered_count, 'nexachat-ai' ), $unanswered_count ), SSC_Modules::is_active( 'faq' ) ? admin_url( 'admin.php?page=ssc-faq' ) : '' );
}

$ssc_active_modules = count(
	array_filter(
		array_column( $modules, 'status' ),
		function ( $status ) {
			return 'active' === $status;
		}
	)
);
?>
<div class="ssc-page ssc-dashboard">
	<header class="ssc-page__head">
		<div>
			<h1><?php echo esc_html( defined( 'NEXACHATAI_NAME' ) ? NEXACHATAI_NAME : 'NexaChatAI' ); ?></h1>
			<p class="ssc-page__sub"><?php echo esc_html( $business['org_name'] ? $business['org_name'] : get_bloginfo( 'name' ) ); ?></p>
		</div>
		<div class="ssc-head-actions">
			<a class="ssc-btn ssc-btn--ghost" href="<?php echo esc_url( SSC_Setup::wizard_url() ); ?>"><?php esc_html_e( 'Setup wizard', 'nexachat-ai' ); ?></a>
			<?php if ( $live ) : ?>
				<a class="ssc-btn ssc-btn--ghost ssc-btn--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_dash_action', 'unpublish' ), 'ssc_dash' ) ); ?>"><?php esc_html_e( 'Take offline', 'nexachat-ai' ); ?></a>
			<?php elseif ( $complete ) : ?>
				<a class="ssc-btn ssc-btn--primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ssc_dash_action', 'publish' ), 'ssc_dash' ) ); ?>"><?php esc_html_e( 'Publish chatbot', 'nexachat-ai' ); ?></a>
			<?php endif; ?>
		</div>
	</header>

	<section class="ssc-card ssc-hhero ssc-hhero--compact ssc-dhero<?php echo $live ? ' is-live' : ''; ?>">
		<div class="ssc-hhero__ring">
			<?php echo SSC_Icons::ring( $ssc_percent, 'ssc-ring--lg' . ( $ssc_percent >= 100 ? ' is-ok' : '' ), __( 'How much of the setup is done', 'nexachat-ai' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
		</div>
		<div class="ssc-hhero__main">
			<p class="ssc-hhero__level"><span class="<?php echo $live ? 'is-live' : ''; ?>"><?php echo $live ? esc_html__( 'Live on your site', 'nexachat-ai' ) : esc_html__( 'Not published', 'nexachat-ai' ); ?></span></p>
			<h2>
				<?php
				if ( ! $complete ) {
					esc_html_e( 'Setup is not finished', 'nexachat-ai' );
				} elseif ( ! $live ) {
					esc_html_e( 'Everything is ready: publish when you like', 'nexachat-ai' );
				} else {
					esc_html_e( 'Your assistant is answering visitors', 'nexachat-ai' );
				}
				?>
			</h2>
			<p class="ssc-hhero__sub">
				<?php
				if ( ! $complete ) {
					esc_html_e( 'The assistant is invisible to visitors until the wizard is complete and you publish it. Pick up where you left off.', 'nexachat-ai' );
				} elseif ( ! $live ) {
					esc_html_e( 'Visitors will not see the chat until you publish it. You can take it offline again at any time.', 'nexachat-ai' );
				} else {
					/* translators: 1: steps done, 2: steps in total. */
					echo esc_html( sprintf( __( '%1$d of %2$d readiness checks pass. Keep the business knowledge fresh for the best answers.', 'nexachat-ai' ), $ssc_done_count, $ssc_total ) );
				}
				?>
			</p>
			<?php if ( ! $complete ) : ?>
				<p class="ssc-dhero__cta"><a class="ssc-btn ssc-btn--primary" href="<?php echo esc_url( SSC_Setup::wizard_url() ); ?>"><?php esc_html_e( 'Resume setup', 'nexachat-ai' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php if ( $ssc_next ) : ?>
			<div class="ssc-hhero__steps">
				<h3><?php esc_html_e( 'Next', 'nexachat-ai' ); ?></h3>
				<ul>
					<?php foreach ( $ssc_next as $ssc_item ) : ?>
						<?php $ssc_go = isset( $ssc_where[ $ssc_item['id'] ] ) ? $ssc_where[ $ssc_item['id'] ] : array( 'check', 'slate', SSC_Setup::wizard_url() ); ?>
						<li><a href="<?php echo esc_url( $ssc_go[2] ); ?>"><span class="ssc-hhero__stepico"><?php echo SSC_Icons::get( $ssc_go[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span><span><?php echo esc_html( $ssc_item['todo'] ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</section>

	<div class="ssc-dstatus-row">
		<div class="ssc-dstatus" data-ssc-hue="<?php echo $live ? 'green' : 'slate'; ?>">
			<span class="ssc-dstatus__ico"><?php echo SSC_Icons::get( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
			<div>
				<strong><?php $live ? esc_html_e( 'Live on your site', 'nexachat-ai' ) : esc_html_e( 'Not published', 'nexachat-ai' ); ?></strong>
				<span><?php $live ? esc_html_e( 'Visitors can chat with the assistant.', 'nexachat-ai' ) : esc_html_e( 'Invisible to visitors.', 'nexachat-ai' ); ?></span>
			</div>
			<span class="ssc-dstatus__dot<?php echo $live ? ' is-live' : ''; ?>" aria-hidden="true"></span>
		</div>
		<div class="ssc-dstatus" data-ssc-hue="<?php echo $conn_ok ? 'blue' : 'amber'; ?>">
			<span class="ssc-dstatus__ico"><?php echo SSC_Icons::get( 'cpu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
			<div>
				<strong><?php echo esc_html( $provider ? $provider->label() : __( 'No AI engine', 'nexachat-ai' ) ); ?></strong>
				<span>
					<?php
					if ( $conn_ok ) {
						esc_html_e( 'Connection verified', 'nexachat-ai' );
					} elseif ( ! empty( $conn_info['attempt'] ) && empty( $conn_info['attempt']['ok'] ) ) {
						echo esc_html( sprintf( /* translators: %s: time span such as "3 hours". */ __( 'The last test failed %s ago', 'nexachat-ai' ), human_time_diff( (int) $conn_info['attempt']['at'] ) ) );
					} else {
						esc_html_e( 'Not verified', 'nexachat-ai' );
					}
					?>
				</span>
			</div>
			<a class="ssc-dstatus__link" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-connection' ) ); ?>"><?php esc_html_e( 'Manage', 'nexachat-ai' ); ?></a>
		</div>
		<div class="ssc-dstatus" data-ssc-hue="<?php echo $ssc_knowledge ? 'violet' : 'amber'; ?>">
			<span class="ssc-dstatus__ico"><?php echo SSC_Icons::get( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
			<div>
				<strong><?php esc_html_e( 'Business knowledge', 'nexachat-ai' ); ?></strong>
				<span><?php echo esc_html( sprintf( __( '%d entries · %d documents', 'nexachat-ai' ), $ssc_entries, $ssc_documents ) ); ?></span>
			</div>
			<a class="ssc-dstatus__link" href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-knowledge' ) ); ?>"><?php esc_html_e( 'Manage', 'nexachat-ai' ); ?></a>
		</div>
	</div>

	<?php if ( $ssc_notes ) : ?>
		<section class="ssc-dnotes" aria-label="<?php esc_attr_e( 'Important notices', 'nexachat-ai' ); ?>">
			<?php foreach ( $ssc_notes as $ssc_note ) : ?>
				<div class="ssc-dnote" data-ssc-hue="<?php echo esc_attr( $ssc_note[0] ); ?>">
					<span class="ssc-dnote__ico"><?php echo SSC_Icons::get( 'blue' === $ssc_note[0] ? 'info' : 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<p><?php echo esc_html( $ssc_note[1] ); ?></p>
					<?php if ( 'retry' === $ssc_note[2] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="ssc_retry_notifications" />
							<?php wp_nonce_field( 'ssc_retry_notifications' ); ?>
							<button class="ssc-btn" type="submit"><?php esc_html_e( 'Retry failed notifications', 'nexachat-ai' ); ?></button>
						</form>
					<?php elseif ( '' !== $ssc_note[2] ) : ?>
						<a class="ssc-btn" href="<?php echo esc_url( $ssc_note[2] ); ?>"><?php esc_html_e( 'Open FAQ bank', 'nexachat-ai' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<section class="ssc-hstats">
		<?php
		SSC_Admin_UI::stat( 'box', 'violet', number_format_i18n( count( (array) SSC_Settings::get( 'products', array() ) ) ), __( 'Products & services', 'nexachat-ai' ), admin_url( 'admin.php?page=ssc-knowledge#products' ) );
		if ( isset( $modules['leads'] ) && 'active' === $modules['leads']['status'] ) {
			SSC_Admin_UI::stat( 'mail', 'amber', number_format_i18n( $requests ), __( 'Requests', 'nexachat-ai' ), admin_url( 'admin.php?page=ssc-requests' ) );
		}
		if ( isset( $modules['analytics'] ) && 'active' === $modules['analytics']['status'] ) {
			SSC_Admin_UI::stat( 'chat', 'cyan', number_format_i18n( $chats_14 ), __( 'Conversations (all time)', 'nexachat-ai' ), admin_url( 'admin.php?page=ssc-analytics' ) );
		}
		SSC_Admin_UI::stat( 'puzzle', 'teal', number_format_i18n( $ssc_active_modules ), __( 'Active modules', 'nexachat-ai' ), admin_url( 'admin.php?page=ssc-modules' ) );
		?>
	</section>

	<section class="ssc-card ssc-dready">
		<header class="ssc-dready__head">
			<h2><?php esc_html_e( 'Readiness', 'nexachat-ai' ); ?></h2>
			<span class="ssc-hbadge"><?php echo esc_html( sprintf( /* translators: 1: steps done, 2: steps in total. */ __( '%1$d of %2$d', 'nexachat-ai' ), $ssc_done_count, $ssc_total ) ); ?></span>
		</header>
		<ul class="ssc-dready__list">
			<?php foreach ( $readiness as $ssc_item ) : ?>
				<?php $ssc_go = isset( $ssc_where[ $ssc_item['id'] ] ) ? $ssc_where[ $ssc_item['id'] ] : array( 'check', 'slate', SSC_Setup::wizard_url() ); ?>
				<li class="<?php echo $ssc_item['done'] ? 'is-done' : ''; ?>" data-ssc-hue="<?php echo esc_attr( $ssc_go[1] ); ?>">
					<span class="ssc-dready__ico"><?php echo SSC_Icons::get( $ssc_go[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<span class="ssc-dready__label"><?php echo esc_html( $ssc_item['done'] ? $ssc_item['label'] : $ssc_item['todo'] ); ?></span>
					<?php if ( $ssc_item['done'] ) : ?>
						<span class="ssc-dready__state"><?php echo SSC_Icons::get( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?><span class="screen-reader-text"><?php esc_html_e( 'Done', 'nexachat-ai' ); ?></span></span>
					<?php else : ?>
						<a class="ssc-dready__go" href="<?php echo esc_url( $ssc_go[2] ); ?>"><?php esc_html_e( 'Finish', 'nexachat-ai' ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>
