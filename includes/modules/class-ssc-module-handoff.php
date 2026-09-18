<?php
/**
 * Human handoff module: escalation offers when the assistant cannot answer.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handoff module.
 */
class SSC_Module_Handoff extends SSC_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'handoff';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Human Handoff', 'smart-support-chatbot' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'When the assistant cannot answer, it transparently offers an escalation to a human expert via a consultation request.', 'smart-support-chatbot' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'No visitor is ever left stuck without a path to a real person.', 'smart-support-chatbot' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category() {
		return 'communication';
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
	}

	/**
	 * Depends on the leads module (escalation lands in a request form).
	 *
	 * @return string[]
	 */
	public function dependencies() {
		return array( 'leads' );
	}

	/**
	 * Handoff message (configurable).
	 *
	 * @return string
	 */
	public static function handoff_text() {
		$text = (string) SSC_Settings::get( 'handoff_text', '' );
		if ( '' === trim( $text ) ) {
			$text = __( 'It looks like this question is better handled by a human expert. Would you like to leave a consultation request?', 'smart-support-chatbot' );
		}
		return $text;
	}
}
