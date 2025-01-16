<?php
/**
 * Route Planning Class
 */
class SDA_Route {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_route_page'));
        
        // Ajax handlers
        add_action('wp_ajax_update_collection_route', array($this, 'update_collection_route'));
        add_action('wp_ajax_get_collections', array($this, 'ajax_get_collections'));
    }

    /**
     * Add Route Planning page
     */
    public function add_route_page() {
        add_submenu_page(
            'edit.php?post_type=sda-collection',
            'Route Planning',
            'Route Planning',
            'manage_options',
            'sda-route-planning',
            array($this, 'render_route_page')
        );
    }


    /**
     * Render the route planning page
     */
    public function render_route_page() {
        include plugin_dir_path(__FILE__) . '../templates/route-page.php';
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
                array(
                    'key' => 'collection_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            )
        );

        if ($driver_id) {
            $args['meta_query'][] = array(
                'key' => 'assigned_driver',
                'value' => $driver_id
            );
        }

        $collections = get_posts($args);
        
        $formatted_collections = array_map(function($collection) {
            $collection_date = get_post_meta($collection->ID, 'collection_date', true);
            
            // Debug log
            error_log(sprintf(
                'Processing collection %d: Date: %s',
                $collection->ID,
                $collection_date
            ));
            
            return array(
                'id' => $collection->ID,
                'title' => $collection->post_title,
                'start' => $collection_date,
                'driver_id' => get_post_meta($collection->ID, 'assigned_driver', true),
                'route_order' => get_post_meta($collection->ID, 'route_order', true)
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
        $driver_id = intval($_POST['driver_id']);
        $route_order = intval($_POST['route_order']);

        // Validate the new date
        $new_date_timestamp = strtotime($new_date);
        $today_timestamp = strtotime(date('Y-m-d'));

        if ($new_date_timestamp < $today_timestamp) {
            wp_send_json_error('Cannot set collection date to past date');
            return;
        }

        // Format the date consistently
        $formatted_date = date('Y-m-d', $new_date_timestamp);

        // Update the collection
        $updated = update_post_meta($collection_id, 'collection_date', $formatted_date);
        update_post_meta($collection_id, 'assigned_driver', $driver_id);
        update_post_meta($collection_id, 'route_order', $route_order);

        if ($updated) {
            wp_send_json_success(array(
                'message' => 'Collection updated successfully',
                'date' => $formatted_date
            ));
        } else {
            wp_send_json_error('Failed to update collection');
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
                'driverId' => $collection['driver_id'],
                'routeOrder' => $collection['route_order']
            );
        }, $collections);
        
        wp_send_json_success($events);
    }
}

new SDA_Route(); 