<?php 
namespace ScrapDriver\Admin;
use ScrapDriver\Admin\Shift;
class Collection {

    public function __construct() {
        // Add collection completion tracking
        add_action('sda_collection_completed', array($this, 'track_collection_completion'), 10, 2);

        add_action('acf/save_post', array($this, 'save_collection'), 20, 1);

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
                    'value' => $driver
                ),
                array(
                    'key' => 'status', 
                    'value' => 'Completed'
                ),
                array(
                    'key' => 'collection_date',
                    'value' => $shift_collection_date
                )
            ),
        );
        $collections = get_posts($args);
        return count($collections);
    }
}

new Collection();