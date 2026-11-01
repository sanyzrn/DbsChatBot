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
 * @package Vazho
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
		return __( 'Consultation & Lead Collection', 'vazho' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Structured request forms inside the chat with custom fields, consent capture and a request inbox.', 'vazho' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Turn conversations into actionable requests with clean, validated contact data.', 'vazho' );
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
	 * Does the message clearly ask, in the end, for a consultation or a call
	 * back? Questions about a consultation (its price, whether it is free) are
	 * left to the assistant. The message is read clause by clause and the last
	 * stand wins: "I want a consultation, actually I do not want one" is a
	 * refusal; a wish that depends on a condition ("call me if I need help
	 * later") is not a request now; a negation about something else ("no
	 * particular problem, but I want a consultation") does not cancel the
	 * request. Pure logic.
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
		$stand     = false;
		$condition = false;
		foreach ( self::request_clauses( (string) $message ) as $clause ) {
			$kind = self::clause_stance( ' ' . SSC_Knowledge::normalize( $clause ) . ' ' );
			if ( 'if' === $kind ) {
				// A condition holds for what is asked next, too ("If I need help, call me").
				$condition = true;
			} elseif ( 'refuse' === $kind ) {
				$stand     = false;
				$condition = false;
			} elseif ( 'ask' === $kind ) {
				$stand     = $condition ? $stand : true;
				$condition = false;
			}
		}
		return $stand;
	}

	/**
	 * The parts of a message that can each carry their own wish. A negation
	 * ("no particular problem", "I do not want a call") belongs to its own
	 * part: "مشکل خاصی ندارم ولی می‌خواهم مشاوره بگیرم" still asks for one.
	 * A part that holds a negation is split once more at "and", so
	 * "I have no problem and I want advice" is read as two statements, while
	 * "I do not want a consultation and no call" stays refused.
	 *
	 * @param string $message Visitor message as written.
	 * @return string[]
	 */
	protected static function request_clauses( $message ) {
		$parts   = preg_split( '/[.!?؟،,;؛:\n]+|\s(?:ولی|اما|لیکن|ولیکن|ولي|but|however|though|although|yet|except)\s/iu', ' ' . $message . ' ' );
		$clauses = array();
		foreach ( (array) $parts as $part ) {
			if ( '' === trim( (string) $part ) ) {
				continue;
			}
			if ( SSC_Knowledge_Polarity::negations( ' ' . SSC_Knowledge::normalize( $part ) . ' ' ) > 0 ) {
				foreach ( (array) preg_split( '/\s(?:و|and|also|plus)\s/iu', (string) $part ) as $piece ) {
					$clauses[] = (string) $piece;
				}
				continue;
			}
			$clauses[] = (string) $part;
		}
		return $clauses;
	}

	/**
	 * What one clause says about a consultation (PURE).
	 *
	 * @param string $text Normalized, space-padded clause.
	 * @return string 'refuse' (says no, takes it back), 'if' (a wish that waits for a condition), 'ask' (asks now) or '' (nothing about it).
	 */
	protected static function clause_stance( $text ) {
		if ( self::refuses( $text ) ) {
			return 'refuse';
		}
		if ( SSC_Knowledge_Polarity::negations( $text ) > 0 ) {
			return '';
		}
		if ( preg_match( '/\s(?:(?:if|when|once|whenever)\s+(?:i|we)\s+(?:will\s+|do\s+|ever\s+|might\s+|should\s+)?(?:need|want|require|decide|have\s+to)|if\s+(?:needed|necessary)|maybe|perhaps|might|someday|some\s+day|in\s+the\s+future|اگر\s+(?:نیاز|لازم|احتیاج|خواستم|بخوام|بخواهم)\S*|در\s+صورت\s+(?:نیاز|لزوم)|هر\s+وقت\s+(?:نیاز|خواستم)|وقتی\s+(?:نیاز|خواستم)|شاید|در\s+آینده)\s/u', $text ) ) {
			return 'if';
		}
		return self::intent_match( $text ) ? 'ask' : '';
	}

	/**
	 * Does a clause say no to a consultation or a call, or take the request back?
	 * "I do not want a consultation", "مشاوره نمی‌خوام", "don't call me", "never
	 * mind", "ولش کن", or a bare "فعلاً نمی‌خواهم". Not a negation about
	 * something else ("مشکل خاصی ندارم").
	 *
	 * @param string $text Normalized, space-padded clause.
	 * @return bool
	 */
	protected static function refuses( $text ) {
		$act  = '(?:مشاوره|مشاور|مشورت|تماس|زنگ|کارشناس|consult\w*|advice|advis\w+|call\w*|phone|contact|expert|specialist)';
		$want = '(?:نمی\s?(?:خوام|خواهم|خواد|خواهد|خوایم|خواهیم)|نخواستم|(?:do\s+not|don\s?t|dont|no\s+longer)\s+(?:want|need|require)|no\s+thanks|actually\s+no)';
		$deny = '(?:' . $want . '|ندارم|نداریم|لازم\s+نیست|نیازی\s+نیست|no\s+need)';
		if ( preg_match( '/\s(?:ولش\s+کن|ول\s+کن|بی\s?خیال|فراموش\s+کن|منصرف|never\s?mind|forget\s+(?:it|that)|scrap\s+that|changed\s+my\s+mind)\s/u', $text ) ) {
			return true;
		}
		if ( preg_match( '/\s(?:(?:do\s+not|don\s?t|dont|never)\s+(?:call|phone|contact|ring)|(?:تماس|زنگ)\s+(?:نگیر|نزن)\S*)/u', $text ) ) {
			return true;
		}
		if ( preg_match( '/\s' . $act . '\s(?:\S+\s+){0,2}?' . $deny . '\s|\s' . $want . '\s(?:\S+\s+){0,3}?' . $act . '\s/u', $text ) ) {
			return true;
		}
		// Nothing left but the refusal itself ("فعلاً نمی‌خواهم", "no thanks").
		if ( preg_match( '/\s' . $want . '\s/u', $text ) ) {
			$rest = preg_replace( '/\s' . $want . '\s/u', ' ', $text );
			$rest = preg_replace( '/\s(?:فعلا|الان|دیگه|هم|اصلا|actually|really|now|anymore|thanks|thank|you|please|i|we|so|نه|ممنون|مرسی)(?=\s)/u', ' ', (string) $rest );
			return count( preg_split( '/\s+/u', trim( (string) $rest ), -1, PREG_SPLIT_NO_EMPTY ) ) <= 1;
		}
		return false;
	}

	/**
	 * Does an unnegated clause ask for a consultation or a call back? (PURE)
	 *
	 * @param string $text Normalized, space-padded clause.
	 * @return bool
	 */
	protected static function intent_match( $text ) {
		$verbs   = 'می\s?خوام|می\s?خواهم|میخوام|میخواهم|نیاز\s+دارم|لازم\s+دارم|درخواست';
		$persian = array(
			'/\s(?:مشاوره|مشاور|کارشناس)\s(?:.*\s)?(?:' . $verbs . '|بگیرم|رزرو|ثبت|بدید|بدهید|بدین)\s/u',
			'/\s(?:' . $verbs . ')\s(?:.*\s)?(?:مشاوره|مشاور|مشورت)\s/u',
			'/\s(?:نیاز|احتیاج)\s+به\s+(?:یک\s+)?(?:مشاوره|مشاور|مشورت)\s/u',
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
		return __( 'Of course. Tap the button below, leave your name and phone number with a short description of what you need, and our team will contact you.', 'vazho' );
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
			__( 'Requests', 'vazho' ),
			__( 'Requests', 'vazho' ),
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
			wp_die( esc_html__( 'Insufficient permissions.', 'vazho' ) );
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
			return new WP_Error( 'ssc_module_off', __( 'ADR reporting is disabled on this site.', 'vazho' ), array( 'status' => 404 ) );
		}
		if ( 'consult' !== $type ) {
			return new WP_Error( 'ssc_bad_type', __( 'Invalid submission type.', 'vazho' ), array( 'status' => 400 ) );
		}
		// Module isolation: consultation forms require the leads module.
		if ( ! SSC_Modules::is_active( 'leads' ) ) {
			return new WP_Error( 'ssc_module_off', __( 'Form submission is disabled on this site.', 'vazho' ), array( 'status' => 404 ) );
		}

		$name  = isset( $params['name'] ) ? SSC_Input::text( $params['name'], 81 ) : '';
		$phone = isset( $params['phone'] ) ? SSC_Input::phone( $params['phone'] ) : '';
		$desc  = isset( $params['description'] ) ? SSC_Input::text( $params['description'], 10001, true ) : '';
		// The email is optional: empty is fine, a wrong one is refused (so it is never stored by mistake).
		$email     = '';
		$bad_email = false;
		if ( self::email_enabled() && array_key_exists( 'email', $params ) ) {
			$checked = SSC_Input::strict_email( $params['email'] );
			if ( false === $checked ) {
				$bad_email = true;
			} else {
				$email = $checked;
			}
		}

		// Validation.
		$errors = array();
		if ( $bad_email ) {
			$errors[] = __( 'Please enter a valid email address, or leave it empty.', 'vazho' );
		}
		if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) {
			$errors[] = __( 'Please enter your name (2-80 characters).', 'vazho' );
		}
		/**
		 * Filter the phone validation pattern.
		 *
		 * @param string $pattern Regex.
		 */
		$pattern = (string) apply_filters( 'ssc_phone_pattern', '/^\+?\d[\d\s\-]{6,18}\d$/' );
		if ( ! preg_match( $pattern, $phone ) ) {
			$errors[] = __( 'Please enter a valid phone number.', 'vazho' );
		}
		if ( mb_strlen( $desc ) < 10 || mb_strlen( $desc ) > 1000 ) {
			$errors[] = __( 'Please describe your request (10-1000 characters).', 'vazho' );
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
				$errors[] = __( 'Your consent is required to submit this form.', 'vazho' );
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
			return new WP_Error( 'ssc_storage', __( 'The request could not be stored. Please try again.', 'vazho' ), array( 'status' => 500 ) );
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
				// A list or an object where a single value belongs is refused, never quietly turned into "empty".
				/* translators: %s: field label. */
				return new WP_Error( 'ssc_field', sprintf( __( 'The field "%s" has an invalid value.', 'vazho' ), $field['label'] ), array( 'status' => 400 ) );
			}
			$typed = trim( (string) $value );
			switch ( $field['type'] ) {
				case 'email':
					$checked = SSC_Input::strict_email( $typed );
					if ( false === $checked ) {
						/* translators: %s: field label. */
						return new WP_Error( 'ssc_field', sprintf( __( 'The field "%s" must be a valid email.', 'vazho' ), $field['label'] ), array( 'status' => 400 ) );
					}
					$value = $checked;
					break;
				case 'number':
					$value = '';
					if ( '' !== $typed ) {
						// Persian and Arabic digits are numbers too; anything else is refused, not emptied.
						$digits = SSC_Input::ascii_number( $typed );
						if ( '' === $digits ) {
							/* translators: %s: field label. */
							return new WP_Error( 'ssc_field', sprintf( __( 'The field "%s" must be a number.', 'vazho' ), $field['label'] ), array( 'status' => 400 ) );
						}
						$value = (float) $digits;
					}
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
					$value = (string) $value;
					if ( '' !== $value && ! in_array( $value, array_map( 'strval', $field['options'] ), true ) ) {
						/* translators: %s: field label. */
						return new WP_Error( 'ssc_field', sprintf( __( 'The field "%s" must be one of the choices.', 'vazho' ), $field['label'] ), array( 'status' => 400 ) );
					}
					break;
			}
			if ( $field['required'] && '' === (string) $value ) {
				/* translators: %s: field label. */
				return new WP_Error( 'ssc_field', sprintf( __( 'The field "%s" is required.', 'vazho' ), $field['label'] ), array( 'status' => 400 ) );
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
			wp_die( esc_html__( 'Insufficient permissions.', 'vazho' ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ssc_export' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'vazho' ) );
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

