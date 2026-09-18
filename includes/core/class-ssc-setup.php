<?php
/**
 * Setup wizard state machine and server-side publication gate.
 *
 * Security contract:
 * - The public chatbot is rendered and its endpoints answer ONLY when
 *   SSC_Setup::is_live() returns true: all wizard steps complete, the
 *   administrator explicitly published, and the master switch is on.
 * - Frontend tampering cannot bypass this: gating happens server-side in
 *   SSC_Frontend (render), SSC_REST and SSC_Ajax (endpoints).
 * - The wizard NEVER blocks the WordPress dashboard or other plugins: it is
 *   a regular admin page that redirects once after activation.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Setup state class.
 */
class SSC_Setup {

        const OPTION = 'ssc_chatbot_setup';

        /**
         * Wizard step ids in order.
         *
         * @return string[]
         */
        public static function steps() {
                return array( 'identity', 'knowledge', 'connection', 'appearance', 'review' );
        }

        /**
         * Full state (merged with defaults).
         *
         * @return array
         */
        public static function state() {
                $saved = get_option( self::OPTION, array() );
                $saved = is_array( $saved ) ? $saved : array();
                return wp_parse_args(
                        $saved,
                        array(
                                'steps'      => array(),
                                'published'  => false,
                                'started_at' => 0,
                                'completed_at' => 0,
                                'connection_verified' => array(),
                                'identity_verified'   => 0,
                                'upgraded_from' => '',
                                'redirect_done' => false,
                        )
                );
        }

        /**
         * Persist partial state.
         *
         * @param array $patch Partial state.
         */
        public static function update_state( $patch ) {
                $state = self::state();
                $next  = array_merge( $state, is_array( $patch ) ? $patch : array() );
                update_option( self::OPTION, $next, false );
        }

        /**
         * Mark one step complete (auto-saves wizard progress).
         *
         * @param string $step Step id.
         */
        public static function complete_step( $step ) {
                if ( ! in_array( $step, self::steps(), true ) ) {
                        return;
                }
                $state = self::state();
                $steps = is_array( $state['steps'] ) ? $state['steps'] : array();
                if ( ! empty( $steps[ $step ] ) ) {
                        return;
                }
                $steps[ $step ] = true;
                self::update_state(
                        array(
                                'steps'      => $steps,
                                'started_at' => $state['started_at'] ? $state['started_at'] : time(),
                        )
                );
                if ( self::is_complete() && ! self::state()['completed_at'] ) {
                        self::update_state( array( 'completed_at' => time() ) );
                }
        }

        /**
         * Reopen a step (validation failed later / user returns).
         *
         * @param string $step Step id.
         */
        public static function reopen_step( $step ) {
                $state = self::state();
                $steps = is_array( $state['steps'] ) ? $state['steps'] : array();
                if ( isset( $steps[ $step ] ) ) {
                        unset( $steps[ $step ] );
                        self::update_state( array( 'steps' => $steps, 'completed_at' => 0 ) );
                }
        }

        /**
         * Are all wizard steps complete?
         *
         * @return bool
         */
        public static function is_complete() {
                $steps = self::state()['steps'];
                foreach ( self::steps() as $step ) {
                        if ( empty( $steps[ $step ] ) ) {
                                return false;
                        }
                }
                return true;
        }

        /**
         * THE publication gate. Everything public consults this method only.
         *
         * @return bool
         */
        public static function is_live() {
                if ( 'yes' !== SSC_Settings::get( 'enabled', 'no' ) ) {
                        return false;
                }
                $state = self::state();
                if ( empty( $state['published'] ) ) {
                        return false;
                }
                return self::is_complete();
        }

        /**
         * Explicit publish action (wizard review step or dashboard toggle).
         */
        public static function publish() {
                self::update_state( array( 'published' => true ) );
        }

        /**
         * Unpublish (takes the public chatbot down immediately).
         */
        public static function unpublish() {
                self::update_state( array( 'published' => false ) );
        }

        /**
         * Wizard entry point URL.
         *
         * @return string
         */
        public static function wizard_url() {
                return admin_url( 'admin.php?page=ssc-wizard' );
        }

        /**
         * Initialize the setup state once (idempotent):
         * - legacy 4.x data -> auto-complete + preserve publication, so an
         *   upgraded site NEVER loses a live chatbot;
         * - fresh install -> wizard starts empty, chatbot stays dark.
         *
         * Called from BOTH the activation hook and the DB upgrade path,
         * because WordPress in-place updates do not re-run activation hooks.
         */
        public static function initialize_state() {
                $state = self::state();

                if ( ! empty( $state['started_at'] ) || ! empty( $state['completed_at'] ) || ! empty( $state['upgraded_from'] ) ) {
                        return;
                }

                $settings = get_option( SSC_Settings::OPTION_KEY, array() );
                $old      = is_array( $settings ) ? $settings : array();

                // Existing 4.x data without 5.x setup state -> safe upgrade path.
                $legacy = isset( $old['company_id'] ) || isset( $old['show_company'] ) || isset( $old['ai_system_prompt'] ) || isset( $old['product_knowledge'] );
                $was_on = isset( $old['enabled'] ) && 'yes' === $old['enabled'];

                if ( $legacy ) {
                        $steps = array();
                        foreach ( self::steps() as $s ) {
                                $steps[ $s ] = true;
                        }
                        self::update_state(
                                array(
                                        'steps'         => $steps,
                                        'completed_at'  => time(),
                                        'published'     => $was_on,
                                        'upgraded_from' => '4.5',
                                        'started_at'    => time(),
                                )
                        );
                        // Mirror the old master switch into the new publication model.
                        SSC_Settings::update( array( 'enabled' => $was_on ? 'yes' : 'no' ) );
                        return;
                }

                // Brand-new installation: wizard starts empty, chatbot stays dark.
                self::update_state( array( 'started_at' => time() ) );
        }

