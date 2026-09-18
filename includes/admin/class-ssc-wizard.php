<?php
/**
 * Mandatory Setup Wizard (5 steps, resumable, RTL/LTR, mobile-friendly).
 *
 * Steps: 1 Business Identity, 2 Business Knowledge, 3 AI Provider
 * Connection (mandatory verification), 4 Appearance & Branding,
 * 5 Final Verification & Launch (readiness checklist + live chat preview +
 * explicit Publish action).
 *
 * Every step POST is validated server-side; the wizard can be paused and
 * resumed (state persists); users can navigate backwards; and the wizard
 * never blocks the WordPress dashboard.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Wizard controller.
 */
class SSC_Wizard {

        /**
         * Current step id.
         *
         * @var string
         */
        protected $step = 'identity';

        /**
         * Constructor: handle POSTs (PRG), compute current step.
         */
        public function __construct() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) );
                }

                $this->handle_posts();

                $state     = SSC_Setup::state();
                $requested = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
                if ( in_array( $requested, SSC_Setup::steps(), true ) ) {
                        $this->step = $requested;
                } else {
                        // Resume at the first incomplete step.
                        foreach ( SSC_Setup::steps() as $step ) {
                                if ( empty( $state['steps'][ $step ] ) ) {
                                        $this->step = $step;
                                        break;
                                }
                        }
                        $this->step = ( 'identity' === $this->step && ! empty( $state['steps']['identity'] ) ) ? 'review' : $this->step;
                }
        }

        /**
         * Handle wizard step submissions (each with its own nonce).
         */
        protected function handle_posts() {
                if ( ! isset( $_POST['ssc_wizard_step'] ) ) {
                        return;
                }
                $step = sanitize_key( wp_unslash( $_POST['ssc_wizard_step'] ) );
                check_admin_referer( 'ssc_wizard_' . $step );

                switch ( $step ) {
                        case 'identity':
                                $this->save_identity();
                                break;
                        case 'knowledge':
                                $this->save_knowledge();
                                break;
                        case 'connection':
                                $this->save_connection();
                                break;
                        case 'appearance':
                                $this->save_appearance();
                                break;
                        case 'review':
                                $this->save_review();
                                break;
                }
        }

        /**
         * Step 1: business identity.
         */
        protected function save_identity() {
                $business = array();
                $fields   = array( 'org_name', 'brand_name', 'category', 'industry', 'location', 'phone', 'email', 'support_phone', 'support_email', 'hours', 'assistant_name', 'assistant_role', 'tone', 'language' );
                foreach ( $fields as $f ) {
                        $business[ $f ] = isset( $_POST['business'][ $f ] ) ? sanitize_text_field( wp_unslash( $_POST['business'][ $f ] ) ) : '';
                }
                foreach ( array( 'description', 'products', 'differentiators' ) as $f ) {
                        $business[ $f ] = isset( $_POST['business'][ $f ] ) ? wp_kses_post( wp_unslash( $_POST['business'][ $f ] ) ) : '';
                }
                $business['url'] = isset( $_POST['business']['url'] ) ? esc_url_raw( wp_unslash( $_POST['business']['url'] ) ) : '';

                // Mandatory: organization name. Everything else is optional detail.
                if ( '' === trim( $business['org_name'] ) ) {
                        self::prg( 'identity', array( 'error' => 'org_name' ) );
                }

                SSC_Settings::update( array( 'business' => SSC_Settings::sanitize_business( $business ) ) );
                SSC_Setup::complete_step( 'identity' );
                self::prg( 'knowledge', array( 'saved' => 1 ) );
        }

        /**
         * Step 2: business knowledge.
         */
        protected function save_knowledge() {
                // Structured entries from the wizard quick form.
                $items = array();
                if ( isset( $_POST['ki'] ) && is_array( $_POST['ki'] ) ) {
                        foreach ( wp_unslash( $_POST['ki'] ) as $i => $item ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below.
                                if ( ! is_array( $item ) ) {
                                        continue;
                                }
                                $title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
                                $body  = isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '';
                                if ( '' === trim( $title ) && '' === trim( $body ) ) {
                                        continue;
                                }
                                $type = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : 'general';
                                $items[] = array(
                                        'id'      => 'ki-' . ( $i + 1 ),
                                        'type'    => $type,
                                        'title'   => $title,
                                        'content' => $body,
                                );
                        }
                }
                if ( ! empty( $items ) ) {
                        SSC_Settings::update( array( 'knowledge_items' => $items ) );
                }

                // Optional website page import (explicitly initiated by the admin).
                // The knowledge step is ALWAYS marked complete before redirecting —
                // otherwise a failed/partial import left the wizard stuck on step 2.
                if ( ! empty( $_POST['import_url'] ) ) {
                        $url = esc_url_raw( wp_unslash( $_POST['import_url'] ) );
                        $result = $this->import_page( $url );
                        SSC_Setup::complete_step( 'knowledge' );
                        self::prg( 'connection', array( 'saved' => 1, 'import' => $result ? 'ok' : 'fail' ) );
                }

                SSC_Setup::complete_step( 'knowledge' );
                self::prg( 'connection', array( 'saved' => 1 ) );
        }

        /**
         * Import a website page into the KB (admin-initiated, SSRF-guarded).
         *
         * @param string $url URL.
         * @return bool
         */
        protected function import_page( $url ) {
                if ( '' === $url || ! SSC_HTTP::is_safe_url( $url, true ) ) {
                        return false;
                }
                $response = wp_safe_remote_get( $url, array( 'timeout' => 20 ) );
                if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
                        return false;
                }
                $html = wp_remote_retrieve_body( $response );
                if ( '' === trim( (string) $html ) ) {
                        return false;
                }

                // Extract readable text: title + body with scripts/styles stripped.
                $title = '';
                if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', (string) $html, $m ) ) {
                        $title = sanitize_text_field( trim( $m[1] ) );
                }
                $text = preg_replace( '/<(script|style|noscript|nav|header|footer)[^>]*>.*?<\/\1>/is', ' ', (string) $html );
                $text = wp_strip_all_tags( (string) $text );
                $text = preg_replace( '/\s+/u', ' ', $text );
                $text = trim( (string) $text );
                if ( mb_strlen( $text ) < 200 ) {
                        return false; // Nothing meaningful to learn.
                }
                $doc_id = 'url-' . substr( md5( $url ), 0, 12 );
                SSC_Schema::kb_delete_document( $doc_id );
                $chunks = SSC_Schema::kb_insert_document( $doc_id, '' !== $title ? $title : $url, $text );
                return $chunks > 0;
        }

        /**
         * Step 3: AI provider connection (verification happens through REST
         * test-connection BEFORE this save - the wizard JS refuses otherwise).
         */
        protected function save_connection() {
                $provider = isset( $_POST['ai_provider'] ) ? sanitize_key( wp_unslash( $_POST['ai_provider'] ) ) : 'none';
                $labels   = SSC_Providers::labels();
                if ( ! isset( $labels[ $provider ] ) ) {
                        $provider = 'none';
                }

                $patch = array( 'ai_provider' => $provider );

                // Per-provider fields.
                foreach ( array( 'openai', 'gemini', 'claude', 'openrouter', 'custom' ) as $pid ) {
                        if ( isset( $_POST[ $pid . '_model' ] ) ) {
                                $patch[ $pid . '_model' ] = sanitize_text_field( wp_unslash( $_POST[ $pid . '_model' ] ) );
                        }
                }
                if ( isset( $_POST['custom_endpoint'] ) ) {
                        $patch['custom_endpoint'] = esc_url_raw( wp_unslash( $_POST['custom_endpoint'] ) );
                }
                if ( isset( $_POST['ai_webhook_url'] ) ) {
                        $patch['ai_webhook_url'] = esc_url_raw( wp_unslash( $_POST['ai_webhook_url'] ) );
                }
                SSC_Settings::update( $patch );

                // Secrets: empty POST = keep existing; explicit clear flag = remove.
                $secret_map = array(
                        'openai'     => 'openai_api_key',
                        'gemini'     => 'gemini_api_key',
                        'claude'     => 'claude_api_key',
                        'openrouter' => 'openrouter_api_key',
                        'custom'     => 'custom_api_key',
                );
                foreach ( $secret_map as $pid => $key ) {
                        if ( isset( $_POST[ $key ] ) && '' !== trim( (string) wp_unslash( $_POST[ $key ] ) ) ) {
                                if ( ! SSC_Settings::set_secret( $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) ) {
                                        self::prg( 'connection', array( 'saved' => 1, 'secret_error' => 1 ) );
                                }
                        } elseif ( isset( $_POST[ $key . '_clear' ] ) ) {
                                SSC_Settings::set_secret( $key, '' );
                        }
                }
                if ( isset( $_POST['ai_webhook_secret'] ) && '' !== trim( (string) wp_unslash( $_POST['ai_webhook_secret'] ) ) ) {
                        if ( ! SSC_Settings::set_secret( 'ai_webhook_secret', sanitize_text_field( wp_unslash( $_POST['ai_webhook_secret'] ) ) ) ) {
                                self::prg( 'connection', array( 'saved' => 1, 'secret_error' => 1 ) );
                        }
                }

                // Gate: the step completes only with a VALID, RECORDED verification
         // for the provider being saved (recorded by test-connection REST call).
                if ( 'none' !== $provider && SSC_Setup::connection_is_verified() ) {
                        SSC_Setup::complete_step( 'connection' );
                        self::prg( 'appearance', array( 'saved' => 1 ) );
                }
                $extra = array( 'saved' => 1 );
                if ( 'none' !== $provider ) {
                        $extra['verify'] = 'required';
                } else {
                        // Offline mode is allowed (bank/fallback answers) but flagged.
                        SSC_Setup::complete_step( 'connection' );
                        self::prg( 'appearance', array( 'saved' => 1, 'offline' => 1 ) );
                }
                self::prg( 'connection', $extra );
        }

        /**
         * Step 4: appearance.
         */
        protected function save_appearance() {
                $patch = array();
                $plain = array( 'theme_mode', 'position', 'direction', 'font_family', 'assistant_display_name', 'welcome_title', 'welcome_text', 'disclaimer' );
                foreach ( $plain as $key ) {
                        if ( isset( $_POST[ $key ] ) ) {
                                $patch[ $key ] = SSC_Settings::sanitize_value( $key, wp_unslash( $_POST[ $key ] ) );
                        }
                }
                foreach ( array( 'primary_color', 'user_bubble_color', 'bot_bubble_color', 'avatar_url', 'launcher_icon_url', 'font_name', 'font_url' ) as $key ) {
                        if ( isset( $_POST[ $key ] ) ) {
                                $patch[ $key ] = SSC_Settings::sanitize_value( $key, wp_unslash( $_POST[ $key ] ) );
                        }
                }
                foreach ( array( 'launcher_size', 'font_size', 'window_width', 'window_radius', 'bubble_radius' ) as $key ) {
                        if ( isset( $_POST[ $key ] ) ) {
                                $patch[ $key ] = (int) $_POST[ $key ];
                        }
                }
                SSC_Settings::update( $patch );
                SSC_Setup::complete_step( 'appearance' );
                self::prg( 'review', array( 'saved' => 1 ) );
        }

        /**
         * Step 5: review + explicit publish.
         */
        protected function save_review() {
                $publish = ! empty( $_POST['publish'] );

                // Privacy disclosure acknowledgment (required before publishing).
                if ( ! empty( $_POST['privacy_ack'] ) ) {
                        SSC_Settings::update( array( 'privacy_acknowledged' => 'yes' ) );
                }

                if ( $publish ) {
                        $ready = true;
                        foreach ( SSC_Setup::readiness() as $item ) {
                                if ( ! $item['done'] ) {
                                        $ready = false;
                                        break;
                                }
                        }
                        if ( ! $ready ) {
                                self::prg( 'review', array( 'error' => 'not_ready' ) );
                        }
                        SSC_Setup::complete_step( 'review' );
                        SSC_Settings::update( array( 'enabled' => 'yes' ) );
                        SSC_Setup::publish();
                        self::prg( 'review', array( 'published' => 1 ) );
                }

                // Save-only (finish later without publishing).
                SSC_Setup::complete_step( 'review' );
                self::prg( 'review', array( 'saved' => 1 ) );
        }

        /**
         * PRG helper for wizard steps.
         *
         * @param string $step Step id.
         * @param array  $args Extra query args.
         */
        protected static function prg( $step, $args = array() ) {
                $args['page'] = 'ssc-wizard';
                $args['step'] = $step;
                wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * Render the wizard shell.
         */
        public function render() {
                $state    = SSC_Setup::state();
                $steps    = SSC_Setup::steps();
                $labels   = array(
                        'identity'   => array( __( 'Business Identity', 'smart-support-chatbot' ), 'M1 20h5V8H1v12Z M13 20h5V4h-5v16Z' ),
                        'knowledge'  => array( __( 'Business Knowledge', 'smart-support-chatbot' ), 'M4 19V5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2Z M8 7h7 M8 11h7' ),
                        'connection' => array( __( 'AI Connection', 'smart-support-chatbot' ), 'M5 12h14 M12 5v14' ),
                        'appearance' => array( __( 'Appearance', 'smart-support-chatbot' ), 'M12 3a9 9 0 1 0 9 9h-9V3Z' ),
                        'review'     => array( __( 'Verify & Launch', 'smart-support-chatbot' ), 'M20 6 9 17l-5-5' ),
                );
                $current  = $this->step;
                $step_index = array_search( $current, $steps, true );

                require SSC_CHATBOT_DIR . 'includes/admin/views/wizard.php';
        }
}
