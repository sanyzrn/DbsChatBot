<?php
/**
 * Admin bootstrap: menu structure, shared assets, page controllers.
 *
 * Navigation model (Phase 9):
 *   Dashboard | Business Knowledge | AI Connection | Appearance | Modules | Settings
 * Module-specific pages appear ONLY while their modules are active.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class SSC_Admin {
	protected $controllers = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'prepare_page' ), 0 );
		// Priority 1: register BEFORE anything that might inspect the menu.
		add_action( 'admin_menu', array( $this, 'register_menu' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_head', array( $this, 'hide_wizard_menu_css' ) );
		add_action( 'admin_head', array( $this, 'force_admin_ltr' ) );
		add_action( 'admin_footer-plugins.php', array( $this, 'uninstall_confirm_script' ) );
		add_action( 'wp_ajax_ssc_set_uninstall_policy', array( $this, 'ajax_set_uninstall_policy' ) );
		add_action( 'admin_post_ssc_retry_notifications', array( $this, 'retry_notifications' ) );
	}

	public function retry_notifications() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) ); }
		check_admin_referer( 'ssc_retry_notifications' );
		if ( SSC_Modules::is_active( 'notifications' ) ) { SSC_Notification_Queue::retry_exhausted(); }
		wp_safe_redirect( admin_url( 'admin.php?page=ssc-dashboard' ) );
		exit;
	}

	/** Construct the requested controller before admin_init handlers and headers. */
	public function prepare_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$pages = array(
			'ssc-dashboard' => 'SSC_Admin_Dashboard',
			'ssc-knowledge' => 'SSC_Admin_Knowledge', 'ssc-connection' => 'SSC_Admin_Connection',
			'ssc-appearance' => 'SSC_Admin_Appearance', 'ssc-modules' => 'SSC_Admin_Modules',
			'ssc-settings' => 'SSC_Admin_Settings', 'ssc-wizard' => 'SSC_Wizard',
		);
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( isset( $pages[ $page ] ) ) { $this->controller( $pages[ $page ] ); }
	}

	protected function controller( $class ) {
		if ( ! isset( $this->controllers[ $class ] ) ) { $this->controllers[ $class ] = new $class(); }
		return $this->controllers[ $class ];
	}

	/**
	 * Menu tree.
	 */
	public function register_menu() {
		$brand = defined( 'NEXACHATAI_NAME' ) ? NEXACHATAI_NAME : 'NexaChatAI';

		add_menu_page(
			$brand,
			$brand,
			'manage_options',
			'ssc-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-format-chat',
			58
		);

		add_submenu_page( 'ssc-dashboard', __( 'Dashboard', 'smart-support-chatbot' ), __( 'Dashboard', 'smart-support-chatbot' ), 'manage_options', 'ssc-dashboard', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'ssc-dashboard', __( 'Business Knowledge', 'smart-support-chatbot' ), __( 'Business Knowledge', 'smart-support-chatbot' ), 'manage_options', 'ssc-knowledge', array( $this, 'render_knowledge' ) );
		add_submenu_page( 'ssc-dashboard', __( 'AI Connection', 'smart-support-chatbot' ), __( 'AI Connection', 'smart-support-chatbot' ), 'manage_options', 'ssc-connection', array( $this, 'render_connection' ) );
		add_submenu_page( 'ssc-dashboard', __( 'Appearance', 'smart-support-chatbot' ), __( 'Appearance', 'smart-support-chatbot' ), 'manage_options', 'ssc-appearance', array( $this, 'render_appearance' ) );
		add_submenu_page( 'ssc-dashboard', __( 'Modules', 'smart-support-chatbot' ), __( 'Modules', 'smart-support-chatbot' ), 'manage_options', 'ssc-modules', array( $this, 'render_modules' ) );
		add_submenu_page( 'ssc-dashboard', __( 'Settings', 'smart-support-chatbot' ), __( 'Settings', 'smart-support-chatbot' ), 'manage_options', 'ssc-settings', array( $this, 'render_settings' ) );

		// Wizard MUST stay registered so admin.php?page=ssc-wizard resolves the
		// page hook. Hiding is done with CSS — never remove_submenu_page(), which
		// makes WordPress fall through to "Cannot load ssc-wizard."
		add_submenu_page(
			'ssc-dashboard',
			__( 'Setup Wizard', 'smart-support-chatbot' ),
			__( 'Setup Wizard', 'smart-support-chatbot' ),
			'manage_options',
			'ssc-wizard',
			array( $this, 'render_wizard' )
		);
	}

	/**
	 * AJAX: store uninstall policy (yes/no) chosen on the Plugins screen.
	 */
	public function ajax_set_uninstall_policy() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'ssc_uninstall_policy', 'nonce' );
		$wipe = isset( $_POST['wipe'] ) && '1' === (string) $_POST['wipe'];
		update_option( 'ssc_chatbot_delete_on_uninstall', $wipe ? 'yes' : 'no', false );
		wp_send_json_success( array( 'wipe' => $wipe ) );
	}

	/**
	 * Ask on Plugins → Delete whether stored data should be erased too.
	 * WordPress cannot natively prompt during uninstall; this intercepts the
	 * delete click, records the choice, then continues the normal delete flow.
	 */
	public function uninstall_confirm_script() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'delete_plugins' ) ) {
			return;
		}
		$plugin = defined( 'SSC_CHATBOT_BASENAME' ) ? SSC_CHATBOT_BASENAME : '';
		if ( '' === $plugin ) {
			return;
		}
		$nonce = wp_create_nonce( 'ssc_uninstall_policy' );
		$label = __( 'NexaChatAI', 'smart-support-chatbot' );
		$ask   = __( 'Also permanently delete all saved data (settings, knowledge, conversations, requests, ADR cases)?', 'smart-support-chatbot' );
		$yes   = __( 'OK = delete plugin + data', 'smart-support-chatbot' );
		$no    = __( 'Cancel = delete plugin, KEEP data', 'smart-support-chatbot' );
		?>
