<?php
/**
 * Industry templates for the setup wizard: a sensible starting point for
 * common businesses (assistant role and tone, welcome text, answer rules,
 * request-form fields) plus the modules that usually help them.
 *
 * Applying a template only fills fields that are still empty: nothing the
 * administrator wrote is overwritten, and modules are recommended, never
 * switched on silently.
 *
 * @package SmartSupportChatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SSC_Templates
 */
class SSC_Templates {

	/**
	 * All templates (translatable).
	 *
	 * @return array id => template.
	 */
	public static function all() {
		$templates = array(
			'store'      => array(
				'label'    => __( 'Online store', 'nexachat-ai' ),
				'icon'     => '🛒',
				'hint'     => __( 'Product questions, prices, shipping and order tracking.', 'nexachat-ai' ),
				'category' => __( 'Online store', 'nexachat-ai' ),
				'industry' => __( 'E-commerce', 'nexachat-ai' ),
				'role'     => __( 'Sales and support assistant', 'nexachat-ai' ),
				'tone'     => 'friendly',
				'welcome'  => __( 'Hi! Looking for something? Ask me about products, prices, shipping or your order.', 'nexachat-ai' ),
				'rules'    => __( 'Help visitors choose the right product and complete their purchase. Mention shipping times and return rules only from the knowledge provided.', 'nexachat-ai' ),
				'fields'   => array(),
				'modules'  => array( 'woocommerce', 'sitesync', 'live', 'leads' ),
			),
			'clinic'     => array(
				'label'    => __( 'Clinic or doctor\'s office', 'nexachat-ai' ),
				'icon'     => '🩺',
				'hint'     => __( 'Services, doctors, hours, insurance and appointment requests.', 'nexachat-ai' ),
				'category' => __( 'Clinic', 'nexachat-ai' ),
				'industry' => __( 'Healthcare', 'nexachat-ai' ),
				'role'     => __( 'Reception assistant', 'nexachat-ai' ),
				'tone'     => 'professional',
				'welcome'  => __( 'Hello, welcome to our clinic. I can help with services, working hours and booking a visit.', 'nexachat-ai' ),
				'rules'    => __( 'Never diagnose or suggest treatment; for symptoms, recommend booking a visit or, in an emergency, calling emergency services. Share services, doctors, hours and insurance information only from the knowledge provided.', 'nexachat-ai' ),
				'fields'   => array(
					array(
						'label'    => __( 'Preferred day and time', 'nexachat-ai' ),
						'type'     => 'text',
						'required' => false,
					),
					array(
						'label'    => __( 'Service needed', 'nexachat-ai' ),
						'type'     => 'text',
						'required' => false,
					),
				),
				'modules'  => array( 'leads', 'live', 'sitesync', 'notifications' ),
			),
			'school'     => array(
				'label'    => __( 'School or training center', 'nexachat-ai' ),
				'icon'     => '🎓',
				'hint'     => __( 'Courses, schedules, fees and registration.', 'nexachat-ai' ),
				'category' => __( 'Education', 'nexachat-ai' ),
				'industry' => __( 'Education', 'nexachat-ai' ),
				'role'     => __( 'Admissions advisor', 'nexachat-ai' ),
				'tone'     => 'friendly',
				'welcome'  => __( 'Hi! Want to know about our courses, schedules or how to register? Just ask.', 'nexachat-ai' ),
				'rules'    => __( 'Help visitors find the right course for their level and goal; ask about their level when it matters. Quote fees, dates and certificates only from the knowledge provided.', 'nexachat-ai' ),
				'fields'   => array(
					array(
						'label'    => __( 'Course of interest', 'nexachat-ai' ),
						'type'     => 'text',
						'required' => false,
					),
				),
				'modules'  => array( 'leads', 'sitesync', 'faq', 'live' ),
			),
			'pharma'     => array(
				'label'    => __( 'Pharmaceutical company', 'nexachat-ai' ),
				'icon'     => '💊',
				'hint'     => __( 'Approved product information and side-effect reporting.', 'nexachat-ai' ),
				'category' => __( 'Pharmaceutical manufacturer', 'nexachat-ai' ),
				'industry' => __( 'Pharmaceutical', 'nexachat-ai' ),
				'role'     => __( 'Product information assistant', 'nexachat-ai' ),
				'tone'     => 'formal',
				'welcome'  => __( 'Hello. I can share approved information about our products and help you report a side effect.', 'nexachat-ai' ),
				'rules'    => __( 'Share only approved product information from the knowledge provided. For personal medical questions, refer the visitor to their doctor or pharmacist.', 'nexachat-ai' ),
				'fields'   => array(),
				'modules'  => array( 'pharma', 'notifications', 'sitesync' ),
			),
			'realestate' => array(
				'label'    => __( 'Real estate', 'nexachat-ai' ),
				'icon'     => '🏠',
				'hint'     => __( 'Listings, neighbourhoods and viewing requests.', 'nexachat-ai' ),
				'category' => __( 'Real estate agency', 'nexachat-ai' ),
				'industry' => __( 'Real estate', 'nexachat-ai' ),
				'role'     => __( 'Property advisor', 'nexachat-ai' ),
				'tone'     => 'professional',
				'welcome'  => __( 'Hello! Tell me what kind of property you are looking for and your budget, and I will point you to the right listings.', 'nexachat-ai' ),
				'rules'    => __( 'Ask for budget, area and size when they are missing. Quote prices and availability only from the knowledge provided and offer a viewing request for serious interest.', 'nexachat-ai' ),
				'fields'   => array(
					array(
						'label'    => __( 'Budget', 'nexachat-ai' ),
						'type'     => 'text',
						'required' => false,
					),
					array(
						'label'    => __( 'Preferred area', 'nexachat-ai' ),
						'type'     => 'text',
						'required' => false,
					),
				),
				'modules'  => array( 'leads', 'live', 'sitesync', 'notifications' ),
			),
			'services'   => array(
				'label'    => __( 'Services company', 'nexachat-ai' ),
				'icon'     => '🛠️',
				'hint'     => __( 'Services, pricing, coverage area and quote requests.', 'nexachat-ai' ),
				'category' => __( 'Services', 'nexachat-ai' ),
				'industry' => __( 'Professional services', 'nexachat-ai' ),
				'role'     => __( 'Customer advisor', 'nexachat-ai' ),
				'tone'     => 'professional',
				'welcome'  => __( 'Hello! Tell me what you need and I will explain our services, prices and how to get a quote.', 'nexachat-ai' ),
				'rules'    => __( 'Understand the visitor\'s need before recommending a service. Offer the quote request form for anything that needs a custom price.', 'nexachat-ai' ),
				'fields'   => array(
					array(
						'label'    => __( 'Service needed', 'nexachat-ai' ),
						'type'     => 'text',
						'required' => false,
					),
				),
				'modules'  => array( 'leads', 'live', 'sitesync' ),
			),
		);
		/**
		 * Filter the industry templates.
		 *
		 * @param array $templates Templates.
		 */
		return (array) apply_filters( 'ssc_industry_templates', $templates );
	}

