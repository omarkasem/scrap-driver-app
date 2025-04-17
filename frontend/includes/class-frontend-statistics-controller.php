<?php
/**
 * Frontend Statistics Controller
 * 
 * Handles AJAX requests and permissions.
 */
class FrontendStatisticsController {
    
    /**
     * Driver Statistics instance
     *
     * @var DriverStatistics
     */
    private $driver_statistics;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->driver_statistics = new DriverStatistics();
        
        $this->register_routes();
        
    }
    
    /**
     * Register AJAX routes for statistics endpoints
     */
    public function register_routes() {
        add_action( 'wp_ajax_get_driver_stats', array( $this, 'ajax_get_driver_stats' ) );
        add_action( 'wp_ajax_export_statistics', array( $this, 'ajax_export_statistics' ) );
    }
    
    /**
     * Check if user can view statistics for a driver
     * 
     * @param int|null $driver_id Optional driver ID to check access for
     * @return bool Whether user has access
     */
    public function can_view_statistics( $driver_id = null ) {
        $current_user_id = get_current_user_id();
        
        // Not logged in
        if ( !$current_user_id ) {
            return false;
        }
        
        // Admin can view all
        if ( current_user_can( 'administrator' ) ) {
            return true;
        }
        
        // Driver can only view their own stats
        if ( $driver_id && $driver_id != $current_user_id ) {
            return false;
        }
        
        // Check if current user is a driver
        $user_meta = get_userdata( $current_user_id );
        if ( $user_meta && in_array( 'driver', $user_meta->roles ) ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * AJAX handler for getting driver statistics
     */
    public function ajax_get_driver_stats() {
        // Verify nonce for security
        check_ajax_referer( 'driver_statistics_nonce', 'nonce' );
        
        $driver_ids = isset( $_POST['driver_ids'] ) ? $_POST['driver_ids'] : array();
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
        $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';
        $interval = isset( $_POST['interval'] ) ? sanitize_text_field( $_POST['interval'] ) : 'day';
        
        // Validate permissions
        foreach ( $driver_ids as $driver_id ) {
            if ( !$this->can_view_statistics( $driver_id ) ) {
                wp_send_json_error( array( 'message' => 'You do not have permission to view these statistics.' ) );
                wp_die();
            }
        }
        
        // Get statistics
        $stats = $this->driver_statistics->get_driver_stats( $driver_ids, $start_date, $end_date, $interval );

        // If comparing multiple drivers, get comparative stats
        $comparative_stats = null;
        if ( count( $driver_ids ) > 1 ) {
            $comparative_stats = $this->driver_statistics->get_comparative_statistics( $driver_ids, $start_date, $end_date );
        }
        wp_send_json_success( array(
            'stats' => $stats,
            'comparative' => $comparative_stats
        ) );
        wp_die();
    }
    
    /**
     * AJAX handler for exporting statistics
     */
    public function ajax_export_statistics() {
        // Verify nonce for security
        check_ajax_referer( 'driver_statistics_nonce', 'nonce' );
        
        $driver_ids = isset( $_POST['driver_ids'] ) ? $_POST['driver_ids'] : array();
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
        $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';
        $format = isset( $_POST['format'] ) ? sanitize_text_field( $_POST['format'] ) : 'csv';
        
        // Validate permissions
        foreach ( $driver_ids as $driver_id ) {
            if ( !$this->can_view_statistics( $driver_id ) ) {
                wp_send_json_error( array( 'message' => 'You do not have permission to export these statistics.' ) );
                wp_die();
            }
        }
        
        // Get statistics
        $stats = $this->driver_statistics->get_driver_stats( $driver_ids, $start_date, $end_date, 'day' );
        
        // Generate export file based on format
        $export_data = '';
        $content_type = '';
        $filename = 'driver-statistics-' . date( 'Y-m-d' );
        
        if ( $format === 'csv' ) {
            $export_data = $this->generate_csv_export( $stats, $driver_ids, $start_date, $end_date );
            $content_type = 'text/csv';
            $filename .= '.csv';
        } else if ( $format === 'pdf' ) {
            // For PDF generation, we'd normally use a library like TCPDF or similar
            // This is a placeholder - in a real implementation, you'd generate a PDF
            $export_data = json_encode( $stats );
            $content_type = 'application/json';
            $filename .= '.json';
        }
        
        // Return the export data
        wp_send_json_success( array(
            'data' => $export_data,
            'filename' => $filename,
            'content_type' => $content_type
        ) );
        wp_die();
    }
    
    /**
     * Generate CSV export
     * 
     * @param array $stats Statistics data
     * @param array $driver_ids Array of driver IDs
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return string CSV content
     */
    private function generate_csv_export( $stats, $driver_ids, $start_date, $end_date ) {
        $csv = "Driver,Date,Collections,Miles,Hours,Collections per Hour,Time per Mile\n";
        
        foreach ( $stats as $driver_id => $data ) {
            $driver_name = get_user_meta( $driver_id, 'first_name', true ) . ' ' . get_user_meta( $driver_id, 'last_name', true );
            
            foreach ( $data['intervals'] as $date => $interval_data ) {
                $collections_per_hour = $interval_data['hours'] > 0 ? $interval_data['collections'] / $interval_data['hours'] : 0;
                $time_per_mile = $interval_data['miles'] > 0 ? $interval_data['hours'] / $interval_data['miles'] : 0;
                
                $csv .= sprintf(
                    "%s,%s,%d,%.2f,%.2f,%.2f,%.2f\n",
                    $driver_name,
                    $date,
                    $interval_data['collections'],
                    $interval_data['miles'],
                    $interval_data['hours'],
                    $collections_per_hour,
                    $time_per_mile
                );
            }
        }
        
        return $csv;
    }
    

    /**
     * For use with shortcode rendering
     * 
     * @return string HTML content
     */
    public function render_statistics_page() {
        if (!$this->can_view_statistics()) {
            ob_start();
            include SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/access-denied.php';
            return ob_get_clean();
        }
        
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('administrator');
        
        // Get all drivers if admin, or just current user if driver
        $drivers = array();
        if ($is_admin) {
            $driver_users = get_users(array('role' => 'driver'));
            foreach ($driver_users as $driver) {
                $drivers[$driver->ID] = $driver->display_name;
            }
        } else {
            $current_user = wp_get_current_user();
            $drivers[$current_user_id] = $current_user->display_name;
        }
        
        // Default date range (last 30 days)
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        // Buffer the output
        ob_start();
        include SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/driver-statistics.php';
        return ob_get_clean();
    }
}

// Initialize the controller
new FrontendStatisticsController(); 