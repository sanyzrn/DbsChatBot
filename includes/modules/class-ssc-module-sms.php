<?php
/**
 * SMS module: one sender for Iranian SMS panels (Kavenegar, Melipayamak,
 * IPPanel/Faraz SMS, SMS.ir), used by the WooCommerce cart reminders and to
 * text the site owner when a new request arrives.
 *
 * Every provider is called over HTTPS with credentials from the encrypted
 * vault; failures are reported as WP_Error with the provider's own message.
 *
 * @package SmartSupportChatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SSC_Module_Sms
 */
class SSC_Module_Sms extends SSC_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'sms';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'SMS', 'nexachat-ai' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Connect your SMS panel (Kavenegar, Melipayamak, IPPanel/Faraz SMS or SMS.ir) to text yourself about new requests and to send cart reminders.', 'nexachat-ai' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Never miss a lead, and bring back customers who left their cart.', 'nexachat-ai' );
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
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M9 7h6M9 11h6M9 15h3"/></svg>';
	}

	/**
	 * Needs credentials.
	 *
	 * @return bool
	 */
	public function needs_config() {
		return true;
	}

	/**
	 * Configured = credentials and a sender line.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return self::ready();
	}

	/**
	 * Runtime hooks.
	 */
	public function register() {
		add_action( 'ssc_submission_created', array( __CLASS__, 'notify_admin' ), 10, 2 );
	}

	/**
	 * Provider names.
	 *
	 * @return array
	 */
	public static function providers() {
		return array(
			'kavenegar'   => __( 'Kavenegar', 'nexachat-ai' ),
			'melipayamak' => __( 'Melipayamak', 'nexachat-ai' ),
			'ippanel'     => __( 'IPPanel / Faraz SMS', 'nexachat-ai' ),
			'smsir'       => __( 'SMS.ir', 'nexachat-ai' ),
		);
	}

	/**
	 * Credentials and sender present?
	 *
	 * @return bool
	 */
	public static function ready() {
		$provider = (string) SSC_Settings::get( 'sms_provider', 'kavenegar' );
		if ( ! SSC_Settings::has_secret( 'sms_api_key' ) ) {
			return false;
		}
		// Kavenegar can use the panel's default line; the others need a sender.
		if ( 'kavenegar' !== $provider && '' === (string) SSC_Settings::get( 'sms_sender', '' ) ) {
			return false;
		}
		return 'melipayamak' !== $provider || '' !== (string) SSC_Settings::get( 'sms_username', '' );
	}

	/**
	 * Iranian mobile numbers in the 09xxxxxxxxx form (others kept as given).
	 *
	 * @param string $phone Phone.
	 * @return string '' when not a usable number.
	 */
	public static function normalize( $phone ) {
		$digits = preg_replace( '/\D/', '', SSC_Input::phone( (string) $phone ) );
		if ( preg_match( '/^(?:0098|98|0)?(9\d{9})$/', $digits, $m ) ) {
			return '0' . $m[1];
		}
		return strlen( $digits ) >= 8 && strlen( $digits ) <= 15 ? $digits : '';
	}

	/**
	 * Send one SMS.
	 *
	 * @param string $to   Recipient mobile.
	 * @param string $text Message.
	 * @return true|WP_Error
	 */
	public static function send( $to, $text ) {
		if ( ! SSC_Modules::is_active( 'sms' ) || ! self::ready() ) {
			return new WP_Error( 'ssc_sms_off', __( 'SMS is not configured.', 'nexachat-ai' ) );
		}
		$to   = self::normalize( $to );
		$text = trim( (string) $text );
		if ( '' === $to || '' === $text ) {
			return new WP_Error( 'ssc_sms_input', __( 'A valid mobile number and a message are required.', 'nexachat-ai' ) );
		}
		$provider = (string) SSC_Settings::get( 'sms_provider', 'kavenegar' );
		$key      = SSC_Settings::get_secret( 'sms_api_key' );
		$sender   = (string) SSC_Settings::get( 'sms_sender', '' );
		/**
		 * Short-circuit sending (tests, custom gateways). Return true or WP_Error.
		 *
		 * @param null|true|WP_Error $result   Null to send normally.
		 * @param string             $to       Recipient.
		 * @param string             $text     Message.
		 * @param string             $provider Provider id.
		 */
		$pre = apply_filters( 'ssc_sms_pre_send', null, $to, $text, $provider );
		if ( null !== $pre ) {
			return $pre;
		}

		switch ( $provider ) {
			case 'melipayamak':
				$response = wp_safe_remote_post(
					'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
					array(
						'timeout' => 15,
						'body'    => array(
							'username' => (string) SSC_Settings::get( 'sms_username', '' ),
							'password' => $key,
							'to'       => $to,
							'from'     => $sender,
							'text'     => $text,
							'isflash'  => 'false',
						),
					)
				);
				$data     = self::decode( $response );
				if ( is_wp_error( $data ) ) {
					return $data;
				}
				return ( isset( $data['RetStatus'] ) && 1 === (int) $data['RetStatus'] ) ? true : self::provider_error( isset( $data['StrRetStatus'] ) ? $data['StrRetStatus'] : '' );

			case 'ippanel':
				$response = wp_safe_remote_post(
					'https://api2.ippanel.com/api/v1/sms/send/webservice/single',
					array(
						'timeout' => 15,
						'headers' => array(
							'apikey'       => $key,
							'Content-Type' => 'application/json',
						),
						'body'    => wp_json_encode(
							array(
								'recipient' => array( $to ),
								'sender'    => $sender,
								'message'   => $text,
							)
						),
					)
				);
				$data     = self::decode( $response );
				if ( is_wp_error( $data ) ) {
					return $data;
				}
				return ( isset( $data['status'] ) && 'OK' === strtoupper( (string) $data['status'] ) ) ? true : self::provider_error( isset( $data['error_message'] ) ? $data['error_message'] : ( isset( $data['message'] ) ? $data['message'] : '' ) );

			case 'smsir':
				$response = wp_safe_remote_post(
					'https://api.sms.ir/v1/send/bulk',
					array(
						'timeout' => 15,
						'headers' => array(
							'X-API-KEY'    => $key,
							'Content-Type' => 'application/json',
							'Accept'       => 'application/json',
						),
						'body'    => wp_json_encode(
							array(
								'lineNumber'  => $sender,
								'messageText' => $text,
								'mobiles'     => array( $to ),
							)
						),
					)
				);
				$data     = self::decode( $response );
				if ( is_wp_error( $data ) ) {
					return $data;
				}
				return ( isset( $data['status'] ) && 1 === (int) $data['status'] ) ? true : self::provider_error( isset( $data['message'] ) ? $data['message'] : '' );

			default: // Kavenegar.
				$params = array(
					'receptor' => $to,
					'message'  => $text,
				);
				if ( '' !== $sender ) {
					$params['sender'] = $sender;
				}
				$response = wp_safe_remote_post(
					'https://api.kavenegar.com/v1/' . rawurlencode( $key ) . '/sms/send.json',
					array(
						'timeout' => 15,
						'body'    => $params,
					)
				);
				$data     = self::decode( $response );
				if ( is_wp_error( $data ) ) {
					return $data;
				}
				return ( isset( $data['return']['status'] ) && 200 === (int) $data['return']['status'] ) ? true : self::provider_error( isset( $data['return']['message'] ) ? $data['return']['message'] : '' );
		}
	}

	/**
	 * Decode a provider response.
	 *
	 * @param array|WP_Error $response Response.
	 * @return array|WP_Error
	 */
	protected static function decode( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ssc_sms_http', __( 'The SMS panel could not be reached.', 'nexachat-ai' ) );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : self::provider_error( 'HTTP ' . (int) wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * Error with the provider's explanation.
	 *
	 * @param string $message Provider message.
	 * @return WP_Error
	 */
	protected static function provider_error( $message ) {
		/* translators: %s: message from the SMS provider. */
		return new WP_Error( 'ssc_sms_rejected', sprintf( __( 'The SMS panel refused the message: %s', 'nexachat-ai' ), '' !== trim( (string) $message ) ? $message : '—' ) );
	}

	/**
	 * Text the site owner about a new request (name and type only).
	 *
	 * @param int    $submission_id Submission id.
	 * @param string $type          Submission type.
	 */
	public static function notify_admin( $submission_id, $type ) {
		$phone = (string) SSC_Settings::get( 'sms_admin_phone', '' );
		if ( 'yes' !== SSC_Settings::get( 'sms_notify_admin', 'no' ) || '' === $phone ) {
			return;
		}
		// Health data never goes out by SMS: only the case number.
		$text = 'pharma_adr' === $type
			/* translators: %d: case number. */
			? sprintf( __( 'New side-effect report #%d. Sign in to review it.', 'nexachat-ai' ), (int) $submission_id )
			/* translators: 1: request type, 2: request number. */
			: sprintf( __( 'New %1$s #%2$d on your website.', 'nexachat-ai' ), SSC_Schema::type_label( $type ), (int) $submission_id );
		self::send( $phone, $text );
	}
}
