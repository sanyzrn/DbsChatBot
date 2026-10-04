<?php
/**
 * Leads & consultation module: the consultation request form (name, phone,
 * an optional email and a short description), dynamic fields, validation,
 * consent enforcement, storage, CSV export, and the chat's offer of the form
 * when a visitor clearly asks for a consultation.
 *
 * Server-side submission handling: every field is validated against the
 * CONFIGURED form definition (never trusted from the client), submission
 * types are whitelisted, and the anti-bot honeypot is enforced here.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Leads module.
 */
class SSC_Module_Leads extends SSC_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'leads';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Consultation & Lead Collection', 'nexachat-ai' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Structured request forms inside the chat with custom fields, consent capture and a request inbox.', 'nexachat-ai' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Turn conversations into actionable requests with clean, validated contact data.', 'nexachat-ai' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category() {
		return 'business';
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
	}

	/**
	 * Needs configuration (form fields are optional but the module is useful
	 * out of the box; returns false by design).
	 *
	 * @return bool
	 */
	public function needs_config() {
		return false;
	}

	/**
	 * Runtime hooks: offer the form when a visitor asks for a consultation. It
	 * runs after the safety checks of the pharmaceutical module (priority 10),
	 * so an emergency is never answered with a form offer.
	 */
	public function register() {
		add_filter( 'ssc_pre_reply', array( $this, 'route_consult_intent' ), 20, 4 );
	}

	/**
	 * Does the built-in email field appear in the form?
	 *
	 * @return bool
	 */
	public static function email_enabled() {
		return 'off' !== (string) SSC_Settings::get( 'consult_email', 'optional' );
	}

	/**
	 * The email a visitor left with a request ('' when none).
	 *
	 * @param array $row Submission row.
	 * @return string
	 */
	public static function email_of( $row ) {
		$extra = isset( $row['extra_fields'] ) ? json_decode( (string) $row['extra_fields'], true ) : array();
		return ( is_array( $extra ) && isset( $extra['_email'] ) && is_string( $extra['_email'] ) ) ? $extra['_email'] : '';
	}

	/**
	 * Does the message clearly ask for a consultation or a call back? Questions
	 * about a consultation (its price, whether it is free) and negated sentences
	 * are left to the assistant. Pure logic.
	 *
	 * @param string $message Visitor message.
	 * @return bool
	 */
	public static function wants_consultation( $message ) {
		$text = ' ' . SSC_Knowledge::normalize( $message ) . ' ';
		if ( mb_strlen( $text ) > 320 ) {
			return false; // A long text is a story to answer, not a request to route.
		}
		// Asking what a consultation costs or how it works is a knowledge question.
		if ( preg_match( '/\s(?:هزینه|قیمت|تعرفه|چقدر|چنده|price|prices|cost|costs|fee|fees|how much)\s/u', $text ) ) {
			return false;
		}
		if ( SSC_Knowledge_Polarity::negations( $text ) > 0 ) {
			return false;
		}
		$verbs   = 'می\s?خوام|می\s?خواهم|میخوام|میخواهم|نیاز\s+دارم|لازم\s+دارم|درخواست';
		$persian = array(
			'/\s(?:مشاوره|مشاور|کارشناس)\s(?:.*\s)?(?:' . $verbs . '|بگیرم|رزرو|ثبت|بدید|بدهید|بدین)\s/u',
			'/\s(?:' . $verbs . ')\s(?:.*\s)?(?:مشاوره|مشاور|مشورت)\s/u',
			'/\s(?:با|به)\s+من\s+(?:تماس|زنگ)\s/u',
			'/\s(?:تماس|زنگ)\s+(?:بگیرید|بگیرین|بزنید|بزنین)\s+(?:با|به)\s+من\s/u',
			'/\s(?:با|به)\s+(?:یک\s+)?(?:کارشناس|مشاور)\s+(?:صحبت|حرف|در\s+ارتباط)\s/u',
			'/\s(?:صحبت|ارتباط)\s+با\s+(?:یک\s+)?(?:کارشناس|مشاور)\s/u',
		);
		$english = array(
			'/\s(?:want|need|would\s+like|like|request|book|schedule|get|require)\s+(?:to\s+(?:book|get|have|schedule|request)\s+)?(?:a|an|some|the|free)?\s*(?:free\s+)?(?:consultation|consultations|consult|consultant|advice)\s/u',
			'/\s(?:talk|speak)\s+(?:to|with)\s+(?:a|an|your)\s+(?:advisor|adviser|consultant|expert|specialist)\s/u',
			'/\s(?:call|phone|contact|ring)\s+me\s/u',
			'/\s(?:can|could|will|would)\s+(?:someone|somebody|anyone|you)\s+(?:please\s+)?(?:call|phone|contact|ring)\s/u',
			'/\s(?:call\s?back|callback)\s/u',
		);
		foreach ( array_merge( $persian, $english ) as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Offer the consultation form when the visitor asks for a consultation.
	 * The widget shows a button that opens the form; the assistant is not called.
	 *
	 * @param string|null          $pre     Reply from an earlier responder.
	 * @param string               $message Visitor message.
	 * @param string               $product Product scope.
	 * @param SSC_Chat_Engine|null $engine  Engine (response flags).
	 * @return string|null
	 */
	public function route_consult_intent( $pre, $message, $product, $engine = null ) {
		if ( null !== $pre || ! SSC_Modules::is_active( 'leads' ) || ! self::wants_consultation( (string) $message ) ) {
			return $pre;
		}
		if ( $engine ) {
			$engine->flags['consult_offer'] = true;
		}
		return __( 'Of course. Tap the button below, leave your name and phone number with a short description of what you need, and our team will contact you.', 'nexachat-ai' );
	}

	/**
	 * Admin hooks: requests inbox page.
	 */
	public function register_admin() {
		add_action( 'admin_menu', array( $this, 'menu' ), 40 );
		add_action( 'admin_post_ssc_export_submissions', array( $this, 'export_csv' ) );
	}

	/**
	 * Requests inbox menu entry (only rendered while the module is active).
	 */
	public function menu() {
		if ( ! SSC_Modules::is_active( 'leads' ) ) {
			return;
		}
		add_submenu_page(
			'ssc-dashboard',
			__( 'Requests', 'nexachat-ai' ),
			__( 'Requests', 'nexachat-ai' ),
			'manage_options',
			'ssc-requests',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the requests inbox.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nexachat-ai' ) );
		}
		// Request handling (PRG pattern - refresh never re-posts).
		$this->handle_row_actions();

		$filters = array(
			'type'   => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'page'   => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
		);
		// The inbox shows non-pharma requests when the pharma module owns its cases.
		$filters['exclude_type'] = SSC_Modules::is_active( 'pharma' ) ? 'pharma_adr' : '';
		$result                  = $this->query( $filters );

		require SSC_CHATBOT_DIR . 'includes/admin/views/page-requests.php';
	}

	/**
	 * Row actions (status change / delete) with nonces + redirect preserving
	 * current filters (4.x bug fixed: filters are no longer lost).
	 */
	protected function handle_row_actions() {
		if ( ! isset( $_GET['ssc_req_action'], $_GET['id'], $_GET['_wpnonce'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_GET['ssc_req_action'] ) );
		$id     = (int) $_GET['id'];
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ssc_req_' . $id ) ) {
			return;
		}
		if ( 'delete' === $action ) {
			SSC_Schema::delete_submission( $id );
		} elseif ( 'status' === $action && isset( $_GET['status_to'] ) ) {
			SSC_Schema::update_status( $id, sanitize_key( wp_unslash( $_GET['status_to'] ) ) );
		}
		// PRG: preserve every filter.
		$args = array( 'page' => 'ssc-requests' );
		foreach ( array( 'type', 'status', 's', 'paged' ) as $keep ) {
			// Nonce already verified above; sanitize before the value is read.
			$value = isset( $_GET[ $keep ] ) ? sanitize_text_field( wp_unslash( $_GET[ $keep ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '' !== $value ) {
				$args[ $keep ] = rawurlencode( $value );
			}
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Query the inbox (excludes a type when requested).
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	public function query( $filters ) {
		return SSC_Schema::get_submissions( wp_parse_args( $filters, array( 'per_page' => 20 ) ) );
	}

	/**
	 * Mark: unused; see query().
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public function strip_excluded_type( $types ) {
		return $types;
	}

	/*
	 * --------------------------------------------------------------
	 * Submission handling (called by REST/AJAX transports).
	 * --------------------------------------------------------------
	 */

	/**
	 * Handle a consultation submission.
	 *
	 * @param array  $params Raw input.
	 * @param string $ip     Client ip.
	 * @return array|WP_Error
	 */
	public function handle_submission( $params, $ip = '' ) {
		$params = is_array( $params ) ? $params : array();

		// Honeypot: bots filling the hidden field get a fake success.
		if ( ! empty( $params['ssc_hp'] ) ) {
			return array(
				'ok'   => true,
				'fake' => true,
			);
		}

		$type = isset( $params['type'] ) ? sanitize_key( $params['type'] ) : 'consult';
		if ( 'pharma_adr' === $type ) {
			// ADR reports belong to the pharma module (when active).
			if ( SSC_Modules::is_active( 'pharma' ) ) {
				$pharma = SSC_Modules::get( 'pharma' );
				return $pharma->handle_submission( $params, $ip );
			}
			return new WP_Error( 'ssc_module_off', __( 'ADR reporting is disabled on this site.', 'nexachat-ai' ), array( 'status' => 404 ) );
		}
		if ( 'consult' !== $type ) {
			return new WP_Error( 'ssc_bad_type', __( 'Invalid submission type.', 'nexachat-ai' ), array( 'status' => 400 ) );
		}
		// Module isolation: consultation forms require the leads module.
		if ( ! SSC_Modules::is_active( 'leads' ) ) {
			return new WP_Error( 'ssc_module_off', __( 'Form submission is disabled on this site.', 'nexachat-ai' ), array( 'status' => 404 ) );
		}

		$name  = isset( $params['name'] ) ? SSC_Input::text( $params['name'], 81 ) : '';
		$phone = isset( $params['phone'] ) ? SSC_Input::phone( $params['phone'] ) : '';
		$desc  = isset( $params['description'] ) ? SSC_Input::text( $params['description'], 10001, true ) : '';
		// The email is optional: empty is fine, a wrong one is refused (so it is never stored by mistake).
		$email     = '';
		$bad_email = false;
		if ( self::email_enabled() && isset( $params['email'] ) ) {
			$typed = SSC_Input::text( $params['email'], 191 );
			if ( '' !== $typed ) {
				$email = sanitize_email( $typed );
				if ( '' === $email || ! is_email( $email ) ) {
					$email     = '';
					$bad_email = true;
				}
			}
		}

		// Validation.
		$errors = array();
		if ( $bad_email ) {
			$errors[] = __( 'Please enter a valid email address, or leave it empty.', 'nexachat-ai' );
		}
		if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) {
			$errors[] = __( 'Please enter your name (2-80 characters).', 'nexachat-ai' );
		}
		/**
		 * Filter the phone validation pattern.
		 *
		 * @param string $pattern Regex.
		 */
		$pattern = (string) apply_filters( 'ssc_phone_pattern', '/^\+?\d[\d\s\-]{6,18}\d$/' );
		if ( ! preg_match( $pattern, $phone ) ) {
			$errors[] = __( 'Please enter a valid phone number.', 'nexachat-ai' );
		}
		if ( mb_strlen( $desc ) < 10 || mb_strlen( $desc ) > 1000 ) {
			$errors[] = __( 'Please describe your request (10-1000 characters).', 'nexachat-ai' );
		}

		// Dynamic custom fields validated against the CONFIGURED definitions.
		$extra = $this->validate_extra_fields( isset( $params['extra'] ) ? $params['extra'] : '' );
		if ( is_wp_error( $extra ) ) {
			$errors[] = $extra->get_error_message();
		}

		// Consent enforcement (when enabled, submission is refused without it).
		$consent_meta = '';
		if ( 'yes' === SSC_Settings::get( 'consent_enabled', 'no' ) ) {
			$consent = SSC_Input::consent( $params['consent'] ?? false );
			if ( ! $consent ) {
				$errors[] = __( 'Your consent is required to submit this form.', 'nexachat-ai' );
			} else {
				$consent_meta = wp_json_encode(
					array(
						'_consent'        => 1,
						'_consent_hash'   => hash( 'sha256', SSC_Input::consent_text( false ) ),
						'_consent_policy' => (string) SSC_Settings::get( 'consent_link', '' ),
						'_consent_at'     => current_time( 'mysql' ),
					)
				);
			}
		}

		if ( $errors ) {
			return new WP_Error( 'ssc_validation', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$extra_json = $extra;
		if ( '' !== $consent_meta || '' !== $email ) {
			$decoded = json_decode( $extra, true );
			$decoded = is_array( $decoded ) ? $decoded : array();
			if ( '' !== $consent_meta ) {
				$decoded['_consent'] = json_decode( $consent_meta, true );
			}
			if ( '' !== $email ) {
				$decoded['_email'] = $email;
			}
			$extra_json = wp_json_encode( $decoded );
		}

		$id = SSC_Schema::insert_submission(
			array(
				'type'         => 'consult',
				'name'         => $name,
				'phone'        => $phone,
				'description'  => $desc,
				'extra_fields' => $extra_json,
				'ip'           => $ip,
			)
		);
		if ( ! $id ) {
			return new WP_Error( 'ssc_storage', __( 'The request could not be stored. Please try again.', 'nexachat-ai' ), array( 'status' => 500 ) );
		}

		// Notify (module-gated).
		if ( SSC_Modules::is_active( 'notifications' ) ) {
			$notifications = SSC_Modules::get( 'notifications' );
			$notifications->dispatch_for_submission( $id, 'consult' );
		}
		return array( 'ok' => true );
	}

	/**
	 * Validate dynamic extra fields against configured definitions.
	 *
	 * @param mixed $raw JSON string or array.
	 * @return string|WP_Error JSON payload or error.
	 */
	public function validate_extra_fields( $raw ) {
		$definitions = SSC_Settings::form_fields();
		if ( empty( $definitions ) ) {
			return '';
		}
		$decoded = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		$decoded = is_array( $decoded ) ? $decoded : array();

		$out = array();
		foreach ( $definitions as $field ) {
			$key   = $field['key'];
			$value = isset( $decoded[ $key ] ) ? $decoded[ $key ] : '';
			if ( ! is_scalar( $value ) ) {
				$value = '';
			}
			switch ( $field['type'] ) {
				case 'email':
					$value = sanitize_email( $value );
					break;
				case 'number':
					$value = is_numeric( $value ) ? (float) $value : '';
					break;
				case 'tel':
				case 'text':
				default:
					$value = sanitize_text_field( (string) $value );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( (string) $value );
					break;
				case 'checkbox':
					$value = SSC_Input::consent( $value ) ? 'yes' : '';
					break;
				case 'select':
				case 'radio':
					$value = in_array( (string) $value, array_map( 'strval', $field['options'] ), true ) ? (string) $value : '';
					break;
			}
			if ( $field['required'] && '' === (string) $value ) {
				/* translators: %s: field label. */
				return new WP_Error( 'ssc_field', sprintf( __( 'The field "%s" is required.', 'nexachat-ai' ), $field['label'] ), array( 'status' => 400 ) );
			}
			if ( 'email' === $field['type'] && '' !== (string) $value && ! is_email( $value ) ) {
				/* translators: %s: field label. */
				return new WP_Error( 'ssc_field', sprintf( __( 'The field "%s" must be a valid email.', 'nexachat-ai' ), $field['label'] ), array( 'status' => 400 ) );
			}
			if ( '' !== (string) $value ) {
				$out[ $key ] = $value;
			}
		}
		return wp_json_encode( $out );
	}

	/*
	 * --------------------------------------------------------------
	 * Export.
	 * --------------------------------------------------------------
	 */

	/**
	 * CSV export (admin_post).
	 */
	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nexachat-ai' ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ssc_export' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'nexachat-ai' ) );
		}

		$type   = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ssc-requests-' . gmdate( 'Ymd-Hi' ) . '.csv' );

		// Custom form fields become their own columns (they were silently dropped).
		$fields = SSC_Settings::form_fields();
		$header = array( 'id', 'type', 'name', 'phone', 'email', 'product', 'description', 'status', 'created_at' );
		foreach ( $fields as $field ) {
			$header[] = $field['label'];
		}
		$header[] = 'consent_at';

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel.
		SSC_Input::write_csv( $out, $header );

		$page = 1;
		do {
			$result = SSC_Schema::get_submissions(
				array(
					'type'         => $type,
					// ADR case records (health data) belong to the capability-gated
					// pharma workspace and must never ride along in this export.
					'exclude_type' => 'pharma_adr',
					'status'       => $status,
					'per_page'     => 500,
					'page'         => $page,
				)
			);
			foreach ( $result['items'] as $row ) {
				$extra = json_decode( (string) $row['extra_fields'], true );
				$extra = is_array( $extra ) ? $extra : array();
				$cells = array( $row['id'], $row['type'], $row['name'], $row['phone'], self::email_of( $row ), $row['product'], $row['description'], $row['status'], $row['created_at'] );
				foreach ( $fields as $field ) {
					$cells[] = isset( $extra[ $field['key'] ] ) && is_scalar( $extra[ $field['key'] ] ) ? (string) $extra[ $field['key'] ] : '';
				}
				$cells[] = isset( $extra['_consent']['_consent_at'] ) ? (string) $extra['_consent']['_consent_at'] : '';
				// write_csv() already runs every cell through csv_cell().
				SSC_Input::write_csv( $out, $cells );
			}
			++$page;
		} while ( $page <= $result['total_pages'] );
		fclose( $out );
		exit;
	}
}
