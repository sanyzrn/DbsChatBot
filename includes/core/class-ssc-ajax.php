<?php
/**
 * admin-ajax bridge (backward compatibility).
 *
 * Kept because page caches can serve 4.x frontend JS that calls admin-ajax.
 * The bridge delegates to the same engines as REST with identical gates.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * AJAX bridge class.
 */
class SSC_Ajax {

        /**
         * Chat engine.
         *
         * @var SSC_Chat_Engine
         */
        protected $engine;

        /**
         * Constructor.
         *
         * @param SSC_Chat_Engine $engine Engine.
         */
        public function __construct( $engine = null ) {
                $this->engine = null === $engine ? new SSC_Chat_Engine() : $engine;

                $public_actions = array(
                        'ssc_chatbot_chat'     => 'handle_chat',
                        'ssc_chatbot_submit'   => 'handle_submit',
                        'ssc_chatbot_feedback' => 'handle_feedback',
                        'ssc_chatbot_suggest'  => 'handle_suggest',
                        'ssc_chatbot_csat'     => 'handle_csat',
                );
                foreach ( $public_actions as $action => $method ) {
                        add_action( 'wp_ajax_' . $action, array( $this, $method ) );
                        add_action( 'wp_ajax_nopriv_' . $action, array( $this, $method ) );
                }
                add_action( 'wp_ajax_ssc_chatbot_test_ai', array( $this, 'handle_test_ai' ) );
        }

