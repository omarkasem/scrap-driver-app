<?php 
namespace ScrapDriver\Admin;
use ScrapDriver\Admin\Shift;
class Collection {

    public function __construct() {
        // Add collection completion tracking
        add_action('sda_collection_completed', array($this, 'track_collection_completion'), 10, 2);

        add_action('acf/save_post', array($this, 'save_collection'), 20, 1);

        // Add shift management
        add_action( 'acf/save_post', array( $this, 'handle_shift_management' ), 20, 1 );
        
        // Add action for collection deletion to clean up shifts
        add_action( 'before_delete_post', array( $this, 'handle_collection_deletion' ), 10, 1 );
    }

    public static function can_view_collection($collection_id) {
        $current_user_id = get_current_user_id();
        $assigned_driver_id = get_field('assigned_driver', $collection_id);

        // Admin can always view
        if(current_user_can('manage_options')) {
            return true;
        }

        // Driver can view if assigned
        if($assigned_driver_id && $assigned_driver_id === $current_user_id) {
            return true;
        }

        return false;
    }

    public static function can_edit_collection($collection_id) {
        $current_user_id = get_current_user_id();
        $assigned_driver_id = get_field('assigned_driver', $collection_id);
        $collection_date = get_field('collection_date', $collection_id);
        $today = date('Y-m-d');

        // Admin can always edit
        if(current_user_can('manage_options')) {
            return true;
        }

        // Driver can edit if:
        // 1. They are assigned to the collection
        // 2. It's the collection date
        if($assigned_driver_id && $assigned_driver_id === $current_user_id && $collection_date === $today) {
            return true;
        }

        return false;
    }

    public function save_collection($post_id) {
        if (get_post_type($post_id) !== 'sda-collection') {
            return;
        }

        $current_shift_id = Shift::get_current_shift_id();
        $shift_collections = get_field('shift_collections', $current_shift_id);

        if(!is_array($shift_collections)) {
            $shift_collections = array();
        }

        if(get_field('status', $post_id) === 'Completed') {
            $shift_collections[] = $post_id;
        }else{
            $key = array_search($post_id, $shift_collections);
            if ($key !== false) {
                unset($shift_collections[$key]);
            }
        }

        update_field('shift_collections', $shift_collections, $current_shift_id);

        // Manage shift for collection
        $this->manage_shift($post_id);
    }

    /**
     * Track collection completion during shift
     */
    public function track_collection_completion($collection_id, $driver_id) {

        update_field('status', 'Completed', $collection_id);
        $current_shift_id = Shift::get_current_shift_id();
        $shift_collections = get_field('shift_collections', $current_shift_id);
        if(!is_array($shift_collections)) {
            $shift_collections = array();
        }
        $shift_collections[] = $collection_id;
        update_field('shift_collections', $shift_collections, $current_shift_id);

    }