	/**
	 * Apply a template, filling only empty fields.
	 *
	 * @param string $id Template id.
	 * @return array|WP_Error Applied: fields (list of changed labels), modules (recommended, still off).
	 */
	public static function apply( $id ) {
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) {
			return new WP_Error( 'ssc_template', __( 'Unknown template.', 'nexachat-ai' ) );
		}
		$t        = $all[ $id ];
		$business = SSC_Settings::business();
		$changed  = array();
		$fill     = array(
			'category'       => $t['category'],
			'industry'       => $t['industry'],
			'assistant_role' => $t['role'],
		);
		foreach ( $fill as $key => $value ) {
			if ( '' === trim( (string) $business[ $key ] ) ) {
				$business[ $key ] = $value;
				$changed[]        = $key;
			}
		}
		// The tone always has a value; only replace the untouched default.
		if ( 'professional' === $business['tone'] && $t['tone'] !== $business['tone'] ) {
			$business['tone'] = $t['tone'];
			$changed[]        = 'tone';
		}
		$patch = array( 'business' => SSC_Settings::sanitize_business( $business ) );
		if ( '' === trim( (string) SSC_Settings::get( 'welcome_text', '' ) ) ) {
			$patch['welcome_text'] = $t['welcome'];
			$changed[]             = 'welcome_text';
		}
		if ( '' === trim( (string) SSC_Settings::get( 'ai_system_prompt_extra', '' ) ) ) {
			$patch['ai_system_prompt_extra'] = $t['rules'];
			$changed[]                       = 'rules';
		}
		if ( $t['fields'] && ! SSC_Settings::form_fields() ) {
			$patch['form_fields'] = SSC_Settings::sanitize_value( 'form_fields', $t['fields'] );
			$changed[]            = 'form_fields';
		}
		SSC_Settings::update( $patch );
		SSC_Setup::update_state( array( 'template' => $id ) );
		return array(
			'fields'  => $changed,
			'modules' => array_values(
				array_filter(
					$t['modules'],
					function ( $module ) {
						return null !== SSC_Modules::get( $module ) && ! SSC_Modules::is_active( $module );
					}
				)
			),
		);
	}
}
