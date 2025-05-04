<?php
namespace ScrapDriver\Admin;

class Tracking {
    
    public function __construct() {
        $this->init();
    }
    
    public function init() {
        // Register REST API endpoint for tracking
        add_action( 'rest_api_init', array( $this, 'register_tracking_endpoint' ) );
    }
    
    /**
     * Register the REST API endpoint for driver location tracking
     */
    public function register_tracking_endpoint() {
        register_rest_route( 'scrap-driver/v1', '/track-location', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'process_tracking_data' ),
            'permission_callback' => array( $this, 'check_api_permission' ),
            'args' => array(
                'coordinates' => array(
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_array( $param ) && ! empty( $param );
                    }
                ),
                'driver_name' => array(
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_string( $param ) && ! empty( $param );
                    }
                )
            )
        ) );
    }
    
    /**
     * Check if the request has valid permissions
     * 
     * @param \WP_REST_Request $request Current request
     * @return bool|\WP_Error
     */
    public function check_api_permission( $request ) {
        // You can implement proper authentication here if needed
        // For example, check API keys or JWT tokens
        
        return true; // Allow all requests for now
    }
    
    /**
     * Process the tracking data received from the app
     * 
     * @param \WP_REST_Request $request Current request
     * @return \WP_REST_Response|\WP_Error
     */
    public function process_tracking_data( $request ) {
        $coordinates = $request->get_param( 'coordinates' );
        $driver_name = $request->get_param( 'driver_name' );
        $timestamp = current_time( 'mysql' );
        
        // Find the driver using flexible matching
        $driver = $this->find_driver( $driver_name );
        
        if ( $driver ) {
            // Check if user is a collection driver
            $is_driver = get_user_meta( $driver->ID, 'collection_driver', true );
            
            if ( $is_driver ) {
                // Save tracking data for verified driver
                $this->save_tracking_data( $driver->ID, $driver_name, $coordinates, $timestamp );
                
                return new \WP_REST_Response( array(
                    'success' => true,
                    'message' => 'Location data saved successfully'
                ), 200 );
            } else {
                // User exists but is not a driver
                $this->log_error( "User {$driver_name} is not a collection driver" );
                $this->save_tracking_data( 0, $driver_name, $coordinates, $timestamp );
                
                return new \WP_REST_Response( array(
                    'success' => false,
                    'message' => 'User is not a collection driver, but location was saved'
                ), 400 );
            }
        } else {
            // Driver not found
            $this->log_error( "Driver not found: {$driver_name}" );
            $this->save_tracking_data( 0, $driver_name, $coordinates, $timestamp );
            
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => 'Driver not found, but location was saved'
            ), 400 );
        }
    }
    
    /**
     * Find a driver user using login name matching
     * 
     * @param string $driver_name The driver name to search for
     * @return \WP_User|false User object if found, false otherwise
     */
    private function find_driver( $driver_name ) {
        // First try exact match by login name
        $driver = get_user_by( 'login', $driver_name );
        if ( $driver ) {
            return $driver;
        }
        
        // Try case-insensitive match
        $lowercase_name = strtolower( $driver_name );
        
        // Get all users (use a reasonable limit to avoid performance issues)
        $args = array(
            'number' => 100, // Adjust as needed for your site
            'fields' => array( 'ID', 'user_login' )
        );
        
        $user_query = new \WP_User_Query( $args );
        $users = $user_query->get_results();
        
        // First pass: Look for case-insensitive exact match
        foreach ( $users as $user ) {
            if ( strtolower( $user->user_login ) === $lowercase_name ) {
                return get_user_by( 'ID', $user->ID );
            }
        }
        
        // Second pass: Find the nearest match using strpos
        $best_match = null;
        $best_match_pos = PHP_INT_MAX; // Start with maximum value
        $best_match_reverse_pos = PHP_INT_MAX;
        
        foreach ( $users as $user ) {
            $user_login_lower = strtolower( $user->user_login );
            
            // Check if user_login contains the driver name
            $pos = strpos( $user_login_lower, $lowercase_name );
            if ( $pos !== false && $pos < $best_match_pos ) {
                $best_match = $user;
                $best_match_pos = $pos;
            }
            
            // Check if driver name contains the user_login
            $reverse_pos = strpos( $lowercase_name, $user_login_lower );
            if ( $reverse_pos !== false && $reverse_pos < $best_match_reverse_pos ) {
                // Only replace if we haven't found a direct match already
                if ( $best_match === null ) {
                    $best_match = $user;
                    $best_match_reverse_pos = $reverse_pos;
                }
            }
        }
        
        // Return the best match if one was found
        if ( $best_match ) {
            return get_user_by( 'ID', $best_match->ID );
        }
        
        return false;
    }
    
    /**
     * Save tracking data for all drivers - valid or unknown
     * 
     * @param int $driver_id Driver user ID (0 for unknown drivers)
     * @param string $driver_name Driver name from request
     * @param array $coordinates Location coordinates
     * @param string $timestamp Current timestamp
     * @return bool Success status
     */
    private function save_tracking_data( $driver_id, $driver_name, $coordinates, $timestamp ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'scrap_driver_tracking';
        
        // Create table if it doesn't exist
        $this->maybe_create_tracking_table();
        
        // Check if it's a multi-dimensional array of coordinates
        if ( isset( $coordinates[0] ) && is_array( $coordinates[0] ) ) {
            // Multiple coordinates
            $success = true;
            
            foreach ( $coordinates as $coord ) {
                $result = $this->validate_and_insert_coordinate( $driver_id, $driver_name, $coord, $timestamp, $table_name );
                if ( $result === false ) {
                    $success = false;
                }
            }
            
            return $success;
        } else {
            // Single coordinate pair
            return $this->validate_and_insert_coordinate( $driver_id, $driver_name, $coordinates, $timestamp, $table_name );
        }
    }
    
    /**
     * Validate and insert a single coordinate pair
     * 
     * @param int $driver_id Driver user ID
     * @param string $driver_name Driver name
     * @param array $coordinate Single coordinate pair
     * @param string $timestamp Current timestamp
     * @param string $table_name Database table name
     * @return bool Success status
     */
    private function validate_and_insert_coordinate( $driver_id, $driver_name, $coordinate, $timestamp, $table_name ) {
        global $wpdb;
        
        // Validate coordinate
        if ( empty( $driver_name ) || 
            !isset( $coordinate['lat'] ) || 
            !isset( $coordinate['lng'] ) ||
            !is_numeric( $coordinate['lat'] ) || 
            !is_numeric( $coordinate['lng'] ) ) {
            $this->log_error( "Invalid tracking data: missing or invalid coordinates or driver name" );
            return false;
        }
        
        // Insert tracking data
        $result = $wpdb->insert(
            $table_name,
            array(
                'driver_id' => $driver_id,
                'driver_name' => $driver_name,
                'latitude' => floatval( $coordinate['lat'] ),
                'longitude' => floatval( $coordinate['lng'] ),
                'timestamp' => $timestamp
            ),
            array( '%d', '%s', '%f', '%f', '%s' )
        );
        
        return $result !== false;
    }
    
    /**
     * Log tracking errors
     * 
     * @param string $message Error message
     */
    private function log_error( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scrap Driver Tracking: ' . $message );
        }
        
        // Save error to log
        $error_log = get_option( 'scrap_driver_tracking_errors', array() );
        $error_log[] = array(
            'message' => $message,
            'timestamp' => current_time( 'mysql' )
        );
        
        // Limit the size of error log
        if ( count( $error_log ) > 100 ) {
            $error_log = array_slice( $error_log, -100 );
        }
        
        update_option( 'scrap_driver_tracking_errors', $error_log );
    }
    
    /**
     * Create tracking table if it doesn't exist
     */
    private function maybe_create_tracking_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'scrap_driver_tracking';
        
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                driver_id bigint(20) NOT NULL,
                driver_name varchar(255) NOT NULL,
                latitude float(10,6) NOT NULL,
                longitude float(10,6) NOT NULL,
                timestamp datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY driver_id (driver_id),
                KEY driver_name (driver_name),
                KEY timestamp (timestamp)
            ) $charset_collate;";
            
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }
    }
}

new Tracking();
