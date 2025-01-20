<?php
namespace ScrapDriver\Frontend;

class Collection {
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
} 