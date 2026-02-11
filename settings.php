<?php

class Dashify_Settings {
	private static $instance = null;

	private function __construct() {}

	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new Dashify_Settings();
		}
		return self::$instance;
	}

	public function init() {
		add_filter(
			'woocommerce_settings_tabs_array',
			array( $this, 'add_settings_tab' ),
			98
		);
		add_action(
			'woocommerce_settings_dashify',
			array( $this, 'render_settings' )
		);
		add_action(
			'woocommerce_settings_save_dashify',
			array( $this, 'save_settings' )
		);
	}

	function add_settings_tab( $settings_tabs ) {
		$settings_tabs['dashify'] = __( 'Dashify', 'dashify' );
		return $settings_tabs;
	}

	function render_settings() {
		WC_Admin_Settings::output_fields( $this->get_settings_fields() );
	}

	function save_settings() {
		WC_Admin_Settings::save_fields( $this->get_settings_fields() );
		// See https://stackoverflow.com/a/40406068 for why an additional
		// redirect is needed. In short, the option is not updated right away,
		// and so without this, an additional page load is required before the
		// correct value returns from get_option().
		header( 'Location: ' . $_SERVER['REQUEST_URI'] );
	}

	private function get_settings_fields() {
		// By using add_filter( 'dashify_settings', … ) features can add
		// their settings without feature-specific code having to be in this file.
		$fields   = apply_filters(
			'dashify_settings',
			array(
				array(
					'title' => 'Dashify',
					'type'  => 'title',
					'desc'  => 'Enable or disable individual Dashify features and customize their behavior.',
				),
			)
		);
		$fields[] = array( 'type' => 'sectionend' );
		return $fields;
	}
}
