<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The current page.
 *
 * @global string $self
 */
$self = preg_replace( '|^.*/wp-admin/network/|i', '', $_SERVER['PHP_SELF'] );
$self = preg_replace( '|^.*/wp-admin/|i', '', $self );
$self = preg_replace( '|^.*/plugins/|i', '', $self );
$self = preg_replace( '|^.*/mu-plugins/|i', '', $self );

class Dashify_Navigation {
	public function __construct() {
		// Since there is no WooCommerce in the Network Admin, we skip creating
		// a custom navigation.
		if ( is_network_admin() ) {
			return;
		}

		add_filter( 'dashify_settings', array( $this, 'add_settings' ) );
		if ( get_option( 'dashify_navigation_enabled', 'yes' ) === 'no' ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_files' ) );
		add_action( 'admin_footer', array( $this, 'render_navigation' ) );
		add_filter(
			'custom_menu_order',
			function () {
				return true;
			}
		);
		add_filter( 'menu_order', array( $this, 'move_woocommerce_to_top' ) );
	}

	public function enqueue_files() {
		wp_enqueue_style(
			'dashify_navigation_styles',
			plugins_url( 'navigation.css', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . 'navigation.css' )
		);
	}

	public function render_navigation() {
		$items = $this->get_navigation_items();

		echo <<<HTML
			<nav id="dashify-navigation">
				<ul>
					$items
				</ul>
			</nav>
			<script>
				// Move our custom navigation to replace the original one.
				const dashifyNavigation = document.querySelector('#dashify-navigation');
				const container = document.querySelector('#adminmenuwrap');
				container.insertAdjacentElement('afterbegin', dashifyNavigation);

				const chevrons = document.querySelectorAll('.dashify-menu-chevron');
				for (const chevron of chevrons) {
					chevron.addEventListener('click', event => {
						event.preventDefault();
						const li = event.target.parentNode.parentNode;
						li.classList.toggle('dashify-has-open-submenu');
						const submenu = li.querySelector('.dashify-submenu');
						submenu.classList.toggle('visible');
					});
				}

				// Compatibility: Ultimate Dashboard (https://wordpress.org/plugins/ultimate-dashboard/)
				const udbLogo = document.querySelector('.udb-admin-logo-wrapper');
				if (udbLogo) {
					document.querySelector('#dashify-navigation > ul').prepend(udbLogo);
				}

				// Compatibility: Admin Menu Editor (https://wordpress.org/plugins/admin-menu-editor/)
				const ameLogo = document.querySelector('#ame_ms_admin_menu_logo')
				if (ameLogo) {
					const ameLogoImageURL = window.getComputedStyle(ameLogo).getPropertyValue('background-image');
					document.querySelector('#dashify-navigation > ul').prepend(ameLogo);
					ameLogo.style.backgroundImage = ameLogoImageURL;
				}
				const ameMenuHeadingLinks = document.querySelectorAll('a.ame-menu-heading-item');
				if (ameMenuHeadingLinks) {
					for (const a of ameMenuHeadingLinks) {
						a.removeAttribute('href');
					}
				}
			</script>
HTML;
	}

	/**
	 * This is a function copied from WordPress core: wp-admin/menu-header.php
	 * It has been modified in quite a few places, but I’ve tried to keep the
	 * structure the same to maximize compatibility with everything that depends
	 * on it in the WordPress ecosystem, which does result in probably more code
	 * than necessary, but I’ve weighed this against the need to ensure compatibility
	 * as well as time constraints.
	 */
	private function get_navigation_items( $submenu_as_parent = true ) {
		global $menu, $submenu, $self, $parent_file, $submenu_file, $plugin_page, $typenow;

		if ( is_plugin_active( 'admin-menu-editor/menu-editor.php' ) || is_plugin_active( 'admin-menu-editor-pro/menu-editor.php' ) ) {
			global $wp_menu_editor;
			if ( isset( $wp_menu_editor ) && $wp_menu_editor->load_custom_menu() ) {
				$wp_menu_editor->replace_wp_menu();
			}
		}

		if ( is_plugin_active( 'ultimate-dashboard-pro/ultimate-dashboard-pro.php' ) ) {
			if ( $this->udb_admin_menu_editor_enabled() && class_exists( '\UdbPro\AdminMenu\Admin_Menu_Output' ) ) {
				$UbdPro_Admin_Menu_Output = \UdbPro\AdminMenu\Admin_Menu_Output::get_instance();
				$UbdPro_Admin_Menu_Output::init();
				$UbdPro_Admin_Menu_Output->menu_output();
			}
		}

		$has_admin_menu_editor_plugin = $this->has_admin_menu_editor_plugin();
		$html                         = '';

		if ( ! $has_admin_menu_editor_plugin ) {
			$menu[] = array(
				'More',
				'manage_woocommerce',
				'woocommerce-more',
				'More',
			);

			// Move some items out of the WooCommerce submenu that we’re going to hide
			// under a More item. After the foreach, the items are at the way end of the
			// menu array.
			foreach ( $menu as $key => $item ) {
				if ( $item[2] !== 'woocommerce' ) {
					continue;
				}

				$submenu_items = array();

				if ( ! empty( $submenu[ $item[2] ] ) ) {
					$submenu_items = $submenu[ $item[2] ];
				}

				$to_move = array(
					'wc-settings',
					'wc-reports',
					'wc-status',
					'wc-admin&path=/extensions',
				);

				foreach ( $submenu_items as $sub_key => $sub_item ) {
					$slug = $sub_item[2];
					if ( in_array( $slug, $to_move ) ) {
						$temp = $submenu_items[ $sub_key ];

						// Manually set the complete slug to account for it being out of its WooCommerce parent.
						$temp[2] = 'admin.php?page=' . $temp[2];

						if ( $temp[2] === 'admin.php?page=wc-settings' ) {
							// Settings will move out of WooCommerce submenu to top level menu.
							array_push( $menu, $temp );
						} else {
							// For the others, we’ll move them as subitems under More.
							foreach ( $menu as $k => $v ) {
								if ( 'woocommerce-more' === $v[2] ) {
									if ( ! isset( $submenu['woocommerce-more'] ) ) {
										$submenu['woocommerce-more'] = array();
									}
									$submenu['woocommerce-more'][] = $temp;
									break;
								}
							}
						}
					}
				}
			}

			// We’re going to move them to the end of the top level WooCommerce items.
			$menu = $this->move_item_after( $menu, 'woocommerce-more', 'woocommerce-marketing' );
			$menu = $this->move_item_after( $menu, 'admin.php?page=wc-settings', 'woocommerce-marketing' );
			$menu = $this->move_item_after( $menu, 'admin.php?page=wc-settings&tab=checkout', 'edit.php?post_type=product' );
			$menu = $this->move_item_after( $menu, 'wf_woocommerce_packing_list', 'edit.php?post_type=product' );
		}

		$first = true;
		// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url.
		foreach ( $menu as $key => $item ) {
			$admin_is_parent = false;
			$class           = array();
			$aria_attributes = '';
			$aria_hidden     = '';
			$is_separator    = false;

			if ( $first ) {
				$class[] = 'wp-first-item';
				$first   = false;
			}

			$submenu_items = array();
			if ( ! empty( $submenu[ $item[2] ] ) ) {
				$class[]       = 'wp-has-submenu';
				$submenu_items = $submenu[ $item[2] ];
			}

			// For some reason, even though these have submenus, they aren’t
			// picked up by the original condition, so I’ve a check for each of
			// the pages to ensure that they get the class `dashify-has-open-submenu`
			// when they need to.
			$viewing_add_product_page = $item[2] === 'edit.php?post_type=product'
				&& isset( $_GET['path'] )
				&& strpos( $_GET['path'], '/add-product' ) !== false;
			$viewing_payments_page    = $item[2] === 'wc-admin&path=/payments/overview'
				&& isset( $_GET['path'] )
				&& strpos( $_GET['path'], '/payments' ) !== false;
			$viewing_analytics_page   = $item[2] === 'wc-admin&path=/analytics/overview'
				&& isset( $_GET['path'] )
				&& strpos( $_GET['path'], '/analytics' ) !== false;
			$viewing_marketing_page   = $item[2] === 'woocommerce-marketing'
				&& isset( $_GET['path'] )
				&& strpos( $_GET['path'], '/marketing' ) !== false;
			// This one is a custom top level menu item made for holding extra items.
			$viewing_more_page =
				strpos( $item[2], 'woocommerce-more' ) !== false
				&& isset( $_GET['page'] )
				&& (
					strpos( $_GET['page'], 'wc-reports' ) !== false
					|| strpos( $_GET['page'], 'wc-status' ) !== false
					|| ( isset( $_GET['path'] ) && strpos( $_GET['path'], '/extensions' ) !== false )
				);

			if (
				( $parent_file && $item[2] === $parent_file )
				|| ( empty( $typenow ) && $self === $item[2] )
				|| $viewing_add_product_page
				|| $viewing_payments_page
				|| $viewing_analytics_page
				|| $viewing_marketing_page
				|| $viewing_more_page
			) {
				if ( ! empty( $submenu_items ) ) {
					$class[] = 'dashify-has-open-submenu';
				} else {
					$class[]          = 'dashify-current';
					$aria_attributes .= 'aria-current="page"';
				}
			} else {
				$class[] = 'wp-not-current-submenu';

				if ( $item[2] === 'wc-admin&path=/wc-pay-welcome-page' && isset( $_GET['path'] ) && strpos( $_GET['path'], '/wc-pay' ) !== false ) {
					$class[] = 'dashify-current';
				}

				if ( $item[2] === 'wc-admin&path=/payments/connect' && isset( $_GET['path'] ) && strpos( $_GET['path'], '/payments/connect' ) !== false ) {
					$class[] = 'dashify-current';
				}

				if ( $item[2] === 'wc-admin&path=/payments/overview' && isset( $_GET['path'] ) && strpos( $_GET['path'], '/payments/overview' ) !== false ) {
					$class[] = 'dashify-current';
				}

				// We moved this item out of the WooCommerce submenu and into the top level menu.
				// We’re rendering the Settings menu item (or possibly the Payments menu item), and the user is on any settings page.
				if ( strpos( $item[2], 'wc-settings' ) !== false && isset( $_GET['page'] ) && $_GET['page'] === 'wc-settings' ) {
					if ( strpos( $item[2], 'tab=checkout' ) !== false && isset( $_GET['tab'] ) && $_GET['tab'] === 'checkout' ) {
						// If we’re rendering the Payments menu item that links to the payment gateway settings
						// and the user is in the payment gateway settings, we want to highlight the Payments menu item.
						$class[] = 'dashify-current';
					} elseif ( strpos( $item[2], 'tab=checkout' ) === false && isset( $_GET['tab'] ) && $_GET['tab'] !== 'checkout' ) {
						// If we’re rending any menu item other than Payments and the user is on a tab other
						// than the payment methods settings, we want to highlight the Settings menu item.
						$class[] = 'dashify-current';
					} elseif ( strpos( $item[2], 'tab=checkout' ) === false && ! isset( $_GET['tab'] ) ) {
						// If we’re rendering any menu item other than Payments and there is no tab set in the URL,
						// which means they clicked directly on the Settings menu item and are viewing the General settings,
						// we want to highlight the Settings menu item.
						$class[] = 'dashify-current';
					}
				}

				if ( ! empty( $submenu_items ) ) {
					$aria_attributes .= 'data-ariahaspopup';
				}
			}

			if ( ! empty( $item[4] ) ) {
				$class[] = esc_attr( $item[4] );
			}

			if ( $has_admin_menu_editor_plugin ) {
				$class[] = 'dashify-has-admin-menu-editor-plugin';
			}

			$class = $class ? ' class="' . implode( ' ', $class ) . '"' : '';
			$id    = ! empty( $item[5] ) ? ' id="' . preg_replace( '|[^a-zA-Z0-9_:.]|', '-', $item[5] ) . '"' : '';
			// $toplevel_page_class was added as a class to the icon container
			// divs to fix some plugins whose CSS is based on the original layout.
			// As a future improvement, we could consider how to render a custom
			// navigation while keeping the CSS structure intact.
			$id_plain            = ! empty( $item[5] ) ? preg_replace( '|[^a-zA-Z0-9_:.]|', '-', $item[5] ) : '';
			$toplevel_page_class = str_starts_with( $id_plain, 'toplevel_page' ) ? $id_plain : '';
			$img                 = '';
			$img_style           = '';
			$img_class           = ' dashicons-before';

			if ( str_contains( $class, 'wp-menu-separator' ) ) {
				if ( $has_admin_menu_editor_plugin ) {
					$is_separator = true;
				} else {
					continue;
				}
			}

			/*
			 * If the string 'none' (previously 'div') is passed instead of a URL, don't output
			 * the default menu image so an icon can be added to div.wp-menu-image as background
			 * with CSS. Dashicons and base64-encoded data:image/svg_xml URIs are also handled
			 * as special cases.
			 */
			if ( ! empty( $item[6] ) ) {
				$img = '<img src="' . esc_url( $item[6] ) . '" alt="" />';

				if ( 'none' === $item[6] || 'div' === $item[6] ) {
					$img = '<br />';
				} elseif ( str_starts_with( $item[6], 'data:image/svg+xml;base64,' ) ) {
					$img = '<br />';
					// The value is base64-encoded data, so esc_attr() is used here instead of esc_url().
					$img_style = ' style="background-image:url(\'' . esc_attr( $item[6] ) . '\')"';
					$img_class = ' svg';
				} elseif ( str_starts_with( $item[6], 'dashicons-' ) ) {
					$img       = '<br />';
					$img_class = ' dashicons-before ' . sanitize_html_class( $item[6] );
				}
			}

			$title = wptexturize( $item[0] );

			// Hide separators from screen readers.
			if ( $is_separator ) {
				$aria_hidden = ' aria-hidden="true"';
			}

			// index.php = Dashboard menu item
			$heading = '';
			if ( ! $has_admin_menu_editor_plugin ) {
				$heading = $item[2] === 'index.php' ? '<span class="dashify-menu-heading">Site management</span>' : '';
			}

			$html .= "
				$heading
				<li $class $id $aria_hidden>
			";

			$has_custom_icon = array(
				'edit.php?post_type=product'              => 'products',
				'wf_woocommerce_packing_list'             => 'invoice',
				'admin.php?page=wc-settings&tab=checkout' => 'payments',
				'wc-admin&path=/wc-pay-welcome-page'      => 'payments',
				'wc-admin&path=/payments/connect'         => 'payments',
				'wc-admin&path=/payments/overview'        => 'payments',
				'wc-admin&path=/analytics/overview'       => 'analytics',
				'woocommerce-marketing'                   => 'marketing',
				'admin.php?page=wc-settings'              => 'settings',
				'woocommerce-more'                        => 'more',
				'index.php'                               => 'dashboard',
				'edit.php'                                => 'posts',
				'upload.php'                              => 'media',
				'edit.php?post_type=page'                 => 'pages',
				'edit-comments.php'                       => 'comments',
				'wpforms-overview'                        => 'form',
				'rank-math'                               => 'rank-math',
				'themes.php'                              => 'appearance',
				'plugins.php'                             => 'plugins',
				'snippets'                                => 'scissors',
				'users.php'                               => 'users',
				'tools.php'                               => 'tools',
				'options-general.php'                     => 'settings',
				'settings.php'                            => 'settings', // Network Settings for Multisite WordPress
			);

			$icon = '';
			foreach ( $has_custom_icon as $slug => $icon_file ) {
				if ( $item[2] === $slug ) {
					$icon = '<img src="' . plugins_url( 'icons/' . $icon_file . '.svg', __FILE__ ) . '" width="15" height="18">';
					break;
				}
			}
			// Use the original menu icon if we don’t have a custom one,
			// or if they have set a custom icon in a menu editor plugin, use that.
			$ame_has_custom_icon = str_contains( $class, 'ame-has-custom-image-url' );
			if ( ( $icon === '' || $ame_has_custom_icon ) && isset( $item[6] ) ) {
				$icon = "<div class='wp-menu-image$img_class'$img_style aria-hidden='true'>$img</div>";
			}

			if ( $is_separator ) {
				$html .= '<div class="separator"></div>';
			} elseif ( $submenu_as_parent && ! empty( $submenu_items ) ) {
				$submenu_items = array_values( $submenu_items ); // Re-index.
				$menu_hook     = get_plugin_page_hook( $submenu_items[0][2], $item[2] );
				$menu_file     = $submenu_items[0][2];
				$pos           = strpos( $menu_file, '?' );

				if ( false !== $pos ) {
					$menu_file = substr( $menu_file, 0, $pos );
				}

				if ( ! empty( $menu_hook )
					|| ( ( 'index.php' !== $submenu_items[0][2] )
						&& file_exists( WP_PLUGIN_DIR . "/$menu_file" )
						&& ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) )
				) {
					$admin_is_parent = true;
					if ( $item[2] !== 'woocommerce' || $has_admin_menu_editor_plugin ) {
						$style = $item[2] === 'meowapps-main-menu' ? 'style="padding-left: 24px;"' : '';
						$html .= "
							<div class='dashify-menu-icon-title-chevron-container'>
								<div class='dashify-menu-icon-title-container $toplevel_page_class'>
									$icon
									<a href='admin.php?page={$submenu_items[0][2]}' $class $style $aria_attributes>
										$title
									</a>
								</div>
						";
					}
				} else {
					$html .= "
						<div class='dashify-menu-icon-title-chevron-container'>
							<div class='dashify-menu-icon-title-container $toplevel_page_class'>
								$icon
								<a href='{$submenu_items[0][2]}' $class $aria_attributes>
									$title
								</a>
							</div>
					";
				}
				if ( ! empty( $submenu_items ) && ( $item[2] !== 'woocommerce' || $has_admin_menu_editor_plugin ) ) {
					$html .= '
							<img
								class="dashify-menu-chevron"
								src="' . plugins_url( 'icons/chevron-down.svg', __FILE__ ) . '"
								width="10"
								height="10"
							>
						</div>
					';
				}
				$html .= '</a>';
			} elseif ( ! empty( $item[2] ) && current_user_can( $item[1] ) ) {
				$menu_hook = get_plugin_page_hook( $item[2], 'admin.php' );
				$menu_file = $item[2];
				$pos       = strpos( $menu_file, '?' );

				if ( false !== $pos ) {
					$menu_file = substr( $menu_file, 0, $pos );
				}

				if ( ! empty( $menu_hook )
					|| ( ( 'index.php' !== $item[2] )
						&& file_exists( WP_PLUGIN_DIR . "/$menu_file" )
						&& ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) )
				) {
					$admin_is_parent = true;
					$html           .= "
						<div class='dashify-menu-icon-title-container $toplevel_page_class'>
							{$icon}
							<a href='admin.php?page={$item[2]}' $class $aria_attributes>
								{$title}
							</a>
						</div>
					";
				} else {
					$html .= "
						<div class='dashify-menu-icon-title-container $toplevel_page_class'>
							{$icon}
							<a href='{$item[2]}' $class $aria_attributes>
								{$title}
							</a>
						</div>
					";
				}
			}

			if ( ! empty( $submenu_items ) ) {
				if ( $item[2] === 'woocommerce' && ! $has_admin_menu_editor_plugin ) {
					$html .= "\n\t<ul class='wp-submenu wp-submenu-wrap'>";
				} else {
					$html .= "\n\t<ul class='wp-submenu wp-submenu-wrap dashify-submenu'>";
				}

				$first = true;

				// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes.
				foreach ( $submenu_items as $sub_key => $sub_item ) {
					if ( ! current_user_can( $sub_item[1] ) ) {
						continue;
					}

					// We moved these WooCommerce submenu items out of the submenu.
					// We’ll skip these so they are not rendered.
					$moved_submenu_items = array( 'wc-reports', 'wc-settings', 'wc-status', 'wc-admin&path=/extensions' );
					if ( in_array( $sub_item[2], $moved_submenu_items ) && ! $has_admin_menu_editor_plugin ) {
						continue;
					}

					$class           = array();
					$aria_attributes = '';

					if ( $first ) {
						$class[] = '';
						$first   = false;
					}

					$menu_file = $item[2];
					$pos       = strpos( $menu_file, '?' );

					if ( false !== $pos ) {
						$menu_file = substr( $menu_file, 0, $pos );
					}

					// Handle current for post_type=post|page|foo pages, which won't match $self.
					$self_type = ! empty( $typenow ) ? $self . '?post_type=' . $typenow : 'nothing';

					if ( isset( $submenu_file ) ) {
						if ( $submenu_file === $sub_item[2] ) {
							$class[]          = 'dashify-current';
							$aria_attributes .= ' aria-current="page"';
						}
						/*
						* If plugin_page is set the parent must either match the current page or not physically exist.
						* This allows plugin pages with the same hook to exist under different parents.
						*/
					} elseif (
						( ! isset( $plugin_page ) && $self === $sub_item[2] )
						|| ( isset( $plugin_page ) && $plugin_page === $sub_item[2]
							&& ( $item[2] === $self_type || $item[2] === $self || file_exists( $menu_file ) === false ) )
					) {
						if (
							! isset( $_GET['path'] )
							|| (
								// Ensure that Home doesn’t get highlighted when any
								// of these pages whose root page is also wc-admin are active.
								strpos( $_GET['path'], '/customers' ) === false
								&& strpos( $_GET['path'], '/add-product' ) === false
								&& strpos( $_GET['path'], '/extensions' ) === false
								&& strpos( $_GET['path'], '/wc-pay' ) === false
								&& strpos( $_GET['path'], '/payments' ) === false
								&& strpos( $_GET['path'], '/analytics' ) === false
								&& strpos( $_GET['path'], '/marketing' ) === false
							)
						) {
							$class[]          = 'dashify-current';
							$aria_attributes .= ' aria-current="page"';
						}
					}

					if ( $sub_item[2] === 'wc-admin&path=/customers' && isset( $_GET['path'] ) && $_GET['path'] === '/customers' ) {
						$class[] = 'dashify-current';
					}

					if ( $sub_item[2] === 'admin.php?page=wc-admin&path=/add-product' && isset( $_GET['path'] ) && $_GET['path'] === '/add-product' ) {
						$class[] = 'dashify-current';
					}

					if ( $sub_item[2] === 'wc-admin&path=/extensions' && isset( $_GET['path'] ) && $_GET['path'] === '/extensions' ) {
						$class[] = 'dashify-current';
					}

					if ( strpos( $sub_item[2], 'wc-reports' ) !== false && isset( $_GET['page'] ) && $_GET['page'] === 'wc-reports' ) {
						$class[] = 'dashify-current';
					}

					if ( strpos( $sub_item[2], 'wc-status' ) !== false && isset( $_GET['page'] ) && $_GET['page'] === 'wc-status' ) {
						$class[] = 'dashify-current';
					}

					if ( strpos( $sub_item[2], '/extensions' ) !== false && isset( $_GET['path'] ) && $_GET['path'] === '/extensions' ) {
						$class[] = 'dashify-current';
					}

					$analytics_base     = 'wc-admin&path=/analytics/';
					$analytics_sections = array(
						'overview',
						'products',
						'revenue',
						'orders',
						'variations',
						'categories',
						'coupons',
						'taxes',
						'downloads',
						'stock',
						'settings',
					);

					$analytics_slugs = array();
					foreach ( $analytics_sections as $section ) {
						$analytics_slugs[ $analytics_base . $section ] = '/analytics/' . $section;
					}

					if (
						isset( $_GET['path'] ) &&
						isset( $analytics_slugs[ $sub_item[2] ] ) &&
						$_GET['path'] === $analytics_slugs[ $sub_item[2] ]
					) {
						$class[] = 'dashify-current';
					}

					if ( $sub_item[2] === 'admin.php?page=wc-admin&path=/marketing' && isset( $_GET['path'] ) && $_GET['path'] === '/marketing' ) {
						$class[] = 'dashify-current';
						$class[] = 'dashify-has-open-submenu';
					}

					if ( ! empty( $sub_item[4] ) ) {
						$class[] = esc_attr( $sub_item[4] );
					}

					$class = $class ? ' class="' . implode( ' ', $class ) . '"' : '';

					$menu_hook = get_plugin_page_hook( $sub_item[2], $item[2] );
					$sub_file  = $sub_item[2];
					$pos       = strpos( $sub_file, '?' );
					if ( false !== $pos ) {
						$sub_file = substr( $sub_file, 0, $pos );
					}

					$title = wptexturize( $sub_item[0] );

					$woocommerce_submenu_has_custom_icon = array(
						'wc-admin'                      => array( 'icon_file' => 'home' ),
						'wc-orders'                     => array( 'icon_file' => 'orders' ),
						'edit.php?post_type=shop_order' => array( 'icon_file' => 'orders' ), // non-HPOS
						'wc-orders--shop_subscription'  => array( 'icon_file' => 'subscriptions' ),
						'edit.php?post_type=shop_subscription' => array( 'icon_file' => 'subscriptions' ), // non-HPOS
						'wc-admin&path=/customers'      => array( 'icon_file' => 'customers' ),
						// From here on down, these are not standard WooCommerce menu items, but we
						// can add icons for popular plugins that would normally show in the WooCommerce submenu without an icon.
						'wpo_wcpdf_options_page'        => array(
							'icon_file' => 'invoice',
							'width'     => 12,
							'css'       => 'margin-right: 3px;',
						),
						'wc-stripe-main'                => array(
							'icon_file' => 'stripe',
							'width'     => 12,
							'css'       => 'margin-right: 3px;',
						),
						'wc-pw-gift-cards'              => array(
							'icon_file' => 'credit-card',
							'width'     => 14,
							'css'       => 'margin-right: 1px;',
						),
						'dgwt_wcas_settings'            => array(
							'icon_file' => 'fibosearch',
							'width'     => 14,
							'css'       => 'margin-right: 1px;',
						),
					);
					$icon = '<img src="' . plugins_url( 'icons/default.svg', __FILE__ ) . '" width="12">';
					foreach ( $woocommerce_submenu_has_custom_icon as $slug => $properties ) {
						if ( $sub_item[2] === $slug ) {
							$width = isset( $properties['width'] ) ? $properties['width'] : 15;
							$css   = isset( $properties['css'] ) ? $properties['css'] : '';
							$icon  = '<img
								src="' . plugins_url( 'icons/' . $properties['icon_file'] . '.svg', __FILE__ ) . '"
								width="' . $width . '"
								style="' . $css . '"
							>';
							break;
						}
					}
					// If they have a menu editor plugin, the WooCommerce submenu is not
					// moved to the top level, so we don’t want to show icons for those.
					if ( $has_admin_menu_editor_plugin ) {
						$icon = '';
					}

					if ( ! empty( $menu_hook )
						|| ( ( 'index.php' !== $sub_item[2] )
							&& file_exists( WP_PLUGIN_DIR . "/$sub_file" )
							&& ! file_exists( ABSPATH . "/wp-admin/$sub_file" ) )
					) {
						// If admin.php is the current page or if the parent exists as a file in the plugins or admin directory.
						if ( ( ! $admin_is_parent && file_exists( WP_PLUGIN_DIR . "/$menu_file" ) && ! is_dir( WP_PLUGIN_DIR . "/{$item[2]}" ) ) || file_exists( $menu_file ) ) {
							$sub_item_url = add_query_arg( array( 'page' => $sub_item[2] ), $item[2] );
						} else {
							$sub_item_url = add_query_arg( array( 'page' => $sub_item[2] ), 'admin.php' );
						}

						$sub_item_url = esc_url( $sub_item_url );

						$html .= "
							<li$class>
								<div class='dashify-menu-icon-title-container'>
									$icon
									<a href='$sub_item_url' $class $aria_attributes $id>
										$title
									</a>
								</div>
							</li>
						";
					} else {
						$html .= "
							<li$class>
								<div class='dashify-menu-icon-title-container'>
									$icon
									<a href='{$sub_item[2]}' $class $aria_attributes>
										$title
									</a>
								</div>
							</li>
						";
					}
				}
				$html .= '</ul>';
			}

			$html .= '</li>';
		}

