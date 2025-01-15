<?php
namespace ScrapDriver;

class Admin {

    public function __construct() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-generator.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-api.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-acf.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-cpt.php';
        
    }

    public function init() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        // Enqueue admin CSS
        wp_enqueue_style(
            'scrap-driver-admin',
            SCRAP_DRIVER_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            SCRAP_DRIVER_VERSION
        );

        // Enqueue admin JavaScript
        wp_enqueue_script(
            'scrap-driver-admin',
            SCRAP_DRIVER_PLUGIN_URL . 'admin/assets/js/admin.js',
            array('jquery'),
            SCRAP_DRIVER_VERSION,
            true
        );


    }
} 