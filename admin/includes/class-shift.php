<?php
namespace ScrapDriver\Admin;

class Shift {
    public function __construct() {
        // Handle shift actions
        add_action('init', array($this, 'handle_shift_actions'));
        
        
        // Add template loading filters
        add_filter('theme_page_templates', array($this, 'add_shifts_template'));
        add_filter('template_include', array($this, 'load_shifts_template'));
        add_filter('single_template', array($this, 'load_single_shift_template'));
        
        // Add shift adjustment handling
        add_action('init', array($this, 'handle_shift_adjustment_request'));
        
        // Add metabox for adjustment requests
        add_action('add_meta_boxes', array($this, 'add_adjustment_requests_metabox'));

        // Add save adjustment request action
        add_action('save_post_sda-shift', array($this, 'save_adjustment_requests'), 10, 2);

        // Remove the old action
        remove_action('save_post_sda-shift', array($this, 'update_shift_title'), 10, 3);
        
        // Add hook for automatic shift title with later priority (20)
        add_filter('default_title', array($this, 'set_default_shift_title'), 10, 2);
        add_action('acf/save_post', array($this, 'update_shift_title'), 20, 1);

        // Add validation for duplicate shifts
        add_action('save_post_sda-shift', array($this, 'validate_duplicate_shifts'), 10, 3);
    }

    public static function get_current_shift_id() {
        return get_user_meta(get_current_user_id(), 'current_shift_id', true);
    }

