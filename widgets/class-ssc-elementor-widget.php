<?php
/**
 * Elementor widget: chatbot placement + safe appearance overrides.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor widget class.
 */
class SSC_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'ssc_chatbot';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'NexaChatAI', 'nexachat-ai' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-chat';
	}

	/**
	 * Categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'ssc_chatbot', 'general' );
	}

	/**
	 * Keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'chat', 'chatbot', 'assistant', 'ai', 'support' );
	}

	/**
	 * Dependencies.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'nexachat-ai' );
	}

	/**
	 * Script dependencies.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'nexachat-ai' );
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$s = SSC_Settings::all();

		$this->start_controls_section(
			'content',
			array( 'label' => __( 'Content', 'nexachat-ai' ) )
		);

		$this->add_control(
			'assistant_display_name',
			array(
				'label'       => __( 'Assistant name (empty = global)', 'nexachat-ai' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => (string) $s['assistant_display_name'],
			)
		);

		$this->add_control(
			'welcome_title',
			array(
				'label'   => __( 'Welcome title (empty = global)', 'nexachat-ai' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'welcome_text',
			array(
				'label'   => __( 'Welcome message (empty = global)', 'nexachat-ai' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => '',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style',
			array(
				'label' => __( 'Style', 'nexachat-ai' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'position',
			array(
				'label'   => __( 'Floating button position', 'nexachat-ai' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''      => __( 'Global setting', 'nexachat-ai' ),
					'right' => __( 'Bottom right', 'nexachat-ai' ),
					'left'  => __( 'Bottom left', 'nexachat-ai' ),
				),
			)
		);

		$this->add_control(
			'primary_color',
			array(
				'label'   => __( 'Primary color (empty = global)', 'nexachat-ai' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '',
			)
		);

		$this->add_control(
			'theme_mode',
			array(
				'label'   => __( 'Theme (empty = global)', 'nexachat-ai' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''      => __( 'Global setting', 'nexachat-ai' ),
					'light' => __( 'Light', 'nexachat-ai' ),
					'dark'  => __( 'Dark', 'nexachat-ai' ),
					'auto'  => __( 'Match device', 'nexachat-ai' ),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render (server-side; empty string when the chatbot is not live).
	 */
	protected function render() {
		if ( ! function_exists( 'SSC_Plugin' ) ) {
			return;
		}
		$overrides = array();

		$settings = $this->get_settings_for_display();
		foreach ( array( 'position', 'primary_color', 'theme_mode', 'assistant_display_name', 'welcome_title', 'welcome_text' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				$overrides[ $key ] = $settings[ $key ];
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static internal markup.
		echo SSC_Plugin::instance()->frontend->render( $overrides );
	}
}
