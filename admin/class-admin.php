<?php
namespace ScrapDriver;

class Admin {

    public function __construct() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-helpers.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-sync.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-acf.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-route.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-cpt.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-caps.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-shift.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-distance.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-schedule.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-collection.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-tracking.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-location-view.php';
    }

    public function init() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        global $post;
        if ($post && ('sda-shift' === $post->post_type || 'driver_schedule' === $post->post_type)) {
            // FullCalendar Bundle (includes all plugins)
            wp_enqueue_script(
                'fullcalendar',
                SCRAP_DRIVER_PLUGIN_URL . 'admin/assets/js/index.global.min.js',
                array('jquery'),
                '6.1.8',
                true
            );

            // Enqueue SweetAlert2
            wp_enqueue_script(
                'sweetalert2',
                SCRAP_DRIVER_PLUGIN_URL . 'admin/assets/js/sweetalert2@11.js',
                array(),
                '11.0.0',
                true
            );

        }

        // Make sure admin.js loads after FullCalendar
        wp_enqueue_script(
            'scrap-driver-admin',
            SCRAP_DRIVER_PLUGIN_URL . 'admin/assets/js/admin.js',
            array('jquery','jquery-ui-dialog','jquery-ui-datepicker'),
            SCRAP_DRIVER_VERSION,
            true
        );

        $post_id = 0;
        if($post) {
            $post_id = $post->ID;
        }

        $ajax_loader_img = includes_url('images/spinner.gif');

        wp_localize_script('scrap-driver-admin', 'sdaRoute', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sda_route_nonce'),
            'schedule_nonce' => wp_create_nonce('schedule_dates_nonce'),
            'postId' => $post_id,
            'loader' => $ajax_loader_img,
            'optimizing' => __('Optimizing route...', 'scrap-driver-app'),
            'success' => __('Route optimized successfully!', 'scrap-driver-app'),
            'error' => __('Error optimizing route', 'scrap-driver-app')
        ));


        // Enqueue admin CSS
        wp_enqueue_style(
            'scrap-driver-admin',
            SCRAP_DRIVER_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            SCRAP_DRIVER_VERSION
        );
    }
} 