<?php
namespace ScrapDriver;

class Frontend {
    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        // Enqueue frontend CSS
        wp_enqueue_style(
            'scrap-driver-frontend',
            SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/css/frontend.css',
            array(),
            SCRAP_DRIVER_VERSION
        );

        // Enqueue frontend JavaScript
        wp_enqueue_script(
            'scrap-driver-frontend',
            SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/frontend.js',
            array('jquery'),
            SCRAP_DRIVER_VERSION,
            true
        );
    }
} 