<?php
namespace ScrapDriver\Admin;

use WP_REST_Response;
use WP_Error;

class Api {
    use CollectionProcessor;
    
    private $secure_key = 'a7f9e3b2c1d5h8j6k4m0p2q9r7s5t3u1v8w6x4y2z0';
    private $sync_endpoint = SCRAP_DRIVER_API_URL . 'wp-json/vrmlookup/v1/sync_collection_data';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('acf/save_post', array($this, 'sync_collection_to_api'), 20);
    }

    public function register_routes() {
        register_rest_route('scrap-driver/v1', '/update-collection', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_collection'),
            'permission_callback' => '__return_true'
        ));
    }

    public function sync_collection_to_api($post_id) {
        // Only proceed if this is a collection post type
        if (get_post_type($post_id) !== 'sda-collection') {
            return;
        }

        // Don't sync if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Prepare the data for the API
        $api_data = array(
            'status_id' => get_field('status', $post_id),
            'collection_date' => get_field('collection_date', $post_id),
            'collection_driver' => get_field('assigned_driver', $post_id),
            'admin_notes' => get_field('admin_notes', $post_id),
            'driver_notes' => get_field('driver_notes', $post_id),
            'car_plate' => get_field('vehicle_info_plate', $post_id),
            'customer_name' => get_field('customer_info_name', $post_id),
            'customer_phone' => get_field('customer_info_phone', $post_id),
            'customer_address' => get_field('customer_info_address', $post_id),
            'modified_at' => current_time('mysql')
        );

        // Process driver photos
        $driver_photos = $this->get_driver_photos($post_id);
        if (!empty($driver_photos)) {
            $api_data['driver_photos'] = json_encode($driver_photos);
        }

        // Filter out null or empty values
        $api_data = array_filter($api_data);

        // Make the API request
        $response = wp_remote_post($this->sync_endpoint, array(
            'body' => [
                'data' => $api_data,
                'secure_key' => $this->secure_key
            ],
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        if (is_wp_error($response)) {
            error_log('Collection sync failed: ' . $response->get_error_message());
        }
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

        // Process the collection using shared method
        $post_id = $this->process_collection($data);

        if (!$post_id) {
            return new WP_Error('processing_failed', 'Failed to process collection', array('status' => 500));
        }

        return new WP_REST_Response(
            array(
                'status' => 'success',
                'message' => 'Collection processed successfully',
                'post_id' => $post_id
            ), 
            200
        );
    }

    private function get_driver_photos($post_id) {
        $driver_photos = array();
        if (have_rows('driver_uploaded_photos', $post_id)) {
            while (have_rows('driver_uploaded_photos', $post_id)) {
                the_row();
                $photo = get_sub_field('photo');
                if ($photo) {
                    $driver_photos[] = $photo['url'];
                }
            }
        }
        return $driver_photos;
    }
}

new Api();