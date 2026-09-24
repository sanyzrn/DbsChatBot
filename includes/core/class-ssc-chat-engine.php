<?php
/**
 * Chat engine: request orchestration for every transport (REST + AJAX).
 *
 * Pipeline: setup gate -> abuse protection -> conversation context ->
 * response engine (AI / bank / fallback per qa_mode) -> caching ->
 * optional logging (module-gated) -> handoff signalling.
 *
 * Privacy: transcript logging is opt-in. Non-pharma replies may use the
 * configured transient cache; aggregate counters are independent of logging.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chat engine class.
 */
class SSC_Chat_Engine {

	/**
	 * Source of the last reply: ai | bank | cache | filter | fallback | unanswered.
	 *
	 * @var string
	 */
	public $last_source = 'fallback';

	/**
	 * Provider failure detail (admin-only diagnostics; never shown publicly).
	 *
	 * @var string
	 */
	public $last_error = '';

	/**
	 * Chat token for feedback binding (module-gated).
	 *
	 * @var string
	 */
	protected $last_log_token = '';

	/**
	 * Runtime flags modules can set on the reply (e.g. adr_offer).
	 *
	 * @var array
	 */
	public $flags = array();

	/**
	 * Handle a chat request (already validated at transport layer).
	 *
	 * @param string        $message  User message.
	 * @param string        $product  Product scope id.
	 * @param array         $history  Conversation history (role/content pairs).
	 * @param callable|null $on_delta Receives each token as it streams, when the
	 *                                transport and provider both support it.
	 * @return array envelope: ok, reply, source, handoff, log_id, log_token, flags.
	 */
	public function chat( $message, $product = 'general', $history = array(), $on_delta = null ) {
		$this->last_source = 'fallback';
		$this->last_error  = '';

		$message = trim( (string) $message );
		if ( '' === $message ) {
			return $this->envelope( false, '', 'empty' );
		}
		// Hard ceiling on message size (token + abuse protection).
		if ( mb_strlen( $message ) > 2000 ) {
			$message = mb_substr( $message, 0, 2000 );
		}

		$product                = $this->sanitize_product( $product );
		$history                = $this->sanitize_history( $history );
		$this->current_question = $message;
		$this->current_product  = $product;

		$this->flags = array();

		/**
		 * Replace the whole reply pipeline (integrations). The engine instance
		 * is passed so modules can attach response flags.
		 */
		$pre = apply_filters( 'ssc_pre_reply', null, $message, $product, $this );
		if ( null !== $pre && is_string( $pre ) && '' !== $pre ) {
			$this->last_source = 'filter';
			return $this->envelope( true, $pre, 'filter' );
		}

		SSC_Schema::record_chat( $product );

		$qa_mode  = (string) SSC_Settings::get( 'qa_mode', 'ai_first' );
		$provider = SSC_Providers::current();

		// Bank-first paths.
		if ( 'bank_first' === $qa_mode || 'bank_only' === $qa_mode ) {
			$bank = SSC_Knowledge::bank_answer( $product, $message );
			if ( $bank ) {
				SSC_Schema::qa_touch( $bank['id'] );
				$this->last_source = 'bank';
				return $this->envelope( true, $bank['answer'], 'bank' );
			}
			if ( 'bank_only' === $qa_mode || null === $provider ) {
				return $this->unanswered();
			}
		}

		// AI path.
		if ( null !== $provider ) {
			$reply = $this->ai_reply( $provider, $message, $product, $history, $on_delta );
			if ( '' !== $reply ) {
				return $this->envelope( true, $reply, $this->last_source );
			}
		}

		// AI failed or absent: fall back to the bank.
		if ( 'bank_first' !== $qa_mode ) {
			$bank = SSC_Knowledge::bank_answer( $product, $message );
			if ( $bank ) {
				SSC_Schema::qa_touch( $bank['id'] );
				$this->last_source = 'bank';
				return $this->envelope( true, $bank['answer'], 'bank' );
			}
		}

		return $this->unanswered();
	}

