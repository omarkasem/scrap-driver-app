<?php
namespace ScrapDriver;

/**
 * Driver Schedule Class
 */
class Schedule {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_driver_schedule_cpt'));
        add_action('user_register', array($this, 'create_schedule_for_new_driver'));
        add_action('profile_update', array($this, 'check_driver_schedule_on_update'), 10, 2);
        add_action('save_post_driver_schedule', array($this, 'force_driver_name_as_title'), 10, 3);
        add_action('admin_notices', array($this, 'prevent_multiple_schedules'));
        add_filter('enter_title_here', array($this, 'change_title_placeholder'), 10, 2);
        
        // Run initial schedule creation for existing drivers
        add_action('admin_init', array($this, 'create_schedules_for_existing_drivers'));
    }

    /**
     * Register Driver Schedule CPT
     */
    public function register_driver_schedule_cpt() {
        $labels = array(
            'name'               => __('Driver Schedules', 'mdtl'),
            'singular_name'      => __('Driver Schedule', 'mdtl'),
            'menu_name'          => __('Driver Schedules', 'mdtl'),
            'add_new'            => __('Add New', 'mdtl'),
            'add_new_item'       => __('Add New Driver Schedule', 'mdtl'),
            'edit_item'          => __('Edit Driver Schedule', 'mdtl'),
            'new_item'           => __('New Driver Schedule', 'mdtl'),
            'view_item'          => __('View Driver Schedule', 'mdtl'),
            'search_items'       => __('Search Driver Schedules', 'mdtl'),
            'not_found'          => __('No driver schedules found', 'mdtl'),
            'not_found_in_trash' => __('No driver schedules found in trash', 'mdtl'),
        );
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'exclude_from_search' => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'driver', 'with_front' => false),
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => array('title'),
            'menu_icon'           => 'dashicons-calendar-alt',
            'capabilities'        => array(
                'create_posts' => 'do_not_allow', // Prevent from creating new posts
            ),
            'map_meta_cap'        => true,
        );

        register_post_type('driver_schedule', $args);
    }

    /**
     * Create schedule for new driver
     */
    public function create_schedule_for_new_driver($user_id) {
        $user = get_user_by('id', $user_id);
        if ($this->is_driver($user)) {
            $this->create_driver_schedule($user);
        }
    }

    /**
     * Check and create schedule when user is updated
     */
    public function check_driver_schedule_on_update($user_id, $old_user_data) {
        $user = get_user_by('id', $user_id);
        if ($this->is_driver($user)) {
            // Check if driver already has a schedule
            if (!$this->get_driver_schedule($user_id)) {
                $this->create_driver_schedule($user);
            }
        }
    }

    /**
     * Force driver name as title
     */
    public function force_driver_name_as_title($post_id, $post, $update) {
        // Remove action to prevent infinite loop
        remove_action('save_post_driver_schedule', array($this, 'force_driver_name_as_title'), 10);

        $driver_id = get_post_meta($post_id, 'driver_id', true);
        if ($driver_id) {
            $driver = get_user_by('id', $driver_id);
            $title = $driver->display_name;
            
            // Update the post title if it's different
            if ($post->post_title !== $title) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => $title
                ));
            }
        }

        // Re-add the action
        add_action('save_post_driver_schedule', array($this, 'force_driver_name_as_title'), 10, 3);
    }

    /**
     * Prevent creating multiple schedules
     */
    public function prevent_multiple_schedules() {
        global $pagenow, $post_type;
        
        if ($pagenow === 'post-new.php' && $post_type === 'driver_schedule') {
            echo '<div class="notice notice-error"><p>' . __('Driver schedules cannot be created manually. They are automatically created when a driver is added.', 'mdtl') . '</p></div>';
            echo '<script>jQuery(document).ready(function($) { $("#post").find("input, textarea, select").prop("disabled", true); });</script>';
        }
    }

    /**
     * Change title placeholder
     */
    public function change_title_placeholder($title_placeholder, $post) {
        if ($post->post_type === 'driver_schedule') {
            return __('Driver name will be automatically set', 'mdtl');
        }
        return $title_placeholder;
    }

    /**
     * Create schedules for existing drivers
     */
    public function create_schedules_for_existing_drivers() {
        // Only run this once
        if (get_option('mdtl_driver_schedules_created')) {
            return;
        }

        $drivers = get_users(array(
            'role' => 'driver',
        ));

        foreach ($drivers as $driver) {
            if (!$this->get_driver_schedule($driver->ID)) {
                $this->create_driver_schedule($driver);
            }
        }

        update_option('mdtl_driver_schedules_created', true);
    }

    /**
     * Helper function to check if user is driver
     */
    private function is_driver($user) {
        return in_array('driver', (array) $user->roles);
    }

    /**
     * Helper function to get driver schedule
     */
    private function get_driver_schedule($driver_id) {
        $args = array(
            'post_type' => 'driver_schedule',
            'meta_query' => array(
                array(
                    'key' => 'driver_id',
                    'value' => $driver_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        );

        $query = new \WP_Query($args);
        return $query->posts ? $query->posts[0] : null;
    }

    /**
     * Helper function to create driver schedule
     */
    private function create_driver_schedule($user) {
        // Check if driver already has a schedule
        if ($this->get_driver_schedule($user->ID)) {
            return;
        }

        // Create new schedule post
        $post_data = array(
            'post_title'   => $user->display_name,
            'post_status'  => 'publish',
            'post_type'    => 'driver_schedule',
            'post_author'  => $user->ID
        );

        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            // Add driver ID as post meta
            update_post_meta($post_id, 'driver_id', $user->ID);
        }
    }
}

// Initialize the class
new Schedule(); 