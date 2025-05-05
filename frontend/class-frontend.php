<?php
namespace ScrapDriver;

class Frontend {
    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('single_template', array($this, 'load_collection_template'));
        
        $this->includes();
        
        // Register shortcode for driver statistics
        add_shortcode('driver_statistics', array($this, 'render_statistics_shortcode'));
        add_shortcode('todays_collections', array($this, 'render_todays_collections_shortcode'));
        add_shortcode('all_collections', array($this, 'render_all_collections_shortcode'));
        add_shortcode('my_shifts', array($this, 'render_my_shifts_shortcode'));
        add_shortcode('start_shift', array($this, 'render_start_shift_shortcode'));
        add_shortcode('annual_leave', array($this, 'render_annual_leave_shortcode'));
        add_shortcode('work_schedule', array($this, 'render_work_schedule_shortcode'));
        add_shortcode('holiday_requests', array($this, 'render_holiday_requests_shortcode'));

        add_filter('page_template', array($this, 'load_shifts_template'));
        add_filter('theme_page_templates', array($this, 'add_templates'));
    }



    /**
     * Add shifts template to page templates
     */
    public function add_templates($templates) {
        $templates['driver-statistics.php'] = __('Driver Statistics', 'scrap-driver');
        return $templates;
    }

    /**
     * Load shifts list template
     */
    public function load_shifts_template($template) {
        if (is_page_template('driver-statistics.php')) {
            $new_template = SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/driver-statistics.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    public function render_holiday_requests_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Holiday Requests', 'scrap-driver'),
            'accordion' => false,
            'open' => false,
        ), $atts);
        if (!$this->can_access()) {
            return $this->access_denied();
        }
        ob_start();
        if ( $atts['accordion'] ) {
            ob_start();
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/schedule/holiday.php';
            $content = ob_get_clean();
            echo $this->render_accordion_section( $atts['title'], $content, $atts['open'] );
        } else {
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/schedule/holiday.php';
        }
        return ob_get_clean();
    }

    public function render_work_schedule_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Work Schedule', 'scrap-driver'),
            'accordion' => false,
            'open' => false,
        ), $atts);
        if (!$this->can_access()) {
            return $this->access_denied();
        }
        ob_start();
        if ( $atts['accordion'] ) {
            ob_start();
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/schedule/work.php';
            $content = ob_get_clean();
            echo $this->render_accordion_section( $atts['title'], $content, $atts['open'] );
        } else {
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/schedule/work.php';
        }
        return ob_get_clean();
    }

    public static function get_driver_schedule() {
        $current_user_id = get_current_user_id();
        $args = array(
            'post_type' => 'driver_schedule',
            'meta_key' => 'driver_id',
            'meta_value' => $current_user_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
        );
        
        $holiday_requests = get_posts($args);
        if (empty($holiday_requests)) {
            return;
        }
        return $holiday_requests[0];
    }

    public function render_annual_leave_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Annual Leave', 'scrap-driver'),
            'accordion' => false,
            'open' => false,
        ), $atts);
        if (!$this->can_access()) {
            return $this->access_denied();
        }
        ob_start();
        if ( $atts['accordion'] ) {
            ob_start();
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/schedule/annual-leave.php';
            $content = ob_get_clean();
            echo $this->render_accordion_section( $atts['title'], $content, $atts['open'] );
        } else {
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/schedule/annual-leave.php';
        }
        return ob_get_clean();
    }



    public function render_my_shifts_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('All Shifts', 'scrap-driver'),
            'accordion' => false,
            'open' => false,
        ), $atts);
        if (!$this->can_access()) {
            return $this->access_denied();
        }
        
        ob_start();
        if ( $atts['accordion'] ) {
            ob_start();
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/shifts/table.php';
            $content = ob_get_clean();
            echo $this->render_accordion_section( $atts['title'], $content, $atts['open'] );
        } else {
            ob_start();
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/shifts/table.php';
        }
        
        return ob_get_clean();
    }

    public function render_accordion_section($title, $content, $open = false) {
        $open_class = $open ? 'open' : '';
        return '<div class="sda-accordion-section ' . $open_class . '">
            <div class="sda-accordion-header">
                <h2>' . $title . '</h2>
                <span class="sda-accordion-icon"></span>
            </div>
            <div class="sda-accordion-content">
                ' . $content . '
            </div>
        </div>';
    }

    public function render_start_shift_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Today\'s Shifts', 'scrap-driver'),
            'accordion' => false,
            'open' => false,
        ), $atts);
        if (!$this->can_access()) {
            return $this->access_denied();
        }
        ob_start();
        if ( $atts['accordion'] ) {
            ob_start();
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/shifts/start.php';
            $content = ob_get_clean();
            echo $this->render_accordion_section( $atts['title'], $content, $atts['open'] );
        } else {
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/shifts/start.php';
        }
        return ob_get_clean();
    }

    public function render_all_collections_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'title' => __( 'All Collections', 'scrap-driver' ),
            'accordion' => false,
            'open' => false,
        ), $atts );
        if ( !$this->can_access() ) {
            return $this->access_denied();
        }
        
        ob_start();
        $view_all = true;
        if ( $atts['accordion'] ) {
            ob_start();
            $view_all = true;
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/collection/table.php';
            $content = ob_get_clean();
            echo $this->render_accordion_section( $atts['title'], $content, $atts['open'] );
        } else {
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/collection/table.php';
        }
        return ob_get_clean();
    }


    public function render_todays_collections_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'title' => __( 'Today\'s Collections', 'scrap-driver' ),
            'accordion' => false,
            'open' => false,
        ), $atts );
        if ( !$this->can_access() ) {
            return $this->access_denied();
        }
        
        ob_start();
        $view_all = false;
        if ( $atts['accordion'] ) {
            ob_start();
            $view_all = false;
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/collection/table.php';
            $content = ob_get_clean();
            echo $this->render_accordion_section( $atts['title'], $content, $atts['open'] );
        } else {
            require SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/collection/table.php';
        }
        return ob_get_clean();
    }

    public function can_access() {
        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_driver = in_array('driver', $current_user->roles);

        if (!$is_admin && !$is_driver) {
            return false;
        }

        return true;
    }

    public function access_denied() {
        require_once SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/global/access-denied.php';
    }


    public function includes() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-collection.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-driver-statistics.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-frontend-statistics-controller.php';
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

            // if ('view-collections.php' === $page_template || 
            //     'view-todays-collections.php' === $page_template || 
            //     'view-driver-dashboard.php' === $page_template) {
                wp_enqueue_style(SCRAP_DRIVER_SLUG . '-datatables', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/css/datatables.min.css');
                wp_enqueue_script(SCRAP_DRIVER_SLUG . '-datatables', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/datatables.min.js', array('jquery'), null, true);
            // }
            
            // Add DataTables and Chart.js for driver statistics page
            // if ('driver-statistics.php' === $page_template) {
                wp_enqueue_style(SCRAP_DRIVER_SLUG . '-datatables', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/css/datatables.min.css');
                wp_enqueue_script(SCRAP_DRIVER_SLUG . '-datatables', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/datatables.min.js', array('jquery'), null, true);
                wp_enqueue_script(SCRAP_DRIVER_SLUG . '-chartjs', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/chart.js', array(), '3.7.0', true);

                // enqueue select 2
                wp_enqueue_style(SCRAP_DRIVER_SLUG . '-select2', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/css/select2.min.css');
                wp_enqueue_script(SCRAP_DRIVER_SLUG . '-select2', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/select2.min.js', array('jquery'), null, true);
            // }

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