		if ( is_plugin_active( 'admin-menu-editor/menu-editor.php' ) || is_plugin_active( 'admin-menu-editor-pro/menu-editor.php' ) ) {
			// $wp_menu_editor is a global from the beginning of this function.
			if ( isset( $wp_menu_editor ) && $wp_menu_editor->load_custom_menu() ) {
				$wp_menu_editor->restore_wp_menu();
			}
		}

		return $html;
	}

	public function move_woocommerce_to_top( $menu_order ) {
		if ( $this->has_admin_menu_editor_plugin() ) {
			return $menu_order;
		}

		$new_positions = array(
			'woocommerce',
			'edit.php?post_type=product',
			'wc-admin&path=/wc-pay-welcome-page',
			'wc-admin&path=/payments/connect',
			'wc-admin&path=/payments/overview',
			'wc-admin&path=/analytics/overview',
			'woocommerce-marketing',
		);

		$position_counter   = 0;
		$adjusted_positions = array();

		foreach ( $new_positions as $item ) {
			$current_index = array_search( $item, $menu_order );
			if ( false !== $current_index ) {
				$adjusted_positions[ $item ] = $position_counter;
				++$position_counter;
			}
		}

		foreach ( $adjusted_positions as $item => $new_position ) {
			$current_index = array_search( $item, $menu_order );
			if ( $current_index !== $new_position ) {
				$removed_item = array_splice( $menu_order, $current_index, 1 );
				array_splice( $menu_order, $new_position, 0, $removed_item );
			}
		}

		return $menu_order;
	}

	private function has_admin_menu_editor_plugin() {
		return is_plugin_active( 'admin-menu-editor/menu-editor.php' ) ||
			is_plugin_active( 'admin-menu-editor-pro/menu-editor.php' ) ||
			(
				is_plugin_active( 'ultimate-dashboard-pro/ultimate-dashboard-pro.php' ) &&
				$this->udb_admin_menu_editor_enabled()
			);
	}

	private function udb_admin_menu_editor_enabled() {
		$udb_saved_modules = get_option( 'udb_modules' );
		return $udb_saved_modules && isset( $udb_saved_modules['admin_menu_editor'] ) && 'true' === $udb_saved_modules['admin_menu_editor'];
	}

	/**
	 * Moves an item in a multidimensional array to a position after another specified item.
	 *
	 * @param array $array            The original array of arrays.
	 * @param mixed $item_to_move     The value in the second of the inner array to move.
	 * @param mixed $after_item_value The value in the second index of the inner array after which the item should be placed.
	 * @return array The modified array.
	 */
	private function move_item_after( $array, $item_to_move, $after_item_value ) {
		$item_to_move_index = null;
		$after_item_index   = null;
		$item_to_move_item  = null;

		// Find the positions of the item to move and the item after which to place it.
		foreach ( $array as $index => $inner_array ) {
			if ( $inner_array[2] === $item_to_move ) {
				$item_to_move_index = $index;
				$item_to_move_item  = $inner_array;
			}
			if ( $inner_array[2] === $after_item_value ) {
				$after_item_index = $index;
			}
		}

		// If either the item to move or the after item is not found, return the original array.
		if ( null === $item_to_move_index || null === $after_item_index ) {
			return $array;
		}

		// Remove the item to move from the array.
		unset( $array[ $item_to_move_index ] );

		// Adjust the after item index if the item to move comes before it.
		if ( $item_to_move_index < $after_item_index ) {
			--$after_item_index;
		}

		// If the after item is the last item in the array, append the item to move.
		if ( $after_item_index === count( $array ) - 1 ) {
			$array[] = $item_to_move_item;
		} else {
			// Otherwise, insert the item to move after the after item.
			$array = array_merge(
				array_slice( $array, 0, $after_item_index + 1 ),
				array( $item_to_move_item ),
				array_slice( $array, $after_item_index + 1 )
			);
		}

		// Re-index the array and return.
		return array_values( $array );
	}

	public function add_settings( $fields ) {
		$fields[] = array(
			'name'    => 'Navigation',
			'type'    => 'checkbox',
			'default' => 'yes',
			'desc'    => 'Enable the WooCommerce-focused admin navigation',
			'id'      => 'dashify_navigation_enabled',
		);
		return $fields;
	}
}
