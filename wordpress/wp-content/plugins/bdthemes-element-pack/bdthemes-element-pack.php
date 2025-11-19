<?php

/**
 * Plugin Name: Element Pack Pro
 * Plugin URI: https://elementpack.pro/
 * Description: The all-new <a href="https://elementpack.pro/">Element Pack Pro</a> brings incredibly advanced, and super-flexible widgets, and A to Z essential addons to the Elementor page builder for WordPress. Explore expertly-coded widgets with first-class support by experts.
 * Version: 8.3.0
 * Author: BdThemes
 * Author URI: https://bdthemes.com/
 * Text Domain: bdthemes-element-pack
 * Domain Path: /languages
 * License: GPL3
 * Elementor requires at least: 3.22
 * Elementor tested up to: 3.30.4
 */

/**
 * Some pre defined value for easy use
 */

define( 'BDTEP_VER', '8.3.0' );
define( 'BDTEP_TPL_DB_VER', '1.0.0' );
define( 'BDTEP__FILE__', __FILE__ );

update_option( 'mpu_license_bdthemes-element-pack', 'activated' );


// Load white label configuration if it exists (before defining BDTEP_TITLE)
if ( get_option( 'ep_white_label_enabled' ) ) {
	$white_label_config = dirname( __FILE__ ) . '/includes/white-label-config.php';
	if ( file_exists( $white_label_config ) ) {
		require_once( $white_label_config );
	}
}

if ( ! defined( 'BDTEP_TITLE' ) ) {
	define( 'BDTEP_TITLE', 'Element Pack Pro' );
}

// Helper and utility functions here
require_once( dirname( __FILE__ ) . '/includes/helper.php' );
require_once( dirname( __FILE__ ) . '/includes/utils.php' );


/**
 * Loads translations
 *
 * @return void
 */

 if( ! function_exists( 'ep_load_textdomain' ) ) {
	 function ep_load_textdomain() {
		 load_plugin_textdomain( 'bdthemes-element-pack', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	 }
	add_action( 'init', 'ep_load_textdomain' );
 }


/**
 * Plugin load here correctly
 * Also loaded the language file from here
 */

if ( ! function_exists( 'bdthemes_element_pack_load_plugin' ) ) {
	function bdthemes_element_pack_load_plugin() {

		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', 'bdthemes_element_pack_fail_load' );
			return;
		}

		require_once( dirname( __FILE__ ) . '/includes/setup-wizard/init.php' );

		// Widgets filters here
		require_once( BDTEP_INC_PATH . 'element-pack-filters.php' );

		/**
		 * Start Validation
		 */
		require_once BDTEP_INC_PATH . 'class-pro-widget-map.php';

		if ( ! function_exists( 'element_pack_pro_activated' ) ) {
			function element_pack_pro_activated() {
				return true;
				if ( bdt_license_validation() ) {
					return true;
				}
				return true;
			}
		}
		/**
		 * End Validation
		 */

		// Element pack widget and assets loader
		require_once( BDTEP_PATH . 'loader.php' );

		// Initialize custom CSS/JS injection on frontend
		add_action( 'wp_head', 'ep_inject_header_custom_code', 999 );
		add_action( 'wp_footer', 'ep_inject_footer_custom_code', 999 );

		// Notice class
		require_once( BDTEP_ADMIN_PATH . 'admin-notice.php' );
	}
}

add_action( 'plugins_loaded', 'bdthemes_element_pack_load_plugin', 9 );

/**
 * Check Elementor installed and activated correctly
 */
