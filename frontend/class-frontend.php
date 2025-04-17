<?php
namespace ScrapDriver;

class Frontend {
    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        add_filter('single_template', array($this, 'load_collection_template'));
        
        // Change to theme_page_templates filter
        add_filter('theme_page_templates', array($this, 'add_templates'));
        add_filter('template_include', array($this, 'load_page_templates'));

        $this->includes();
        
        // Register shortcode for driver statistics
        add_shortcode('driver_statistics', array($this, 'render_statistics_shortcode'));
    }

    public function includes() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-collection.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-driver-statistics.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-frontend-statistics-controller.php';
    }

    /**
     * Add the page templates to the dropdown
     */
    public function add_templates($templates) {
        $templates['view-collections.php'] = __('Collections List', 'scrap-driver');
        $templates['view-todays-collections.php'] = __('Today\'s Collections', 'scrap-driver');
        $templates['view-driver-dashboard.php'] = __('Driver Dashboard', 'scrap-driver');
        $templates['driver-statistics.php'] = __('Driver Statistics', 'scrap-driver');
        return $templates;
    }

    public function load_page_templates($template) {
        // Get the template selected for the page
        if (is_page()) {
            $page_template = get_page_template_slug();
            
            if ('view-collections.php' === $page_template) {
                $custom_template = SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/view-collections.php';
                if (file_exists($custom_template)) {
                    return $custom_template;
                }
            }

            if ('view-todays-collections.php' === $page_template) {
                $custom_template = SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/view-todays-collections.php';
                if (file_exists($custom_template)) {
                    return $custom_template;
                }
            }
            
            if ('view-driver-dashboard.php' === $page_template) {
                $custom_template = SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/view-driver-dashboard.php';
                if (file_exists($custom_template)) {
                    return $custom_template;
                }
            }
            
            if ('driver-statistics.php' === $page_template) {
                $custom_template = SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/driver-statistics.php';
                if (file_exists($custom_template)) {
                    return $custom_template;
                }
            }
        }
        
        return $template;
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
        if (is_page()) {
            $page_template = get_page_template_slug();

            if ('view-collections.php' === $page_template || 
                'view-todays-collections.php' === $page_template || 
                'view-driver-dashboard.php' === $page_template) {
                wp_enqueue_style(SCRAP_DRIVER_SLUG . '-datatables', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/css/datatables.min.css');
                wp_enqueue_script(SCRAP_DRIVER_SLUG . '-datatables', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/datatables.min.js', array('jquery'), null, true);
            }
            
            // Add DataTables and Chart.js for driver statistics page
            if ('driver-statistics.php' === $page_template) {
                wp_enqueue_style(SCRAP_DRIVER_SLUG . '-datatables', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/css/datatables.min.css');
                wp_enqueue_script(SCRAP_DRIVER_SLUG . '-datatables', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/datatables.min.js', array('jquery'), null, true);
                wp_enqueue_script(SCRAP_DRIVER_SLUG . '-chartjs', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/chart.js', array(), '3.7.0', true);

                // enqueue select 2
                wp_enqueue_style(SCRAP_DRIVER_SLUG . '-select2', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/css/select2.min.css');
                wp_enqueue_script(SCRAP_DRIVER_SLUG . '-select2', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/select2.min.js', array('jquery'), null, true);
            }

        }

        // Enqueue frontend CSS
        wp_enqueue_style(
            SCRAP_DRIVER_SLUG . '-  frontend',
            SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/css/frontend.css',
            array(),
            SCRAP_DRIVER_VERSION
        );

        // enqueue jquery ui styles
        wp_enqueue_style(SCRAP_DRIVER_SLUG . '-jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

        // Enqueue frontend JavaScript
        wp_enqueue_script(
            SCRAP_DRIVER_SLUG . '-frontend',
            SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/frontend.js',
            array('jquery','jquery-ui-datepicker'),
            SCRAP_DRIVER_VERSION,
            true
        );


        
        wp_localize_script(SCRAP_DRIVER_SLUG . '-frontend', 'sdaAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('driver_statistics_nonce'),
            'currentUserId' => get_current_user_id(),
            'currentUserName' => wp_get_current_user()->display_name,
            'isAdmin' => current_user_can('administrator') ? 'true' : 'false'
        ));

    }
    
    /**
     * Render statistics shortcode
     * 
     * @return string HTML content
     */
    public function render_statistics_shortcode() {
        $controller = new \FrontendStatisticsController();
        return $controller->render_statistics_page();
    }
} 