<?php

/**
 * Plugin Name: VBOUT Woocommerce Plugin
 * Plugin URI: https://vbout.com
 * Description: A woocommerce extension to integrate with VBOUT.
 * Version: 3.6.0
 * Author: VBOUT Inc.
 * Author URI: https://vbout.com
 * Developer: VBOUT Dev Team
 * Developer URI: https://vbout.com
 * Text Domain: VBOUT
 * Domain Path: /languages
 *
 * Copyright: © VBOUT.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('VBOUT_WOOCOMMERCE_VERSION', '3.6.0');

define('VBOUT_WOOCOMMERCE__FILE__', __FILE__);
define('VBOUT_WOOCOMMERCE_SLUG', 'vbout-woocommerce-integration');
define('VBOUT_WOOCOMMERCE_INTEGRATION_ID', 'vbout-integration');
define('VBOUT_WOOCOMMERCE_PLUGIN_BASE', plugin_basename(VBOUT_WOOCOMMERCE__FILE__));

if( defined( 'VBOUT_WOOCOMMERCE_UNINSTALL_PLUGIN' ) ) {

	require __DIR__ . '/vendor/autoload.php';

	//Uninstalling process
	$vboutUninstall = new App\WCVbout();
	//$vboutUninstall->init();
	$vboutUninstall->uninstall();
}
else {
	/**
	 * Check if WooCommerce is active
	 **/

	if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
		/**
		 * Start of application
		 */

		require __DIR__ . '/vendor/autoload.php';

		load_plugin_textdomain('vbout-woocommerce');

		$WCVbout = new App\WCVbout();

		$updater = new App\Updater();
	}
}

