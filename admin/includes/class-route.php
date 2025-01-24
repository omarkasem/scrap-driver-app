<?php
namespace ScrapDriver;
/**
 * Route Planning Class
 */
class Route {
    
    /**
     * Constructor
     */
    public function __construct() {
        
        // Ajax handlers
        add_action('wp_ajax_update_collection_route', array($this, 'update_collection_route'));
        add_action('wp_ajax_get_collections', array($this, 'ajax_get_collections'));

        add_filter('acf/load_field', array($this, 'render_route_planning'));
    }

    /**
     * Render the route planning interface within shift
     */
    public function render_route_planning($field) {
        if($field['key'] === 'field_67938eec736a9') {
            $value = '';
            $value.= '<div class="sda-route-container">';
            $value.= '<div id="calendar"></div>';
            $value.= '</div>';
            $field['message'] = $value;
        }
        return $field;
    }

    /**
     * Get all drivers
     */
    private function get_drivers() {
        $drivers = get_users(array('role' => 'driver'));
        return array_map(function($driver) {
            return array(
                'id' => $driver->ID,
                'name' => $driver->display_name
            );
        }, $drivers);
    }

    /**
     * Get collections for calendar
     */
    public function get_collections($driver_id = null) {
        $args = array(
            'post_type' => 'sda-collection',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'collection_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                ),
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id
                )
            )
        );


        $collections = get_posts($args);

        if(empty($collections)) {
            return array();
        }
        
        $formatted_collections = array_map(function($collection) {
            $collection_date = get_post_meta($collection->ID, 'collection_date', true);
            $start_time = get_post_meta($collection->ID, 'collection_start_time', true) ?: '08:00:00';
            $end_time = get_post_meta($collection->ID, 'collection_end_time', true) ?: '09:00:00';
            
            // Debug log
            error_log(sprintf(
                'Processing collection %d: Date: %s',
                $collection->ID,
                $collection_date
            ));
            
            return array(
                'id' => $collection->ID,
                'title' => $collection->post_title,
                'start' => $collection_date . 'T' . $start_time,
                'end' => $collection_date . 'T' . $end_time,
                'driver_id' => get_post_meta($collection->ID, 'assigned_driver', true),
                'route_order' => get_post_meta($collection->ID, 'route_order', true),
                'url' => get_edit_post_link($collection->ID)
            );
        }, $collections);

        // Debug log
        error_log('Formatted collections: ' . print_r($formatted_collections, true));

        return $formatted_collections;
    }

    /**
     * Ajax handler for updating collection route
     */
    public function update_collection_route() {
        check_ajax_referer('sda_route_nonce', 'nonce');

        $collection_id = intval($_POST['collection_id']);
        $new_date = sanitize_text_field($_POST['new_date']);
        $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '08:00:00';
        $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '09:00:00';
        $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;
        $route_order = intval($_POST['route_order']);

        // Debug logs
        error_log('Update collection request: ' . print_r([
            'collection_id' => $collection_id,
            'new_date' => $new_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'driver_id' => $driver_id,
            'route_order' => $route_order
        ], true));

        // Validate inputs
        if (!$collection_id || !$new_date || !$start_time || !$end_time) {
            wp_send_json_error('Missing required fields');
            return;
        }

        // Validate the new date
        $new_date_timestamp = strtotime($new_date);
        $today_timestamp = strtotime(date('Y-m-d'));

        if ($new_date_timestamp === false) {
            wp_send_json_error('Invalid date format');
            return;
        }

        if ($new_date_timestamp < $today_timestamp) {
            wp_send_json_error('Cannot set collection date to past date');
            return;
        }

        // Format the date consistently
        $formatted_date = date('Y-m-d', $new_date_timestamp);

        try {
            // Get current values
            $current_date = get_post_meta($collection_id, 'collection_date', true);
            $current_start = get_post_meta($collection_id, 'collection_start_time', true);
            $current_end = get_post_meta($collection_id, 'collection_end_time', true);

            // Only update if values are different
            $date_updated = ($current_date === $formatted_date) || update_post_meta($collection_id, 'collection_date', $formatted_date);
            $start_updated = ($current_start === $start_time) || update_post_meta($collection_id, 'collection_start_time', $start_time);
            $end_updated = ($current_end === $end_time) || update_post_meta($collection_id, 'collection_end_time', $end_time);
            
            // Only update driver if a specific driver is selected
            if ($driver_id > 0) {
                update_post_meta($collection_id, 'assigned_driver', $driver_id);
            }
            
            update_post_meta($collection_id, 'route_order', $route_order);

            // Log the update results
            error_log('Update results: ' . print_r([
                'date_updated' => $date_updated,
                'start_updated' => $start_updated,
                'end_updated' => $end_updated,
                'current_date' => $current_date,
                'new_date' => $formatted_date,
                'current_start' => $current_start,
                'new_start' => $start_time,
                'current_end' => $current_end,
                'new_end' => $end_time
            ], true));

            wp_send_json_success([
                'message' => 'Collection updated successfully',
                'date' => $formatted_date,
                'start_time' => $start_time,
                'end_time' => $end_time
            ]);

        } catch (Exception $e) {
            error_log('Error updating collection: ' . $e->getMessage());
            wp_send_json_error('Error updating collection: ' . $e->getMessage());
        }
    }

    /**
     * Ajax handler for getting collections
     */
    public function ajax_get_collections() {
        check_ajax_referer('sda_route_nonce', 'nonce');
        
        $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : null;
        $collections = $this->get_collections($driver_id);
        
        $events = array_map(function($collection) {
            return array(
                'id' => $collection['id'],
                'title' => $collection['title'],
                'start' => $collection['start'],
                'end' => $collection['end'],
                'driverId' => $collection['driver_id'],
                'routeOrder' => $collection['route_order'],
                'url' => $collection['url']
            );
        }, $collections);
        
        wp_send_json_success($events);
    }
}

new Route(); 