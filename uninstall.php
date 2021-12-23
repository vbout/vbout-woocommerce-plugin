<?php

if (!defined('ABSPATH')) {
	exit;
}

if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {

	define( 'VBOUT_WOOCOMMERCE_UNINSTALL_PLUGIN', true );

	include(__DIR__ . '/vbout.php');
}
