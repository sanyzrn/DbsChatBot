<?php
/**
 * REST API (namespace ssc/v1 - unchanged from 4.x for cache compatibility).
 *
 * Public endpoints require the chatbot to be LIVE (setup complete +
 * published) - enforced server-side, so frontend tampering cannot expose an
 * unconfigured assistant.
 *
 * Nonce policy: public chat endpoints stay nonce-less BY DESIGN (page-cache
 * compatibility); abuse protection is layered server-side (rate limits,
 * honeypot, size caps, whitelists). Sites that disagree can re-enable
 * enforcement with the ssc_enforce_rest_nonce filter.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST class.
 */
class SSC_REST {

	const NS = 'ssc/v1';

	/**
	 * Chat engine instance.
	 *
	 * @var SSC_Chat_Engine
	 */
	protected $engine;

	/**
	 * Constructor.
	 *
	 * @param SSC_Chat_Engine $engine Chat engine.
	 */
	public function __construct( $engine = null ) {
		$this->engine = null === $engine ? new SSC_Chat_Engine() : $engine;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Public permission callback (optional nonce enforcement).
	 *
	 * @param WP_REST_Request|null $request Request, when one is available.
	 * @return true|WP_Error
	 */
	public function public_permission( $request = null ) {
		if ( $request && strlen( $request->get_body() ) > 131072 ) {
			return new WP_Error( 'ssc_too_large', __( 'The request is too large.', 'smart-support-chatbot' ), array( 'status' => 413 ) );
		}
		if ( apply_filters( 'ssc_enforce_rest_nonce', false ) ) {
			$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error( 'ssc_bad_nonce', __( 'Your session has expired. Please refresh the page.', 'smart-support-chatbot' ), array( 'status' => 403 ) );
			}
		}
		return true;
	}