    public static function get_collection_number_completed_by_driver($shift_id = 0){
        $driver = get_current_user_id();

        // get current shift collection_date and compare it to collection_date of all collections for current driver
        $shift_collection_date = date('Y-m-d', strtotime(get_field('shift_date', $shift_id)));

        $args = array(
            'post_type' => 'sda-collection',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver,
                    'compare' => '='
                ),
                array(
                    'key' => 'status', 
                    'value' => 'Completed',
                    'compare' => '='
                ),
                array(
                    'key' => 'collection_date',
                    'value' => $shift_collection_date,
                    'compare' => '=',
                    'type' => 'DATE'
                )
            ),
        );
        $collections = get_posts($args);
        return count($collections);
    }

    /**
     * Manage shift for collection
     */
    public function manage_shift( $collection_id ) {
        // Get collection details
        $driver_id = get_field( 'assigned_driver', $collection_id );
        $collection_date = get_field( 'collection_date', $collection_id );
        
        if ( !$driver_id || !$collection_date ) {
            return;
        }

        // Format date to match shift date format (Ymd)
        $shift_date = date( 'Ymd', strtotime( $collection_date ) );

        // Check for existing shift
        $existing_shift = get_posts( array(
            'post_type' => 'sda-shift',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id
                ),
                array(
                    'key' => 'shift_date',
                    'value' => $shift_date
                )
            ),
            'posts_per_page' => 1
        ) );

        if ( empty( $existing_shift ) ) {
            // Create new shift
            $driver = get_userdata( $driver_id );
            $shift_title = sprintf( 'Shift By %s on %s',
                $driver->display_name,
                date( 'Y-m-d', strtotime( $collection_date ) )
            );

            $shift_id = wp_insert_post( array(
                'post_type' => 'sda-shift',
                'post_title' => $shift_title,
                'post_status' => 'publish'
            ) );

            if ( !is_wp_error( $shift_id ) ) {
                // Set shift metadata
                update_field( 'assigned_driver', $driver_id, $shift_id );
                update_field( 'shift_date', $shift_date, $shift_id );
                
                // Set default start and end times for the shift
                update_field( 'start_time', '08:00:00', $shift_id );
                update_field( 'end_time', '17:00:00', $shift_id );
            }
        }
    }

    /**
     * Clean up shifts without collections
     */
    public function cleanup_empty_shifts( $driver_id = null, $collection_date = null ) {
        // If specific driver and date are provided, only check that shift
        if ( $driver_id && $collection_date ) {
            $this->cleanup_specific_shift( $driver_id, $collection_date );
            return;
        }
        
        // Otherwise, check all shifts for empty ones
        $args = array(
            'post_type' => 'sda-shift',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $shifts = get_posts( $args );
        
        foreach ( $shifts as $shift_id ) {
            $shift_driver = get_field( 'assigned_driver', $shift_id );
            $shift_date = get_field( 'shift_date', $shift_id );
            
            if ( $shift_driver && $shift_date ) {
                // Format date for query
                $formatted_date = date( 'Y-m-d', strtotime( $shift_date ) );
                
                // Check for any collections on this date for this driver
                $collections = get_posts( array(
                    'post_type' => 'sda-collection',
                    'meta_query' => array(
                        'relation' => 'AND',
                        array(
                            'key' => 'assigned_driver',
                            'value' => $shift_driver
                        ),
                        array(
                            'key' => 'collection_date',
                            'value' => $formatted_date,
                            'compare' => '=',
                            'type' => 'DATE'
                        )
                    ),
                    'posts_per_page' => 1
                ) );

                // If no collections exist, delete the shift
                if ( empty( $collections ) ) {
                    wp_delete_post( $shift_id, true );
                }
            }
        }
    }

    /**
     * Clean up a specific shift if empty
     */
    private function cleanup_specific_shift( $driver_id, $collection_date ) {
        // Format date
        $shift_date = date( 'Ymd', strtotime( $collection_date ) );
        
        // Get shift for this date/driver
        $shift = get_posts( array(
            'post_type' => 'sda-shift',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id
                ),
                array(
                    'key' => 'shift_date',
                    'value' => $shift_date
                )
            ),
            'posts_per_page' => 1
        ) );

        if ( !empty( $shift ) ) {
            // Check for any collections on this date for this driver
            $collections = get_posts( array(
                'post_type' => 'sda-collection',
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'assigned_driver',
                        'value' => $driver_id
                    ),
                    array(
                        'key' => 'collection_date',
                        'value' => date( 'Y-m-d', strtotime( $collection_date ) ),
                        'compare' => '=',
                        'type' => 'DATE'
                    )
                ),
                'posts_per_page' => 1
            ) );

            // If no collections exist, delete the shift
            if ( empty( $collections ) ) {
                wp_delete_post( $shift[0]->ID, true );
            }
        }
    }

    /**
     * Handle shift management when collection is saved
     */
    public function handle_shift_management( $post_id ) {
        // Only process collections
        if ( get_post_type( $post_id ) !== 'sda-collection' ) {
            return;
        }

        // Get old and new values
        $old_driver_id = get_post_meta( $post_id, 'assigned_driver', true );
        $old_date = get_post_meta( $post_id, 'collection_date', true );
        
        $new_driver_id = isset( $_POST['acf']['field_assigned_driver'] ) ? 
            $_POST['acf']['field_assigned_driver'] : 
            get_field( 'assigned_driver', $post_id );
        
        $new_date = isset( $_POST['acf']['field_collection_date'] ) ? 
            $_POST['acf']['field_collection_date'] : 
            get_field( 'collection_date', $post_id );

        // Create/update shift for new assignment
        if ( $new_driver_id && $new_date ) {
            $this->manage_shift( $post_id );
        }

        // Clean up old shift if driver or date changed
        if ( $old_driver_id && $old_date && 
             ( $old_driver_id !== $new_driver_id || $old_date !== $new_date ) ) {
            $this->cleanup_specific_shift( $old_driver_id, $old_date );
        }
        
        // Clean up all empty shifts
        $this->cleanup_empty_shifts();
        
        // Store the current values as post meta for future reference
        update_post_meta( $post_id, 'assigned_driver', $new_driver_id );
        update_post_meta( $post_id, 'collection_date', $new_date );
    }
    
    /**
     * Handle collection deletion
     * Clean up shifts if needed when a collection is deleted
     */
    public function handle_collection_deletion( $post_id ) {
        // Only process collections
        if ( get_post_type( $post_id ) !== 'sda-collection' ) {
            return;
        }
        
        // Get collection details before it's deleted
        $driver_id = get_field( 'assigned_driver', $post_id );
        $collection_date = get_field( 'collection_date', $post_id );
        
        if ( $driver_id && $collection_date ) {
            // Check if this is the last collection for this driver/date
            $this->cleanup_empty_shifts( $driver_id, $collection_date );
        }
    }
}

new Collection();