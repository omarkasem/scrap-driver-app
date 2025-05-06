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
        // $query = $wpdb->prepare(
        //     "SELECT DISTINCT driver_id, driver_name FROM {$table_name} 
        //     WHERE timestamp >= %s 
        //     AND driver_id > 0
        //     ORDER BY driver_name ASC",
        //     $hours_ago
        // );
        $query = $wpdb->prepare(
            "SELECT DISTINCT driver_id, driver_name FROM {$table_name} 
            WHERE driver_id > 0
            ORDER BY driver_name ASC", array()
        );
        
        $drivers = $wpdb->get_results( $query );
        
        if ( empty( $drivers ) ) {
            return array();
        }
        
        $driver_data = array();
        
        // For each driver, get their tracking points ordered by timestamp
        foreach ( $drivers as $driver ) {
            // $query = $wpdb->prepare(
            //     "SELECT * FROM {$table_name} 
            //     WHERE driver_id = %d 
            //     AND timestamp >= %s 
            //     ORDER BY timestamp ASC 
            //     LIMIT %d",
            //     $driver->driver_id,
            //     $hours_ago,
            //     $limit
            // );
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} 
                WHERE driver_id = %d 
                ORDER BY timestamp ASC 
                LIMIT %d",
                $driver->driver_id,
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
        
        // Get drivers tracking data and pass to JavaScript
        $tracking_data = $this->get_driver_tracking_data( 24, 1000 ); // Get 24 hours of data, up to 1000 points per driver
        
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
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div class="location-history-container">
                <!-- Location history content will go here -->
                <p><?php _e( 'View historical location data for drivers.', 'scrap-driver' ); ?></p>
            </div>
        </div>
        <?php
    }
}

// Instantiate the class
new LocationView();
