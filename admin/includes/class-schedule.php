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
        add_action('wp_ajax_save_schedule_dates', array($this, 'save_schedule_dates'));
        add_action('wp_ajax_get_schedule_dates', array($this, 'get_schedule_dates'));

        // add_filter('acf/load_field/key=field_67963b704e341', array($this, 'load_calendar_schedule_field'));

        add_action('acf/load_field/key=field_67963b5a4e33f', array($this, 'set_default_value_start_of_year_date'),);
        add_filter('single_template', array($this, 'register_schedule_template'));

        // Add holiday request handling
        add_action('init', array($this, 'handle_holiday_request'));
        
        // Add metabox for holiday requests
        add_action('add_meta_boxes', array($this, 'add_holiday_requests_metabox'));
        
        // Add save holiday request action
        add_action('save_post_driver_schedule', array($this, 'save_holiday_requests'), 10, 2);
    }

    public function set_default_value_start_of_year_date($field) {
        $field['value'] = date('Y-01-01');
        return $field;
    }

    public function load_calendar_schedule_field($field) {
        $field['message'] = '<div id="schedule-calendar"></div>';
        return $field;
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

    /**
     * Save schedule dates via AJAX
     */
    public function save_schedule_dates() {
        check_ajax_referer('schedule_dates_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = intval($_POST['post_id']);
        $dates = sanitize_text_field($_POST['dates']);
        $status = sanitize_text_field($_POST['status']);
        
        if (empty($dates)) {
            wp_send_json_error('No dates provided');
        }
        
        // Get existing schedule data
        $schedule_data = get_post_meta($post_id, 'schedule_dates', true);
        if (!is_array($schedule_data)) {
            $schedule_data = array();
        }
        
        // Process the dates
        $dates_array = explode(',', $dates);
        foreach ($dates_array as $date) {
            $date = sanitize_text_field(trim($date));
            if (!empty($date)) {
                $schedule_data[$date] = $status;
            }
        }
        
        // Save the updated schedule data
        $updated = update_post_meta($post_id, 'schedule_dates', $schedule_data);
        
        if ($updated) {
            wp_send_json_success(array(
                'message' => 'Schedule dates updated successfully',
                'dates' => $schedule_data
            ));
        } else {
            wp_send_json_error('Failed to update schedule dates');
        }
    }

    /**
     * Get schedule dates via AJAX
     */
    public function get_schedule_dates() {
        check_ajax_referer('schedule_dates_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = intval($_POST['post_id']);
        $schedule_data = get_post_meta($post_id, 'schedule_dates', true);
        
        if (!$schedule_data) {
            $schedule_data = array();
        }
        
        wp_send_json_success($schedule_data);
    }

    public function register_schedule_template($template) {
        if (is_singular('driver_schedule')) {
            $new_template = plugin_dir_path(dirname(__FILE__)) . '../frontend/templates/single-driver_schedule.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Add metabox for holiday requests
     */
    public function add_holiday_requests_metabox() {
        add_meta_box(
            'holiday_requests',
            __('Holiday Requests', 'scrap-driver'),
            array($this, 'render_holiday_requests_metabox'),
            'driver_schedule',
            'normal',
            'default'
        );
    }

    /**
     * Render the holiday requests metabox
     */
    public function render_holiday_requests_metabox($post) {
        $requests = get_post_meta($post->ID, 'holiday_requests', true);
        
        if (empty($requests)) {
            echo '<p>' . __('No holiday requests for this schedule.', 'scrap-driver') . '</p>';
            return;
        }

        // If single request is stored (old format), convert to array
        if (!isset($requests[0]) && isset($requests['requested_at'])) {
            $requests = array($requests);
        }

        wp_nonce_field('save_holiday_requests', 'holiday_requests_nonce');

        echo '<table class="widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Date Requested', 'scrap-driver') . '</th>';
        echo '<th>' . __('Period', 'scrap-driver') . '</th>';
        echo '<th>' . __('Days', 'scrap-driver') . '</th>';
        echo '<th>' . __('Comments', 'scrap-driver') . '</th>';
        echo '<th>' . __('Status', 'scrap-driver') . '</th>';
        echo '<th>' . __('Admin Response', 'scrap-driver') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($requests as $index => $request) {
            $status = isset($request['status']) ? $request['status'] : 'pending';
            $admin_response = isset($request['admin_response']) ? $request['admin_response'] : '';
            $days = $this->calculate_working_days($request['start_date'], $request['end_date']);
            
            echo '<tr>';
            echo '<td>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), 
                strtotime($request['requested_at'])) . '</td>';
            echo '<td>' . date_i18n(get_option('date_format'), strtotime($request['start_date'])) . ' - ' . 
                date_i18n(get_option('date_format'), strtotime($request['end_date'])) . '</td>';
            echo '<td>' . $days . '</td>';
            echo '<td>' . esc_html($request['comments']) . '</td>';
            echo '<td>';
            echo '<select name="holiday_request[' . $index . '][status]">';
            echo '<option value="pending" ' . selected($status, 'pending', false) . '>' . __('Pending', 'scrap-driver') . '</option>';
            echo '<option value="approved" ' . selected($status, 'approved', false) . '>' . __('Approved', 'scrap-driver') . '</option>';
            echo '<option value="denied" ' . selected($status, 'denied', false) . '>' . __('Denied', 'scrap-driver') . '</option>';
            echo '</select>';
            echo '</td>';
            echo '<td>';
            echo '<textarea name="holiday_request[' . $index . '][admin_response]" rows="2" style="width: 100%;">' . 
                esc_textarea($admin_response) . '</textarea>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        // Add save button
        echo '<p><input type="submit" class="button button-primary" value="' . __('Update Requests', 'scrap-driver') . '"></p>';
    }

    /**
     * Get admin user emails
     */
    private function get_admin_emails() {
        $admin_emails = array();
        $admin_users = get_users(array('role' => 'administrator'));
        
        foreach ($admin_users as $user) {
            $admin_emails[] = $user->user_email;
        }
        
        return $admin_emails;
    }


    /**
     * Handle holiday request submission
     */
    public function handle_holiday_request() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'request_holiday') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['holiday_request_nonce']) || 
            !wp_verify_nonce($_POST['holiday_request_nonce'], 'holiday_request')) {
            wp_die(__('Security check failed', 'scrap-driver'));
        }

        $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $comments = isset($_POST['comments']) ? sanitize_textarea_field($_POST['comments']) : '';
        
        if (!$schedule_id || !$start_date || !$end_date || !$comments) {
            wp_die(__('Invalid request', 'scrap-driver'));
        }

        // Calculate working days
        $days = $this->calculate_working_days($start_date, $end_date);

        // Store the holiday request
        $request_data = array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'days' => $days,
            'comments' => $comments,
            'requested_by' => get_current_user_id(),
            'requested_at' => current_time('mysql'),
            'status' => 'pending'
        );
        
        // Get existing requests and add new one
        $existing_requests = get_post_meta($schedule_id, 'holiday_requests', true);
        if (!is_array($existing_requests)) {
            $existing_requests = array();
        }
        
        // Add new request
        $existing_requests[] = $request_data;
        
        // Update post meta with all requests
        update_post_meta($schedule_id, 'holiday_requests', $existing_requests);

        // Send email notification to admin users
        $admin_emails = $this->get_admin_emails();
        $driver_name = get_userdata(get_current_user_id())->display_name;
        $schedule_url = get_edit_post_link($schedule_id);
        
        $subject = sprintf(__('[%s] New Holiday Request', 'scrap-driver'), get_bloginfo('name'));
        $message = sprintf(
            __("Driver %s has requested holiday leave.\n\nPeriod: %s to %s\nDays: %d\nComments: %s\n\nView schedule: %s", 'scrap-driver'),
            $driver_name,
            $start_date,
            $end_date,
            $days,
            $comments,
            $schedule_url
        );

        foreach ($admin_emails as $email) {
            wp_mail($email, $subject, $message);
        }

        // Redirect back to schedule page with success message
        wp_redirect(add_query_arg('holiday_requested', '1', get_permalink($schedule_id)));
        exit;
    }

    /**
     * Calculate working days between two dates
     */
    private function calculate_working_days($start_date, $end_date) {
        $start = new \DateTime($start_date);
        $end = new \DateTime($end_date);
        $end->modify('+1 day'); // Include the end date in the calculation
        $days = 0;

        while ($start < $end) {
            // Check if it's not a weekend
            if ($start->format('N') < 6) {
                $days++;
            }
            $start->modify('+1 day');
        }

        return $days;
    }

    /**
     * Save holiday request updates
     */
    public function save_holiday_requests($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['holiday_requests_nonce']) || 
            !wp_verify_nonce($_POST['holiday_requests_nonce'], 'save_holiday_requests')) {
            return;
        }

        // Check if we have holiday request data
        if (!isset($_POST['holiday_request'])) {
            return;
        }

        $requests = get_post_meta($post_id, 'holiday_requests', true);
        if (!is_array($requests)) {
            if (isset($requests['requested_at'])) {
                $requests = array($requests);
            } else {
                return;
            }
        }

        $total_allowance = get_field('total_annual_leave_allowance_days', $post_id);
        $days_taken = $this->get_days_taken($post_id);

        foreach ($_POST['holiday_request'] as $index => $data) {
            if (isset($requests[$index])) {
                $old_status = $requests[$index]['status'];
                $new_status = sanitize_text_field($data['status']);
                
                // If approving a request, check if enough days are available
                if ($new_status === 'approved' && $old_status !== 'approved') {
                    $request_days = $requests[$index]['days'];
                    if (($days_taken + $request_days) > $total_allowance) {
                        // Add admin note about exceeding allowance
                        $data['admin_response'] = __('Cannot approve - exceeds annual leave allowance.', 'scrap-driver');
                        $new_status = 'denied';
                    }
                }
                
                $requests[$index]['status'] = $new_status;
                $requests[$index]['admin_response'] = sanitize_textarea_field($data['admin_response']);
            }
        }

        update_post_meta($post_id, 'holiday_requests', $requests);
    }

    /**
     * Get total days taken from approved requests
     */
    private function get_days_taken($schedule_id) {
        $requests = get_post_meta($schedule_id, 'holiday_requests', true);
        if (!is_array($requests)) {
            return 0;
        }

        $days_taken = 0;
        foreach ($requests as $request) {
            if (isset($request['status']) && $request['status'] === 'approved') {
                $days_taken += $request['days'];
            }
        }

        return $days_taken;
    }
}

// Initialize the class
new Schedule(); 