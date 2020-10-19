<?php

/**
 * Plugin Name: VBOUT Woocommerce Plugin
 * Plugin URI: https://vbout.com
 * Description: A Woocommerce extension to integrate with VBOUT.
 * Version: 3.0.0
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