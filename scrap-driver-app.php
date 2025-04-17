<?php
/**
 * Plugin Name: Scrap Driver App
 * Plugin URI: #
 * Description: A WordPress plugin for scrap driver management
 * Version: 1.0.5
 * Author: Omar Kasem
 * Author URI: #
 * Text Domain: scrap-driver-app
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('SCRAP_DRIVER_SLUG', 'scrap-driver-app');
define('SCRAP_DRIVER_VERSION', '1.0.5');
define('SCRAP_DRIVER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCRAP_DRIVER_PLUGIN_URL', plugin_dir_url(__FILE__));


// ACF
define( 'SCRAP_DRIVER_ACF_PATH', __DIR__ . '/lib/acf/' );
define( 'SCRAP_DRIVER_ACF_URL', plugin_dir_url( __FILE__ ) . '/lib/acf/' );
define( 'SCRAP_DRIVER_ACF_SHOW', true );
// check if current site has 'wplinkup.com' in it 
if (strpos($_SERVER['HTTP_HOST'], 'wplinkup.com') !== false) {
    define( 'SCRAP_DRIVER_API_URL', 'https://scrapmycaronline.co.uk/dev/' );
} else {
    define( 'SCRAP_DRIVER_API_URL', 'https://vrm-dev.local/' );
}

// Initialize the plugin
function run_scrap_driver() {
    // Initialize Admin
    require_once SCRAP_DRIVER_PLUGIN_DIR . 'admin/class-admin.php';
    $admin = new ScrapDriver\Admin();
    $admin->init();

    // Initialize Frontend
    require_once SCRAP_DRIVER_PLUGIN_DIR . 'frontend/class-frontend.php';
    $frontend = new ScrapDriver\Frontend();
    $frontend->init();

}

run_scrap_driver();
