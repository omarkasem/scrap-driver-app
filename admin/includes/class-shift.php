<?php
namespace ScrapDriver\Admin;

class Shift {
    public function __construct() {
        // Handle shift actions
        add_action('init', array($this, 'handle_shift_actions'));
        
        // Add collection completion tracking
        add_action('sda_collection_completed', array($this, 'track_collection_completion'), 10, 2);
        
        // Add template loading filters
        add_filter('theme_page_templates', array($this, 'add_shifts_template'));
        add_filter('template_include', array($this, 'load_shifts_template'));
        add_filter('single_template', array($this, 'load_single_shift_template'));
    }

    /**
     * Handle shift start/end actions
     */
    public function handle_shift_actions() {
        if (!isset($_POST['shift_action']) || !is_user_logged_in()) {
            return;
        }

        $current_user = wp_get_current_user();
        $current_user_id = get_current_user_id();

        if ($_POST['shift_action'] === 'start') {
            $this->start_shift($current_user, $current_user_id);
        } elseif ($_POST['shift_action'] === 'end') {
            $this->end_shift($current_user_id);
        }
    }

    /**
     * Start a new shift
     */
    private function start_shift($current_user, $current_user_id) {
        $shift_start = current_time('mysql');
        update_user_meta($current_user_id, 'shift_start_time', $shift_start);
        
        // Create shift post
        $shift_title = sprintf('Shift By %s on %s', 
            $current_user->display_name,
            date('Y-m-d H:i', strtotime($shift_start))
        );
        
        $shift_id = wp_insert_post(array(
            'post_type' => 'sda-shift',
            'post_title' => $shift_title,
            'post_status' => 'publish',
            'meta_input' => array(
                'driver_id' => $current_user_id,
                'shift_start' => $shift_start
            )
        ));

        update_user_meta($current_user_id, 'current_shift_id', $shift_id);

        // Redirect to first collection
        $first_collection = get_posts(array(
            'post_type' => 'sda-collection',
            'meta_query' => array(
                array(
                    'key' => 'assigned_driver',
                    'value' => $current_user_id
                )
            ),
            'meta_key' => 'route_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'posts_per_page' => 1
        ));

        if (!empty($first_collection)) {
            wp_redirect(get_permalink($first_collection[0]->ID));
            exit;
        }
    }

    /**
     * End current shift
     */
    private function end_shift($current_user_id) {
        $shift_end = current_time('mysql');
        delete_user_meta($current_user_id, 'shift_start_time');
        
        // Update shift post
        $current_shift_id = get_user_meta($current_user_id, 'current_shift_id', true);
        if ($current_shift_id) {
            update_post_meta($current_shift_id, 'shift_end', $shift_end);
            delete_user_meta($current_user_id, 'current_shift_id');
        }
    }

    /**
     * Track collection completion during shift
     */
    public function track_collection_completion($collection_id, $driver_id) {
        $current_shift_id = get_user_meta($driver_id, 'current_shift_id', true);
        
        if ($current_shift_id) {
            $collections = get_post_meta($current_shift_id, 'collections_completed', true);
            if (!is_array($collections)) {
                $collections = array();
            }
            
            $collections[$collection_id] = current_time('mysql');
            update_post_meta($current_shift_id, 'collections_completed', $collections);
        }
    }

    /**
     * Add shifts template to page templates
     */
    public function add_shifts_template($templates) {
        $templates['view-shifts.php'] = __('Driver Shifts List', 'scrap-driver');
        return $templates;
    }

    /**
     * Load shifts list template
     */
    public function load_shifts_template($template) {
        if (is_page_template('view-shifts.php')) {
            $new_template = SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/view-shifts.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Load single shift template
     */
    public function load_single_shift_template($template) {
        global $post;
        
        if ($post->post_type === 'sda-shift') {
            $new_template = SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/single-sda-shift.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        
        return $template;
    }
}

new Shift(); 