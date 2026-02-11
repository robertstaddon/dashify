<?php
/*
 * Plugin Name: Dashify
 * Description: A modern design and UI for the WooCommerce admin. Manage, search, and navigate orders faster. Make the WordPress admin dashboard ecommerce-focused.
 * Version: 1.3.14
 * Author: Dashify
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DASHIFY_BASE_FILE', __FILE__ );
define( 'DASHIFY_BASENAME', plugin_basename( __FILE__ ) );
define( 'DASHIFY_PATH', plugin_dir_path( __FILE__ ) );

require_once DASHIFY_PATH . 'polyfill.php';
require_once DASHIFY_PATH . 'modules/plugin-action-links/class-plugin-action-links.php';

use Dashify\Modules;

class Dashify_Base {

	const VERSION = '1.3.14';

	// HPOS
	const SUBSCRIPTION_PAGE_ID = 'woocommerce_page_wc-orders--shop_subscription';
	// Non-HPOS (Yes, the page IDs in WooCommerce are not intuitive and seem backwards.)
	const LEGACY_SUBSCRIPTION_LIST_PAGE_ID = 'edit-shop_subscription';
	const LEGACY_SUBSCRIPTION_EDIT_PAGE_ID = 'shop_subscription';

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		// If not viewing the admin, or if on the frontend and cannot see the
		// admin bar, there’s no need to load Dashify.
		if ( ! is_admin() && ! is_admin_bar_showing() ) {
			return;
		}
		add_action(
			'admin_enqueue_scripts',
			array( $this, 'dashify_admin_enqueue_scripts' )
		);
		add_filter(
			'dashify_current_page_filter',
			array( $this, 'subscriptions_current_page' ),
			10,
			2
		);
		register_activation_hook(
			__FILE__,
			array( $this, 'move_subscription_edit_meta_boxes' )
		);
		register_deactivation_hook(
			__FILE__,
			array( $this, 'restore_subscription_edit_meta_box_layout' )
		);
		add_action(
			'wp_ajax_save_dashify_on',
			array( $this, 'save_dashify_on' )
		);
		add_action(
			'wp_ajax_save_dashify_option',
			array( $this, 'save_dashify_option' )
		);
		add_action(
			'wp_ajax_mark_notice_dismissed',
			array( $this, 'mark_notice_dismissed' )
		);
		add_action(
			'wp_ajax_dashify_order_list_analytics',
			array( $this, 'order_list_analytics' )
		);
		register_activation_hook(
			__FILE__,
			array( $this, 'move_order_edit_meta_boxes' )
		);
		register_deactivation_hook(
			__FILE__,
			array( $this, 'restore_order_edit_meta_box_layout' )
		);

		new Modules\Plugin_Action_Links();

		add_filter(
			'plugin_row_meta',
			array( $this, 'dashify_row_meta' ),
			10,
			2
		);
		add_action(
			'admin_init',
			array( $this, 'migrate_order_edit_meta_box_layout_to_user_meta' )
		);
		add_action(
			'admin_init',
			array( $this, 'migrate_subscription_edit_meta_box_layout_to_user_meta' )
		);

		require_once DASHIFY_PATH . 'settings.php';
		$settings = Dashify_Settings::get_instance();
		$settings->init();

		add_filter( 'dashify_settings', array( $this, 'list_table_settings' ), 11 );

		require_once DASHIFY_PATH . 'modules/navigation/navigation.php';
		new Dashify_Navigation();
	}

	/**
	 * Updates value of dashify_on option as requested
	 */
	public function save_dashify_on() {
		if ( empty( $_POST['dashify_on'] ) ) {
			return;
		}

		check_ajax_referer( 'save_dashify_option_nonce' );

		update_option( 'dashify_on', sanitize_key( $_POST['dashify_on'] ) );
		if ( $this->dashify_on() ) {
			$this->move_order_edit_meta_boxes();
			if ( is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
				$this->move_subscription_edit_meta_boxes();
			}
		} else {
			$this->restore_order_edit_meta_box_layout();
			if ( is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
				$this->restore_subscription_edit_meta_box_layout();
			}
		}
		wp_die(); // this is required to terminate immediately and return a proper response
	}

	public function save_dashify_option() {
		if ( empty( $_POST['option_name'] ) || empty( $_POST['option_value'] ) ) {
			return;
		}
		$allowed_option_names = array(
			'dashify_line_item_menu_order_column_enabled',
			'dashify_line_item_menu_order_column_default_sort',
		);
		if ( ! in_array( $_POST['option_name'], $allowed_option_names ) ) {
			return;
		}
		check_ajax_referer( 'save_dashify_option_nonce' );
		$result = update_option(
			sanitize_key( $_POST['option_name'] ),
			sanitize_text_field( $_POST['option_value'] )
		);
		if ( ! $result ) {
			echo 'Something went wrong. The options were not saved.';
		}
		wp_die();
	}

	/**
	 * Marks the release notes dismissed for the current version of Dashify.
	 */
	public function mark_notice_dismissed() {
		check_ajax_referer( 'mark_notice_dismissed_nonce' );
		if ( isset( $_POST['option'] ) && 'forever' === $_POST['option'] ) {
			update_option( 'dashify_dismissed_release_notices_forever', 1 );
			return;
		}
		update_option(
			'dashify_dismissed_notices',
			array_merge(
				get_option( 'dashify_dismissed_notices', array() ),
				array( self::VERSION )
			)
		);
		wp_die();
	}

	public static function dashify_on() {
		return ( get_option( 'dashify_on', 'true' ) === 'true' ) ? 1 : 0;
	}

	public function order_list_analytics() {
		if ( ! isset( $_POST['days'] ) ) {
			return;
		}
		check_ajax_referer( 'dashify_order_list_analytics_nonce' );

		update_option( 'dashify_analytics_range', intval( sanitize_key( $_POST['days'] ) ) );

		// We can't use the class method here for determining this because when
		// doing AJAX it doesn't know which page it's on from the PHP side.
		$is_subscription_list = sanitize_key( $_POST['is_subscriptions'] );

		try {
			$range     = get_option( 'dashify_analytics_range' );
			$analytics = $is_subscription_list
				? $this->calculate_subscription_list_analytics( $range )
				: $this->calculate_order_list_analytics( $range );
		} catch ( Exception $exception ) {
			error_log( $exception );
		}

		$sections = $is_subscription_list
			? $this->create_subscription_list_analytics_sections( $analytics )
			: $this->create_order_list_analytics_sections( $analytics );

		wp_send_json_success(
			array(
				'analytics_range' => $analytics['analytics_range'],
				'sections'        => $sections,
				'intervalData'    => $analytics['intervalData'],
				'graphDivisions'  => $analytics['graphDivisions'],
				'divisionMaxima'  => $analytics['divisionMaxima'],
			)
		);
	}

	/**
	 * Restore the original positions of the order meta boxes.
	 */
	public function restore_order_edit_meta_box_layout() {
		$default = array(
			'normal'   => 'woocommerce-order-data,woocommerce-order-items,woocommerce-order-downloads',
			'side'     => 'woocommerce-order-actions,woocommerce-order-source-data,woocommerce-customer-history,woocommerce-order-notes',
			'advanced' => 'order_custom',
		);
		$page    = $this->isHPOS() ? 'woocommerce_page_wc-orders' : 'shop_order';
		update_user_meta( get_current_user_id(), "meta-box-order_$page", $default );
	}

	public function move_order_edit_meta_boxes() {
		$page   = $this->isHPOS() ? 'woocommerce_page_wc-orders' : 'shop_order';
		$layout = $this->get_layout( $page );

		// If they have never moved the meta boxes, $layout will be an empty string,
		// so we need to set a default before we move them around.
		if ( empty( $layout ) ) {
			$this->restore_order_edit_meta_box_layout();
			$layout = $this->get_layout( $page );
		}

		$layout = $this->move_meta_box( $layout, 'woocommerce-order-data', 'normal', 'side', 'prepend' );
		$layout = $this->move_meta_box( $layout, 'woocommerce-order-downloads', 'normal', 'side', 'append' );
		$layout = $this->move_meta_box( $layout, 'order_custom', 'advanced', 'side', 'append' );
		$layout = $this->move_meta_box( $layout, 'woocommerce-order-notes', 'side', 'normal', 'append' );

		$this->update_layout( $page, $layout );
	}

	public function restore_subscription_edit_meta_box_layout() {
		$default = array(
			'normal'   => 'woocommerce-subscription-data,subscription_renewal_orders,woocommerce-order-items,woocommerce-order-downloads',
			'side'     => 'woocommerce-order-actions,woocommerce-order-source-data,woocommerce-customer-history,woocommerce-subscription-schedule,woocommerce-order-notes',
			'advanced' => $this->isHPOS() ? 'order_custom' : 'postcustom',
		);
		$page    = $this->isHPOS() ? $this::SUBSCRIPTION_PAGE_ID : $this::LEGACY_SUBSCRIPTION_EDIT_PAGE_ID;
		update_user_meta( get_current_user_id(), "meta-box-order_$page", $default );
	}

	public function move_subscription_edit_meta_boxes() {
		if ( ! is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
			return;
		}

		$page   = $this->isHPOS() ? $this::SUBSCRIPTION_PAGE_ID : $this::LEGACY_SUBSCRIPTION_EDIT_PAGE_ID;
		$layout = $this->get_layout( $page );

		// If they have never moved the meta boxes, $layout will be an empty string,
		// so we need to set a default before we move them around.
		if ( empty( $layout ) ) {
			$this->restore_subscription_edit_meta_box_layout();
			$layout = $this->get_layout( $page );
		}

		$layout = $this->move_meta_box( $layout, 'woocommerce-subscription-data', 'normal', 'side', 'prepend' );
		$layout = $this->move_meta_box( $layout, 'woocommerce-order-downloads', 'normal', 'side', 'append' );
		$layout = $this->move_meta_box( $layout, $this->isHPOS() ? 'order_custom' : 'postcustom', 'advanced', 'side', 'append' );
		$layout = $this->move_meta_box( $layout, 'subscription_renewal_orders', 'side', 'normal', 'append' );
		$layout = $this->move_meta_box( $layout, 'woocommerce-subscription-schedule', 'side', 'normal', 'append' );
		$layout = $this->move_meta_box( $layout, 'woocommerce-order-notes', 'side', 'normal', 'append' );

		$this->update_layout( $page, $layout );
	}

	private function get_layout( $page ) {
		return get_user_meta( get_current_user_id(), "meta-box-order_$page", true );
	}

	private function update_layout( $page, $layout ) {
		update_user_meta( get_current_user_id(), "meta-box-order_$page", $layout );
	}

	private function move_meta_box( $layout, $box_id, $from_section, $to_section, $position = 'append' ) {
		$layout[ $from_section ] = $this->remove_from_csv( $layout[ $from_section ], $box_id );

		if ( ! str_contains( $layout[ $to_section ], $box_id ) ) {
			$layout[ $to_section ] = 'prepend' === $position
				? $box_id . ',' . $layout[ $to_section ]
				: $layout[ $to_section ] . ',' . $box_id;
		}

		return $layout;
	}

	private function remove_from_csv( $csv, $to_remove ) {
		return implode(
			',',
			array_diff(
				str_getcsv( $csv ),
				array( $to_remove )
			)
		);
	}

	/**
	 * Is WooCommerce High Performance Order Storage is enabled?
	 */
	private function isHPOS(): bool {
		return get_option( 'woocommerce_custom_orders_table_enabled' ) === 'yes';
	}

	function dashify_row_meta( $plugin_meta, $plugin_file ) {
		if ( strpos( $plugin_file, 'dashify.php' ) === false ) {
			return $plugin_meta;
		}

		if ( ! is_plugin_active( 'dashify-pro/dashify-pro.php' ) ) {
			$new_links   = array(
				'upgrade' => '<a href="https://getdashify.com/#pro" target="_blank">Upgrade to Pro</a>',
			);
			$plugin_meta = array_merge( $plugin_meta, $new_links );
		}

		return $plugin_meta;
	}

	public function dashify_admin_enqueue_scripts() {
		$current_page          = $this->dashify_current_page( get_current_screen()->id );
		$pages_to_load_dashify = array(
			'order_list',
			'order_summary',
		);
		if ( ! in_array( $current_page, $pages_to_load_dashify ) && ! $this->is_subscription_table_page() && ! $this->is_subscription_edit_page() ) {
			return;
		}

		// screen option must load regardless of dashify_on option
		self::dashify_enqueue_screen_options_files( $this->dashify_on() );

		if ( ! $this->dashify_on() ) {
			return;
		}

		/* begin: for all pages with active dashify */
		$this->dashify_enqueue_utils();
		self::enqueue_dismissible();
		/* end: for all pages with active dashify */

		if ( 'order_summary' === $current_page ) {
			$this->dashify_enqueue_order_summary_files();
		} elseif ( $this->is_subscription_edit_page() ) {
			$this->dashify_enqueue_order_summary_files();
			$this->dashify_enqueue_subscription_edit_files();
		} elseif ( 'order_list' === $current_page ) {
			$this->dashify_enqueue_table_files();
			$this->dashify_enqueue_order_table_files();
		} elseif ( $this->is_subscription_table_page() ) {
			$this->dashify_enqueue_table_files();
			$this->dashify_enqueue_subscription_table_files();
		}
	}

	private function is_subscription_table_page() {
		$screen_id = get_current_screen()->id;
		return $this->is_HPOS_subscription_table_page( $screen_id )
			|| $this->is_legacy_subscription_table_page( $screen_id );
	}

	private function is_HPOS_subscription_table_page( $screen_id ) {
		return $this::SUBSCRIPTION_PAGE_ID === $screen_id
			&& (
				! isset( $_GET['action'] ) ||
				( isset( $_GET['action'] ) && 'edit' !== $_GET['action'] && 'new' !== $_GET['action'] )
			);
	}

	private function is_legacy_subscription_table_page( $screen_id ) {
		return $this::LEGACY_SUBSCRIPTION_LIST_PAGE_ID === $screen_id;
	}

	private function is_subscription_edit_page() {
		$screen_id = get_current_screen()->id;
		return $this->is_HPOS_subscription_edit_page( $screen_id )
			|| $this->is_legacy_subscription_edit_page( $screen_id );
	}

	/**
	 * This function also considers the add new subscription page to be an edit
	 * page, as it’s nearly identical.
	 */
	private function is_HPOS_subscription_edit_page( $screen_id ) {
		return $this::SUBSCRIPTION_PAGE_ID === $screen_id
			&& ( isset( $_GET['action'] ) && ( 'edit' === $_GET['action'] || 'new' === $_GET['action'] ) );
	}

	private function is_legacy_subscription_edit_page( $screen_id ) {
		return $this::LEGACY_SUBSCRIPTION_EDIT_PAGE_ID === $screen_id;
	}

	// This filter callback is used in the filter dashify_current_page_filter.
	public function subscriptions_current_page( $page, $screen_id ) {
		if (
			$this::SUBSCRIPTION_PAGE_ID === $screen_id
			&& ( ! isset( $_GET['action'] ) || ( isset( $_GET['action'] ) && 'edit' !== $_GET['action'] ) )
		) {
			$page = 'subscription_list';
		}
		if (
			$this::SUBSCRIPTION_PAGE_ID === $screen_id
			&& isset( $_GET['action'] )
			&& 'edit' === $_GET['action']
		) {
			$page = 'subscription_edit';
		}
		if ( $this::LEGACY_SUBSCRIPTION_LIST_PAGE_ID === $screen_id ) {
			$page = 'subscription_list';
		}
		if ( $this::LEGACY_SUBSCRIPTION_EDIT_PAGE_ID === $screen_id ) {
			$page = 'subscription_edit';
		}
		return $page;
	}

	public function dashify_enqueue_utils() {
		wp_enqueue_script(
			'dashify_util_script',
			plugins_url( '/admin/js/util.js', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/admin/js/util.js' )
		);
	}

	static function dashify_enqueue_screen_options_files( $dashify_on ) {
		wp_enqueue_script(
			'dashify_screen_options_script',
			plugins_url( '/admin/js/dashify-screen-options-script.js', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/admin/js/dashify-screen-options-script.js' )
		);

		$nonce = wp_create_nonce( 'save_dashify_option_nonce' );

		// Returning the false and true as strings is needed for them to appear
		// as booleans in JavaScript.
		$dashify_pro_enabled                      = is_plugin_active( 'dashify-pro/dashify-pro.php' ) ? 'true' : 'false';
		$line_item_menu_order_column_enabled      = get_option( 'dashify_line_item_menu_order_column_enabled', 'false' );
		$line_item_menu_order_column_default_sort = get_option( 'dashify_line_item_menu_order_column_default_sort', 'none' );

		wp_add_inline_script(
			'dashify_screen_options_script',
			"const dashifyScreenOptions = {
				nonce: '$nonce',
				dashifyOn: $dashify_on,
				dashifyProEnabled: $dashify_pro_enabled,
				lineItemMenuOrderColumnEnabled: $line_item_menu_order_column_enabled,
				lineItemMenuOrderColumnDefaultSort: '$line_item_menu_order_column_default_sort',
			};",
			'before'
		);
	}

	/**
	 * Enqueues files for 'dismissibles', currently only supporting a single
	 * item in the form of a flyout.
	 */
	public static function enqueue_dismissible() {
		if ( get_option( 'dashify_dismissed_release_notices_forever' ) ) {
			return;
		}

		$dismissedForCurrentVersion = in_array(
			self::VERSION,
			get_option( 'dashify_dismissed_notices', array() )
		);
		if ( $dismissedForCurrentVersion ) {
			return;
		}

		wp_enqueue_script(
			'dashify_dismissibles_script',
			plugins_url( '/admin/js/dashify-dismissibles-script.js', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/admin/js/dashify-dismissibles-script.js' )
		);

		// data to display in the flyout
		$dashifyDismissible = json_encode(
			array(
				'nonce'       => wp_create_nonce( 'mark_notice_dismissed_nonce' ),
				'heading'     => 'Dashify ' . self::VERSION,
				'description' => 'Check out what’s new in this release!',
				'content'     => array(
					array(
						'heading' => 'Bug fixes',
						'content' => array(
							'The styling of a button in TrackShip has been fixed just a little bit more!'
						),
					),
				),
			)
		);
		wp_add_inline_script(
			'dashify_dismissibles_script',
			"const dashifyDismissible = $dashifyDismissible;",
			'before'
		);
		wp_enqueue_style(
			'dashify_dismissibles_styles',
			plugins_url( '/admin/css/dashify-dismissibles-styles.css', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/admin/css/dashify-dismissibles-styles.css' )
		);
	}

	public static function dashify_current_page( $screen_id ) {
		$page = 'unknown';

		switch ( $screen_id ) {
			// If WooCommerce HPOS setting is enabled (https://woo.com/document/high-performance-order-storage/)
			case 'woocommerce_page_wc-orders':
				if ( isset( $_GET['action'] ) && ( $_GET['action'] === 'edit' || $_GET['action'] === 'new' ) ) {
					$page = 'order_summary';
				} else {
					$page = 'order_list';
				}
				break;
			case 'shop_order': // Order summary (without WooCommerce HPOS setting enabled)
				$page = 'order_summary';
				break;
			case 'edit-shop_order': // Orders list (without WooCommerce HPOS setting enabled)
				$page = 'order_list';
				break;
		}

		return apply_filters( 'dashify_current_page_filter', $page, $screen_id );
	}

	private function dashify_enqueue_order_summary_files() {
		wp_enqueue_script(
			'dashify_order_script',
			plugins_url( '/admin/js/dashify-order-script.js', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/admin/js/dashify-order-script.js' )
		);

		$admin_url                   = admin_url();
		$previous_and_next_order_ids = $this->prev_and_next_ids();
		$orders                      = json_encode( $previous_and_next_order_ids );
		$order_edit_page             = 'admin.php?page=wc-orders&action=edit&id=';
		// WooCommerce Subscriptions
		if ( $this->is_subscription_edit() ) {
			$order_edit_page =
				$this->is_HPOS_subscription_edit_page( get_current_screen()->id )
				? 'admin.php?page=wc-orders--shop_subscription&action=edit&id='
				: 'post.php?action=edit&post=';
		}
		$previous_order_url = admin_url( $order_edit_page . ( $previous_and_next_order_ids['prev'] ?? '' ) );
		$next_order_url     = admin_url( $order_edit_page . ( $previous_and_next_order_ids['next'] ?? '' ) );
		$timeZone           = wp_timezone_string();

		$is_subscription_edit = $this->is_subscription_edit();
		wp_add_inline_script(
			'dashify_order_script',
			"
			const dashify = {
				adminURL: '$admin_url',
				orders: $orders,
				previousOrderURL: '$previous_order_url',
				nextOrderURL: '$next_order_url',
				timeZone: '$timeZone',
			};
			const dashifyIsSubscriptionEdit = $is_subscription_edit;
			",
			'before'
		);

		wp_enqueue_style(
			'dashify_order_styles',
			plugins_url( '/admin/css/dashify-order-styles.css', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/admin/css/dashify-order-styles.css' )
		);
	}

	private function dashify_enqueue_subscription_edit_files() {
		wp_enqueue_style(
			'dashify_subscription_edit_styles',
			plugins_url( '/modules/subscriptions/edit.css', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/modules/subscriptions/edit.css' )
		);
		wp_enqueue_script(
			'dashify_subscription_edit_script',
			plugins_url( '/modules/subscriptions/edit.js', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/modules/subscriptions/edit.js' )
		);
	}

	private function dashify_enqueue_subscription_table_files() {
		wp_enqueue_style(
			'dashify_subscription_list_styles',
			plugins_url( '/modules/subscriptions/list.css', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/modules/subscriptions/list.css' )
		);
		wp_enqueue_script(
			'dashify_subscription_list_script',
			plugins_url( '/modules/subscriptions/list.js', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/modules/subscriptions/list.js' )
		);
	}

	/**
	 * Returns previous and next order ids
	 *
	 * @return array array( 'prev' => [previous order id or -1 if n.a.], 'next' => [next order id or -1 if n.a.] )
	 */
	private function prev_and_next_ids() {
		// If we're on the "Add new order" page, we don't need to do this, as we
		// don't have any `id` or `post`.
		if ( ! isset( $_GET['id'] ) && ! isset( $_GET['post'] ) ) {
			return array();
		}
		$current_id   = intval( $this->isHPOS() ? $_GET['id'] : $_GET['post'] );
		$statusFilter =
			isset( $_GET['status'] ) ? $_GET['status'] :
			( isset( $_GET['post_status'] ) ? $_GET['post_status'] : false );
		// The order status in the WC_Order object does not contain the wc-
		// prefix, so we remove it here.
		$statusFilter = str_replace( 'wc-', '', $statusFilter );
		$prev         = -1;
		$next         = -1;
		for ( $ptr = $current_id - 1; $ptr > 0; $ptr-- ) {
			$order = wc_get_order( $ptr );
			// WooCommerce Subscriptions
			if ( $this->is_subscription_edit() ) {
				$order = wcs_get_subscription( $ptr );
			}
			if ( ! $order ) {
				continue;
			}
			if ( ! $this->is_subscription_edit() && $order instanceof WC_Subscription ) {
				continue;
			}
			$status = $order->get_base_data()['status'];
			if ( 'trash' === $status ) {
				continue;
			}
			if ( 'completed' === $status && array_key_exists( 'refunded_by', $order->get_base_data() ) ) {
				continue;
			}
			if ( $statusFilter && $statusFilter !== 'all' && $status !== $statusFilter ) {
				continue;
			}
			$prev = $ptr;
			break;
		}
		for ( $ptr = $current_id + 1; $ptr <= $this->get_single_order( 'DESC' ); $ptr++ ) {
			$order = wc_get_order( $ptr );
			// WooCommerce Subscriptions
			if ( $this->is_subscription_edit() ) {
				$order = wcs_get_subscription( $ptr );
			}
			if ( ! $order ) {
				continue;
			}
			if ( ! $this->is_subscription_edit() && $order instanceof WC_Subscription ) {
				continue;
			}
			$status = $order->get_base_data()['status'];
			if ( 'trash' === $status ) {
				continue;
			}
			if ( 'completed' === $status && array_key_exists( 'refunded_by', $order->get_base_data() ) ) {
				continue;
			}
			if ( $statusFilter && $statusFilter !== 'all' && $status !== $statusFilter ) {
				continue;
			}
			$next = $ptr;
			break;
		}
		return array(
			'prev' => $prev,
			'next' => $next,
		);
	}

	/**
	 * @param string $direction DESC or ASC
	 *
	 * @return -1 if there are no orders
	 */
	private function get_single_order( string $direction ): int {
		// WooCommerce Subscriptions
		if ( $this->is_subscription_edit() ) {
			$subscriptions = wcs_get_subscriptions(
				array(
					'subscriptions_per_page' => 1,
					'order'                  => $direction,
				)
			);
			if ( empty( $subscriptions ) ) {
				return -1;
			}
			return reset( $subscriptions )->get_id();
		}

		$query  = new WC_Order_Query(
			array(
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => $direction,
				'return'  => 'ids',
			)
		);
		$orders = $query->get_orders();

		if ( empty( $orders ) ) {
			return -1;
		}

		return $orders[0];
	}

	/**
	 * Enqueues JS and CSS files for restyling WooCommerce tables.
	 * So far, works for the order list, subscription list (partially), and the product list pages.
	 */
	private function dashify_enqueue_table_files() {
		wp_enqueue_script(
			'dashify_table_script',
			plugins_url( '/admin/js/dashify-table-script.js', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/admin/js/dashify-table-script.js' )
		);

		$nonce = wp_create_nonce( 'dashify_order_list_analytics_nonce' );

		try {
			$range     = get_option( 'dashify_analytics_range', 7 );
			$analytics = $this->is_subscription_list()
				? $this->calculate_subscription_list_analytics( $range )
				: $this->calculate_order_list_analytics( $range );
		} catch ( Exception $exception ) {
			error_log( $exception );
		}

		$sections             = $this->is_subscription_list()
			? $this->create_subscription_list_analytics_sections( $analytics )
			: $this->create_order_list_analytics_sections( $analytics );
		$sections             = json_encode( $sections );
		$is_subscription_list = $this->is_subscription_list();

		// Returning the false and true as strings is needed for them to appear
		// as booleans in JavaScript.
		$open_search_and_filter_by_default = get_option( 'dashify_list_table_open_search_and_filter_by_default', 'no' ) === 'yes' ? 'true' : 'false';

		wp_add_inline_script(
			'dashify_table_script',
			"const dashifyAnalyticsAJAX = {
				nonce: '$nonce',
			};

			let dashifyAnalyticsData = {
				analytics_range: {$analytics['analytics_range']},
				sections: $sections,
				intervalData: {$analytics['intervalData']},
				graphDivisions: {$analytics['graphDivisions']},
				divisionMaxima: {$analytics['divisionMaxima']},
			};

			const isDashifySubscriptionList = $is_subscription_list;

			const openSearchAndFilterByDefault = $open_search_and_filter_by_default;
			",
			'before'
		);

		wp_enqueue_style(
			'dashify_table_styles',
			plugins_url( '/admin/css/dashify-table-styles.css', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/admin/css/dashify-table-styles.css' )
		);
	}

	private function dashify_enqueue_order_table_files() {
		wp_enqueue_script(
			'dashify_order_table_script',
			plugins_url( '/admin/js/dashify-order-table-script.js', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . '/admin/js/dashify-order-table-script.js' )
		);
		$has_subscriptions = json_encode( is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) );
		wp_add_inline_script(
			'dashify_order_table_script',
			"const dashifyHasWooCommerceSubscriptions = $has_subscriptions;",
			'before'
		);
	}

	private function calculate_order_list_analytics( $days ) {
		$analytics_range = intval( $days );

		$midnight = new DateTimeImmutable( 'midnight', wp_timezone() );
		$now      = new DateTimeImmutable( 'now', wp_timezone() );

		$start_time = $analytics_range === 0 ? $midnight->getTimestamp() : ( $now->getTimestamp() - $analytics_range * 86400 );
		$end_time   = $now->getTimestamp();
		$orders     = wc_get_orders(
			array(
				'date_created' => '' . $start_time . '...' . $end_time,
				'limit'        => -1,
			)
		);

		$num_orders    = 0;
		$num_refunded  = 0;
		$num_completed = 0;

		$graph_divisions = $analytics_range;
		if ( 1 === $analytics_range ) {
			$graph_divisions = 24;
		}
		if ( 0 === $analytics_range ) {
			$interval             = $midnight->diff( $now );
			$hours_since_midnight = $interval->h + ( $interval->i / 60 ) + ( $interval->s / 3600 );
			$graph_divisions      = $hours_since_midnight;
		}
		$s_per_interval  = ( 0 === $analytics_range || 1 === $analytics_range ) ? 3600 : 86400;
		$interval_data   = array();
		$division_maxima = array(
			'num_orders'    => 0,
			'num_refunded'  => 0,
			'num_completed' => 0,
		);
		foreach ( $orders as $order ) {
			$order_data = $order->get_base_data();
			if ( 'deleted' === $order_data['status'] ) {
				continue;
			}
			if ( 'completed' === $order_data['status'] && array_key_exists( 'refunded_by', $order_data ) ) {
				continue;
			}
			$date_created    = $order_data['date_created']->getTimestamp();
			$interval_number = intdiv( ( $date_created - $start_time ), $s_per_interval );
			if ( ! array_key_exists( $interval_number, $interval_data ) ) {
				$interval_data[ $interval_number ] = array(
					'num_orders'    => 0,
					'num_refunded'  => 0,
					'num_completed' => 0,
				);
			}
			++$num_orders;
			$interval_data[ $interval_number ]['num_orders'] += 1;
			$division_maxima['num_orders']                    = max( $division_maxima['num_orders'], $interval_data[ $interval_number ]['num_orders'] );
			if ( 'refunded' === $order_data['status'] ) {
				++$num_refunded;
				$interval_data[ $interval_number ]['num_refunded'] += 1;
				$division_maxima['num_refunded']                    = max( $division_maxima['num_refunded'], $interval_data[ $interval_number ]['num_refunded'] );
			} elseif ( 'completed' === $order_data['status'] ) {
				++$num_completed;
				$interval_data[ $interval_number ]['num_completed'] += 1;
				$division_maxima['num_completed']                    = max( $division_maxima['num_completed'], $interval_data[ $interval_number ]['num_completed'] );
			}
		}
		$interval_data   = json_encode( $interval_data );
		$division_maxima = json_encode( $division_maxima );

		return array(
			'analytics_range' => $analytics_range,
			'num_orders'      => $num_orders,
			'num_refunded'    => $num_refunded,
			'num_completed'   => $num_completed,
			'intervalData'    => $interval_data,
			'graphDivisions'  => $graph_divisions,
			'divisionMaxima'  => $division_maxima,
		);
	}

	private function calculate_subscription_list_analytics( $days ) {
		$range = intval( $days );

		$subscription_revenue        = 0;
		$num_new_subscriptions       = 0;
		$num_cancelled_subscriptions = 0;

		$midnight        = new DateTimeImmutable( 'midnight', wp_timezone() );
		$now             = new DateTimeImmutable( 'now', wp_timezone() );
		$graph_divisions = ( 1 === $range ) ? 24 : $range;
		if ( 0 === $range ) {
			$interval             = $midnight->diff( $now );
			$hours_since_midnight = $interval->h + ( $interval->i / 60 ) + ( $interval->s / 3600 );
			$graph_divisions      = $hours_since_midnight;
		}
		$seconds_per_interval = ( 0 === $range || 1 === $range ) ? 3600 : 86400;
		$interval_data        = array();
		$division_maxima      = array(
			'subscription_revenue'        => 0,
			'num_new_subscriptions'       => 0,
			'num_cancelled_subscriptions' => 0,
		);

		$start_time = $range === 0 ? $midnight->getTimestamp() : ( $now->getTimestamp() - $range * 86400 );
		$end_time   = $now->getTimestamp();

		// wcs_get_subscriptions() does not support passing in a date range
		// (https://github.com/riclain/woocommerce-subscriptions/blob/master/wcs-functions.php#L332)
		// so we have to get the posts directly so that we can pass in date_query.
		$subscription_post_ids = get_posts(
			array(
				'numberposts' => -1, // Get all that match.
				'post_type'   => 'shop_subscription',
				'post_status' => array( 'wc-active', 'wc-cancelled' ),
				'orderby'     => 'post_date',
				'order'       => 'ASC',
				'date_query'  => array(
					array(
						'after'     => date( 'c', $start_time ),
						'before'    => date( 'c', $end_time ),
						'inclusive' => true,
					),
				),
				'fields'      => 'ids', // Return just IDs—we will create WC_Subscription object from these.
			)
		);
		$subscriptions         = array();
		foreach ( $subscription_post_ids as $post_id ) {
			$subscriptions[ $post_id ] = wcs_get_subscription( $post_id );
		}

		foreach ( $subscriptions as $subscription ) {
			if ( 'deleted' === $subscription->get_status() ) {
				continue;
			}

			$subscription_data = $subscription->get_base_data();
			$date_created      = $subscription_data['date_created']->getTimestamp();
			$interval_number   = intdiv( ( $date_created - $start_time ), $seconds_per_interval );
			if ( ! array_key_exists( $interval_number, $interval_data ) ) {
				$interval_data[ $interval_number ] = array(
					'subscription_revenue'        => 0,
					'num_new_subscriptions'       => 0,
					'num_cancelled_subscriptions' => 0,
				);
			}

			$related_orders = $subscription->get_related_orders( 'all' );
			foreach ( $related_orders as $order ) {
				$amount                = $order->get_total( 'edit' );
				$subscription_revenue += $amount;
				$interval_data[ $interval_number ]['subscription_revenue'] += $amount;
				$division_maxima['subscription_revenue']                    = max( $division_maxima['subscription_revenue'], $interval_data[ $interval_number ]['subscription_revenue'] );
			}

			if ( 'cancelled' === $subscription->get_status() ) {
				++$num_cancelled_subscriptions;
				$interval_data[ $interval_number ]['num_cancelled_subscriptions'] += 1;
				$division_maxima['num_cancelled_subscriptions']                    = max( $division_maxima['num_cancelled_subscriptions'], $interval_data[ $interval_number ]['num_cancelled_subscriptions'] );
			} else {
				++$num_new_subscriptions;
				$interval_data[ $interval_number ]['num_new_subscriptions'] += 1;
				$division_maxima['num_new_subscriptions']                    = max( $division_maxima['num_new_subscriptions'], $interval_data[ $interval_number ]['num_new_subscriptions'] );
			}
		}

		$interval_data   = json_encode( $interval_data );
		$division_maxima = json_encode( $division_maxima );

		return array(
			'analytics_range'             => $range,
			'subscription_revenue'        => $subscription_revenue,
			'num_new_subscriptions'       => $num_new_subscriptions,
			'num_cancelled_subscriptions' => $num_cancelled_subscriptions,
			'intervalData'                => $interval_data,
			'graphDivisions'              => $graph_divisions,
			'divisionMaxima'              => $division_maxima,
		);
	}

	private function create_order_list_analytics_sections( $analytics ) {
		return array(
			array(
				'label' => 'Total orders',
				'value' => $analytics['num_orders'],
				'key'   => 'num_orders',
			),
			array(
				'label' => 'Refunded',
				'value' => $analytics['num_refunded'],
				'key'   => 'num_refunded',
			),
			array(
				'label' => 'Completed',
				'value' => $analytics['num_completed'],
				'key'   => 'num_completed',
			),
		);
	}

	private function create_subscription_list_analytics_sections( $analytics ) {
		$revenue = numfmt_format_currency(
			numfmt_create( get_locale(), NumberFormatter::CURRENCY ),
			$analytics['subscription_revenue'],
			get_option( 'woocommerce_currency' )
		);

		return array(
			array(
				'label' => 'Subscription revenue',
				'value' => $revenue,
				'key'   => 'subscription_revenue',
			),
			array(
				'label' => 'New subscriptions',
				'value' => $analytics['num_new_subscriptions'],
				'key'   => 'num_new_subscriptions',
			),
			array(
				'label' => 'Cancelled subscriptions',
				'value' => $analytics['num_cancelled_subscriptions'],
				'key'   => 'num_cancelled_subscriptions',
			),
		);
	}

	private function is_subscription_list() {
		$current_page = $this->dashify_current_page( get_current_screen()->id );
		// Ternary is needed because PHP will otherwise give empty string for false.
		return 'subscription_list' === $current_page ? 1 : 0;
	}

	private function is_subscription_edit() {
		$current_page = $this->dashify_current_page( get_current_screen()->id );
		return 'subscription_edit' === $current_page ? 1 : 0;
	}

	/**
	 * Set the positions of the meta boxes in the order edit view to those
	 * preferred by Dashify upon first activation.
	 *
	 * This will also run a single time after anyone updates an older version
	 * of Dashify to the version specified below.
	 *
	 * @since 1.2.8
	 */
	public function migrate_order_edit_meta_box_layout_to_user_meta() {
		if ( get_option( 'dashify_migrated_order_edit_meta_box_layout_to_user_meta' ) ) {
			return;
		}
		// People might switch between the two, at least it seems like it based
		// on our blog post for turning off HPOS being so popular, so we’ll clear
		// the user_option for both HPOS and non-HPOS.
		delete_user_option( get_current_user_id(), 'meta-box-order_woocommerce_page_wc-orders' );
		delete_user_option( get_current_user_id(), 'meta-box-order_shop_order' );
		$this->move_order_edit_meta_boxes();
		update_option( 'dashify_migrated_order_edit_meta_box_layout_to_user_meta', 1 );
	}

	/**
	 * @since 1.2.8
	 */
	public function migrate_subscription_edit_meta_box_layout_to_user_meta() {
		if ( ! is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
			return;
		}
		if ( get_option( 'dashify_migrated_subscription_edit_meta_box_layout_to_user_meta' ) ) {
			return;
		}
		$this->move_subscription_edit_meta_boxes();
		update_option( 'dashify_migrated_subscription_edit_meta_box_layout_to_user_meta', 1 );
	}

	public function list_table_settings( $fields ) {
		$fields[] = array(
			'name'     => 'List table search',
			'type'     => 'checkbox',
			'default'  => 'no',
			'desc'     => 'Expand the search and filter by default',
			'desc_tip' => 'When checked, the search and filter on list tables (orders, products, etc.) will be open by default. This is helpful if you frequently use the search or filters.',
			'id'       => 'dashify_list_table_open_search_and_filter_by_default',
		);
		return $fields;
	}
}

$dashify = new Dashify_Base();
