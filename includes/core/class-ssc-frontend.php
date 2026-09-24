<?php
/**
 * Frontend widget renderer + configuration payload.
 *
 * Security contract:
 * - Nothing renders unless SSC_Setup::is_live() (setup done + published).
 * - The config payload NEVER contains secrets (API keys stay server-side).
 * - Module-gated features are computed server-side; the client only mirrors.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend class.
 */
class SSC_Frontend {

	/**
	 * Assets localized once.
	 *
	 * @var bool
	 */
	protected $assets_done = false;

	/**
	 * Widget rendered at least once.
	 *
	 * @var bool
	 */
	protected $rendered = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'ssc_chatbot', array( $this, 'shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_footer', array( $this, 'maybe_render_floating' ) );
	}

	/**
	 * Register (not enqueue) styles and scripts.
	 */
	public function register_assets() {
		wp_register_style( 'smart-support-chatbot', SSC_CHATBOT_URL . 'assets/css/chatbot.css', array(), SSC_CHATBOT_VERSION );
		wp_register_style( 'smart-support-chatbot-fonts', SSC_CHATBOT_URL . 'assets/css/fonts.css', array(), SSC_CHATBOT_VERSION );
		wp_register_script( 'smart-support-chatbot', SSC_CHATBOT_URL . 'assets/js/chatbot.js', array(), SSC_CHATBOT_VERSION, true );
		// Non-blocking: never compete with LCP for the main thread.
		wp_script_add_data( 'smart-support-chatbot', 'strategy', 'defer' );
	}

	/**
	 * Enqueue assets + inject the public configuration (once).
	 *
	 * @param array $overrides Elementor/shortcode overrides.
	 */
	public function enqueue_with_config( $overrides = array() ) {
		wp_enqueue_style( 'smart-support-chatbot' );
		wp_enqueue_script( 'smart-support-chatbot' );

		if ( $this->assets_done ) {
			return;
		}
		$this->assets_done = true;

		$s          = SSC_Settings::all();
		$business   = SSC_Settings::business();
		$font_stack = $this->enqueue_font( $s );

		// Products payload (public-safe fields only).
		$products = array();
		foreach ( (array) $s['products'] as $p ) {
			if ( empty( $p['id'] ) ) {
				continue;
			}
			$summary    = ! empty( $p['summary'] ) ? $p['summary'] : '';
			$products[] = array(
				'id'       => $p['id'],
				'name'     => isset( $p['name'] ) ? $p['name'] : $p['id'],
				'summary'  => $summary,
				'brochure' => isset( $p['brochure'] ) ? $p['brochure'] : '',
				'image'    => isset( $p['image'] ) ? $p['image'] : '',
			);
		}

		$display_name = '' !== trim( (string) $s['assistant_display_name'] ) ? $s['assistant_display_name'] : __( 'Nexa', 'smart-support-chatbot' );
		$direction    = $this->resolve_direction( $s['direction'] );

		$config = array(
			// Transports (REST first, admin-ajax fallback for cached pages).
			'restUrl'         => esc_url_raw( rest_url( SSC_REST::NS . '/' ) ),
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			// Public traffic is cache-safe; strict mode uses WordPress's REST action.
			'nonce'           => apply_filters( 'ssc_enforce_rest_nonce', false ) ? wp_create_nonce( 'wp_rest' ) : '',

			// Identity & texts.
			'assistantName'   => $display_name,
			'orgName'         => '' !== trim( (string) $business['org_name'] ) ? $business['org_name'] : get_bloginfo( 'name' ),
			'welcomeTitle'    => '' !== trim( (string) $s['welcome_title'] ) ? $s['welcome_title'] : __( 'Hello! 👋', 'smart-support-chatbot' ),
			'welcomeText'     => '' !== trim( (string) $s['welcome_text'] ) ? $s['welcome_text'] : __( 'How can I help you today?', 'smart-support-chatbot' ),
			'disclaimer'      => (string) $s['disclaimer'],
			'direction'       => $direction,

			// Appearance.
			'themeMode'       => $s['theme_mode'],
			'position'        => $s['position'],
			'primaryColor'    => $s['primary_color'],
			'fontSize'        => (int) $s['font_size'],
			'windowWidth'     => (int) $s['window_width'],
			'windowRadius'    => (int) $s['window_radius'],
			'bubbleRadius'    => (int) $s['bubble_radius'],
			'userBubble'      => $s['user_bubble_color'],
			'botBubble'       => $s['bot_bubble_color'],
			'fontStack'       => $font_stack,
			'avatarUrl'       => $s['avatar_url'],
			'launcherSize'    => (int) $s['launcher_size'],
			'launcherIconUrl' => $s['launcher_icon_url'],
			'supportPhone'    => $business['support_phone'] ? $business['support_phone'] : $business['phone'],

			// Catalog.
			'products'        => array_values( $products ),

			// Module-gated capabilities (server-enforced mirror).
			'features'        => array(
				'leads'       => SSC_Modules::is_active( 'leads' ),
				'faq'         => SSC_Modules::is_active( 'faq' ),
				'voice'       => SSC_Modules::is_active( 'voice' ),
				'voiceInput'  => SSC_Modules::is_active( 'voice' ) && 'yes' === $s['voice_input'],
				'voiceOutput' => SSC_Modules::is_active( 'voice' ) && 'yes' === $s['voice_output'],
				'csat'        => SSC_Modules::is_active( 'csat' ),
				'handoff'     => SSC_Modules::is_active( 'handoff' ),
				'proactive'   => SSC_Modules::is_active( 'proactive' ),
				'pharma'      => SSC_Modules::is_active( 'pharma' ),
				'feedback'    => SSC_Modules::is_active( 'history' ),
			),

			// Availability + behaviour (server-computed).
			'availability'    => SSC_Availability::public_status(),
			'handoffText'     => (string) $s['handoff_text'],
			'proactiveDelay'  => (int) $s['proactive_delay'],
			'proactiveText'   => (string) $s['proactive_text'],
			'voiceLanguage'   => $this->voice_language(),

			// Leads form (module-gated).
			'formFields'      => ( SSC_Modules::is_active( 'leads' ) ) ? SSC_Settings::form_fields() : array(),
			'consent'         => array(
				'enabled' => 'yes' === $s['consent_enabled'],
				'text'    => SSC_Input::consent_text( SSC_Modules::is_active( 'pharma' ) ),
				'link'    => (string) $s['consent_link'],
			),

			// Pharma ADR options (module-gated).
			'adrOptions'      => SSC_Modules::is_active( 'pharma' ) ? SSC_Module_Pharma::adr_options_public() : null,

			// i18n strings for the widget.
			'i18n'            => $this->strings(),
		);

		$config = apply_filters( 'ssc_frontend_config', $this->apply_overrides( $config, $overrides ) );
		wp_localize_script( 'smart-support-chatbot', 'SSCChatbotConfig', $config );
	}

	/**
	 * Resolve UI direction: rtl | ltr (auto = site language heuristic).
	 *
	 * @param string $setting Configured direction.
	 * @return string
	 */
	protected function resolve_direction( $setting ) {
		if ( 'rtl' === $setting || 'ltr' === $setting ) {
			return $setting;
		}
		// auto: follow the site locale, preferring WordPress's own RTL flag.
		if ( function_exists( 'is_rtl' ) && is_rtl() ) {
			return 'rtl';
		}
		$locale = strtolower( (string) get_locale() );
		$prefix = substr( $locale, 0, 2 );
		return in_array( $prefix, array( 'fa', 'ar', 'he', 'ur', 'ps', 'ug', 'yi', 'ku', 'sd', 'dv' ), true ) ? 'rtl' : 'ltr';
	}

	/**
	 * Voice recognition language (module setting or site language).
	 *
	 * @return string
	 */
	protected function voice_language() {
		$setting = (string) SSC_Settings::get( 'voice_language', 'auto' );
		if ( 'auto' !== $setting && preg_match( '/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $setting ) ) {
			return $setting;
		}
		$locale = str_replace( '_', '-', get_locale() );
		return $locale;
	}

	/**
	 * Frontend i18n strings (translatable).
	 *
	 * @return array
	 */
	protected function strings() {
		return array(
			'open'            => __( 'Open chat', 'smart-support-chatbot' ),
			'newConversation' => __( 'New conversation', 'smart-support-chatbot' ),
			'close'           => __( 'Close chat', 'smart-support-chatbot' ),
			'send'            => __( 'Send message', 'smart-support-chatbot' ),
			'inputLabel'      => __( 'Message text', 'smart-support-chatbot' ),
			'placeholder'     => __( 'Write your message…', 'smart-support-chatbot' ),
			'sessionExpired'  => __( 'Your session expired. Please refresh the page and try again.', 'smart-support-chatbot' ),
			'connectionError' => __( 'Connection error. Please check your internet and try again.', 'smart-support-chatbot' ),
			'rateLimited'     => __( 'You have reached the daily usage limit. Please try again tomorrow.', 'smart-support-chatbot' ),
			'mainMenu'        => __( 'Main menu', 'smart-support-chatbot' ),
			'askUs'           => __( 'Ask us', 'smart-support-chatbot' ),
			'askUsDesc'       => __( 'About us, services and contact info', 'smart-support-chatbot' ),
			'products'        => __( 'Products & services', 'smart-support-chatbot' ),
			'productsDesc'    => __( 'Product information', 'smart-support-chatbot' ),
			'chooseProduct'   => __( 'Which one?', 'smart-support-chatbot' ),
			'requestForm'     => __( 'Consultation request', 'smart-support-chatbot' ),
			'reportAdr'       => __( 'Report a side effect', 'smart-support-chatbot' ),
			'brochure'        => __( 'View brochure', 'smart-support-chatbot' ),
			'callUs'          => __( 'Call us', 'smart-support-chatbot' ),
			'speak'           => __( 'Listen to this answer', 'smart-support-chatbot' ),
			'speakStop'       => __( 'Stop audio', 'smart-support-chatbot' ),
			'mic'             => __( 'Speak', 'smart-support-chatbot' ),
			'micListening'    => __( 'Listening…', 'smart-support-chatbot' ),
			'handoffBtn'      => __( 'Talk to a human expert', 'smart-support-chatbot' ),
			'csatTitle'       => __( 'How was this conversation?', 'smart-support-chatbot' ),
			'csatThanks'      => __( 'Thanks for your rating 🙏', 'smart-support-chatbot' ),
			'csatSkip'        => __( 'Skip', 'smart-support-chatbot' ),
			'copy'            => __( 'Copy answer', 'smart-support-chatbot' ),
			'copied'          => __( 'Copied ✓', 'smart-support-chatbot' ),
			'consentRequired' => __( 'Your consent is required to continue.', 'smart-support-chatbot' ),
			'privacy'         => __( 'Privacy policy', 'smart-support-chatbot' ),
			'formName'        => __( 'Full name', 'smart-support-chatbot' ),
			'formPhone'       => __( 'Phone number', 'smart-support-chatbot' ),
			'goodAnswer'      => __( 'Good answer', 'smart-support-chatbot' ),
			'poorAnswer'      => __( 'Poor answer', 'smart-support-chatbot' ),
			'formMessage'     => __( 'Your message', 'smart-support-chatbot' ),
			'formSubmit'      => __( 'Submit', 'smart-support-chatbot' ),
			'formSent'        => __( 'Received ✓ We will contact you soon.', 'smart-support-chatbot' ),
			'formError'       => __( 'The form could not be submitted. Please try again.', 'smart-support-chatbot' ),
			'suggestions'     => __( 'Related questions:', 'smart-support-chatbot' ),
			'typing'          => __( 'Typing…', 'smart-support-chatbot' ),
			'offline'         => __( 'Offline', 'smart-support-chatbot' ),
			'online'          => __( 'Online', 'smart-support-chatbot' ),
			'shortcutHint'    => __( 'Press Alt+C to open chat', 'smart-support-chatbot' ),
		);
	}

	/**
	 * Bundled font handling (local files only; graceful fallback).
	 *
	 * @param array $s Settings.
	 * @return string font-family stack.
	 */
	protected function enqueue_font( $s ) {
		$family       = isset( $s['font_family'] ) ? $s['font_family'] : 'vazirmatn';
		$system_stack = "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif";

		if ( 'custom' === $family ) {
			$url  = isset( $s['font_url'] ) ? $s['font_url'] : '';
			$name = isset( $s['font_name'] ) ? trim( (string) $s['font_name'] ) : '';
			if ( $url ) {
				wp_enqueue_style( 'smart-support-chatbot-font-custom', $url, array(), SSC_CHATBOT_VERSION );
			}
			// The stack lands in a CSS custom property: strip anything that
			// could terminate the declaration or open a new rule.
			$name = trim( preg_replace( '/[^\p{L}\p{N} _-]+/u', '', $name ) );
			return '' !== $name ? "'" . $name . "', sans-serif" : $system_stack;
		}
		if ( 'system' === $family ) {
			return $system_stack;
		}

		// Font-family names are SSC-prefixed to avoid colliding with theme @font-face.
		$bundled = array(
			'vazirmatn' => array(
				'probe' => 'vazirmatn-400.woff2',
				'stack' => "'SSC Vazirmatn', 'Vazir', Tahoma, sans-serif",
			),
			'inter'     => array(
				'probe' => 'inter-400.woff2',
				'stack' => "'SSC Inter', system-ui, sans-serif",
			),
			'roboto'    => array(
				'probe' => 'roboto-400.woff2',
				'stack' => "'SSC Roboto', system-ui, sans-serif",
			),
		);
		if ( ! isset( $bundled[ $family ] ) ) {
			return $system_stack;
		}
		if ( ! file_exists( SSC_CHATBOT_DIR . 'assets/fonts/' . $bundled[ $family ]['probe'] ) ) {
			return "Tahoma, 'Segoe UI', sans-serif";
		}
		wp_enqueue_style( 'smart-support-chatbot-fonts' );
		return $bundled[ $family ]['stack'];
	}

	/**
	 * Apply Elementor/shortcode overrides (public-safe subset).
	 *
	 * @param array $config    Config.
	 * @param array $overrides Overrides.
	 * @return array
	 */
	protected function apply_overrides( $config, $overrides ) {
		if ( empty( $overrides ) || ! is_array( $overrides ) ) {
			return $config;
		}
		$map = array(
			'position'               => 'position',
			'primary_color'          => 'primaryColor',
			'theme_mode'             => 'themeMode',
			'assistant_display_name' => 'assistantName',
			'welcome_title'          => 'welcomeTitle',
			'welcome_text'           => 'welcomeText',
			'disclaimer'             => 'disclaimer',
			'font_size'              => 'fontSize',
			'window_width'           => 'windowWidth',
			'window_radius'          => 'windowRadius',
			'bubble_radius'          => 'bubbleRadius',
			'user_bubble_color'      => 'userBubble',
			'bot_bubble_color'       => 'botBubble',
		);
		foreach ( $map as $from => $to ) {
			if ( isset( $overrides[ $from ] ) && '' !== $overrides[ $from ] ) {
				$config[ $to ] = $overrides[ $from ];
			}
		}
		return $config;
	}

	/**
	 * Render the widget container (empty string when not live).
	 *
	 * @param array $overrides Overrides.
	 * @return string
	 */
	public function render( $overrides = array() ) {
		if ( $this->rendered ) {
			return '';
		}
		if ( ! SSC_Availability::should_render() ) {
			return ''; // Display rules + setup gate: zero assets on filtered pages.
		}
		$this->rendered = true;
		$this->enqueue_with_config( $overrides );

		$dir = $this->resolve_direction( SSC_Settings::get( 'direction', 'rtl' ) );
		return '<div id="ssc-chatbot-root" class="ssc-root" dir="' . esc_attr( $dir ) . '"></div>';
	}

	/**
	 * Gutenberg block registration.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		$dir = SSC_CHATBOT_DIR . 'blocks/chatbot';
		if ( ! file_exists( $dir . '/block.json' ) ) {
			return;
		}
		register_block_type( $dir, array( 'render_callback' => array( $this, 'render_block' ) ) );
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '';
		}
		$overrides = array();
		if ( ! empty( $attributes['position'] ) ) {
			$overrides['position'] = sanitize_text_field( $attributes['position'] );
		}
		return $this->render( $overrides );
	}

	/**
	 * Shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'position' => '' ), $atts, 'ssc_chatbot' );
		return $this->render( array_filter( $atts ) );
	}

	/**
	 * Auto floating widget (wp_footer).
	 */
	public function maybe_render_floating() {
		if ( $this->rendered || ! SSC_Availability::should_render() ) {
			return;
		}
		// Static internal string only (no user input) - safe by construction.
		echo $this->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Rendered getter.
	 *
	 * @return bool
	 */
	public function is_rendered() {
		return $this->rendered;
	}
}
