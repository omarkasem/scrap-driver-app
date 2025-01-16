<?php
namespace ScrapDriver;

class Admin {

    public function __construct() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-helpers.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-sync.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-acf.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-cpt.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-route.php';
        
    }

    public function init() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets($hook) {

        if ('sda-collection_page_sda-route-planning' === $hook) {
            // FullCalendar Core
            wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', array('jquery'), '5.11.3', true);
            wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css', array(), '5.11.3');
        }

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

        wp_localize_script('scrap-driver-admin', 'sdaRoute', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sda_route_nonce'),
        ));

    }
} 