<script id="ssc-uninstall-confirm">
(function () {
	var PLUGIN = <?php echo wp_json_encode( $plugin ); ?>;
	var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
	var AJAX = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var ASK = <?php echo wp_json_encode( $ask . "\n\n" . $yes . "\n" . $no ); ?>;

	function rowMatches(link) {
		var row = link.closest('tr[data-plugin]');
		if (!row) { return false; }
		return row.getAttribute('data-plugin') === PLUGIN;
	}

	function setPolicy(wipe, done) {
		var body = new URLSearchParams();
		body.append('action', 'ssc_set_uninstall_policy');
		body.append('nonce', NONCE);
		body.append('wipe', wipe ? '1' : '0');
		fetch(AJAX, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).then(function (res) { return res.json(); }).then(function (result) {
			if (!result.success) { throw new Error('Policy not saved'); }
			done();
		}).catch(function () {
			window.alert(<?php echo wp_json_encode( __( 'The data deletion preference could not be saved. Deletion was stopped. Please check Settings before trying again.', 'smart-support-chatbot' ) ); ?>);
		});
	}

	document.addEventListener('click', function (e) {
		var link = e.target && e.target.closest ? e.target.closest('a') : null;
		if (!link) { return; }
		var href = link.getAttribute('href') || '';
		if (href.indexOf('action=delete-selected') === -1 && href.indexOf('action=delete-plugin') === -1) {
			return;
		}
		if (!rowMatches(link)) { return; }
		if (link.getAttribute('data-ssc-uninstall') === '1') { return; }

		e.preventDefault();
		e.stopPropagation();
		if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }

		var wipe = window.confirm(ASK);
		setPolicy(wipe, function () {
			link.setAttribute('data-ssc-uninstall', '1');
			// Re-click so WordPress runs its own confirm + delete flow.
			link.click();
		});
	}, true);
})();
</script>
		<?php
	}

	/**
	 * Visually hide the wizard submenu link (page stays URL-reachable).
	 */
	public function hide_wizard_menu_css() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$hook   = $screen ? (string) $screen->id : '';
		if ( '' !== $hook && false === strpos( $hook, 'ssc' ) && false === strpos( $hook, 'smart' ) ) {
			// Still needed globally so the item never flashes on other admin screens.
		}
		echo '<style id="ssc-hide-wizard-menu">#adminmenu a[href*="page=ssc-wizard"]{display:none !important}</style>';
	}

	/**
	 * Shared admin assets (only on our screens).
	 *
	 * @param string $hook Current hook suffix.
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'ssc-' ) ) {
			return;
		}
		wp_enqueue_style( 'ssc-admin', SSC_CHATBOT_URL . 'assets/css/admin.css', array(), SSC_CHATBOT_VERSION );
		wp_enqueue_script( 'ssc-admin', SSC_CHATBOT_URL . 'assets/js/admin.js', array(), SSC_CHATBOT_VERSION, true );
		wp_localize_script(
			'ssc-admin',
			'SSCAdmin',
			array(
				'restUrl'    => esc_url_raw( rest_url( SSC_REST::NS . '/' ) ),
				'restNonce'  => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'ajaxNonce'  => wp_create_nonce( 'ssc_admin' ),
				'wizardUrl'  => SSC_Setup::wizard_url(),
				'chatbotUrl'  => SSC_CHATBOT_URL,
				'i18n'       => array(
					'online'  => __( 'Online', 'smart-support-chatbot' ),
					'offline' => __( 'Offline', 'smart-support-chatbot' ),
				),
			)
		);
	}

	/**
	 * Force LTR on every plugin admin screen, even when the site is RTL.
	 * The plugin UI is English-first; RTL inheritance breaks layout and reading.
	 */
	public function force_admin_ltr() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$hook   = $screen ? (string) $screen->id : '';
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$is_ssc = ( '' !== $hook && false !== strpos( $hook, 'ssc' ) )
			|| ( '' !== $page && 0 === strpos( $page, 'ssc-' ) );

		if ( ! $is_ssc ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS.
		echo '<style id="ssc-force-ltr">
html.rtl body.wp-admin .ssc-page,
html.rtl body.wp-admin .ssc-wizard,
body.wp-admin .ssc-page,
body.wp-admin .ssc-wizard {
	direction: ltr !important;
	text-align: left !important;
}
body.wp-admin .ssc-page input[dir="rtl"],
body.wp-admin .ssc-wizard input[dir="rtl"],
body.wp-admin .ssc-page textarea[dir="rtl"],
body.wp-admin .ssc-wizard textarea[dir="rtl"] {
	direction: rtl !important;
	text-align: right !important;
}
</style>';
	}

	/**
	 * Page renderers (delegate to dedicated controllers).
	 */
	public function render_dashboard() {
		$this->controller( 'SSC_Admin_Dashboard' )->render();
	}

	/**
	 * Render knowledge page.
	 */
	public function render_knowledge() {
		$this->controller( 'SSC_Admin_Knowledge' )->render();
	}

	/**
	 * Render connection page.
	 */
	public function render_connection() {
		$this->controller( 'SSC_Admin_Connection' )->render();
	}

	/**
	 * Render appearance page.
	 */
	public function render_appearance() {
		$this->controller( 'SSC_Admin_Appearance' )->render();
	}

	/**
	 * Render modules page.
	 */
	public function render_modules() {
		$this->controller( 'SSC_Admin_Modules' )->render();
	}

	/**
	 * Render settings page.
	 */
	public function render_settings() {
		$this->controller( 'SSC_Admin_Settings' )->render();
	}

	/**
	 * Render the wizard.
	 */
	public function render_wizard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) );
		}
		$this->controller( 'SSC_Wizard' )->render();
	}
}