        /**
         * POST param helper.
         *
         * @param string $key     Key.
         * @param bool   $textarea Textarea sanitization.
         * @return string
         */
        protected function post( $key, $textarea = false ) {
                if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- public endpoints are rate-limited by design.
                        return '';
                }
                $value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                return $textarea ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
        }

        protected function public_permission() {
                if ( isset( $_SERVER['CONTENT_LENGTH'] ) && (int) $_SERVER['CONTENT_LENGTH'] > 131072 ) {
                        wp_send_json_error( array( 'message' => __( 'The request is too large.', 'smart-support-chatbot' ) ), 413 );
                }
                $rest = new SSC_REST( $this->engine );
                $result = $rest->public_permission();
                if ( is_wp_error( $result ) ) {
                        wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ), 403 );
                }
        }

        /**
         * Chat (legacy action).
         */
        public function handle_chat() {
                $this->public_permission();
                if ( ! SSC_Setup::is_live() ) {
                        wp_send_json_error( array( 'message' => __( 'The assistant is not available yet.', 'smart-support-chatbot' ) ), 403 );
                }
                $cid = $this->post( 'cid' );
                if ( ! $this->engine->allow_request( 'chat', $cid ) ) {
                        wp_send_json_error( array( 'message' => __( 'You have reached the daily usage limit. Please try again tomorrow.', 'smart-support-chatbot' ) ), 429 );
                }
                $history = json_decode( $this->post( 'history' ), true );
                $this->engine->set_context( $this->engine->client_ip() );
                $result = $this->engine->chat( $this->post( 'message', true ), $this->post( 'product' ), is_array( $history ) ? $history : array() );

                if ( ! $result['ok'] ) { wp_send_json_error( array( 'message' => __( 'The message could not be processed.', 'smart-support-chatbot' ) ), 400 ); }
                $reply = $result['reply'];
                if ( 'unanswered' === $result['source'] && '' !== $this->engine->last_error && current_user_can( 'manage_options' ) ) {
                        $reply = '⚠️ ' . __( 'Admin-only notice — AI engine error:', 'smart-support-chatbot' ) . ' ' . $this->engine->last_error;
                }
                wp_send_json_success(
                        array(
                                'reply'     => $reply,
                                'source'    => $result['source'],
                                'handoff'   => ! empty( $result['handoff'] ),
                                'log_id'    => (int) $result['log_id'],
                                'log_token' => (string) $result['log_token'],
                                'flags'     => isset( $result['flags'] ) ? (object) $result['flags'] : new stdClass(),
                        )
                );
        }

        /**
         * Submit (legacy action).
         */
        public function handle_submit() {
                $this->public_permission();
                if ( ! SSC_Setup::is_live() ) {
                        wp_send_json_error( array( 'message' => __( 'The assistant is not available yet.', 'smart-support-chatbot' ) ), 403 );
                }
                $type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'consult'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- public endpoint, rate-limited.

                // Strict module isolation (mirrors REST).
                if ( 'pharma_adr' === $type ) {
                        if ( ! SSC_Modules::is_active( 'pharma' ) ) {
                                wp_send_json_error( array( 'message' => __( 'ADR reporting is disabled on this site.', 'smart-support-chatbot' ) ), 404 );
                        }
                        $module = SSC_Modules::get( 'pharma' );
                } else {
                        if ( ! SSC_Modules::is_active( 'leads' ) ) {
                                wp_send_json_error( array( 'message' => __( 'Form submission is disabled on this site.', 'smart-support-chatbot' ) ), 404 );
                        }
                        $module = SSC_Modules::get( 'leads' );
                }

                if ( ! $this->engine->allow_request( 'submit', $this->post( 'cid' ) ) ) {
                        wp_send_json_error( array( 'message' => __( 'Too many submissions today. Please try again tomorrow.', 'smart-support-chatbot' ) ), 429 );
                }
                $result = $module->handle_submission( wp_unslash( $_POST ), $this->engine->client_ip() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- module sanitizes every field.
                if ( is_wp_error( $result ) ) {
                        wp_send_json_error( array( 'message' => $result->get_error_message() ), (int) ( $result->get_error_data()['status'] ?? 400 ) );
                }
                wp_send_json_success( $result );
        }

        /**
         * Feedback (legacy action).
         */
        public function handle_feedback() {
                $this->public_permission();
                if ( ! SSC_Setup::is_live() || ! SSC_Modules::is_active( 'history' ) ) {
                        wp_send_json_error( array( 'message' => __( 'Feedback is disabled on this site.', 'smart-support-chatbot' ) ), 404 );
                }
                $log_id = (int) $this->post( 'log_id' );
                $rating = (int) $this->post( 'rating' );
                $token  = $this->post( 'log_token' );
                if ( ! $this->engine->allow_request( 'feedback', $this->post( 'cid' ) ) ) { wp_send_json_error( array( 'message' => __( 'Too many votes today.', 'smart-support-chatbot' ) ), 429 ); }
                if ( ! in_array( $rating, array( 1, -1 ), true ) || ! $this->engine->verify_log_token( $log_id, $token ) ) {
                        wp_send_json_error( array( 'message' => __( 'Invalid feedback.', 'smart-support-chatbot' ) ), 400 );
                }
                SSC_Schema::set_chatlog_rating( $log_id, $rating );
                wp_send_json_success( array( 'ok' => true ) );
        }

        /**
         * Suggest (legacy action).
         */
        public function handle_suggest() {
                $this->public_permission();
                if ( ! SSC_Setup::is_live() || ! SSC_Modules::is_active( 'faq' ) ) {
                        wp_send_json_success( array( 'items' => array() ) );
                }
                if ( ! $this->engine->allow_request( 'suggest', $this->post( 'cid' ) ) ) {
                        wp_send_json_success( array( 'items' => array() ) );
                }
                $items = SSC_Knowledge::related_questions( $this->post( 'product' ), $this->post( 'term' ), 5 );
                $out   = array();
                foreach ( $items as $item ) {
                        $out[] = array( 'question' => $item['question'] );
                }
                wp_send_json_success( array( 'items' => $out ) );
        }

        /**
         * CSAT (legacy action).
         */
        public function handle_csat() {
                $this->public_permission();
                if ( ! SSC_Setup::is_live() || ! SSC_Modules::is_active( 'csat' ) ) {
                        wp_send_json_error( array( 'message' => __( 'The survey is disabled on this site.', 'smart-support-chatbot' ) ), 404 );
                }
                $score = (int) $this->post( 'score' );
                if ( $score < 1 || $score > 5 ) {
                        wp_send_json_error( array( 'message' => __( 'Invalid score.', 'smart-support-chatbot' ) ), 400 );
                }
                if ( ! $this->engine->allow_request( 'csat', '' ) ) {
                        wp_send_json_error( array( 'message' => __( 'Too many votes today.', 'smart-support-chatbot' ) ), 429 );
                }
                SSC_Schema::record_csat( $score );
                wp_send_json_success( array( 'ok' => true ) );
        }

        /**
         * Admin: connection test (legacy action) - delegates to provider system.
         */
        public function handle_test_ai() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'smart-support-chatbot' ) ), 403 );
                }
                check_ajax_referer( 'ssc_admin', 'nonce' );

                $provider = SSC_Providers::current();
                if ( null === $provider ) {
                        wp_send_json_error( array( 'message' => __( 'Select and save an AI provider first.', 'smart-support-chatbot' ) ) );
                }
                $result = $provider->test_connection();
                if ( $result['ok'] ) {
                        wp_send_json_success(
                                array(
                                        'message' => __( 'Connection verified — the model replied successfully.', 'smart-support-chatbot' ),
                                        'reply'   => $result['text'],
                                )
                        );
                }
                wp_send_json_error( array( 'message' => $result['error']['friendly'] ) );
        }
}
