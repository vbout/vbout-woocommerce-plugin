<?php


use App\WCVbout;
require __DIR__ . '/vendor/autoload.php';

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
else
{
    if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {
        //Uninstalling process
        $vboutUninstall = new WCVbout();
        $vboutUninstall->init();
        $vboutUninstall->uninstall();
    }
}