if ( ! function_exists( 'bdthemes_element_pack_fail_load' ) ) {
	function bdthemes_element_pack_fail_load() {

		$screen = get_current_screen();

		if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
			return;
		}

		$plugin = 'elementor/elementor.php';

		if ( _is_elementor_installed() ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );
			$admin_message  = '<p>' . esc_html__( 'Ops! Element Pack not working because you need to activate the Elementor plugin first.', 'bdthemes-element-pack' ) . '</p>';
			$admin_message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $activation_url, esc_html__( 'Activate Elementor Now', 'bdthemes-element-pack' ) ) . '</p>';
		} else {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return;
			}

			$install_url   = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );
			$admin_message = '<p>' . esc_html__( 'Ops! Element Pack not working because you need to install the Elementor plugin', 'bdthemes-element-pack' ) . '</p>';
			$admin_message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $install_url, esc_html__( 'Install Elementor Now', 'bdthemes-element-pack' ) ) . '</p>';
		}

		echo '<div class="error">' . wp_kses_post( $admin_message ) . '</div>';
	}
}

/**
 * Check the elementor installed or not
 */
if ( ! function_exists( '_is_elementor_installed' ) ) {
	function _is_elementor_installed() {
		$file_path         = 'elementor/elementor.php';
		$installed_plugins = get_plugins();

		return isset( $installed_plugins[ $file_path ] );
	}
}

/**
 * Review Automation Integration
 */

if ( ! function_exists( 'rc_ep_pro_plugin' ) ) {
	function rc_ep_pro_plugin() {

		require_once BDTEP_INC_PATH . 'feedback-hub/start.php';

		rc_dynamic_init( array(
			'sdk_version'  => '1.0.0',
			'plugin_name'  => 'Element Pack Pro',
			'plugin_icon'  => BDTEP_ASSETS_URL . 'images/logo.svg',
			'slug'         => 'bdthemes-element-pack',
			'menu'         => array(
				'slug' => 'element_pack_options',
			),
			'review_url'   => 'https://bdt.to/element-pack-elementor-addons-review',
			'plugin_title' => 'Yay! Great that you\'re using Element Pack Pro',
			'plugin_msg'   => '<p>Loved using Element Pack on your website? Share your experience in a review and help us spread the love to everyone right now. Good words will help the community.</p>',
		) );
	}
	add_action( 'admin_init', 'rc_ep_pro_plugin' );
}

// Rooten theme header footer compatibility
add_action('after_setup_theme', function() {
	// Rooten theme header footer compatibility
	if ( 'Rooten' === wp_get_theme()->name || 'Rooten' === wp_get_theme()->parent_theme ) {
		if ( ! class_exists( 'RootenCustomTemplate' )) {
			require_once BDTEP_INC_PATH . 'class-rooten-theme-compatibility.php';
		}
	}
});

/**
 * SDK Integration
 */

if ( ! function_exists( 'dci_plugin_element_pack_pro' ) ) {
	function dci_plugin_element_pack_pro() {

		// Include DCI SDK.
		require_once dirname( __FILE__ ) . '/dci/start.php';

		dci_dynamic_init( array(
			'sdk_version'          => '1.2.1',
			'product_id'           => 3,
			'plugin_name'          => 'Element Pack Pro', // make simple, must not empty
			'plugin_title'         => 'Love using Element Pack Pro? Congrats 🎉  ( Never miss an Important Update )', // You can describe your plugin title here
			'plugin_icon'          => BDTEP_ASSETS_URL . 'images/logo.svg',
			'api_endpoint'         => 'https://analytics.bdthemes.com/wp-json/dci/v1/data-insights',
			'slug'                 => 'bdthemes-element-pack',
			'plugin_deactivate_id' => 'element-pack-pro',
			'menu'                 => array(
				'slug' => 'element_pack_options',
			),
			'public_key'           => 'pk_mS3nY7QA0IMBRuULyBbXB9IpJlEfmVZ7',
			'is_premium'           => false,
			'popup_notice'         => false,
			'deactivate_feedback'  => true,
			'plugin_msg'           => '<p>Be Top-contributor by sharing non-sensitive plugin data and create an impact to the global WordPress community today! You can receive valuable emails periodically.</p>',
		) );
	}
	add_action( 'admin_init', 'dci_plugin_element_pack_pro' );
}