	/**
	 * AI generation with response caching.
	 *
	 * @param SSC_Provider  $provider Provider adapter.
	 * @param string        $message  User message.
	 * @param string        $product  Product scope.
	 * @param array         $history  History.
	 * @param callable|null $on_delta Receives each streamed token, when available.
	 * @return string Empty on failure.
	 */
	protected function ai_reply( $provider, $message, $product, $history, $on_delta = null ) {
		$system = SSC_Prompt_Builder::build_for_chat( $message, $product );
		$opts   = array(
			'endpoint'     => $provider->saved_credentials()['endpoint'],
			'product'      => $product,
			'product_name' => $this->product_name( $product ),
		);

		// Messages: strip leading assistant turns (some APIs require user-first).
		$messages = $history;
		while ( ! empty( $messages ) && isset( $messages[0]['role'] ) && 'assistant' === $messages[0]['role'] ) {
			array_shift( $messages );
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		// Response cache only for history-less questions (deterministic + cheap).
		// Health conversations must never enter a shared response cache.
		$cache_enabled = ! SSC_Modules::is_active( 'pharma' ) && ( 'yes' === SSC_Settings::get( 'ai_cache_enabled', 'yes' ) ) && empty( $history );
		$cache_key     = '';
		if ( $cache_enabled ) {
			$cache_key = 'ssc_ai_' . md5( $provider->id() . '|' . $product . '|' . mb_strtolower( trim( $message ) ) . '|' . md5( $system ) . '|' . SSC_Setup::connection_fingerprint() );
			$cached    = get_transient( $cache_key );
			if ( false !== $cached && '' !== $cached ) {
				$this->last_source = 'cache';
				return (string) $cached;
			}
		}

		$result = null;
		if ( is_callable( $on_delta ) && SSC_Stream::provider_supports( $provider ) ) {
			$result = SSC_Stream::generate( $provider, $system, $messages, $opts, $on_delta );
		}
		if ( null === $result ) {
			$result = $provider->generate_with(
				$this->provider_key( $provider ),
				$this->provider_model( $provider ),
				$system,
				$messages,
				$opts
			);
		}

		if ( ! $result['ok'] ) {
			$this->last_error = isset( $result['error']['message'] ) ? $result['error']['message'] : 'unknown';
			if ( $this->last_error ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- provider failure diagnostics.
				error_log( '[SSC Chatbot] provider ' . $provider->id() . ' failed (' . sanitize_key( $result['error']['code'] ?? 'unknown' ) . ')' );
			}
			return '';
		}

		$reply = $result['text'];
		if ( $cache_enabled && $cache_key ) {
			$ttl = (int) apply_filters( 'ssc_ai_cache_ttl', 6 * HOUR_IN_SECONDS );
			set_transient( $cache_key, $reply, $ttl );
		}
		$this->last_source = 'ai';
		return $reply;
	}

	/**
	 * Unanswered path: honest fallback message (never disguised as AI).
	 *
	 * @return array
	 */
	protected function unanswered() {
		$this->last_source = 'unanswered';
		$fallback          = (string) SSC_Settings::get( 'ai_fallback_msg', '' );
		if ( '' === trim( $fallback ) ) {
			$fallback = __( 'Thanks for your message. I do not have enough verified information to answer this right now. Please leave a request or contact us directly so we can help you properly.', 'smart-support-chatbot' );
		}
		$envelope = $this->envelope( true, $fallback, 'unanswered' );
		// Handoff is suggested only when the module is active (server-enforced).
		if ( SSC_Modules::is_active( 'handoff' ) ) {
			$envelope['handoff'] = true;
		}
		return $envelope;
	}

	/**
	 * Final envelope (+ optional logging when the history module is on).
	 *
	 * @param bool   $ok     Success.
	 * @param string $reply  Reply text.
	 * @param string $source Source.
	 * @return array
	 */
	protected function envelope( $ok, $reply, $source ) {
		$out = array(
			'ok'        => $ok,
			'reply'     => $reply,
			'source'    => $source,
			'handoff'   => false,
			'log_id'    => 0,
			'log_token' => '',
			'flags'     => $this->flags,
		);

		if ( $ok && SSC_Modules::is_active( 'history' ) && 'yes' === SSC_Settings::get( 'chatlog_enabled', 'no' ) && '' !== $reply ) {
			$log_id = SSC_Schema::log_chat(
				$this->current_question,
				$reply,
				( 'unanswered' === $source ) ? 'unanswered' : $source,
				$this->current_product,
				$this->current_ip
			);
			if ( $log_id ) {
				$out['log_id']    = $log_id;
				$token            = $this->log_token( $log_id );
				$out['log_token'] = $token;
			}
		}
		return $out;
	}

	/**
	 * Context captured before reply for logging.
	 *
	 * @var string
	 */
	protected $current_question = '';

	/**
	 * Product scope captured before reply for logging.
	 *
	 * @var string
	 */
	protected $current_product = 'general';

	/**
	 * Client IP captured before reply for logging.
	 *
	 * @var string
	 */
	protected $current_ip = '';

	/**
	 * Capture request context (called by transports right before chat()).
	 *
	 * @param string $ip  Client ip.
	 */
	public function set_context( $ip ) {
		$this->current_ip = (string) $ip;
	}

	/**
	 * Unforgeable-ish token binding feedback to a log row.
	 *
	 * @param int $log_id Log row id.
	 * @return string
	 */
	protected function log_token( $log_id ) {
		$secret = wp_salt( 'auth' ) . '|ssc-log';
		return substr( hash_hmac( 'sha256', 'log' . $log_id, $secret ), 0, 20 );
	}

	/**
	 * Verify a log token.
	 *
	 * @param int    $log_id Log row.
	 * @param string $token  Token.
	 * @return bool
	 */
	public function verify_log_token( $log_id, $token ) {
		$secret   = wp_salt( 'auth' ) . '|ssc-log';
		$expected = substr( hash_hmac( 'sha256', 'log' . (int) $log_id, $secret ), 0, 20 );
		return hash_equals( $expected, (string) $token );
	}

	/**
	 * Validate product scope against the real catalog.
	 *
	 * @param string $product Raw product id.
	 * @return string
	 */
	protected function sanitize_product( $product ) {
		$product = (string) $product;
		if ( '' === $product || 'general' === $product ) {
			return 'general';
		}
		foreach ( (array) SSC_Settings::get( 'products', array() ) as $p ) {
			if ( isset( $p['id'] ) && $p['id'] === $product ) {
				return $product;
			}
		}
		return 'general';
	}

	/**
	 * Product display name.
	 *
	 * @param string $product Product id.
	 * @return string
	 */
	protected function product_name( $product ) {
		foreach ( (array) SSC_Settings::get( 'products', array() ) as $p ) {
			if ( isset( $p['id'] ) && $p['id'] === $product ) {
				return isset( $p['name'] ) ? $p['name'] : $p['id'];
			}
		}
		return '';
	}

	/**
	 * Decrypted key for the active provider.
	 *
	 * @param SSC_Provider $provider Provider.
	 * @return string
	 */
	protected function provider_key( $provider ) {
		return $provider->saved_credentials()['api_key'];
	}

	/**
	 * Model for the active provider.
	 *
	 * @param SSC_Provider $provider Provider.
	 * @return string
	 */
	protected function provider_model( $provider ) {
		$m = $provider->saved_credentials()['model'];
		return '' !== $m ? $m : $provider->default_model();
	}

	/**
	 * Sanitize client-supplied history (conversation context).
	 *
	 * @param array $raw History.
	 * @return array
	 */
	public function sanitize_history( $raw ) {
		if ( ! is_array( $raw ) ) {
			$decoded = json_decode( is_string( $raw ) ? $raw : '', true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		$limit = max( 0, min( 20, (int) SSC_Settings::get( 'ai_history_limit', 8 ) ) );
		if ( 0 === $limit ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $raw, -$limit ) as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['role'], $item['content'] ) ) {
				continue;
			}
			if ( ! in_array( $item['role'], array( 'user', 'assistant' ), true ) || ! is_string( $item['content'] ) ) {
				continue;
			}
			$role = $item['role'];
			$text = sanitize_textarea_field( (string) $item['content'] );
			if ( '' === trim( $text ) ) {
				continue;
			}
			if ( mb_strlen( $text ) > 1500 ) {
				$text = mb_substr( $text, 0, 1500 );
			}
			$out[] = array(
				'role'    => $role,
				'content' => $text,
			);
		}
		if ( count( $out ) > $limit ) {
			$out = array_slice( $out, -$limit );
		}
		return $out;
	}

