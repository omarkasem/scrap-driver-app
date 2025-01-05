<?php
/**
 * Plugin Name: Scrap Driver App
 * Plugin URI: #
 * Description: A WordPress plugin for scrap driver management
 * Version: 1.0.0
 * Author: Omar Kasem
 * Author URI: #
 * Text Domain: scrap-driver-app
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('SCRAP_DRIVER_VERSION', '1.0.0');
define('SCRAP_DRIVER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCRAP_DRIVER_PLUGIN_URL', plugin_dir_url(__FILE__));

// ACF
define( 'SCRAP_DRIVER_ACF_PATH', __DIR__ . '/lib/acf/' );
define( 'SCRAP_DRIVER_ACF_URL', plugin_dir_url( __FILE__ ) . '/lib/acf/' );
define( 'SCRAP_DRIVER_ACF_SHOW', true );


// Autoloader function
function scrap_driver_autoloader($class) {
    $namespace = 'ScrapDriver\\';
    
    if (strpos($class, $namespace) !== 0) {
        return;
    }

    $class = str_replace($namespace, '', $class);
    $class = str_replace('\\', DIRECTORY_SEPARATOR, strtolower($class));
    $class = str_replace('_', '-', $class);

    $file = SCRAP_DRIVER_PLUGIN_DIR . $class . '.php';

    if (file_exists($file)) {
        require $file;
    }
}

spl_autoload_register('scrap_driver_autoloader');

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
