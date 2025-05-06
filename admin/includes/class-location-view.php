<?php
namespace ScrapDriver\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class LocationView
 * 
 * Handles the location view admin pages for driver tracking
 * 
 * @package ScrapDriver\Admin
 */
class LocationView {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu pages
        add_action( 'admin_menu', array( $this, 'add_location_menu_pages' ) );
        
        // Add AJAX handler for location history
        add_action('wp_ajax_get_location_history', array($this, 'ajax_get_location_history'));
        add_action('wp_ajax_get_driver_shift_dates', array($this, 'ajax_get_driver_shift_dates'));
    }
    
    /**
     * Add location menu pages under driver-app menu
     */
    public function add_location_menu_pages() {
        // Add Live Location page
        add_submenu_page(
            'driver-app',                        // Parent slug
            __( 'Live Location', 'scrap-driver' ),  // Page title
            __( 'Live Location', 'scrap-driver' ),  // Menu title
            'manage_options',                   // Capability
            'live-location',                    // Menu slug
            array( $this, 'render_live_location_page' )  // Callback function
        );
        
        // Add Location History page
        add_submenu_page(
            'driver-app',                        // Parent slug
            __( 'Location History', 'scrap-driver' ),  // Page title
            __( 'Location History', 'scrap-driver' ),  // Menu title
            'manage_options',                   // Capability
            'location-history',                 // Menu slug
            array( $this, 'render_location_history_page' )  // Callback function
        );
    }
    
    /**
     * Get tracking data grouped by driver
     * 
     * @param int $hours_back Optional. Hours of data to fetch. Default 1.
     * @param int $limit Optional. Maximum number of records per driver. Default 100.
     * @return array Array of tracking data grouped by driver
     */
    public function get_driver_tracking_data( $hours_back = 1, $limit = 100 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'scrap_driver_tracking';
        $hours_ago = date( 'Y-m-d H:i:s', strtotime( "-{$hours_back} hour" ) );
        
        // First get distinct drivers
        $query = $wpdb->prepare(
            "SELECT DISTINCT driver_id, driver_name FROM {$table_name} 
            WHERE timestamp >= %s 
            AND driver_id > 0
            ORDER BY driver_name ASC",
            $hours_ago
        );
        
        $drivers = $wpdb->get_results( $query );
        
        if ( empty( $drivers ) ) {
            return array();
        }
        
        $driver_data = array();
        
        // For each driver, get their tracking points ordered by timestamp
        foreach ( $drivers as $driver ) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} 
                WHERE driver_id = %d 
                AND timestamp >= %s 
                ORDER BY timestamp ASC 
                LIMIT %d",
                $driver->driver_id,
                $hours_ago,
                $limit
            );
            
            $tracking_points = $wpdb->get_results( $query, ARRAY_A );
            
            if ( ! empty( $tracking_points ) ) {
                $driver_data[ $driver->driver_id ] = array(
                    'driver_id' => $driver->driver_id,
                    'driver_name' => $driver->driver_name,
                    'points' => $tracking_points
                );
            }
        }
        
        return $driver_data;
    }
    
    /**
     * Render the Live Location page
     */
    public function render_live_location_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <!-- Driver routes legend -->
            <div id="drivers-legend" class="drivers-legend">
                <!-- Legend will be populated by JavaScript -->
            </div>
            
            <div class="live-location-container">
                <div id="driver-live-map" style="height: 600px; width: 100%;"></div>
            </div>
        </div>
        <?php
        $time = 24;
        if(isset($_GET['location-history'])){
            $time = 99999999999;
        }
        // Get drivers tracking data and pass to JavaScript
        $tracking_data = $this->get_driver_tracking_data( $time, 1000 ); // Get 24 hours of data, up to 1000 points per driver
        
        if ( ! empty( $tracking_data ) ) {
            wp_localize_script( 'scrap-driver-admin', 'sdaDriversTracking', array(
                'drivers' => $tracking_data
            ) );
        }
    }
    
    /**
     * Get tracking data for the last hour
     * 
     * @param int $limit Optional. Number of records to return. Default 100.
     * @return array Array of tracking data
     */
    public function get_last_hour_tracking_data( $limit = 100 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'scrap_driver_tracking';
        $one_hour_ago = date( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} 
            WHERE timestamp >= %s 
            ORDER BY timestamp DESC 
            LIMIT %d",
            $one_hour_ago,
            $limit
        );
        
        $tracking_data = $wpdb->get_results( $query, ARRAY_A );
        
        return $tracking_data;
    }
    
    /**
     * Render the Location History page
     */
    public function render_location_history_page() {
        global $wpdb;
        
        // Get all drivers
        $drivers = get_users(array(
            'meta_key' => 'collection_driver',
            'meta_compare' => 'EXISTS'
        ));
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="location-history-filters">
                <form id="location-history-form">
                    <select name="driver" id="history-driver-select">
                        <option value=""><?php _e('Select Driver', 'scrap-driver'); ?></option>
                        <?php foreach ($drivers as $driver): ?>
                            <option value="<?php echo esc_attr($driver->ID); ?>">
                                <?php echo esc_html($driver->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="shift_date" id="history-date-select" disabled>
                        <option value=""><?php _e('Select Shift Date', 'scrap-driver'); ?></option>
                    </select>
                    
                    <button type="submit" class="button button-primary" disabled>
                        <?php _e('Apply Filter', 'scrap-driver'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Driver routes legend -->
            <div id="drivers-legend" class="drivers-legend">
                <!-- Legend will be populated by JavaScript -->
            </div>
            
            <div class="location-history-container">
                <div id="driver-live-map" style="height: 600px; width: 100%;"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for getting location history
     */
    public function ajax_get_location_history() {
        // Verify nonce
        check_ajax_referer('sda_route_nonce', 'nonce');
        
        $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        
        if (!$driver_id || !$date) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        // Get shift data for this driver and date
        $args = array(
            'post_type' => 'sda-shift',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'start_time',
                    'value' => $date,
                    'compare' => 'LIKE'
                )
            )
        );
        
        $shift = get_posts($args);
        
        $tracking_data = $this->get_driver_tracking_history($driver_id, $date);
        $metrics = array();
        
        if (!empty($shift)) {
            $shift_id = $shift[0]->ID;
            
            // Get shift metrics
            $total_distance = get_post_meta($shift_id, 'total_distance', true);
            $total_time = get_post_meta($shift_id, 'total_time', true);
            $start_time = get_post_meta($shift_id, 'start_time', true);
            $end_time = get_post_meta($shift_id, 'end_time', true);
            $shift_collections = get_post_meta($shift_id, 'shift_collections', true);
            
            // Get collection locations
            $collection_locations = array();
            if ($shift_collections) {
                $collections = maybe_unserialize($shift_collections);
                $distance = new Distance();
                
                foreach ($collections as $collection_id) {
                    $address = get_post_meta($collection_id, 'customer_info_address', true);
                    $postcode = get_post_meta($collection_id, 'customer_info_postcode', true);
                    $lat_lng = $distance->get_lat_lng_by_address($address, $postcode, $collection_id);
                    
                    if (!is_wp_error($lat_lng) && isset($lat_lng['lat'], $lat_lng['lng'])) {
                        $collection_locations[] = array(
                            'id' => $collection_id,
                            'lat' => $lat_lng['lat'],
                            'lng' => $lat_lng['lng'],
                            'address' => $address,
                            'postcode' => $postcode
                        );
                    }
                }
            }
            
            // Format the time using Distance class method
            $distance = new Distance();
            $formatted_time = $distance->format_time_duration($total_time);
            
            $metrics = array(
                'total_distance' => $total_distance ? round($total_distance, 2) . ' miles' : 'N/A',
                'total_time' => $formatted_time ? $formatted_time : 'N/A',
                'start_time' => $start_time ? date('H:i', strtotime($start_time)) : 'N/A',
                'end_time' => $end_time ? date('H:i', strtotime($end_time)) : 'N/A',
                'total_collections' => $shift_collections ? count(maybe_unserialize($shift_collections)) : 0,
                'collection_locations' => $collection_locations
            );
        }
        
        if (!empty($tracking_data)) {
            wp_send_json_success(array(
                'drivers' => $tracking_data,
                'metrics' => $metrics
            ));
        } else {
            wp_send_json_error('No tracking data found');
        }
    }
    
    /**
     * Get tracking data for specific driver and date
     * 
     * @param int $driver_id Driver ID
     * @param string $date Date in Y-m-d format
     * @return array Array of tracking data
     */
    public function get_driver_tracking_history($driver_id, $date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'scrap_driver_tracking';
        $start_of_day = $date . ' 00:00:00';
        $end_of_day = $date . ' 23:59:59';
        
        // Get tracking points for the specific driver and date
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} 
            WHERE driver_id = %d 
            AND timestamp >= %s 
            AND timestamp <= %s 
            ORDER BY timestamp ASC",
            $driver_id,
            $start_of_day,
            $end_of_day
        );
        
        $tracking_points = $wpdb->get_results($query, ARRAY_A);
        
        if (!empty($tracking_points)) {
            $driver = get_user_by('id', $driver_id);
            return array(
                $driver_id => array(
                    'driver_id' => $driver_id,
                    'driver_name' => $driver->display_name,
                    'points' => $tracking_points
                )
            );
        }
        
        return array();
    }
    
    /**
     * Get shift dates for a specific driver
     * 
     * @param int $driver_id Driver ID
     * @return array Array of shift dates
     */
    public function get_driver_shift_dates($driver_id) {
        $shifts = get_posts(array(
            'post_type' => 'sda-shift',
            'posts_per_page' => -1,
            'meta_key' => 'start_time',
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id,
                    'compare' => '='
                )
            )
        ));
        
        $dates = array();
        foreach ($shifts as $shift) {
            $start_time = get_post_meta($shift->ID, 'start_time', true);
            if ($start_time) {
                $date = date('Y-m-d', strtotime($start_time));
                $formatted_date = date('F j, Y', strtotime($start_time));
                $dates[] = array(
                    'value' => $date,
                    'label' => $formatted_date
                );
            }
        }
        
        return $dates;
    }
    
    /**
     * AJAX handler for getting driver shift dates
     */
    public function ajax_get_driver_shift_dates() {
        check_ajax_referer('sda_route_nonce', 'nonce');
        
        $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;
        
        if (!$driver_id) {
            wp_send_json_error('Missing driver ID');
            return;
        }
        
        $dates = $this->get_driver_shift_dates($driver_id);
        
        if (!empty($dates)) {
            wp_send_json_success(array('dates' => $dates));
        } else {
            wp_send_json_error('No shifts found for this driver');
        }
    }
}

// Instantiate the class
new LocationView();