	/**
	 * Client IP (REMOTE_ADDR by default; trusted proxy header opt-in).
	 *
	 * Behind Cloudflare / nginx / any reverse proxy, REMOTE_ADDR is the
	 * proxy IP — every visitor would share one rate-limit bucket. Set the
	 * trusted header in Settings → Protection, or via the ssc_ip_header filter.
	 *
	 * @return string
	 */
	public function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		// Setting wins; filter can still override for advanced setups.
		$header = (string) SSC_Settings::get( 'trusted_proxy_header', '' );
		/**
		 * Trusted proxy header name for real client IPs (empty = REMOTE_ADDR only).
		 *
		 * @param string $header $_SERVER key.
		 */
		$header = (string) apply_filters( 'ssc_ip_header', $header );

		if ( '' !== $header && ! empty( $_SERVER[ $header ] ) ) {
			// Only accept well-known proxy header names (defense in depth).
			$allowed = array(
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_REAL_IP',
				'HTTP_CF_CONNECTING_IP',
				'HTTP_TRUE_CLIENT_IP',
				'HTTP_X_CLIENT_IP',
				'HTTP_FASTLY_CLIENT_IP',
			);
			$allowed = apply_filters( 'ssc_trusted_proxy_headers', $allowed );
			if ( in_array( $header, $allowed, true ) ) {
				$forwarded = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				$parts     = explode( ',', $forwarded );
				$first     = trim( $parts[0] );
				if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
					$ip = $first;
				}
			}
		}
		return $ip;
	}

	/**
	 * Rate limit check for a bucket using current settings.
	 *
	 * @param string $bucket Bucket.
	 * @param string $cid    Client id.
	 * @param string $ip     Client ip (optional; defaults to detected).
	 * @return bool
	 */
	public function allow_request( $bucket, $cid, $ip = null ) {
		$ip  = null === $ip ? $this->client_ip() : $ip;
		$cid = sanitize_text_field( (string) $cid );

		$mode       = (string) SSC_Settings::get( 'rate_limit_mode', 'ip' );
		$chat_limit = (int) SSC_Settings::get( 'chat_rate_limit', 100 );
		$sess_limit = (int) SSC_Settings::get( 'session_rate_limit', 50 );

		switch ( $bucket ) {
			case 'submit':
				// Forms have their own (stricter) quota - NOT the AI quota.
				$chat_limit = (int) apply_filters( 'ssc_submit_rate_limit', SSC_Settings::get( 'submit_rate_limit', 20 ) );
				$sess_limit = $chat_limit;
				break;
			case 'suggest':
				$chat_limit = 1000;
				$sess_limit = 600;
				break;
			case 'csat':
			case 'feedback':
				$chat_limit = 20;
				$sess_limit = 20;
				break;
		}
		return SSC_Schema::rate_limit( $bucket, $mode, $chat_limit, $sess_limit, $ip, $cid );
	}
}
