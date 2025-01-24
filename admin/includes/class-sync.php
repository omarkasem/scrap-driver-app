<?php
namespace ScrapDriver\Admin;

use WP_REST_Response;
use WP_Error;

class Sync {
    private $secure_key = 'a7f9e3b2c1d5h8j6k4m0p2q9r7s5t3u1v8w6x4y2z0';
    private $vrm_api_base = SCRAP_DRIVER_API_URL . 'wp-json/vrmlookup/v1/';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('acf/save_post', array($this, 'sync_to_vrm'), 20);
        add_action('init', array($this, 'schedule_sync'));
        add_action('sda_hourly_sync', array($this, 'sync_from_vrm'));
        add_action('admin_init', array($this, 'handle_manual_sync'));
    }

    public function register_routes() {
        register_rest_route('scrap-driver/v1', '/update-collection', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_vrm_update'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Process and save collection data
     */
    private function process_collection($collection) {
        // Get customer name
        $customer_info = !empty($collection['customer_info']) ? json_decode($collection['customer_info'], true) : array();
        $customer_name = !empty($collection['customer_name']) ? $collection['customer_name'] : (
            isset($customer_info['first_name']) ? 
            trim($customer_info['first_name'] . ' ' . $customer_info['last_name']) : 
            'Unknown Customer'
        );

        // Check for existing collection
        $existing_posts = get_posts(array(
            'post_type' => 'sda-collection',
            'meta_key' => 'vehicle_info_plate',
            'meta_value' => $collection['car_plate'],
            'posts_per_page' => 1
        ));

        // Prepare post data
        $post_data = array(
            'post_type' => 'sda-collection',
            'post_status' => 'publish',
            'post_title' => sprintf(
                '%s - %s %s (%s)',
                $customer_name,
                $collection['car_make'] ?? '',
                $collection['car_model'] ?? '',
                $collection['car_plate']
            ),
        );

        if (!empty($existing_posts)) {
            $post_data['ID'] = $existing_posts[0]->ID;
        }

        // check if collection date is not empty and it's valid date
        if (empty($collection['collection_date']) || $collection['collection_date'] === '0000-00-00' || strtotime($collection['collection_date']) < strtotime(date('Y-m-d'))) {
            return new WP_Error('invalid_date', 'Invalid collection date', array('status' => 400));
        }

        // Insert or update post
        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            // Update fields
            $fields = array(
                'status_id' => 'status',
                'collection_date' => 'collection_date',
                'collection_driver' => 'assigned_driver',
                'admin_notes' => 'admin_notes',
                'driver_notes' => 'driver_notes',
                'car_make' => 'vehicle_info_make',
                'car_model' => 'vehicle_info_model',
                'car_year' => 'vehicle_info_year',
                'car_plate' => 'vehicle_info_plate',
            );

            $statuses = sda_get_statuses_ids();
            $collection['status_id'] = $statuses[$collection['status_id']];

            foreach ($fields as $source => $target) {
                if (isset($collection[$source])) {
                    update_field($target, $collection[$source], $post_id);
                }
            }

            // Update customer info
            if (!empty($customer_name)) {
                update_field('customer_info_name', $customer_name, $post_id);
            }
            if (!empty($customer_info['phone'])) {
                update_field('customer_info_phone', $customer_info['phone'], $post_id);
            }
            if (!empty($customer_info['address'])) {
                update_field('customer_info_address', $customer_info['address'], $post_id);
            }
            if (!empty($customer_info['postcode'])) {
                update_field('customer_info_postcode', $customer_info['postcode'], $post_id);
            }

            // Process photos
            if (!empty($collection['driver_photos'])) {
                $photos = json_decode($collection['driver_photos'], true);
                if (is_array($photos)) {
                    delete_field('driver_uploaded_photos', $post_id);
                    foreach ($photos as $photo_url) {
                        add_row('driver_uploaded_photos', array(
                            'photo' => $photo_url
                        ), $post_id);
                    }
                }
            }

            // Set default start and end times if not already set
            $start_time = get_post_meta($post_id, 'collection_start_time', true);
            $end_time = get_post_meta($post_id, 'collection_end_time', true);

            if (empty($start_time)) {
                update_post_meta($post_id, 'collection_start_time', '08:00:00');
            }
            if (empty($end_time)) {
                update_post_meta($post_id, 'collection_end_time', '09:00:00');
            }

            // If times are provided in the collection data, update them
            if (!empty($collection['start_time'])) {
                update_post_meta($post_id, 'collection_start_time', $collection['start_time']);
            }
            if (!empty($collection['end_time'])) {
                update_post_meta($post_id, 'collection_end_time', $collection['end_time']);
            }
        }

        return $post_id;
    }

    public function sync_from_vrm() {
        $page = 1;
        $total_pages = 1;

        do {
            $response = wp_remote_get(add_query_arg(array(
                'collection_date' => date('Y-m-d'),
                'page' => $page
            ), $this->vrm_api_base . 'get_all_data'));

            if (is_wp_error($response)) {
                error_log('VRM sync failed: ' . $response->get_error_message());
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!empty($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $collection) {
                    if (!empty($collection['collection_date']) && strtotime($collection['collection_date']) !== false) {
                        $this->process_collection($collection);
                    }
                }

                if (isset($data['meta']['total_pages'])) {
                    $total_pages = $data['meta']['total_pages'];
                }
            }

            $page++;
        } while ($page <= $total_pages);

        return true;
    }

    public function sync_to_vrm($post_id) {
        if (get_post_type($post_id) !== 'sda-collection' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        $statuses = array_flip(sda_get_statuses_ids());
        $status_id = $statuses[get_field('status', $post_id)];

        $api_data = array(
            'status_id' => $status_id,
            'collection_date' => get_field('collection_date', $post_id),
            'collection_driver' => get_field('assigned_driver', $post_id),
            'admin_notes' => get_field('admin_notes', $post_id),
            'driver_notes' => get_field('driver_notes', $post_id),
            'modified_at' => current_time('mysql')
        );

        $driver_photos = $this->get_driver_photos($post_id);
        if (!empty($driver_photos)) {
            $api_data['driver_photos'] = json_encode($driver_photos);
        }

        $api_data = array_filter($api_data);

        $response = wp_remote_post($this->vrm_api_base . 'sync_collection_data', array(
            'body' => [
                'data' => $api_data,
                'secure_key' => $this->secure_key
            ],
            'headers' => array('Content-Type' => 'application/x-www-form-urlencoded')
        ));

        if (is_wp_error($response)) {
            error_log('VRM sync failed: ' . $response->get_error_message());
        }
    }

    public function handle_vrm_update($request) {
        $data = $request->get_param('data');
        $secure_key = $request->get_param('secure_key');

        if (empty($data) || empty($secure_key)) {
            return new WP_Error('invalid_request', 'Missing required parameters', array('status' => 400));
        }

        if ($secure_key !== $this->secure_key) {
            return new WP_Error('invalid_key', 'Invalid secure key', array('status' => 401));
        }

        $post_id = $this->process_collection($data);

        return $post_id ? 
            new WP_REST_Response(
                array(
                    'status' => 'success',
                    'message' => 'Collection processed successfully',
                    'post_id' => $post_id
                ), 
                200
            ) : 
            new WP_Error('processing_failed', 'Failed to process collection', array('status' => 500));
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

    public function handle_manual_sync() {
        if (isset($_GET['sda-manual-sync-collections']) && current_user_can('manage_options')) {
            $this->sync_from_vrm();
            wp_redirect(admin_url('edit.php?post_type=sda-collection&sync=success'));
            exit;
        }
    }

    public function schedule_sync() {
        if (!wp_next_scheduled('sda_hourly_sync')) {
            wp_schedule_event(time(), 'hourly', 'sda_hourly_sync');
        }
    }
}

new Sync(); 