<?php 
namespace ScrapDriver\Admin;

class Collection {

    public function __construct() {

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