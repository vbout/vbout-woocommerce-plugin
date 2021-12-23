<?php

namespace App;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Main class plugin
 */
class Updater
{
	private $plugin_path;

	private $plugin_slug;

	private $plugin_info_url = 'https://app.vbout.com/integrations/vbout_woocommerce_plugin.json';

	private $transient_cache = 43200; // 12 hours

	private $transient_slug;

	/**
	 * Updater constructor.
	 */
	public function __construct() {

		$this->transient_slug	=  'vbout_plugin_info_' . VBOUT_WOOCOMMERCE_SLUG;
		$this->transient_cache	=  12 *60 * 60; // 12 hours

		$this->plugin_slug		= VBOUT_WOOCOMMERCE_SLUG;
		$this->plugin_path		= VBOUT_WOOCOMMERCE_PLUGIN_BASE;

		add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);

		add_filter('transient_update_plugins', [$this, 'plugin_push_update']);
		add_filter('site_transient_update_plugins', [$this, 'plugin_push_update']);

		add_action('upgrader_process_complete', [$this, 'plugin_after_update'], 10, 2);
	}

    /**
     * Get plugin info
     * @param $res
     * @param $action
     * @param $args
     * @return false|\stdClass
     */
    public function plugin_info($res, $action, $args) {
        // Do nothing if this is not about getting plugin information
        if ('plugin_information' !== $action) {
            return $res;
        }

        // do nothing if it is not our plugin
        if (empty($args->slug) || $this->plugin_slug !== $args->slug) {
            return $res;
        }

		$plugin	= $this->get_plugin_info();

        if ( $plugin ) {
			$res = $plugin;

			$res->slug		= $this->plugin_slug;
			$res->author	= '<a href="'. $res->author_profile .'" target="_blank">'. $res->author .'</a>';
			$res->trunk		= $res->download_url;

            return $res;
        }

        return $res;
	}

    /**
     * @param $update_plugins
     * @return mixed
     */
    public function plugin_push_update( $update_plugins ) {
        if (empty($update_plugins->checked)) {
            return $update_plugins;
        }

		if ( ! is_object( $update_plugins ) ) {
			return $update_plugins;
		}

		if ( ! isset( $update_plugins->response ) || ! is_array( $update_plugins->response ) ) {
			$update_plugins->response = array();
		}

        $plugin = $this->get_plugin_info();

		if ($plugin
			&& version_compare(VBOUT_WOOCOMMERCE_VERSION, $plugin->version, '<')
			&& version_compare($plugin->requires, get_bloginfo('version'), '<')) {

			$res = (object) array(
				'slug'			=> $this->plugin_slug,
				'plugin'		=> $this->plugin_path,
				'new_version'	=> $plugin->version,
				//'url'			=> '', // Informational
				'package'		=> $plugin->download_url,
				'tested'		=> $plugin->tested,
			);

			$update_plugins->response[$res->plugin] = $res;
		}

        return $update_plugins;
    }

    /**
     * @param $upgrader_object
     * @param $options
     */
	public function plugin_after_update($upgrader_object, $options) {
		if ($options['action'] == 'update' && $options['type'] === 'plugin') {
			$plugins = $options['plugins'];

			foreach ($plugins as $plugin) {
				if ($plugin == $this->plugin_path) {
					// Clean the cache when new plugin version is installed
					delete_transient( $this->transient_slug );
				}
			}
		}
	}

    private function get_plugin_info($allowCache = true ) {
    	$info = null;

        // trying to get from cache first
        if (!$allowCache || false == ($info = get_transient( $this->transient_slug )) ) {

            // request .json path to get latest plugin information on server
			$info = wp_remote_get($this->plugin_info_url, array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json'
				)
            ));

            if ( $this->transient_valid($info) ) {
                set_transient($this->transient_slug, $info, $this->transient_cache);
            }
        }

		if ( $this->transient_valid($info) ) {
			return (object) json_decode( $info['body'], true);
		}

        return null;
    }

    private function transient_valid( $transient ) {
    	return !empty($transient)
			&& !is_wp_error($transient)
			&& isset($transient['response']['code'])
			&& $transient['response']['code'] == 200
			&& !empty($transient['body']);
	}
}
