<?php
/*
Plugin Name: GeoIP
Description: Custom GeoIP Api
Version: 1.0
Author: Alex Nguyen
Text Domain: geoip
License: GPLv3
*/

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once dirname(__FILE__) . '/inc/class-geoip.php';
$geo = new GeoIP();
