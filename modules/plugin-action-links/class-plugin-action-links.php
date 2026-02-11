<?php

namespace Dashify\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin_Action_Links {
	public function __construct() {
		add_action(
			'plugin_action_links_' . DASHIFY_BASENAME,
			array( $this, 'plugin_action_links' )
		);
	}

	/**
	 * Adds links next to Deactivate for Dashify in the Plugins admin page.
	 *
	 * @param  array $links List of existing plugin action links.
	 * @return array        List of modified plugin action links.
	 */
	public function plugin_action_links( $links ) {
		return array_merge(
			array(
				'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=dashify' ) . '">Settings</a>',
				'<a href="' . esc_url( 'https://forms.gle/pRezSbdUcZmvZdX27' ) . '">Help</a>',
			),
			$links
		);
	}
}
