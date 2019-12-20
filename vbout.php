<?php

/**
 * Plugin Name: Vbout Woocommerce Plugin
 * Plugin URI: http://woocommerce.com/products/woocommerce-extension/
 * Description: A Woocommerce extension to integrate with Vbout.
 * Version: 2.0.0
 * Author: Vbout Inc.
 * Author URI: https://vbout.com
 * Developer: Mohamad Ghannoum
 * Developer URI: https://vbout.com
 * Text Domain: vbout
 * Domain Path: /languages
 *
 * Copyright: © 2009-2015 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) { 
    exit;
}

/**
 * Check if WooCommerce is active
 **/
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    /**
     * Start of application
     */
    require __DIR__ . '/vendor/autoload.php';

    $WCVbout = new App\WCVbout();
}