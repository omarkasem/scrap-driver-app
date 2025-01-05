<?php
namespace ScrapDriver;

class Frontend {
    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        add_filter('single_template', array($this, 'load_collection_template'));

    }


    public function load_collection_template($template) {
        global $post;
        if ($post->post_type === 'sda-collection') {
            $custom_template = SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/single-collection.php';

            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
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