	/**
	 * Admin-only permission callback.
	 *
	 * @return true|WP_Error
	 */
	public function admin_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'ssc_forbidden', __( 'Insufficient permissions.', 'smart-support-chatbot' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Register all routes.
	 */
	public function register_routes() {
		$public = array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'public_permission' ),
		);
		$admin  = array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'admin_permission' ),
		);

		/* Public: chat. */
		register_rest_route(
			self::NS,
			'/chat',
			array_merge(
				$public,
				array(
					'callback' => array( $this, 'chat' ),
					'args'     => array(
						'message' => array(
							'required'          => true,
							'maxLength'         => 2000,
							'validate_callback' => 'rest_validate_request_arg',
							'minLength'         => 1,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'product' => array(
							'type'              => 'string',
							'default'           => 'general',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'history' => array(
							'maxLength'         => 64000,
							'validate_callback' => 'rest_validate_request_arg',
							'type'              => 'string',
							'default'           => '',
						),
						'cid'     => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			)
		);

		/* Public: streaming chat (SSE). */
		register_rest_route(
			self::NS,
			'/chat-stream',
			array_merge(
				$public,
				array(
					'callback' => array( $this, 'chat_stream' ),
					'args'     => array(
						'message' => array(
							'required'          => true,
							'maxLength'         => 2000,
							'validate_callback' => 'rest_validate_request_arg',
							'minLength'         => 1,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'product' => array(
							'type'              => 'string',
							'default'           => 'general',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'history' => array(
							'maxLength'         => 64000,
							'validate_callback' => 'rest_validate_request_arg',
							'type'              => 'string',
							'default'           => '',
						),
						'cid'     => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			)
		);

		/* Public: submit (leads module only). */
		register_rest_route(
			self::NS,
			'/submit',
			array_merge(
				$public,
				array(
					'callback' => array( $this, 'submit' ),
				)
			)
		);

		/* Public: suggestions (faq module only). */
		register_rest_route(
			self::NS,
			'/suggest',
			array_merge(
				$public,
				array(
					'callback' => array( $this, 'suggest' ),
					'args'     => array(
						'term'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'product' => array(
							'type'              => 'string',
							'default'           => 'general',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			)
		);

		/* Public: feedback (needs a log row => history module only). */
		register_rest_route(
			self::NS,
			'/feedback',
			array_merge(
				$public,
				array(
					'callback' => array( $this, 'feedback' ),
					'args'     => array(
						'log_id'    => array(
							'required' => true,
							'type'     => 'integer',
						),
						'rating'    => array(
							'required' => true,
							'type'     => 'integer',
						),
						'log_token' => array(
							'required'          => true,
							'maxLength'         => 2000,
							'validate_callback' => 'rest_validate_request_arg',
							'minLength'         => 1,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			)
		);

		/* Public: CSAT (module only). */
		register_rest_route(
			self::NS,
			'/csat',
			array_merge(
				$public,
				array(
					'callback' => array( $this, 'csat' ),
					'args'     => array(
						'score' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				)
			)
		);

		/* Admin: connection test with UNSAVED credentials (wizard). */
		register_rest_route(
			self::NS,
			'/test-connection',
			array_merge(
				$admin,
				array(
					'callback' => array( $this, 'test_connection' ),
				)
			)
		);

		/* Admin: pre-launch preview chat (wizard step 5). */
		register_rest_route(
			self::NS,
			'/preview-chat',
			array_merge(
				$admin,
				array(
					'callback' => array( $this, 'preview_chat' ),
					'args'     => array(
						'message' => array(
							'required'          => true,
							'maxLength'         => 2000,
							'validate_callback' => 'rest_validate_request_arg',
							'minLength'         => 1,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'product' => array(
							'type'              => 'string',
							'default'           => 'general',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'history' => array(
							'maxLength'         => 64000,
							'validate_callback' => 'rest_validate_request_arg',
							'type'              => 'string',
							'default'           => '',
						),
					),
				)
			)
		);

		/* Admin: business identity test. */
		register_rest_route(
			self::NS,
			'/test-identity',
			array_merge(
				$admin,
				array(
					'callback' => array( $this, 'test_identity' ),
				)
			)
		);
	}

	/**
	 * Streaming chat endpoint (SSE). Terminates the request after the stream.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return void|WP_Error
	 */
	public function chat_stream( $request ) {
		if ( ! SSC_Setup::is_live() ) {
			return new WP_Error( 'ssc_not_live', __( 'The assistant is not available yet.', 'smart-support-chatbot' ), array( 'status' => 403 ) );
		}
		$cid = (string) $request->get_param( 'cid' );
		if ( ! $this->engine->allow_request( 'chat', $cid ) ) {
			return new WP_Error( 'ssc_rate_limited', __( 'You have reached the daily usage limit. Please try again tomorrow.', 'smart-support-chatbot' ), array( 'status' => 429 ) );
		}

		$history = json_decode( (string) $request->get_param( 'history' ), true );
		$this->engine->set_context( $this->engine->client_ip() );

		SSC_Stream::serve(
			$this->engine,
			(string) $request->get_param( 'message' ),
			(string) $request->get_param( 'product' ),
			is_array( $history ) ? $history : array()
		);
	}

	/**
	 * Chat endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function chat( $request ) {
		if ( ! SSC_Setup::is_live() ) {
			return new WP_Error( 'ssc_not_live', __( 'The assistant is not available yet.', 'smart-support-chatbot' ), array( 'status' => 403 ) );
		}
		$cid = (string) $request->get_param( 'cid' );
		if ( ! $this->engine->allow_request( 'chat', $cid ) ) {
			return new WP_Error( 'ssc_rate_limited', __( 'You have reached the daily usage limit. Please try again tomorrow.', 'smart-support-chatbot' ), array( 'status' => 429 ) );
		}

		$history = json_decode( (string) $request->get_param( 'history' ), true );
		$this->engine->set_context( $this->engine->client_ip() );
		$result = $this->engine->chat(
			(string) $request->get_param( 'message' ),
			(string) $request->get_param( 'product' ),
			is_array( $history ) ? $history : array()
		);

		if ( ! $result['ok'] ) {
			return new WP_Error( 'ssc_chat_failed', __( 'The message could not be processed.', 'smart-support-chatbot' ), array( 'status' => 400 ) );
		}

		// Admin-only diagnostics: real provider errors never reach visitors.
		$reply = $result['reply'];
		if ( 'unanswered' === $result['source'] && '' !== $this->engine->last_error && current_user_can( 'manage_options' ) ) {
			$reply = '⚠️ ' . __( 'Admin-only notice — AI engine error:', 'smart-support-chatbot' ) . ' ' . $this->engine->last_error;
		}

		return rest_ensure_response(
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
	 * Submit endpoint (leads module gate).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit( $request ) {
		if ( ! SSC_Setup::is_live() ) {
			return new WP_Error( 'ssc_not_live', __( 'The assistant is not available yet.', 'smart-support-chatbot' ), array( 'status' => 403 ) );
		}
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$type = isset( $params['type'] ) ? sanitize_key( $params['type'] ) : 'consult';

		// Strict module isolation: each submission type requires its own module.
		if ( 'pharma_adr' === $type ) {
			if ( ! SSC_Modules::is_active( 'pharma' ) ) {
				return new WP_Error( 'ssc_module_off', __( 'ADR reporting is disabled on this site.', 'smart-support-chatbot' ), array( 'status' => 404 ) );
			}
			$module = SSC_Modules::get( 'pharma' );
		} else {
			if ( ! SSC_Modules::is_active( 'leads' ) ) {
				return new WP_Error( 'ssc_module_off', __( 'Form submission is disabled on this site.', 'smart-support-chatbot' ), array( 'status' => 404 ) );
			}
			$module = SSC_Modules::get( 'leads' );
		}

		$cid = isset( $params['cid'] ) ? (string) $params['cid'] : '';
		if ( ! $this->engine->allow_request( 'submit', $cid ) ) {
			return new WP_Error( 'ssc_rate_limited', __( 'Too many submissions today. Please try again tomorrow.', 'smart-support-chatbot' ), array( 'status' => 429 ) );
		}
		$result = $module->handle_submission( $params, $this->engine->client_ip() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Suggest endpoint (faq module gate).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function suggest( $request ) {
		if ( ! SSC_Setup::is_live() || ! SSC_Modules::is_active( 'faq' ) ) {
			return rest_ensure_response( array( 'items' => array() ) );
		}
		$cid = (string) $request->get_param( 'cid' );
		if ( ! $this->engine->allow_request( 'suggest', $cid ) ) {
			return rest_ensure_response( array( 'items' => array() ) );
		}
		$items = SSC_Knowledge::related_questions( (string) $request->get_param( 'product' ), (string) $request->get_param( 'term' ), 5 );
		$out   = array();
		foreach ( $items as $item ) {
			$out[] = array( 'question' => $item['question'] );
		}
		return rest_ensure_response( array( 'items' => $out ) );
	}

	/**
	 * Feedback endpoint (history module gate + token).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function feedback( $request ) {
		if ( ! SSC_Setup::is_live() || ! SSC_Modules::is_active( 'history' ) ) {
			return new WP_Error( 'ssc_module_off', __( 'Feedback is disabled on this site.', 'smart-support-chatbot' ), array( 'status' => 404 ) );
		}
		$cid    = (string) $request->get_param( 'cid' );
		$log_id = (int) $request->get_param( 'log_id' );
		$rating = (int) $request->get_param( 'rating' );
		$token  = (string) $request->get_param( 'log_token' );
		if ( ! $this->engine->allow_request( 'feedback', $cid ) ) {
			return new WP_Error( 'ssc_rate_limited', __( 'Too many votes today.', 'smart-support-chatbot' ), array( 'status' => 429 ) );
		}
		if ( ! in_array( $rating, array( 1, -1 ), true ) ) {
			return new WP_Error( 'ssc_invalid_rating', __( 'Invalid rating.', 'smart-support-chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! $this->engine->verify_log_token( $log_id, $token ) ) {
			return new WP_Error( 'ssc_bad_token', __( 'Invalid feedback token.', 'smart-support-chatbot' ), array( 'status' => 403 ) );
		}
		SSC_Schema::set_chatlog_rating( $log_id, $rating );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * CSAT endpoint (module gate + fixed cap).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function csat( $request ) {
		if ( ! SSC_Setup::is_live() || ! SSC_Modules::is_active( 'csat' ) ) {
			return new WP_Error( 'ssc_module_off', __( 'The survey is disabled on this site.', 'smart-support-chatbot' ), array( 'status' => 404 ) );
		}
		$score = (int) $request->get_param( 'score' );
		if ( $score < 1 || $score > 5 ) {
			return new WP_Error( 'ssc_invalid_score', __( 'Invalid score.', 'smart-support-chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! $this->engine->allow_request( 'csat', '' ) ) {
			return new WP_Error( 'ssc_rate_limited', __( 'Too many votes today.', 'smart-support-chatbot' ), array( 'status' => 429 ) );
		}
		SSC_Schema::record_csat( $score );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Admin: real connection test (accepts UNSAVED credentials from wizard).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function test_connection( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$provider_id = isset( $params['provider'] ) ? sanitize_key( $params['provider'] ) : (string) SSC_Settings::get( 'ai_provider', 'none' );
		$provider    = SSC_Providers::get( $provider_id );
		if ( null === $provider ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'message' => __( 'Unknown provider.', 'smart-support-chatbot' ),
				)
			);
		}
		$overrides = array();
		foreach ( array( 'api_key', 'model', 'endpoint' ) as $field ) {
			if ( isset( $params[ $field ] ) && '' !== trim( (string) $params[ $field ] ) ) {
				$overrides[ $field ] = sanitize_text_field( (string) $params[ $field ] );
			}
		}
		$result = $provider->test_connection( $overrides );

		// Record WHAT WAS TESTED: the fingerprint of the exact credentials the
		// provider just validated. When the wizard saves those same values the
		// saved-state fingerprint matches and step 3 completes; changing any
		// credential afterwards invalidates it until re-tested.
		if ( $result['ok'] ) {
			$saved  = $provider->saved_credentials();
			$tested = SSC_Setup::connection_fingerprint(
				$provider_id,
				isset( $overrides['api_key'] ) ? $overrides['api_key'] : $saved['api_key'],
				isset( $overrides['model'] ) ? $overrides['model'] : $saved['model'],
				isset( $overrides['endpoint'] ) ? $overrides['endpoint'] : $saved['endpoint']
			);
			SSC_Setup::mark_verified( 'connection', '', $tested );
		}

		return rest_ensure_response(
			array(
				'ok'      => $result['ok'],
				'reply'   => isset( $result['text'] ) ? $result['text'] : '',
				'code'    => isset( $result['error']['code'] ) ? $result['error']['code'] : '',
				'message' => $result['ok'] ? __( 'Connection verified — the model replied successfully.', 'smart-support-chatbot' ) : ( isset( $result['error']['friendly'] ) ? $result['error']['friendly'] : '' ),
			)
		);
	}

	/**
	 * Admin: pre-launch preview chat (wizard step 5). Identical pipeline to
	 * the public chat but capability-gated - the assistant can be tested
	 * BEFORE it is published, without ever exposing it to visitors.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview_chat( $request ) {
		$history = json_decode( (string) $request->get_param( 'history' ), true );
		$this->engine->set_context( $this->engine->client_ip() );
		$result = $this->engine->chat(
			(string) $request->get_param( 'message' ),
			(string) $request->get_param( 'product' ),
			is_array( $history ) ? $history : array()
		);
		if ( ! $result['ok'] ) {
			return new WP_Error( 'ssc_chat_failed', __( 'The message could not be processed.', 'smart-support-chatbot' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response(
			array(
				'reply'   => $result['reply'],
				'source'  => $result['source'],
				'handoff' => ! empty( $result['handoff'] ),
				'flags'   => isset( $result['flags'] ) ? (object) $result['flags'] : new stdClass(),
			)
		);
	}

	/**
	 * Admin: business identity test — proves the assistant knows WHO it
	 * represents, not merely that the API answers.
	 *
	 * @return WP_REST_Response
	 */
	public function test_identity() {
		$provider = SSC_Providers::current();
		if ( null === $provider ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'message' => __( 'Configure and verify an AI provider first.', 'smart-support-chatbot' ),
				)
			);
		}
		$business = SSC_Settings::business();
		if ( '' === trim( (string) $business['org_name'] ) ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'message' => __( 'Complete the business profile (step 1) first.', 'smart-support-chatbot' ),
				)
			);
		}

		$question = SSC_Prompt_Builder::identity_test_questions()[0];
		$system   = SSC_Prompt_Builder::build_for_chat( $question, 'general' );
		$result   = $provider->generate_with(
			$provider->saved_credentials()['api_key'],
			$provider->saved_credentials()['model'] ? $provider->saved_credentials()['model'] : $provider->default_model(),
			$system,
			array(
				array(
					'role'    => 'user',
					'content' => $question,
				),
			),
			array(
				'endpoint'    => $provider->saved_credentials()['endpoint'],
				'max_tokens'  => 300,
				'temperature' => 0,
			)
		);

		if ( ! $result['ok'] ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'message' => isset( $result['error']['friendly'] ) ? $result['error']['friendly'] : __( 'The identity test could not run.', 'smart-support-chatbot' ),
				)
			);
		}

		// Content check: the organization name must appear in the answer.
		$answer   = mb_strtolower( $result['text'], 'UTF-8' );
		$org      = mb_strtolower( trim( (string) $business['org_name'] ), 'UTF-8' );
		$brand    = mb_strtolower( trim( (string) $business['brand_name'] ), 'UTF-8' );
		$mentions = ( '' !== $org && false !== mb_strpos( $answer, $org ) ) || ( '' !== $brand && false !== mb_strpos( $answer, $brand ) );
		if ( $mentions ) {
			SSC_Setup::mark_verified( 'identity' );
		}
		return rest_ensure_response(
			array(
				'ok'      => $mentions,
				'reply'   => $result['text'],
				'message' => $mentions ? __( 'Identity verified — the assistant correctly identified your organization.', 'smart-support-chatbot' ) : __( 'The model replied, but it did not identify your organization correctly. Review the business profile and knowledge, then run the test again.', 'smart-support-chatbot' ),
			)
		);
	}
}
