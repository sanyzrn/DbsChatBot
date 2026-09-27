<?php
/**
 * Server-side conversation memory.
 *
 * The model's context used to be rebuilt from history the BROWSER sent,
 * including "assistant" turns. Anyone could therefore put words in the
 * assistant's mouth ("earlier you promised a 90% discount") and the model
 * would treat them as its own statements. The context now comes from what
 * this server actually answered, keyed by an unguessable conversation id the
 * widget generates; client-sent history is ignored when an id is present.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conversation store (transients: auto-expire, object-cache friendly).
 */
class SSC_Conversation {

	/**
	 * Maximum stored messages (user + assistant) per conversation.
	 */
	const MAX_MESSAGES = 40;

	/**
	 * Validate a conversation id (128-bit hex from the widget).
	 *
	 * @param mixed $id Raw id.
	 * @return string '' when invalid.
	 */
	public static function sanitize_id( $id ) {
		$id = is_scalar( $id ) ? strtolower( (string) $id ) : '';
		return preg_match( '/^[a-f0-9]{24,64}$/', $id ) ? $id : '';
	}

	/**
	 * Transient key; salted so ids never appear in the options table.
	 *
	 * @param string $id Conversation id.
	 * @return string
	 */
	protected static function key( $id ) {
		return 'ssc_conv_' . substr( hash_hmac( 'sha256', $id, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * Lifetime: health conversations are kept for the shortest useful time.
	 *
	 * @return int Seconds.
	 */
	protected static function ttl() {
		$ttl = SSC_Modules::is_active( 'pharma' ) ? 30 * MINUTE_IN_SECONDS : 2 * HOUR_IN_SECONDS;
		return (int) apply_filters( 'ssc_conversation_ttl', $ttl );
	}

	/**
	 * Stored messages (role/content pairs, oldest first).
	 *
	 * @param string $id Conversation id.
	 * @return array
	 */
	public static function load( $id ) {
		$id = self::sanitize_id( $id );
		if ( '' === $id ) {
			return array();
		}
		$data = get_transient( self::key( $id ) );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Record one completed exchange.
	 *
	 * @param string $id       Conversation id.
	 * @param string $question User message.
	 * @param string $answer   Assistant reply actually sent.
	 */
	public static function append( $id, $question, $answer ) {
		$id = self::sanitize_id( $id );
		if ( '' === $id || '' === trim( (string) $answer ) ) {
			return;
		}
		$messages   = self::load( $id );
		$messages[] = array(
			'role'    => 'user',
			'content' => (string) $question,
		);
		$messages[] = array(
			'role'    => 'assistant',
			'content' => (string) $answer,
		);
		set_transient( self::key( $id ), array_slice( $messages, -self::MAX_MESSAGES ), self::ttl() );
	}

	/**
	 * Forget a conversation.
	 *
	 * @param string $id Conversation id.
	 */
	public static function forget( $id ) {
		$id = self::sanitize_id( $id );
		if ( '' !== $id ) {
			delete_transient( self::key( $id ) );
		}
	}
}
