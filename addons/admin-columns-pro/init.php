<?php

use AC\Asset\Location\Absolute;

require_once( __DIR__ .'/classes/SimplePayment.php' );

$addon = \ACA\SimplePayment\SimplePayment::class;
$path = 'addons/admin-columns-pro';

$location = new Absolute(
    plugin_dir_url( __FILE__ ) . $path,
    plugin_dir_path( __FILE__ ) . $path
);

( new $addon( $location ) )->register();