        /**
         * Activation hook.
         */
        public static function on_activation() {
                self::initialize_state();
        }

        /* ------------------------------------------------------------------ *
         * Connection verification (must survive credential changes).
         * ------------------------------------------------------------------ */

        /**
         * Fingerprint of an AI connection. Parameterless form reads the CURRENT
         * SAVED connection; the wizard/REST layer passes the credentials that
         * were ACTUALLY TESTED so verification survives the save-then-continue
         * flow (test entered credentials -> save -> fingerprint matches).
         *
         * Any change to provider, key, model or endpoint produces a different
         * fingerprint, which invalidates previous verification.
         *
         * @param string|null $provider_id Provider id (null = saved current).
         * @param string|null $api_key     Key (null = saved).
         * @param string|null $model       Model (null = saved).
         * @param string|null $endpoint    Endpoint (null = saved).
         * @return string
         */
        public static function connection_fingerprint( $provider_id = null, $api_key = null, $model = null, $endpoint = null ) {
                if ( null === $provider_id ) {
                        $provider = SSC_Providers::current();
                        if ( null === $provider ) {
                                return md5( 'none||||' );
                        }
                        $creds        = $provider->saved_credentials();
                        $provider_id  = $provider->id();
                        $api_key      = $creds['api_key'];
                        $model        = $creds['model'];
                        $endpoint     = $creds['endpoint'];
                }
                return md5( $provider_id . '|' . (string) $api_key . '|' . (string) $model . '|' . (string) $endpoint );
        }

        /**
         * Record a successful connection verification.
         *
         * @param string $mode            'connection' or 'identity'.
         * @param string $note            Optional note.
         * @param string $tested_fingerprint Explicit fingerprint of the
         *                                    credentials that were ACTUALLY
         *                                    tested (wizard tests unsaved
         *                                    input). Empty = current saved state.
         */
        public static function mark_verified( $mode, $note = '', $tested_fingerprint = '' ) {
                $patch = array(
                        'connection_verified' => array(
                                'fingerprint' => '' !== $tested_fingerprint ? $tested_fingerprint : self::connection_fingerprint(),
                                'at'          => time(),
                        ),
                );
                if ( 'identity' === $mode ) {
                        $patch['identity_verified'] = time();
                }
                self::update_state( $patch );
        }

        /**
         * Is the recorded connection verification still valid?
         *
         * @return bool
         */
        public static function connection_is_verified() {
                $v = self::state()['connection_verified'];
                if ( empty( $v['fingerprint'] ) ) {
                        return false;
                }
                return hash_equals( (string) $v['fingerprint'], self::connection_fingerprint() );
        }

        /**
         * Readiness checklist for the review step / dashboard.
         *
         * @return array[] Each item: id, label, done.
         */
        public static function readiness() {
                $b        = SSC_Settings::business();
                $identity = '' !== trim( $b['org_name'] );
                $knowledge = self::knowledge_ready();
                $conn      = self::connection_is_verified();
                $identity_verified = ! empty( self::state()['identity_verified'] );
                $appearance = '' !== trim( (string) SSC_Settings::get( 'welcome_text', '' ) );
                $privacy    = ( 'yes' === SSC_Settings::get( 'privacy_acknowledged', 'no' ) );

                return array(
                        array( 'id' => 'identity', 'label' => __( 'Business profile completed', 'smart-support-chatbot' ), 'done' => $identity ),
                        array( 'id' => 'knowledge', 'label' => __( 'Essential business knowledge added', 'smart-support-chatbot' ), 'done' => $knowledge ),
                        array( 'id' => 'connection', 'label' => __( 'AI provider connection verified', 'smart-support-chatbot' ), 'done' => $conn ),
                        array( 'id' => 'identity_test', 'label' => __( 'Business identity test passed', 'smart-support-chatbot' ), 'done' => $identity_verified ),
                        array( 'id' => 'appearance', 'label' => __( 'Appearance configured', 'smart-support-chatbot' ), 'done' => $appearance ),
                        array( 'id' => 'privacy', 'label' => __( 'Privacy disclosures acknowledged', 'smart-support-chatbot' ), 'done' => $privacy ),
                );
        }

        /**
         * Does the knowledge base contain anything usable?
         *
         * @return bool
         */
        public static function knowledge_ready() {
                $items = (array) SSC_Settings::get( 'knowledge_items', array() );
                foreach ( $items as $item ) {
                        if ( ! empty( $item['content'] ) ) {
                                return true;
                        }
                }
                foreach ( (array) SSC_Settings::get( 'products', array() ) as $p ) {
                        if ( ! empty( $p['summary'] ) ) {
                                return true;
                        }
                }
                return SSC_Schema::kb_count() > 0 || SSC_Schema::qa_count() > 0;
        }

        /**
         * Should the one-time activation redirect happen (new install)?
         *
         * @return bool
         */
        public static function needs_wizard_redirect() {
                $state = self::state();
                if ( $state['redirect_done'] || $state['completed_at'] ) {
                        return false;
                }
                return ! empty( $state['started_at'] );
        }

        /**
         * Remember the redirect so it happens once.
         */
        public static function mark_wizard_redirected() {
                self::update_state( array( 'redirect_done' => true ) );
        }
}
