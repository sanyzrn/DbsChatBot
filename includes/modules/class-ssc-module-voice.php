<?php
/**
 * Voice interaction module (Web Speech API, browser-side).
 *
 * When inactive: no mic/speaker controls in the widget, no voice config sent
 * to the frontend, no voice features reachable (server computes the flags).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Voice module.
 */
class SSC_Module_Voice extends SSC_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'voice';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Voice Interaction', 'smart-support-chatbot' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Visitors speak to the assistant and listen to answers (microphone input and text-to-speech playback).', 'smart-support-chatbot' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Hands-free conversations and accessible answers for visitors on the go.', 'smart-support-chatbot' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category() {
		return 'engagement';
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>';
	}

	/**
	 * Module settings schema.
	 *
	 * @return array
	 */
	public function settings_schema() {
		return array(
			'voice_input'  => 'yes',
			'voice_output' => 'yes',
			'voice_language' => 'auto',
		);
	}
}