    public function set_default_shift_title($post_title, $post) {
        if ($post->post_type !== 'sda-shift') {
            return $post_title;
        }

        return sprintf('Shift By %s on %s', 
            '{driver}',
            '{date}'
        );
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
        // Check if user has a shift assigned for today
        $today = date('Ymd');
        $today_shift = get_posts(array(
            'post_type' => 'sda-shift',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'assigned_driver',
                    'value' => $current_user_id
                ),
                array(
                    'key' => 'shift_date',
                    'value' => $today
                )
            ),
            'posts_per_page' => 1
        ));

        if (empty($today_shift)) {
            wp_die(__('You don\'t have any shifts assigned for today.', 'scrap-driver'));
        }

        $shift_id = $today_shift[0]->ID;
        
        // Check if shift has already been started
        $existing_start_time = get_field('start_time', $shift_id);
        if ( !empty( $existing_start_time ) ) {
            wp_die( __( 'This shift has already been started.', 'scrap-driver' ) );
        }
        
        $shift_start = current_time('mysql');
        
        // Update shift start time
        update_field('start_time', $shift_start, $shift_id);
        update_user_meta($current_user_id, 'shift_start_time', $shift_start);
        update_user_meta($current_user_id, 'current_shift_id', $shift_id);

        // Redirect to first collection
        $all_collections = get_posts(array(
            'post_type' => 'sda-collection',
            'meta_query' => array(
                array(
                    'key' => 'assigned_driver',
                    'value' => $current_user_id
                )
            ),
            'fields'=>'ids',
            'posts_per_page' => -1
        ));

        $collections_order = \ScrapDriver\Frontend\Collection::get_collections_order($all_collections);
        $collections_order = array_flip($collections_order);
        $first_collection = array_shift($collections_order);

        if (!empty($first_collection)) {
            wp_redirect(get_permalink($first_collection));
            exit;
        }
    }

    /**
     * End current shift
     */
    private function end_shift($current_user_id) {
        $shift_end = current_time('mysql');
        
        // Update shift post
        $current_shift_id = get_user_meta($current_user_id, 'current_shift_id', true);
        if ( $current_shift_id ) {
            // Check if shift has already been ended
            $existing_end_time = get_field('end_time', $current_shift_id);
            if ( !empty( $existing_end_time ) ) {
                wp_die( __( 'This shift has already been ended.', 'scrap-driver' ) );
            }
            
            update_field('end_time', $shift_end, $current_shift_id);
            delete_user_meta($current_user_id, 'current_shift_id');
        }
        
        delete_user_meta($current_user_id, 'shift_start_time');
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

    /**
     * Add metabox for shift adjustment requests
     */
    public function add_adjustment_requests_metabox() {
        add_meta_box(
            'shift_adjustment_requests',
            __( 'Shift Adjustment Requests', 'scrap-driver' ),
            array( $this, 'render_adjustment_requests_metabox' ),
            'sda-shift',
            'normal',
            'default'
        );
    }

    /**
     * Render the adjustment requests metabox
     */
    public function render_adjustment_requests_metabox($post) {
        $requests = get_post_meta($post->ID, 'shift_adjustment_request', true);
        
        if (empty($requests)) {
            echo '<p>' . __('No adjustment requests for this shift.', 'scrap-driver') . '</p>';
            return;
        }

        // If single request is stored (old format), convert to array
        if (!isset($requests[0]) && isset($requests['requested_at'])) {
            $requests = array($requests);
        }

        wp_nonce_field('save_shift_adjustments', 'shift_adjustments_nonce');

        echo '<table class="widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Date', 'scrap-driver') . '</th>';
        echo '<th>' . __('Comments', 'scrap-driver') . '</th>';
        echo '<th>' . __('Status', 'scrap-driver') . '</th>';
        echo '<th>' . __('Admin Response', 'scrap-driver') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($requests as $index => $request) {
            $driver = get_userdata($request['requested_by']);
            $status = isset($request['status']) ? $request['status'] : 'pending';
            $admin_response = isset($request['admin_response']) ? $request['admin_response'] : '';
            
            echo '<tr>';
            echo '<td>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), 
                strtotime($request['requested_at'])) . '</td>';
            echo '<td>' . esc_html($request['comments']) . '</td>';
            echo '<td>';
            echo '<select name="shift_adjustment[' . $index . '][status]">';
            echo '<option value="pending" ' . selected($status, 'pending', false) . '>' . __('Pending', 'scrap-driver') . '</option>';
            echo '<option value="approved" ' . selected($status, 'approved', false) . '>' . __('Approved', 'scrap-driver') . '</option>';
            echo '<option value="denied" ' . selected($status, 'denied', false) . '>' . __('Denied', 'scrap-driver') . '</option>';
            echo '</select>';
            echo '</td>';
            echo '<td>';
            echo '<textarea name="shift_adjustment[' . $index . '][admin_response]" rows="2" style="width: 100%;">' . 
                esc_textarea($admin_response) . '</textarea>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        // Add save button
        echo '<p><input type="submit" class="button button-primary" value="' . __('Update Requests', 'scrap-driver') . '"></p>';

        // Add existing CSS...
    }

    /**
     * Handle shift adjustment requests
     */
    public function handle_shift_adjustment_request() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'request_shift_adjustment') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['shift_adjustment_nonce']) || 
            !wp_verify_nonce($_POST['shift_adjustment_nonce'], 'shift_adjustment_request')) {
            wp_die(__('Security check failed', 'scrap-driver'));
        }

        $shift_id = isset($_POST['shift_id']) ? intval($_POST['shift_id']) : 0;
        $comments = isset($_POST['adjustment_comments']) ? sanitize_textarea_field($_POST['adjustment_comments']) : '';
        
        if (!$shift_id || !$comments) {
            wp_die(__('Invalid request', 'scrap-driver'));
        }

        // Store the adjustment request
        $request_data = array(
            'comments' => $comments,
            'requested_by' => get_current_user_id(),
            'requested_at' => current_time('mysql'),
            'status' => 'pending'
        );
        
        // Get existing requests and add new one
        $existing_requests = get_post_meta($shift_id, 'shift_adjustment_request', true);
        if (!is_array($existing_requests)) {
            $existing_requests = array();
        }
        // If old format (single request), convert to array
        if (!empty($existing_requests) && !isset($existing_requests[0]) && isset($existing_requests['requested_at'])) {
            $existing_requests = array($existing_requests);
        }
        
        // Add new request
        $existing_requests[] = $request_data;
        
        // Update post meta with all requests
        update_post_meta($shift_id, 'shift_adjustment_request', $existing_requests);

        // Send email notification to admin users
        $admin_emails = $this->get_admin_emails();
        $driver_name = get_userdata(get_current_user_id())->display_name;
        $shift_url = get_edit_post_link($shift_id);
        
        $subject = sprintf(__('[%s] New Shift Adjustment Request', 'scrap-driver'), get_bloginfo('name'));
        $message = sprintf(
            __("Driver %s has requested a shift adjustment.\n\nShift: %s\nComments: %s\n\nView shift: %s", 'scrap-driver'),
            $driver_name,
            get_the_title($shift_id),
            $comments,
            $shift_url
        );

        foreach ($admin_emails as $email) {
            wp_mail($email, $subject, $message);
        }

        // Redirect back to shift page with success message
        wp_redirect(add_query_arg('adjustment_requested', '1', get_permalink($shift_id)));
        exit;
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
     * Save adjustment request updates
     */
    public function save_adjustment_requests($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['shift_adjustments_nonce']) || 
            !wp_verify_nonce($_POST['shift_adjustments_nonce'], 'save_shift_adjustments')) {
            return;
        }

        // Check if we have adjustment data
        if (!isset($_POST['shift_adjustment'])) {
            return;
        }

        $requests = get_post_meta($post_id, 'shift_adjustment_request', true);
        if (!is_array($requests)) {
            if (isset($requests['requested_at'])) {
                $requests = array($requests);
            } else {
                return;
            }
        }

        foreach ($_POST['shift_adjustment'] as $index => $data) {
            if (isset($requests[$index])) {
                $requests[$index]['status'] = sanitize_text_field($data['status']);
                $requests[$index]['admin_response'] = sanitize_textarea_field($data['admin_response']);
            }
        }

        update_post_meta($post_id, 'shift_adjustment_request', $requests);
    }

    /**
     * Update shift title when published or updated
     */
    public function update_shift_title($post_id) {
        // Get post type
        $post_type = get_post_type($post_id);
        if ($post_type !== 'sda-shift') {
            return;
        }

        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

        // Get driver and shift date after ACF has saved them
        $driver_id = get_field('assigned_driver', $post_id);
        $shift_date = get_field('shift_date', $post_id);

        if ($driver_id && $shift_date) {
            $driver = get_userdata($driver_id);
            if ($driver) {
                $new_title = sprintf('Shift By %s on %s',
                    $driver->display_name,
                    $shift_date
                );

                // Update post title and slug
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => $new_title,
                    'post_name' => sanitize_title($new_title)
                ));
            }
        }
    }

    /**
     * Validate and prevent duplicate shifts
     */
    public function validate_duplicate_shifts($post_id, $post, $update) {
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        
        // Only check published posts
        if ($post->post_status !== 'publish') return;

        // Get driver and shift date FROM $_post ACF fields
        $driver_id = $_POST['acf']['field_67938b4d7f418'];
        $shift_date = $_POST['acf']['field_67938e50736a3'];

        if (!$driver_id || !$shift_date) return;

        // Check for existing shifts with same driver and date
        $existing_shifts = get_posts(array(
            'post_type' => 'sda-shift',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__not_in' => array($post_id), // Exclude current shift
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id
                ),
                array(
                    'key' => 'shift_date',
                    'value' => date('Ymd', strtotime($shift_date))
                )
            )
        ));

        if (!empty($existing_shifts)) {
            // Set post status to draft
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft'
            ));

            wp_die(__('A shift already exists for this driver on this date. The shift has been saved as draft.<br> <br><button class="button button-primary" onclick="window.history.back()">Go back to Edit shift</button>', 'scrap-driver'));
            
        }
    }
}

new Shift(); 