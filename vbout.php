<?php

/**
 * Plugin Name: VBOUT Woocommerce Plugin
 * Plugin URI: https://vbout.com
 * Description: A woocommerce extension to integrate with VBOUT.
 * Version: 3.1
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

    define('vbout_woocommerce_current_version', '3.1');
    define('vbout_woocommerce_slug', 'vbout-woocommerce-integration');
    define('vbout_woocommerce_updater', 'https://app.vbout.com/integrations/vbout_woocommerce_plugin.json');

    add_filter('plugins_api', 'vbout_woocommerce_plugin_info', 20, 3);
    /**
     * Get VBOUT woocommerce plugin info
     * @param $res
     * @param $action
     * @param $args
     * @return false|stdClass
     */
    function vbout_woocommerce_plugin_info($res, $action, $args)
    {
        // Do nothing if this is not about getting plugin information
        if ('plugin_information' !== $action) {
            return false;
        }

        // do nothing if it is not our plugin
        if (vbout_woocommerce_slug !== $args->slug) {
            return false;
        }

        // trying to get from cache first
        if (false == $remote = get_transient('vbout_update_' . vbout_woocommerce_slug)) {
            // vbout_woocommerce_plugin.json is the file with the latest plugin information on VBOUT APP server
            $remote = wp_remote_get(vbout_woocommerce_updater, array(
                    'timeout' => 10,
                    'headers' => array(
                        'Accept' => 'application/json'
                    ))
            );

            if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] == 200 && !empty($remote['body'])) {
                set_transient('vbout_update_' . vbout_woocommerce_slug, $remote, 43200); // 12 hours cache
            }
        }
        if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] == 200 && !empty($remote['body'])) {

            $remote = json_decode($remote['body']);
            $res = new stdClass();

            $res->slug = vbout_woocommerce_slug;
            $res->version = $remote->version;
            $res->tested = $remote->tested;
            $res->requires = $remote->requires;
            $res->author = '<a href="https://vbout.com">VBOUT Inc.</a>';
            $res->author_profile = 'https://vbout.com';
            $res->download_link = $remote->download_url;
            $res->trunk = $remote->download_url;
            $res->requires_php = $remote->requires_php;
            $res->last_updated = $remote->last_updated;
            $res->sections = array(
                'changelog' => $remote->sections->changelog
            );
            return $res;
        }
        return $res;
    }

    add_filter('site_transient_update_plugins', 'vbout_woocommerce_plugin_push_update');
    /**
     * @param $transient
     * @return mixed
     */
    function vbout_woocommerce_plugin_push_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        if (false == $remote = get_transient('vbout_update_' . vbout_woocommerce_slug)) {
            $remote = wp_remote_get(vbout_woocommerce_updater, array(
                    'timeout' => 10,
                    'headers' => array(
                        'Accept' => 'application/json'
                    ))
            );
            if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] == 200 && !empty($remote['body'])) {
                set_transient('vbout_update_' . vbout_woocommerce_slug, $remote, 43200); // 12 hours cache
            }
        }

        if ($remote) {
            $remote = json_decode($remote['body']);
            if ($remote && version_compare(vbout_woocommerce_current_version, $remote->version, '<') && version_compare($remote->requires, get_bloginfo('version'), '<')) {
                $res = new stdClass();
                $res->slug = vbout_woocommerce_slug;
                $res->plugin = 'vbout-woocommerce-plugin/vbout.php';
                $res->new_version = $remote->version;
                $res->tested = $remote->tested;
                $res->package = $remote->download_url;
                $transient->response[$res->plugin] = $res;
            }
        }
        return $transient;
    }

    add_action('upgrader_process_complete', 'vbout_woocommerce_plugin_after_update', 10, 2);
    /**
     * @param $upgrader_object
     * @param $options
     */
    function vbout_woocommerce_plugin_after_update($upgrader_object, $options)
    {
        if ($options['action'] == 'update' && $options['type'] === 'plugin') {
            $plugins = $options['plugins'];
            foreach ($plugins as $plugin) {
                if ($plugin == 'vbout-woocommerce-plugin/vbout.php') {
                    // Clean the cache when new plugin version is installed
                    delete_transient('vbout_update_' . vbout_woocommerce_slug);
                }
            }
        }
    }
}