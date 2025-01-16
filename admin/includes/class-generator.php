<?php
namespace ScrapDriver\Admin;

trait CollectionProcessor {
    private function process_collection($collection) {
        // Parse customer info
        $customer_info = !empty($collection['customer_info']) ? json_decode($collection['customer_info'], true) : array();
        
        // Get customer name from either direct data or customer_info
        $customer_name = !empty($collection['customer_name']) ? $collection['customer_name'] : (
            isset($customer_info['first_name']) ? 
            trim($customer_info['first_name'] . ' ' . $customer_info['last_name']) : 
            'Unknown Customer'
        );

        // Check if collection already exists based on car plate
        $existing_posts = get_posts(array(
            'post_type' => 'sda-collection',
            'meta_key' => 'vehicle_info_plate',
            'meta_value' => $collection['car_plate'],
            'posts_per_page' => 1
        ));

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

        // Insert or update post
        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            // Update all available fields
            $field_mappings = array(
                'status_id' => 'status',
                'collection_date' => 'collection_date',
                'collection_driver' => 'assigned_driver',
                'staff_notes' => 'admin_notes',
                'admin_notes' => 'admin_notes',
                'driver_notes' => 'driver_notes',
                'car_make' => 'vehicle_info_make',
                'car_model' => 'vehicle_info_model',
                'car_year' => 'vehicle_info_year',
                'car_plate' => 'vehicle_info_plate',
            );

            foreach ($field_mappings as $source => $target) {
                if (isset($collection[$source])) {
                    update_field($target, $collection[$source], $post_id);
                }
            }

            // Update customer information
            if (!empty($customer_name)) {
                update_field('customer_info_name', $customer_name, $post_id);
            }
            if (!empty($customer_info['phone'])) {
                update_field('customer_info_phone', $customer_info['phone'], $post_id);
            }
            if (!empty($customer_info['address'])) {
                update_field('customer_info_address', $customer_info['address'], $post_id);
            }

            // Process driver photos if available
            if (!empty($collection['driver_photos'])) {
                $photos = json_decode($collection['driver_photos'], true);
                if (is_array($photos)) {
                    // Clear existing photos first
                    delete_field('driver_uploaded_photos', $post_id);
                    
                    foreach ($photos as $photo_url) {
                        add_row('driver_uploaded_photos', array(
                            'photo' => $photo_url
                        ), $post_id);
                    }
                }
            }
        }

        return $post_id;
    }
}

class Generator {
    use CollectionProcessor;
    private $api_endpoint = SCRAP_DRIVER_API_URL . 'wp-json/vrmlookup/v1/get_all_data';

    public function __construct() {
        // Hook into the manual sync action
        add_action('admin_init', array($this, 'handle_manual_sync'));
        
        // Add hourly cron job
        add_action('init', array($this, 'schedule_sync'));
        add_action('sda_hourly_sync', array($this, 'sync_collections'));


    }

    public function schedule_sync() {
        if (!wp_next_scheduled('sda_hourly_sync')) {
            wp_schedule_event(time(), 'hourly', 'sda_hourly_sync');
        }
    }

    public function handle_manual_sync() {
        if (isset($_GET['sda-manual-sync-collections']) && current_user_can('manage_options')) {
            $this->sync_collections();
            wp_redirect(admin_url('edit.php?post_type=sda-collection&sync=success'));
            exit;
        }
    }

    public function sync_collections() {
        $page = 1;
        $total_pages = 1;

        do {
            $response = wp_remote_get(add_query_arg(array(
                'collection_date' => date('Y-m-d'),
                'page' => $page
            ), $this->api_endpoint));

            if (is_wp_error($response)) {
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!isset($data['data']) || !is_array($data['data'])) {
                continue;
            }

            // Get total pages from meta data
            if (isset($data['meta']['total_pages'])) {
                $total_pages = $data['meta']['total_pages'];
            }

            foreach ($data['data'] as $collection) {
                if (!empty($collection['collection_date']) && strtotime($collection['collection_date']) !== false) {
                    $this->process_collection($collection);
                }
            }

            $page++;
        } while ($page <= $total_pages);

        return true;
    }

}

new Generator(); 