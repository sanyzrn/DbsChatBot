<?php
/**
 * Proactive engagement module: a restrained, dismissible invitation bubble.
 *
 * Design principles: configurable, restrained, respectful - a single nudge
 * per browser session, auto-dismisses, never covers content.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Proactive module.
 */
class SSC_Module_Proactive extends SSC_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'proactive';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Proactive Invitation', 'smart-support-chatbot' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'A single, dismissible invitation bubble after a delay - shown at most once per browser session.', 'smart-support-chatbot' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Gently start conversations with hesitant visitors without being intrusive.', 'smart-support-chatbot' );
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
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-8-8 18-2-8-8-2z"/></svg>';
	}

	/**
	 * Defaults are deliberately restrained.
	 *
	 * @return array
	 */
	public function settings_schema() {
		return array(
			'proactive_delay' => 12,
			'proactive_text'  => '',
		);
	}
}
