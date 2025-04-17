<?php
/**
 * Driver Statistics Class
 * 
 * Handles calculations and retrieval of driver performance data.
 */
class DriverStatistics {
    
    /**
     * Get driver statistics for specified drivers and date range
     * 
     * @param array|int $driver_ids Single driver ID or array of driver IDs
     * @param string $start_date Start date in Y-m-d format
     * @param string $end_date End date in Y-m-d format
     * @param string $interval Interval for grouping data (day, week, month)
     * @return array Structured statistical data
     */
    public function get_driver_stats( $driver_ids, $start_date, $end_date, $interval = 'day' ) {
        // Convert single driver ID to array for consistent processing
        if ( !is_array( $driver_ids ) ) {
            $driver_ids = array( $driver_ids );
        }

        $stats = array();
        
        foreach ( $driver_ids as $driver_id ) {
            // Get all shifts for this driver in the date range
            $shifts = $this->get_driver_shifts( $driver_id, $start_date, $end_date );
            // Initialize metrics
            $total_collections = 0;
            $total_miles = 0;
            $total_hours = 0;
            $intervals = array();
            
            // Process each shift
            foreach ( $shifts as $shift ) {
                $metrics = $this->calculate_shift_metrics( $shift['id'] );
                
                // Add to totals
                $total_collections += $metrics['collections'];
                $total_miles += $metrics['miles'];
                $total_hours += $metrics['hours'];
                
                // Group by interval (day, week, month)
                $interval_key = $this->get_interval_key( $shift['date'], $interval );
                
                if ( !isset( $intervals[$interval_key] ) ) {
                    $intervals[$interval_key] = array(
                        'collections' => 0,
                        'miles' => 0,
                        'hours' => 0
                    );
                }
                
                $intervals[$interval_key]['collections'] += $metrics['collections'];
                $intervals[$interval_key]['miles'] += $metrics['miles'];
                $intervals[$interval_key]['hours'] += $metrics['hours'];
            }
            
            // Calculate performance metrics with proper scaling
            // For collections per hour: (collections / hours) * 1 hour
            $collections_per_hour = $total_hours > 0 ? round( ($total_collections / $total_hours) * 1, 2 ) : 0;
            
            // For time per mile: (hours / miles) * 1 mile
            $time_per_mile = $total_miles > 0 ? round( ($total_hours / $total_miles) * 1, 2 ) : 0;
            
            // Format the statistics
            $stats[$driver_id] = array(
                'summary' => array(
                    'total_collections' => intval( $total_collections ),
                    'total_miles' => round( $total_miles, 2 ),
                    'total_hours' => round( $total_hours, 2 ),
                    'collections_per_hour' => $collections_per_hour,
                    'time_per_mile' => $time_per_mile
                ),
                'intervals' => $intervals
            );
        }
        
        return $stats;
    }
    
    /**
     * Get all shifts for a driver within a date range
     * 
     * @param int $driver_id Driver user ID
     * @param string $start_date Start date in Y-m-d format
     * @param string $end_date End date in Y-m-d format
     * @return array Array of shift details
     */
    public function get_driver_shifts( $driver_id, $start_date, $end_date ) {
        $args = array(
            'post_type' => 'sda-shift',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'shift_date',
                    'value' => array( $start_date, $end_date ),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            )
        );
        
        $query = new WP_Query( $args );
        $shifts = array();
        
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $shifts[] = array(
                    'id' => $post_id,
                    'date' => get_post_meta( $post_id, 'shift_date', true ),
                    'start_time' => get_post_meta( $post_id, 'shift_start', true ),
                    'end_time' => get_post_meta( $post_id, 'shift_end', true )
                );
            }
            wp_reset_postdata();
        }
        
        return $shifts;
    }
    
    /**
     * Calculate metrics for a specific shift
     * 
     * @param int $shift_id The shift post ID
     * @return array Metrics including collections, miles, hours, etc.
     */
    public function calculate_shift_metrics( $shift_id ) {
        // Get shift basic data
        $shift_date = get_post_meta( $shift_id, 'shift_date', true );
        $shift_start = get_post_meta( $shift_id, 'shift_start', true );
        $shift_end = get_post_meta( $shift_id, 'shift_end', true );
        $driver_id = get_post_meta( $shift_id, 'assigned_driver', true );
        
        // Get collections completed during this shift
        $args = array(
            'post_type' => 'sda-collection',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'collection_date',
                    'value' => $shift_date,
                    'compare' => '=',
                    'type' => 'DATE'
                ),
                array(
                    'key' => 'status',
                    'value' => 'Completed',
                    'compare' => '='
                )
            )
        );
        
        $query = new WP_Query( $args );
        $collections_count = $query->found_posts;
        
        // Get and convert distance
        $miles = get_post_meta( $shift_id, 'total_distance', true );
        $miles = !empty( $miles ) ? floatval( $miles ) : 0;
        
        // Get and convert time from seconds to hours
        $total_time = get_post_meta( $shift_id, 'total_time', true );
        $hours = !empty( $total_time ) ? floatval( $total_time ) / 3600 : 0; // Convert seconds to hours
        
        return array(
            'collections' => $collections_count,
            'miles' => $miles,
            'hours' => $hours
        );
    }
    
    /**
     * Get comparative statistics for multiple drivers
     * 
     * @param array $driver_ids Array of driver IDs to compare
     * @param string $start_date Start date in Y-m-d format
     * @param string $end_date End date in Y-m-d format
     * @return array Comparative statistics data
     */
    public function get_comparative_statistics( $driver_ids, $start_date, $end_date ) {
        $all_stats = $this->get_driver_stats( $driver_ids, $start_date, $end_date );
        $comparative_data = array(
            'collections_per_hour' => array(),
            'time_per_mile' => array(),
            'total_collections' => array(),
            'total_miles' => array()
        );
        
        foreach ( $all_stats as $driver_id => $stats ) {
            $user = get_user_by('id', $driver_id);
            $driver_name = $user->display_name;
            
            $comparative_data['collections_per_hour'][$driver_name] = $stats['summary']['collections_per_hour'];
            $comparative_data['time_per_mile'][$driver_name] = $stats['summary']['time_per_mile'];
            $comparative_data['total_collections'][$driver_name] = $stats['summary']['total_collections'];
            $comparative_data['total_miles'][$driver_name] = $stats['summary']['total_miles'];
        }
        
        return $comparative_data;
    }
    
    /**
     * Get interval key based on date and interval type
     * 
     * @param string $date Date in Y-m-d format
     * @param string $interval Interval type (day, week, month)
     * @return string Formatted interval key
     */
    private function get_interval_key( $date, $interval ) {
        $timestamp = strtotime( $date );
        
        switch ( $interval ) {
            case 'week':
                return date( 'Y-W', $timestamp ); // Year and week number
                
            case 'month':
                return date( 'Y-m', $timestamp ); // Year and month
                
            case 'day':
            default:
                return date( 'Y-m-d', $timestamp ); // Full date
        }
    }
} 