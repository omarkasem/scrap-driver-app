<?php
namespace ScrapDriver\Frontend;

class Collection {

    public function __construct() {
        add_action('init', array($this, 'complete_collection'), 10, 2);
    }

    public function complete_collection() {
        if (isset($_POST['complete_collection']) && wp_verify_nonce($_POST['complete_collection_nonce'], 'complete_collection')) {
            $collection_id = $_POST['collection_id'];
            $driver_id = get_current_user_id();
            do_action('sda_collection_completed', $collection_id, $driver_id);
        }
    }

    /**
     * Get the order number for collections based on date and time
     *
     * @param array $collection_ids Array of collection post IDs
     * @return array Associative array of collection_id => order_number
     */
    public static function get_collections_order($collection_ids) {
        if (empty($collection_ids)) {
            return [];
        }

        $collections_datetime = [];
        
        foreach ($collection_ids as $collection_id) {
            $date = get_field('collection_date', $collection_id);
            $time = get_field('collection_start_time', $collection_id);
            
            // Create a datetime string for sorting
            $datetime_str = $date . ' ' . $time;
            $collections_datetime[$collection_id] = strtotime($datetime_str);
        }

        // Sort by datetime
        asort($collections_datetime);
        
        // Assign order numbers
        $order = [];
        $counter = 1;
        foreach ($collections_datetime as $collection_id => $timestamp) {
            $order[$collection_id] = $counter++;
        }
        
        return $order;
    }

    /**
     * Check if a collection can be started based on route order
     *
     * @param int $collection_id The collection ID to check
     * @param int $driver_id The driver ID
     * @return bool|string True if can start, error message if cannot
     */
    public static function can_start_collection($collection_id, $driver_id) {
        // Get all collections for this driver
        $driver_collections = get_posts(array(
            'post_type' => 'sda-collection',
            'meta_query' => array(
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id
                ),
                array(
                    'key' => 'status',
                    'value' => 'Completed',
                    'compare' => '!='
                )
            ),
            'posts_per_page' => -1
        ));

        if (empty($driver_collections)) {
            return true;
        }

        // Get order for all collections
        $collection_ids = wp_list_pluck($driver_collections, 'ID');
        $orders = self::get_collections_order($collection_ids);

        // Get current collection's order
        $current_order = $orders[$collection_id] ?? 0;

        // Check for any incomplete collections with lower order numbers
        foreach ($driver_collections as $collection) {
            $status = get_field('status', $collection->ID);
            if ($collection->ID !== $collection_id && 
                $orders[$collection->ID] < $current_order && 
                $status !== 'Completed') {
                return '<h4>You must follow the order of collections. <br>Complete this <a href="' . get_the_permalink($collection->ID) . '">Collection</a> first</h4>';
            }
        }

        // Check if today is the collection date
        $collection_date = get_field('collection_date', $collection_id);
        $today = date('Y-m-d');
        if($collection_date !== $today) {
            //format January 28, 2025
            $formatted_collection_date = date('F d, Y', strtotime($collection_date));
            return '<h4>You must start this collection on the collection date. <br>Today is ' . date('F d, Y', strtotime($today)) . ' and the collection date is ' . $formatted_collection_date . '</h4>';
        }

        return true;
    }

    /**
     * Start a collection
     *
     * @param int $collection_id The collection ID
     * @return bool Whether the collection was started successfully
     */
    public static function start_collection($collection_id) {
        update_field('status', 'Collection in Progress', $collection_id);
        update_user_meta(get_current_user_id(), 'collection_started', $collection_id);
        
        do_action('acf/save_post', $collection_id);
        return true;
    }
} 

new Collection();