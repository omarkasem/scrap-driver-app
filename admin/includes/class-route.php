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
            'post_status' => 'publish'
        );

        if ($driver_id) {
            $args['meta_query'] = array(
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id
                )
            );
        }

        $collections = get_posts($args);
        
        return array_map(function($collection) {
            $collection_date = get_post_meta($collection->ID, 'collection_date', true);
            return array(
                'id' => $collection->ID,
                'title' => $collection->post_title,
                'date' => $collection_date,
                'driver_id' => get_post_meta($collection->ID, 'assigned_driver', true),
                'route_order' => get_post_meta($collection->ID, 'route_order', true)
            );
        }, $collections);
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

        update_post_meta($collection_id, 'collection_date', $new_date);
        update_post_meta($collection_id, 'assigned_driver', $driver_id);
        update_post_meta($collection_id, 'route_order', $route_order);

        wp_send_json_success();
    }
}

new SDA_Route(); 