<?php
/**
 * Pharmaceutical industry extension (Layer C): structured adverse drug
 * reaction (ADR) reporting, case management, audit trail, follow-up
 * workflow, role-based access and dedicated privacy controls.
 *
 * Alignment notes:
 * - Seriousness criteria follow the ICH E2A definitions (death,
 *   life-threatening, hospitalization or prolongation, persistent
 *   disability, congenital anomaly, other medically important events).
 * - Clinical SEVERITY (mild/moderate/severe) is kept strictly separate from
 *   regulatory SERIOUSNESS: a case is SERIOUS when ANY seriousness
 *   criterion is met OR the outcome belongs to the serious outcome set,
 *   regardless of how "mild" the reported severity looks. Potentially
 *   serious cases can never be silently classified as routine.
 * - No regulatory compliance is claimed: implementers must verify their own
 *   jurisdiction's reporting obligations (e.g., national PV center rules).
 * - The AI never invents pharmaceutical facts: when this module is active a
 *   strict medical-answer rule set is injected into every prompt.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pharma module.
 */
class SSC_Module_Pharma extends SSC_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'pharma';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Pharmaceutical Extension', 'smart-support-chatbot' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Structured adverse drug reaction (ADR) intake with ICH E2A-style seriousness criteria, case management, audit trail and follow-up workflow for pharmacovigilance teams.', 'smart-support-chatbot' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Capture complete, reviewable safety reports instead of loose chat messages.', 'smart-support-chatbot' );
	}

	/**
	 * Category (industry extension).
	 *
	 * @return string
	 */
	public function category() {
		return 'industry';
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 20.5 3.5 13.5a4.95 4.95 0 1 1 7-7l.5.5.5-.5a4.95 4.95 0 1 1 7 7l-7 7Z"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>';
	}

	/**
	 * Industry extension marker.
	 *
	 * @return bool
	 */
	public function is_industry() {
		return true;
	}

	/**
	 * Needs its own setup (onboarding flow) after activation.
	 *
	 * @return bool
	 */
	public function needs_config() {
		return true;
	}

	/**
	 * Configured once PV essentials are answered.
	 *
	 * @return bool
	 */
	public function is_configured() {
		$state = get_option( 'ssc_pharma_setup', array() );
		return ! empty( $state['done'] );
	}

	/**
	 * Capability protecting case records (role-based access).
	 *
	 * @return string
	 */
	public static function cap() {
		// Administrators implicitly hold manage_options; PV officers can be
		// granted this dedicated capability by role plugins.
		return apply_filters( 'ssc_pharma_capability', 'ssc_pharma_cases' );
	}

	/**
	 * Can the current user access pharma case records?
	 *
	 * @return bool
	 */
	public static function user_can() {
		return current_user_can( 'manage_options' ) || current_user_can( self::cap() );
	}

	/**
	 * Grant the dedicated capability to administrators at runtime.
	 */
	public function register() {
		add_filter( 'ssc_submission_types', array( $this, 'register_type' ) );
		// AI safety rules whenever the pharma context is active.
		add_filter( 'ssc_pre_reply', array( $this, 'route_adr_intent' ), 10, 4 );
		add_filter( 'ssc_prompt_extra', array( __CLASS__, 'prompt_rules' ) );
	}

	/**
	 * Register the pharma submission type (server-side whitelist).
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public function register_type( $types ) {
		$types['pharma_adr'] = __( 'Adverse drug reaction report', 'smart-support-chatbot' );
		return $types;
	}

	/**
	 * Detect explicit ADR reporting intent in chat and route to the form
	 * instead of letting the AI improvise medical advice.
	 *
	 * @param string|null     $pre     Prefiltered reply.
	 * @param string          $message Message.
	 * @param string          $product Product scope.
	 * @param SSC_Chat_Engine $engine  Chat engine (flag attachment).
	 * @return string|null
	 */
	public function route_adr_intent( $pre, $message, $product, $engine = null ) {
		if ( null !== $pre ) {
			return $pre;
		}
		$normalized = ' ' . SSC_Knowledge::normalize( $message ) . ' ';
		$triggers   = array( ' عارضه ', ' عوارض ', ' side effect ', ' side effects ', ' adr ', ' adverse ', ' واکنش دارویی ' );
		foreach ( $triggers as $t ) {
			if ( false !== stripos( $normalized, $t ) ) {
				if ( $engine ) {
					$engine->flags['adr_offer'] = true;
				}
				return __( 'If you want to report a side effect of a medicine, I can register a structured safety report that our team will review. Would you like to start the report?', 'smart-support-chatbot' );
			}
		}
		return null;
	}

	/**
	 * Prompt rules injected while the module is active (hooked by register()).
	 *
	 * @param string $extra Existing extra instructions.
	 * @return string
	 */
	public static function prompt_rules( $extra ) {
		$rules  = "\nPHARMACOVIGILANCE RULES (non-negotiable):\n";
		$rules .= "- NEVER invent or guess pharmaceutical facts: dosages, indications, contraindications, interactions, or product quality claims.\n";
		$rules .= "- NEVER provide personalized treatment or medication advice. Refer the user to a healthcare professional.\n";
		$rules .= "- When a user describes a possible side effect, do NOT assess its cause. Encourage them to file the structured ADR report and, when the description may be serious, urge immediate medical attention.\n";
		if ( 'general_education' === SSC_Settings::get( 'pharma_answer_mode', 'approved_only' ) ) {
			$rules .= "- General educational health information is allowed. Clearly distinguish it from approved company/product information; do not imply endorsement or invent sources.\n";
			$rules .= "- Product-specific medical claims must come ONLY from the supplied approved references. If missing, say you do not have that information and offer human support.\n";
		} else {
			$rules .= "- Only use approved information supplied in the ORGANIZATION PROFILE and REFERENCE KNOWLEDGE; otherwise say you do not have that information and offer human support. Do not supplement it with general medical knowledge.\n";
		}
		return $extra . $rules;
	}

	/*
	 * --------------------------------------------------------------
	 * ADR form definition.
	 * --------------------------------------------------------------
	 */

	/**
	 * Structured ADR form schema (labels translatable; keys stable).
	 *
	 * @return array
	 */
	public static function form_schema() {
		return array(
			'reporter' => array(
				'label'  => __( 'Reporter', 'smart-support-chatbot' ),
				'fields' => array(
					'reporter_type' => array(
						'label'   => __( 'Reporter role', 'smart-support-chatbot' ),
						'type'    => 'select',
						'options' => array( 'patient', 'physician', 'pharmacist', 'nurse', 'other_health_professional' ),
					),
					'name'          => array(
						'label'    => __( 'Reporter name', 'smart-support-chatbot' ),
						'type'     => 'text',
						'required' => true,
					),
					'phone'         => array(
						'label'    => __( 'Contact phone', 'smart-support-chatbot' ),
						'type'     => 'tel',
						'required' => true,
					),
					'patient_age'   => array(
						'label' => __( 'Patient age (years)', 'smart-support-chatbot' ),
						'type'  => 'number',
					),
					'patient_sex'   => array(
						'label'   => __( 'Patient sex', 'smart-support-chatbot' ),
						'type'    => 'select',
						'options' => array( 'female', 'male', 'other', 'unknown' ),
					),
				),
			),
			'product'  => array(
				'label'  => __( 'Suspected product', 'smart-support-chatbot' ),
				'fields' => array(
					'product'      => array(
						'label'    => __( 'Product name', 'smart-support-chatbot' ),
						'type'     => 'product',
						'required' => true,
					),
					'batch_number' => array(
						'label' => __( 'Batch / lot number', 'smart-support-chatbot' ),
						'type'  => 'text',
					),
					'dose'         => array(
						'label' => __( 'Dose and frequency used', 'smart-support-chatbot' ),
						'type'  => 'text',
					),
					'route'        => array(
						'label'   => __( 'Route of administration', 'smart-support-chatbot' ),
						'type'    => 'select',
						'options' => array( 'oral', 'topical', 'intravenous', 'intramuscular', 'subcutaneous', 'inhalation', 'ophthalmic', 'other', 'unknown' ),
					),
				),
			),
			'reaction' => array(
				'label'  => __( 'Suspected reaction', 'smart-support-chatbot' ),
				'fields' => array(
					'description'       => array(
						'label'    => __( 'What happened? Describe the reaction and dates.', 'smart-support-chatbot' ),
						'type'     => 'textarea',
						'required' => true,
					),
					'severity'          => array(
						'label'   => __( 'Clinical severity (how it felt)', 'smart-support-chatbot' ),
						'type'    => 'select',
						'options' => array( 'mild', 'moderate', 'severe' ),
					),
					'seriousness'       => array(
						'label'   => __( 'Regulatory seriousness criteria (any that apply)', 'smart-support-chatbot' ),
						'type'    => 'checkboxes',
						'options' => array(
							'death'               => __( 'Resulted in death', 'smart-support-chatbot' ),
							'life_threatening'    => __( 'Life-threatening', 'smart-support-chatbot' ),
							'hospitalization'     => __( 'Required or prolonged hospitalization', 'smart-support-chatbot' ),
							'disability'          => __( 'Persistent or significant disability', 'smart-support-chatbot' ),
							'congenital_anomaly'  => __( 'Congenital anomaly or birth defect', 'smart-support-chatbot' ),
							'medically_important' => __( 'Other medically important event', 'smart-support-chatbot' ),
						),
					),
					'outcome'           => array(
						'label'   => __( 'Outcome so far', 'smart-support-chatbot' ),
						'type'    => 'select',
						'options' => array( 'recovered', 'recovering', 'not_recovered', 'sequelae', 'hospitalized', 'death', 'unknown' ),
					),
					'concomitant_drugs' => array(
						'label' => __( 'Other medicines taken at the same time', 'smart-support-chatbot' ),
						'type'  => 'textarea',
					),
				),
			),
		);
	}

	/**
	 * Public (widget-facing) ADR form: labels translated, values validated.
	 *
	 * @return array
	 */
	public static function adr_options_public() {
		$schema = self::form_schema();
		$out    = array();
		foreach ( $schema as $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				$entry = array(
					'key'      => $key,
					'label'    => $field['label'],
					'type'     => $field['type'],
					'required' => ! empty( $field['required'] ),
				);
				if ( isset( $field['options'] ) ) {
					if ( 'checkboxes' === $field['type'] ) {
						$entry['options'] = array();
						foreach ( $field['options'] as $value => $label ) {
							$entry['options'][] = array(
								'value' => $value,
								'label' => $label,
							);
						}
					} else {
						$entry['options'] = array();
						foreach ( $field['options'] as $value ) {
							$entry['options'][] = array(
								'value' => $value,
								'label' => self::option_label( $key, $value ),
							);
						}
					}
				}
				$out[] = $entry;
			}
		}
		return $out;
	}

	/**
	 * Translate stored option values into labels.
	 *
	 * @param string $field Field key.
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function option_label( $field, $value ) {
		$labels = array(
			'reporter_type' => array(
				'patient'                   => __( 'Patient / consumer', 'smart-support-chatbot' ),
				'physician'                 => __( 'Physician', 'smart-support-chatbot' ),
				'pharmacist'                => __( 'Pharmacist', 'smart-support-chatbot' ),
				'nurse'                     => __( 'Nurse', 'smart-support-chatbot' ),
				'other_health_professional' => __( 'Other health professional', 'smart-support-chatbot' ),
			),
			'patient_sex'   => array(
				'female'  => __( 'Female', 'smart-support-chatbot' ),
				'male'    => __( 'Male', 'smart-support-chatbot' ),
				'other'   => __( 'Other', 'smart-support-chatbot' ),
				'unknown' => __( 'Unknown', 'smart-support-chatbot' ),
			),
			'route'         => array(
				'oral'          => __( 'Oral', 'smart-support-chatbot' ),
				'topical'       => __( 'Topical', 'smart-support-chatbot' ),
				'intravenous'   => __( 'Intravenous', 'smart-support-chatbot' ),
				'intramuscular' => __( 'Intramuscular', 'smart-support-chatbot' ),
				'subcutaneous'  => __( 'Subcutaneous', 'smart-support-chatbot' ),
				'inhalation'    => __( 'Inhalation', 'smart-support-chatbot' ),
				'ophthalmic'    => __( 'Ophthalmic', 'smart-support-chatbot' ),
				'other'         => __( 'Other', 'smart-support-chatbot' ),
				'unknown'       => __( 'Unknown', 'smart-support-chatbot' ),
			),
			'severity'      => array(
				'mild'     => __( 'Mild', 'smart-support-chatbot' ),
				'moderate' => __( 'Moderate', 'smart-support-chatbot' ),
				'severe'   => __( 'Severe', 'smart-support-chatbot' ),
			),
			'outcome'       => array(
				'recovered'     => __( 'Recovered', 'smart-support-chatbot' ),
				'recovering'    => __( 'Recovering', 'smart-support-chatbot' ),
				'not_recovered' => __( 'Not recovered', 'smart-support-chatbot' ),
				'sequelae'      => __( 'Sequelae / permanent impairment', 'smart-support-chatbot' ),
				'hospitalized'  => __( 'Hospitalized', 'smart-support-chatbot' ),
				'death'         => __( 'Death', 'smart-support-chatbot' ),
				'unknown'       => __( 'Unknown', 'smart-support-chatbot' ),
			),
		);
		if ( isset( $labels[ $field ] ) && isset( $labels[ $field ][ $value ] ) ) {
			return $labels[ $field ][ $value ];
		}
		return $value;
	}

	/**
	 * SERIOUSNESS evaluation - deliberately independent from severity.
	 *
	 * A case is serious when ANY ICH E2A criterion is flagged OR the outcome
	 * is in the serious outcome set. A "mild" severity never downgrades an
	 * otherwise serious case.
	 *
	 * @param array $row Submission row (or handled payload).
	 * @return bool
	 */
	public static function is_serious_row( $row ) {
		$criteria = self::seriousness_criteria( $row );
		if ( ! empty( $criteria ) ) {
			return true;
		}
		$serious_outcomes = array( 'hospitalized', 'death', 'sequelae' );
		return isset( $row['outcome'] ) && in_array( (string) $row['outcome'], $serious_outcomes, true );
	}

	/**
	 * Extract flagged seriousness criteria from a row/payload.
	 *
	 * @param array $row Row.
	 * @return string[]
	 */
	public static function seriousness_criteria( $row ) {
		$extra   = isset( $row['extra_fields'] ) ? json_decode( (string) $row['extra_fields'], true ) : array();
		$extra   = is_array( $extra ) ? $extra : array();
		$flags   = isset( $extra['seriousness'] ) && is_array( $extra['seriousness'] ) ? $extra['seriousness'] : array();
		$allowed = array( 'death', 'life_threatening', 'hospitalization', 'disability', 'congenital_anomaly', 'medically_important' );
		return array_values( array_intersect( array_map( 'strval', (array) $flags ), $allowed ) );
	}

	/*
	 * --------------------------------------------------------------
	 * Submission handling (called via leads module dispatch).
	 * --------------------------------------------------------------
	 */

	/**
	 * Handle an ADR submission.
	 *
	 * @param array  $params Raw input.
	 * @param string $ip     Client ip.
	 * @return array|WP_Error
	 */
	public function handle_submission( $params, $ip = '' ) {
		$params = is_array( $params ) ? $params : array();
		if ( isset( $params['extra'] ) && ! is_array( $params['extra'] ) ) {
			$params['extra'] = is_string( $params['extra'] ) ? json_decode( $params['extra'], true ) : array();
		}
		if ( ! SSC_Modules::is_active( 'pharma' ) ) {
			return new WP_Error( 'ssc_module_off', __( 'ADR reporting is disabled on this site.', 'smart-support-chatbot' ), array( 'status' => 404 ) );
		}

		if ( ! empty( $params['ssc_hp'] ) ) {
			return array(
				'ok'   => true,
				'fake' => true,
			); // Honeypot.
		}

		$name  = isset( $params['name'] ) ? SSC_Input::text( $params['name'], 81 ) : '';
		$phone = isset( $params['phone'] ) ? SSC_Input::phone( $params['phone'] ) : '';
		$desc  = isset( $params['description'] ) ? SSC_Input::text( $params['description'], 10001, true ) : '';

		$errors = array();
		if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) {
			$errors[] = __( 'Please enter the reporter name (2-80 characters).', 'smart-support-chatbot' );
		}
		$pattern = (string) apply_filters( 'ssc_phone_pattern', '/^\+?\d[\d\s\-]{6,18}\d$/' );
		if ( ! preg_match( $pattern, $phone ) ) {
			$errors[] = __( 'Please enter a valid contact phone.', 'smart-support-chatbot' );
		}
		if ( mb_strlen( $desc ) < 10 || mb_strlen( $desc ) > 10000 ) {
			$errors[] = __( 'Please describe the reaction (10-10000 characters).', 'smart-support-chatbot' );
		}

		// Consent is REQUIRED for ADR reports (sensitive health data), independent
		// of the leads-module consent toggle. Stored with the case for audit.
		$consent_meta = array();
		$consent      = SSC_Input::consent( $params['consent'] ?? false );
		if ( ! $consent ) {
			$errors[] = __( 'Your consent is required to submit a safety report.', 'smart-support-chatbot' );
		} else {
			$consent_meta = array(
				'_consent'        => 1,
				'_consent_scope'  => 'pharma_adr',
				'_consent_hash'   => hash( 'sha256', SSC_Input::consent_text( true ) ),
				'_consent_policy' => (string) SSC_Settings::get( 'consent_link', '' ),
				'_consent_at'     => current_time( 'mysql' ),
			);
		}

		// Structured fields validated against the schema whitelist.
		$extra = array();
		foreach ( self::form_schema() as $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				if ( 'name' === $key || 'phone' === $key || 'description' === $key ) {
					continue; // Top-level columns.
				}
				$raw = isset( $params[ $key ] ) ? $params[ $key ] : ( isset( $params['extra'][ $key ] ) ? $params['extra'][ $key ] : '' );
				switch ( $field['type'] ) {
					case 'checkboxes':
						$raw = array_intersect( SSC_Input::list_value( $raw ), array_keys( $field['options'] ) );
						if ( ! empty( $field['required'] ) && empty( $raw ) ) {
							/* translators: %s: field label. */
							$errors[] = sprintf( __( 'The field "%s" is required.', 'smart-support-chatbot' ), $field['label'] );
						}
						$extra[ $key ] = array_values( array_map( 'sanitize_key', $raw ) );
						break;
					case 'select':
						$raw = sanitize_key( SSC_Input::text( $raw, 100 ) );
						if ( ! in_array( $raw, array_map( 'sanitize_key', $field['options'] ), true ) ) {
							$raw = '';
						}
						if ( ! empty( $field['required'] ) && '' === $raw ) {
							/* translators: %s: field label. */
							$errors[] = sprintf( __( 'The field "%s" is required.', 'smart-support-chatbot' ), $field['label'] );
						}
						if ( '' !== $raw ) {
							$extra[ $key ] = $raw;
						}
						break;
					case 'product':
						// Product must exist in the catalog (product identification).
						$raw   = SSC_Input::text( $raw, 100 );
						$valid = 'general';
						foreach ( (array) SSC_Settings::get( 'products', array() ) as $p ) {
							if ( isset( $p['id'] ) && $p['id'] === $raw ) {
								$valid = $raw;
								break;
							}
						}
						if ( 'general' === $valid ) {
							$errors[] = __( 'Unknown product. Please choose from the list.', 'smart-support-chatbot' );
						}
						$extra['product_id'] = $valid;
						break;
					case 'number':
						if ( '' !== $raw && ( ! is_numeric( $raw ) || (float) $raw < 0 || (float) $raw > 130 ) ) {
							$errors[] = __( 'Patient age must be between 0 and 130 years.', 'smart-support-chatbot' );
						}
						$extra[ $key ] = is_numeric( $raw ) ? (float) $raw : '';
						break;
					case 'textarea':
						$extra[ $key ] = SSC_Input::text( $raw, 4000, true );
						break;
					default:
						$extra[ $key ] = SSC_Input::text( $raw, 100 );
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'ssc_validation', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		// Attach consent receipt to extra_fields.
		if ( ! empty( $consent_meta ) ) {
			$extra = array_merge( $extra, $consent_meta );
		}

		$id = SSC_Schema::insert_submission(
			array(
				'type'              => 'pharma_adr',
				'name'              => $name,
				'phone'             => $phone,
				'product'           => isset( $extra['product_id'] ) ? $extra['product_id'] : '',
				'description'       => $desc,
				'severity'          => isset( $extra['severity'] ) ? $extra['severity'] : '',
				'outcome'           => isset( $extra['outcome'] ) ? $extra['outcome'] : '',
				'batch_number'      => isset( $extra['batch_number'] ) ? $extra['batch_number'] : '',
				'concomitant_drugs' => isset( $extra['concomitant_drugs'] ) ? $extra['concomitant_drugs'] : '',
				'reporter_type'     => isset( $extra['reporter_type'] ) ? $extra['reporter_type'] : '',
				'extra_fields'      => wp_json_encode( $extra ),
				'ip'                => $ip,
			)
		);
		if ( ! $id ) {
			return new WP_Error( 'ssc_storage', __( 'The report could not be stored. Please try again.', 'smart-support-chatbot' ), array( 'status' => 500 ) );
		}

		// Audit trail entry (case creation).
		SSC_Schema::audit( $id, 'created', '', 'new', 'system', __( 'ADR report received', 'smart-support-chatbot' ) );

		// Notifications: immediate for serious cases, normal otherwise.
		if ( SSC_Modules::is_active( 'notifications' ) ) {
			$notifications = SSC_Modules::get( 'notifications' );
			$notifications->dispatch_for_submission( $id, 'pharma_adr' );
		}
		/**
		 * Fired when an ADR report is captured (PV integrations).
		 */
		do_action( 'ssc_adr_reported', $id );

		return array( 'ok' => true );
	}

	/*
	 * --------------------------------------------------------------
	 * Case management admin.
	 * --------------------------------------------------------------
	 */

	/**
	 * Admin hooks.
	 */
	public function register_admin() {
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * ADR cases menu (capability-gated, own onboarding entry).
	 */
	public function menu() {
		if ( ! SSC_Modules::is_active( 'pharma' ) ) {
			return;
		}
		add_submenu_page(
			current_user_can( 'manage_options' ) ? 'ssc-dashboard' : 'tools.php',
			__( 'ADR Cases', 'smart-support-chatbot' ),
			__( 'ADR Cases', 'smart-support-chatbot' ),
			current_user_can( 'manage_options' ) ? 'manage_options' : self::cap(),
			'ssc-pharma',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Admin assets on the pharma page only.
	 *
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'ssc-pharma' ) ) {
			return;
		}
		wp_enqueue_style( 'ssc-admin', SSC_CHATBOT_URL . 'assets/css/admin.css', array(), SSC_CHATBOT_VERSION );
	}

	/**
	 * Case page actions (status workflow, follow-up note, export).
	 */
	public function handle_actions() {
		if ( ! self::user_can() ) {
			return;
		}

		// Onboarding completion.
		if ( current_user_can( 'manage_options' ) && isset( $_POST['ssc_pharma_setup_save'] ) && check_admin_referer( 'ssc_pharma_setup' ) ) {
			$pv_email = isset( $_POST['pv_contact'] ) ? sanitize_email( wp_unslash( $_POST['pv_contact'] ) ) : '';
			if ( ! is_email( $pv_email ) ) {
				wp_die( esc_html__( 'Enter a valid pharmacovigilance contact email.', 'smart-support-chatbot' ) );
			}
			update_option(
				'ssc_pharma_setup',
				array(
					'done'       => 1,
					'pv_contact' => $pv_email,
					'at'         => time(),
				),
				false
			);
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => 'ssc-pharma',
						'configured' => 1,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( isset( $_POST['case_id'] ) ) {
			global $wpdb;
			$case_type = $wpdb->get_var( $wpdb->prepare( 'SELECT type FROM ' . SSC_Schema::table_name() . ' WHERE id = %d', (int) $_POST['case_id'] ) );
			if ( 'pharma_adr' !== $case_type ) {
				return;
			}
		}

		// Case status change (follow-up workflow).
		if ( isset( $_POST['ssc_case_status'], $_POST['case_id'] ) && check_admin_referer( 'ssc_case_' . (int) $_POST['case_id'] ) ) {
			$id     = (int) $_POST['case_id'];
			$status = sanitize_key( wp_unslash( $_POST['ssc_case_status'] ) );
			$note   = isset( $_POST['case_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['case_note'] ) ) : '';
			$map    = self::case_statuses();
			if ( isset( $map[ $status ] ) ) {
				SSC_Schema::update_status( $id, $status, $note );
			}
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'ssc-pharma',
						'updated' => 1,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Per-record privacy actions (export single / anonymize / purge).
		if ( isset( $_POST['ssc_case_privacy'], $_POST['case_id'] ) && check_admin_referer( 'ssc_case_privacy_' . (int) $_POST['case_id'] ) ) {
			$id     = (int) $_POST['case_id'];
			$action = sanitize_key( wp_unslash( $_POST['ssc_case_privacy'] ) );
			$actor  = wp_get_current_user()->user_login ? wp_get_current_user()->user_login : 'admin';

			if ( 'export' === $action ) {
				$this->export_single( $id );
				exit;
			}
			if ( 'anonymize' === $action ) {
				$this->anonymize_case( $id, $actor );
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'    => 'ssc-pharma',
							'view'    => $id,
							'privacy' => 'anon',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
			if ( 'purge' === $action ) {
				$this->purge_case( $id, $actor );
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'   => 'ssc-pharma',
							'purged' => 1,
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
		}

		// Export CSV (dedicated, capability-gated).
		if ( isset( $_POST['ssc_pharma_export'] ) && check_admin_referer( 'ssc_pharma_export' ) ) {
			$this->export_csv();
		}
	}

	/**
	 * Export a single ADR case as JSON (compliance / data-subject request).
	 *
	 * @param int $id Case id.
	 */
	protected function export_single( $id ) {
		global $wpdb;
		$table = SSC_Schema::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- single-case export.
		$case = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND type = 'pharma_adr'", $id ), ARRAY_A );
		if ( ! $case ) {
			wp_die( esc_html__( 'Case not found.', 'smart-support-chatbot' ) );
		}
		$audit   = SSC_Schema::get_audit( $id );
		$payload = array(
			'exported_at' => gmdate( 'c' ),
			'case'        => $case,
			'audit'       => $audit,
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ssc-adr-case-' . $id . '.json' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Anonymize PII on a single case (keeps clinical fields for PV review).
	 *
	 * @param int    $id    Case id.
	 * @param string $actor Actor label for the audit trail.
	 */
	public function anonymize_case( $id, $actor = 'admin' ) {
		global $wpdb;
		$table = SSC_Schema::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- targeted privacy action.
		$case = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND type = 'pharma_adr'", $id ), ARRAY_A );
		if ( ! $case ) {
			return;
		}
		$extra = json_decode( (string) $case['extra_fields'], true );
		$extra = is_array( $extra ) ? $extra : array();
		unset( $extra['patient_age'], $extra['patient_sex'] );
		$extra['_anonymized']    = 1;
		$extra['_anonymized_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- targeted privacy action.
		$wpdb->update(
			$table,
			array(
				'name'         => '[redacted]',
				'phone'        => '',
				'ip'           => '',
				'extra_fields' => wp_json_encode( $extra ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		SSC_Schema::audit( $id, 'anonymized', $case['status'], $case['status'], $actor, __( 'Reporter PII removed (privacy request)', 'smart-support-chatbot' ) );
	}

	/**
	 * Permanently delete a single case. The audit trail is KEPT (append-only)
	 * so compliance reviewers can still see that a purge occurred.
	 *
	 * @param int    $id    Case id.
	 * @param string $actor Actor label for site-level log.
	 */
	public function purge_case( $id, $actor = 'admin' ) {
		SSC_Schema::delete_submission( $id );
		/**
		 * Fired after a single ADR case is purged (compliance hooks).
		 *
		 * @param int    $id    Case id.
		 * @param string $actor Actor.
		 */
		do_action( 'ssc_adr_case_purged', $id, $actor );
	}

	/**
	 * Case statuses (workflow states; audit-tracked).
	 *
	 * @return string[] status => label.
	 */
	public static function case_statuses() {
		return array(
			'new'         => __( 'New (awaiting triage)', 'smart-support-chatbot' ),
			'in_progress' => __( 'Under assessment', 'smart-support-chatbot' ),
			'follow_up'   => __( 'Follow-up requested', 'smart-support-chatbot' ),
			'done'        => __( 'Closed', 'smart-support-chatbot' ),
			'archived'    => __( 'Archived', 'smart-support-chatbot' ),
		);
	}

	/**
	 * Render the ADR cases page.
	 */
	public function render_page() {
		if ( ! self::user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access case records.', 'smart-support-chatbot' ) );
		}
		$setup = get_option( 'ssc_pharma_setup', array() );
		if ( empty( $setup['done'] ) && current_user_can( 'manage_options' ) ) {
			// Dedicated onboarding before any case tooling is exposed.
			require SSC_CHATBOT_DIR . 'includes/admin/views/pharma-setup.php';
			return;
		}

		$view = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0;
		if ( $view > 0 ) {
			global $wpdb;
			$table = SSC_Schema::table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- single case read.
			$case = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND type = 'pharma_adr'", $view ), ARRAY_A );
			if ( $case ) {
				$audit = SSC_Schema::get_audit( $view );
				require SSC_CHATBOT_DIR . 'includes/admin/views/pharma-case.php';
				return;
			}
		}

		$filters = array(
			'type'   => 'pharma_adr',
			'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'page'   => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
		);
		$result  = SSC_Schema::get_submissions( $filters );
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-pharma.php';
	}

	/**
	 * ADR cases CSV export.
	 */
	protected function export_csv() {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ssc-adr-cases-' . gmdate( 'Ymd-Hi' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		SSC_Input::write_csv( $out, array( 'case_id', 'created_at', 'reporter_type', 'reporter_name', 'reporter_phone', 'patient_age', 'patient_sex', 'product', 'batch_number', 'dose', 'route', 'reaction', 'severity', 'seriousness_criteria', 'outcome', 'concomitant_drugs', 'status', 'serious' ) );

		$page = 1;
		do {
			$result = SSC_Schema::get_submissions(
				array(
					'type'     => 'pharma_adr',
					'per_page' => 500,
					'page'     => $page,
				)
			);
			foreach ( $result['items'] as $row ) {
				$extra = json_decode( (string) $row['extra_fields'], true );
				$extra = is_array( $extra ) ? $extra : array();
				SSC_Input::write_csv(
					$out,
					// write_csv() already runs every cell through csv_cell().
					array(
						$row['id'],
						$row['created_at'],
						isset( $extra['reporter_type'] ) ? $extra['reporter_type'] : $row['reporter_type'],
						$row['name'],
						$row['phone'],
						isset( $extra['patient_age'] ) ? $extra['patient_age'] : '',
						isset( $extra['patient_sex'] ) ? $extra['patient_sex'] : '',
						$row['product'],
						$row['batch_number'],
						isset( $extra['dose'] ) ? $extra['dose'] : '',
						isset( $extra['route'] ) ? $extra['route'] : '',
						$row['description'],
						$row['severity'],
						implode( '|', self::seriousness_criteria( $row ) ),
						$row['outcome'],
						$row['concomitant_drugs'],
						$row['status'],
						self::is_serious_row( $row ) ? 'yes' : 'no',
					)
				);
			}
			++$page;
		} while ( $page <= $result['total_pages'] );
		fclose( $out );
		exit;
	}
}
