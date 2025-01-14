<?php
namespace ScrapDriver\Admin;

class Generator {
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
                $this->process_collection($collection);
            }

            $page++;
        } while ($page <= $total_pages);

        return true;
    }

    private function process_collection($collection) {
        // Parse customer info
        $customer_info = !empty($collection['customer_info']) ? json_decode($collection['customer_info'], true) : array();
        
        // Parse car info
        $car_info = !empty($collection['car_info']) ? json_decode($collection['car_info'], true) : array();
        
        // Check if collection already exists based on car plate
        $existing_posts = get_posts(array(
            'post_type' => 'sda-collection',
            'meta_key' => 'vehicle_info_plate',
            'meta_value' => $collection['car_plate'],
            'posts_per_page' => 1
        ));

        $customer_name = isset($customer_info['first_name']) ? 
            trim($customer_info['first_name'] . ' ' . $customer_info['last_name']) : 
            'Unknown Customer';

        $post_data = array(
            'post_type' => 'sda-collection',
            'post_status' => 'publish',
            'post_title' => sprintf(
                '%s - %s %s (%s)',
                $customer_name,
                $collection['car_make'],
                $collection['car_model'],
                $collection['car_plate']
            ),
        );

        if (!empty($existing_posts)) {
            $post_data['ID'] = $existing_posts[0]->ID;
        }

        // Insert or update post
        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            // Update ACF fields
            update_field('status', 'pending', $post_id);
            update_field('customer_name', $customer_name, $post_id);
            update_field('phone', isset($customer_info['phone']) ? $customer_info['phone'] : '', $post_id);
            update_field('address', isset($customer_info['address']) ? $customer_info['address'] : '', $post_id);
            
            // Vehicle information group
            update_field('vehicle_info_make', $collection['car_make'], $post_id);
            update_field('vehicle_info_model', $collection['car_model'], $post_id);
            update_field('vehicle_info_year', $collection['car_year'], $post_id);
            update_field('vehicle_info_plate', $collection['car_plate'], $post_id);
            
            // Collection date
            if (!empty($collection['collection_date']) && $collection['collection_date'] !== '0000-00-00') {
                update_field('collection_date', $collection['collection_date'], $post_id);
            }

            // Additional notes
            if (!empty($collection['staff_notes'])) {
                update_field('admin_notes', $collection['staff_notes'], $post_id);
            }
        }
    }
}

new Generator(); 