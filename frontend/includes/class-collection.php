<?php
namespace ScrapDriver\Frontend;

class SDA_Collection {
    
    public function __construct() {
        // Add AJAX handlers
        add_action('wp_ajax_complete_collection', array($this, 'handle_complete_collection'));
    }

    public function handle_complete_collection() {
        check_ajax_referer('complete_collection', 'complete_collection_nonce');

        $collection_id = isset($_POST['collection_id']) ? intval($_POST['collection_id']) : 0;
        $current_user_id = get_current_user_id();

        if (!$collection_id) {
            wp_send_json_error(array('message' => 'Invalid collection ID'));
        }

        // Verify user has permission
        $assigned_driver_id = get_field('assigned_driver', $collection_id);
        if (!current_user_can('manage_options') && $assigned_driver_id != $current_user_id) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Update collection status
        update_post_meta($collection_id, '_collection_status', 'completed');

        // Trigger collection completion action
        do_action('sda_collection_completed', $collection_id, $current_user_id);

        // Get next collection in route
        $next_collection = get_posts(array(
            'post_type' => 'sda-collection',
            'meta_query' => array(
                array(
                    'key' => 'assigned_driver',
                    'value' => $current_user_id
                ),
                array(
                    'key' => '_collection_status',
                    'value' => 'completed',
                    'compare' => '!='
                )
            ),
            'meta_key' => 'route_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'posts_per_page' => 1
        ));

        $redirect_url = !empty($next_collection) 
            ? get_permalink($next_collection[0]->ID)
            : home_url();

        wp_send_json_success(array(
            'message' => 'Collection completed successfully',
            'redirect_url' => $redirect_url
        ));
    }

}

new SDA_Collection(); 