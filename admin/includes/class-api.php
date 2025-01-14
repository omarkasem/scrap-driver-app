<?php
namespace ScrapDriver\Admin;

use WP_REST_Response;
use WP_Error;

class Api {
    private $secure_key = 'a7f9e3b2c1d5h8j6k4m0p2q9r7s5t3u1v8w6x4y2z0';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('scrap-driver/v1', '/update-collection', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_collection'),
            'permission_callback' => '__return_true'
        ));
    }

    public function update_collection($request) {
        $data = $request->get_param('data');
        $secure_key = $request->get_param('secure_key');

        // Validate request
        if (empty($data) || empty($secure_key)) {
            return new WP_Error('invalid_request', 'Missing required parameters', array('status' => 400));
        }

        if ($secure_key !== $this->secure_key) {
            return new WP_Error('invalid_key', 'Invalid secure key', array('status' => 401));
        }

        // Find the collection post by plate number
        $args = array(
            'post_type' => 'sda-collection',
            'meta_query' => array(
                array(
                    'key' => 'vehicle_info_plate',
                    'value' => $data['car_plate'],
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        );

        $collections = get_posts($args);

        if (empty($collections)) {
            return new WP_Error('not_found', 'Collection not found', array('status' => 404));
        }

        $post_id = $collections[0]->ID;

        // Update the collection data
        $updates = array(
            'status' => $data['status_id'],
            'collection_date' => $data['collection_date'],
            'assigned_driver' => $data['collection_driver'],
            'admin_notes' => $data['admin_notes'],
            'driver_notes' => $data['driver_notes']
        );

        foreach ($updates as $key => $value) {
            update_field($key, $value, $post_id);
        }

        return new WP_REST_Response(
            array(
                'status' => 'success',
                'message' => 'Collection updated successfully',
                'post_id' => $post_id
            ), 
            200
        );
    }

}

new Api();