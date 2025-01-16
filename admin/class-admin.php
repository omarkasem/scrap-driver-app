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
            wp_enqueue_script('fullcalendar-core', 'https://cdn.jsdelivr.net/npm/@fullcalendar/core/main.min.js', array(), '7.0.0', true);
            wp_enqueue_script('fullcalendar-daygrid', 'https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid/main.min.js', array('fullcalendar-core'), '7.0.0', true);
            wp_enqueue_script('fullcalendar-interaction', 'https://cdn.jsdelivr.net/npm/@fullcalendar/interaction/main.min.js', array('fullcalendar-core'), '7.0.0', true);
            wp_enqueue_style('fullcalendar-core', 'https://cdn.jsdelivr.net/npm/@fullcalendar/core/main.min.css', array(), '7.0.0');
            wp_enqueue_style('fullcalendar-daygrid', 'https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid/main.min.css', array(), '7.0.0');
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