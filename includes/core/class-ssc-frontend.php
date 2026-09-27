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
		wp_register_style( 'nexachat-ai', SSC_CHATBOT_URL . 'assets/css/chatbot.css', array(), SSC_CHATBOT_VERSION );
		wp_register_style( 'nexachat-ai-fonts', SSC_CHATBOT_URL . 'assets/css/fonts.css', array(), SSC_CHATBOT_VERSION );
		wp_register_script( 'nexachat-ai', SSC_CHATBOT_URL . 'assets/js/chatbot.js', array(), SSC_CHATBOT_VERSION, true );
		// Non-blocking: never compete with LCP for the main thread.
		wp_script_add_data( 'nexachat-ai', 'strategy', 'defer' );
	}

	/**
	 * Enqueue assets + inject the public configuration (once).
	 *
	 * @param array $overrides Elementor/shortcode overrides.
	 */
	public function enqueue_with_config( $overrides = array() ) {
		wp_enqueue_style( 'nexachat-ai' );
		wp_enqueue_script( 'nexachat-ai' );

		if ( $this->assets_done ) {
			return;
		}
		$this->assets_done = true;

		wp_localize_script( 'nexachat-ai', 'SSCChatbotConfig', $this->build_config( $overrides ) );
	}

	/**
	 * Admin preview: the REAL widget with the saved look, talking to the
	 * capability-gated preview endpoint, with visitor-facing extras off.
	 * Shared by the setup wizard and the Appearance page so the preview can
	 * never drift from what visitors see.
	 */
	public function enqueue_preview() {
		$this->register_assets();
		wp_enqueue_style( 'nexachat-ai' );
		wp_enqueue_script( 'nexachat-ai' );
		// The preview speaks the widget language, like the live widget.
		$switched = SSC_I18n::use_widget_locale();
		$config   = $this->build_config();
		if ( $switched ) {
			SSC_I18n::restore();
		}
		$config = array_merge(
			$config,
			array(
				'preview'       => true,
				'previewRoute'  => 'preview-chat',
				'ajaxUrl'       => '',
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'proactiveText' => '',
				'products'      => array(),
				'formFields'    => array(),
				'adrOptions'    => null,
			)
		);
		foreach ( array_keys( $config['features'] ) as $feature ) {
			$config['features'][ $feature ] = false;
		}
		$config['availability']['online']  = true;
		$config['availability']['dynamic'] = false;
		wp_localize_script( 'nexachat-ai', 'SSCChatbotConfig', $config );
	}

	/**
	 * Public widget configuration (never contains secrets).
	 *
	 * @param array $overrides Elementor/shortcode overrides.
	 * @return array
	 */
	public function build_config( $overrides = array() ) {
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

		$display_name = '' !== trim( (string) $s['assistant_display_name'] ) ? $s['assistant_display_name'] : __( 'Nexa', 'nexachat-ai' );
		$direction    = $this->resolve_direction( $s['direction'] );

		$config = array(
			// Transports (REST first, admin-ajax fallback for cached pages).
			'restUrl'          => esc_url_raw( rest_url( SSC_REST::NS . '/' ) ),
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			// Public traffic is cache-safe; strict mode uses WordPress's REST action.
			'nonce'            => apply_filters( 'ssc_enforce_rest_nonce', false ) ? wp_create_nonce( 'wp_rest' ) : '',

			// Identity & texts.
			'assistantName'    => $display_name,
			'orgName'          => '' !== trim( (string) $business['org_name'] ) ? $business['org_name'] : get_bloginfo( 'name' ),
			'welcomeTitle'     => '' !== trim( (string) $s['welcome_title'] ) ? $s['welcome_title'] : __( 'Hello! 👋', 'nexachat-ai' ),
			'welcomeText'      => '' !== trim( (string) $s['welcome_text'] ) ? $s['welcome_text'] : __( 'How can I help you today?', 'nexachat-ai' ),
			'disclaimer'       => (string) $s['disclaimer'],
			'direction'        => $direction,

			// Appearance.
			'themeMode'        => $s['theme_mode'],
			'position'         => $s['position'],
			'primaryColor'     => $s['primary_color'],
			'fontSize'         => (int) $s['font_size'],
			'windowWidth'      => (int) $s['window_width'],
			'windowRadius'     => (int) $s['window_radius'],
			'bubbleRadius'     => (int) $s['bubble_radius'],
			'userBubble'       => $s['user_bubble_color'],
			'botBubble'        => $s['bot_bubble_color'],
			'fontStack'        => $font_stack,
			'avatarUrl'        => $s['avatar_url'],
			'launcherSize'     => (int) $s['launcher_size'],
			'launcherIconUrl'  => $s['launcher_icon_url'],
			'supportPhone'     => $business['support_phone'] ? $business['support_phone'] : $business['phone'],

			// Catalog.
			'products'         => array_values( $products ),

			// Module-gated capabilities (server-enforced mirror).
			'features'         => array(
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
			'availability'     => SSC_Availability::public_status(),
			'handoffText'      => (string) $s['handoff_text'],
			'proactiveDelay'   => (int) $s['proactive_delay'],
			'proactiveText'    => SSC_Availability::proactive_text_for( (string) $s['proactive_rules'], (string) $s['proactive_text'] ),
			'proactiveTrigger' => (string) $s['proactive_trigger'],
			'proactiveScroll'  => (int) $s['proactive_scroll'],
			'voiceLanguage'    => $this->voice_language(),

			// Leads form (module-gated).
			'formFields'       => ( SSC_Modules::is_active( 'leads' ) ) ? SSC_Settings::form_fields() : array(),
			'consent'          => array(
				'enabled' => 'yes' === $s['consent_enabled'],
				'text'    => SSC_Input::consent_text( SSC_Modules::is_active( 'pharma' ) ),
				'link'    => (string) $s['consent_link'],
			),

			// Pharma ADR options (module-gated).
			'adrOptions'       => SSC_Modules::is_active( 'pharma' ) ? SSC_Module_Pharma::adr_options_public() : null,
			'adrForm'          => SSC_Modules::is_active( 'pharma' ) ? SSC_Module_Pharma::adr_form_public() : null,

			// i18n strings for the widget.
			'i18n'             => $this->strings(),
		);

		return apply_filters( 'ssc_frontend_config', $this->apply_overrides( $config, $overrides ) );
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
		// auto: follow the widget language (which itself follows the site by default).
		return SSC_I18n::is_rtl_locale( SSC_I18n::widget_locale() ) ? 'rtl' : 'ltr';
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
			'open'            => __( 'Open chat', 'nexachat-ai' ),
			'newConversation' => __( 'New conversation', 'nexachat-ai' ),
			'close'           => __( 'Close chat', 'nexachat-ai' ),
			'send'            => __( 'Send message', 'nexachat-ai' ),
			'inputLabel'      => __( 'Message text', 'nexachat-ai' ),
			'placeholder'     => __( 'Write your message…', 'nexachat-ai' ),
			'sessionExpired'  => __( 'Your session expired. Please refresh the page and try again.', 'nexachat-ai' ),
			'connectionError' => __( 'Connection error. Please check your internet and try again.', 'nexachat-ai' ),
			'rateLimited'     => __( 'You have reached the daily usage limit. Please try again tomorrow.', 'nexachat-ai' ),
			'mainMenu'        => __( 'Main menu', 'nexachat-ai' ),
			'menuPrompt'      => __( 'How can I help you?', 'nexachat-ai' ),
			'askUs'           => __( 'Ask us', 'nexachat-ai' ),
			'askUsDesc'       => __( 'About us, services and contact info', 'nexachat-ai' ),
			'products'        => __( 'Products & services', 'nexachat-ai' ),
			'productsDesc'    => __( 'Product information', 'nexachat-ai' ),
			'chooseProduct'   => __( 'Which one?', 'nexachat-ai' ),
			'requestForm'     => __( 'Consultation request', 'nexachat-ai' ),
			'reportAdr'       => __( 'Report a side effect', 'nexachat-ai' ),
			'moreDetails'     => __( 'More details (optional)', 'nexachat-ai' ),
			'brochure'        => __( 'View brochure', 'nexachat-ai' ),
			'callUs'          => __( 'Call us', 'nexachat-ai' ),
			'speak'           => __( 'Listen to this answer', 'nexachat-ai' ),
			'speakStop'       => __( 'Stop audio', 'nexachat-ai' ),
			'mic'             => __( 'Speak', 'nexachat-ai' ),
			'micListening'    => __( 'Listening…', 'nexachat-ai' ),
			'handoffBtn'      => __( 'Talk to a human expert', 'nexachat-ai' ),
			'csatTitle'       => __( 'How was this conversation?', 'nexachat-ai' ),
			'csatThanks'      => __( 'Thanks for your rating 🙏', 'nexachat-ai' ),
			'csatSkip'        => __( 'Skip', 'nexachat-ai' ),
			'copy'            => __( 'Copy answer', 'nexachat-ai' ),
			'copied'          => __( 'Copied ✓', 'nexachat-ai' ),
			'sources'         => __( 'Sources:', 'nexachat-ai' ),
			'consentRequired' => __( 'Your consent is required to continue.', 'nexachat-ai' ),
			'privacy'         => __( 'Privacy policy', 'nexachat-ai' ),
			'formName'        => __( 'Full name', 'nexachat-ai' ),
			'formPhone'       => __( 'Phone number', 'nexachat-ai' ),
			'goodAnswer'      => __( 'Good answer', 'nexachat-ai' ),
			'poorAnswer'      => __( 'Poor answer', 'nexachat-ai' ),
			'formMessage'     => __( 'Your message', 'nexachat-ai' ),
			'formSubmit'      => __( 'Submit', 'nexachat-ai' ),
			'formSent'        => __( 'Received ✓ We will contact you soon.', 'nexachat-ai' ),
			'formError'       => __( 'The form could not be submitted. Please try again.', 'nexachat-ai' ),
			'suggestions'     => __( 'Related questions:', 'nexachat-ai' ),
			'typing'          => __( 'Typing…', 'nexachat-ai' ),
			'offline'         => __( 'Offline', 'nexachat-ai' ),
			'online'          => __( 'Online', 'nexachat-ai' ),
			'shortcutHint'    => __( 'Press Alt+C to open chat', 'nexachat-ai' ),
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
				wp_enqueue_style( 'nexachat-ai-font-custom', $url, array(), SSC_CHATBOT_VERSION );
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
		wp_enqueue_style( 'nexachat-ai-fonts' );
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
			if ( ! isset( $overrides[ $from ] ) || '' === $overrides[ $from ] ) {
				continue;
			}
			// Overrides come from shortcode/block/Elementor input: validate like saved settings.
			$value = SSC_Settings::sanitize_value( $from, $overrides[ $from ] );
			if ( null !== $value && '' !== $value ) {
				$config[ $to ] = $value;
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
