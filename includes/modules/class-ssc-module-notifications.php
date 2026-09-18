<?php
/**
 * Notifications module: messenger (Bale/Telegram) + email delivery with
 * OBSERVABLE failures and a retry queue (the 4.x fire-and-forget behaviour
 * is replaced by durable delivery jobs and scheduled retries).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifications module.
 */
class SSC_Module_Notifications extends SSC_Module {

	const QUEUE_OPTION = 'ssc_notify_queue';

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'notifications';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Notifications', 'smart-support-chatbot' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Instant alerts for new requests via Bale/Telegram and/or email, with delivery tracking and automatic retries for failed deliveries.', 'smart-support-chatbot' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Never miss a request - failed deliveries are retried and surfaced instead of vanishing.', 'smart-support-chatbot' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category() {
		return 'communication';
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
	}

	/**
	 * Needs configuration (at least one channel must be set up).
	 *
	 * @return bool
	 */
	public function needs_config() {
		return true;
	}

	/**
	 * Configured = messenger or email channel ready.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return $this->messenger_ready() || $this->email_ready() || ( SSC_Modules::is_active( 'pharma' ) && is_email( (string) ( get_option( 'ssc_pharma_setup', array() )['pv_contact'] ?? '' ) ) );
	}

	/**
	 * Messenger channel ready?
	 *
	 * @return bool
	 */
	protected function messenger_ready() {
		return SSC_Settings::has_secret( 'notify_token' ) && '' !== trim( (string) SSC_Settings::get( 'notify_chat_id', '' ) );
	}

	/**
	 * Email channel ready?
	 *
	 * @return bool
	 */
	protected function email_ready() {
		if ( 'yes' !== SSC_Settings::get( 'notify_email_enabled', 'no' ) ) {
			return false;
		}
		$to = (string) SSC_Settings::get( 'notify_email_to', '' );
		return '' !== $to ? is_email( $to ) : true; // admin_email fallback.
	}

	/**
	 * Dispatch notifications for a new submission (enqueue + attempt).
	 *
	 * @param int    $submission_id Submission id.
	 * @param string $type          Submission type.
	 */
	public function dispatch_for_submission( $submission_id, $type ) {
		if ( ! SSC_Modules::is_active( 'notifications' ) ) {
			return;
		}
		$jobs = array();
		if ( $this->messenger_ready() ) {
			$jobs[] = 'messenger';
		}
		if ( $this->email_ready() ) {
			$jobs[] = 'email';
		}
		$pv = get_option( 'ssc_pharma_setup', array() );
		if ( 'pharma_adr' === $type && ! empty( $pv['pv_contact'] ) && is_email( $pv['pv_contact'] ) && ! in_array( 'email', $jobs, true ) ) {
			$jobs[] = 'email';
		}
		foreach ( $jobs as $channel ) {
			$id = SSC_Notification_Queue::enqueue( $channel, (int) $submission_id );
			if ( $id ) { $this->deliver_job( $id ); }
			else { do_action( 'ssc_notification_failed', $channel, $submission_id, 'queue-write-failed' ); }
		}
	}

	/**
	 * One delivery attempt.
	 *
	 * @param string $channel       messenger|email.
	 * @param int    $submission_id Submission id.
	 * @return array ok/error.
	 */
	protected function attempt( $channel, $submission_id ) {
		global $wpdb;
		$table = SSC_Schema::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- single row read.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $submission_id ), ARRAY_A );
		if ( ! $row ) {
			return array( 'ok' => false, 'error' => 'missing-row' );
		}
		$text = $this->build_text( $row );

		if ( 'messenger' === $channel ) {
			$platform = (string) SSC_Settings::get( 'notify_platform', 'bale' );
			$base     = ( 'telegram' === $platform ) ? 'https://api.telegram.org' : 'https://tapi.bale.ai';
			$token    = SSC_Settings::get_secret( 'notify_token' );
			$url      = $base . '/bot' . rawurlencode( $token ) . '/sendMessage';
			$response = SSC_HTTP::post_json(
				$url,
				array(),
				array(
					'chat_id' => (string) SSC_Settings::get( 'notify_chat_id', '' ),
					'text'    => $text,
				),
				array( 'timeout' => 8 )
			);
			if ( ! $response['ok'] || empty( $response['data']['ok'] ) ) {
				return array( 'ok' => false, 'error' => isset( $response['error']['code'] ) ? $response['error']['code'] : 'messenger-rejected' );
			}
			return array( 'ok' => true, 'error' => '' );
		}

		// Email channel.
		$to = (string) SSC_Settings::get( 'notify_email_to', '' );
		$pv = get_option( 'ssc_pharma_setup', array() );
		if ( 'pharma_adr' === $row['type'] && ! empty( $pv['pv_contact'] ) && is_email( $pv['pv_contact'] ) ) { $to = $pv['pv_contact']; }
		if ( '' === $to || ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}
		/* translators: %s: submission type label. */
		$subject = sprintf( __( 'New request: %s', 'smart-support-chatbot' ), SSC_Schema::type_label( $row['type'] ) );
		$sent    = wp_mail( $to, $subject, $text, array( 'Content-Type: text/plain; charset=UTF-8' ) );
		return $sent ? array( 'ok' => true, 'error' => '' ) : array( 'ok' => false, 'error' => 'mail-rejected' );
	}

	/**
	 * Build the notification text.
	 *
	 * @param array $row Submission row.
	 * @return string
	 */
	protected function build_text( $row ) {
		$lines   = array();
		$type    = SSC_Schema::type_label( $row['type'] );
		$is_adr  = ( 'pharma_adr' === $row['type'] );
		$serious = $is_adr && SSC_Module_Pharma::is_serious_row( $row );

		if ( $serious ) {
			$lines[] = '🚨 ' . __( 'SERIOUS adverse reaction report - review immediately', 'smart-support-chatbot' );
		}
		/* translators: %s: type label. */
		$lines[] = sprintf( __( 'New request (%s)', 'smart-support-chatbot' ), $type );
		// Medical narratives and direct identifiers stay in the access-controlled inbox.
		if ( $is_adr ) {
			$lines[] = sprintf( __( 'Case #%d — sign in to review the report.', 'smart-support-chatbot' ), (int) $row['id'] );
			$lines[] = admin_url( 'admin.php?page=ssc-pharma&view=' . (int) $row['id'] );
			return implode( "\n", $lines );
		}
		if ( ! empty( $row['name'] ) ) {
			/* translators: %s: name. */
			$lines[] = sprintf( __( 'Name: %s', 'smart-support-chatbot' ), $row['name'] );
		}
		if ( ! empty( $row['phone'] ) ) {
			/* translators: %s: phone. */
			$lines[] = sprintf( __( 'Phone: %s', 'smart-support-chatbot' ), $row['phone'] );
		}
		if ( ! empty( $row['product'] ) ) {
			/* translators: %s: product. */
			$lines[] = sprintf( __( 'Product: %s', 'smart-support-chatbot' ), $row['product'] );
		}
		if ( $is_adr ) {
			foreach ( array( 'severity', 'outcome', 'batch_number', 'concomitant_drugs', 'reporter_type' ) as $field ) {
				if ( ! empty( $row[ $field ] ) ) {
					$lines[] = $field . ': ' . $row[ $field ];
				}
			}
		}
		if ( ! empty( $row['description'] ) ) {
			$lines[] = '---';
			$lines[] = mb_substr( (string) $row['description'], 0, 500 );
		}
		$lines[] = admin_url( 'admin.php?page=ssc-requests' );
		return implode( "\n", $lines );
	}

	/** Delivery occurs only after the job is durably stored and claimed. */
	protected function deliver_job( $id ) {
		$job = SSC_Notification_Queue::claim( $id );
		if ( ! $job ) { return false; }
		try {
			$result = $this->attempt( $job['channel'], (int) $job['submission_id'] );
		} catch ( Throwable $error ) {
			$result = array( 'ok' => false, 'error' => 'delivery-exception' );
		}
		SSC_Notification_Queue::finish( $job, $result['ok'], $result['error'] );
		if ( ! $result['ok'] ) {
			do_action( 'ssc_notification_failed', $job['channel'], (int) $job['submission_id'], 'delivery-failed' );
		}
		return $result['ok'];
	}

	/** Bounded worker; failed jobs remain visible after five attempts. */
	public static function retry_failed() {
		if ( ! SSC_Modules::is_active( 'notifications' ) ) { return 0; }
		SSC_Notification_Queue::migrate_legacy();
		$module = SSC_Modules::get( 'notifications' );
		if ( ! $module ) { return 0; }
		$healed = 0;
		foreach ( SSC_Notification_Queue::due_ids() as $id ) {
			if ( $module->deliver_job( $id ) ) { ++$healed; }
		}
		return $healed;
	}

	public static function pending_count() {
		return SSC_Notification_Queue::pending_count();
	}

	public static function last_failure() {
		return SSC_Notification_Queue::last_failure();
	}
}
