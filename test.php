<?php
if (!defined('ABSPATH')) {
    exit;
}

class Vrm_Lookup_API{
    public function __construct(){
        $this->add_hooks();
    }

    public function add_hooks(){

        add_action('rest_api_init', array($this,'register_shortcode_endpoint'));

    }

    // Function to register the REST API endpoint
    function register_shortcode_endpoint() {
        register_rest_route('vrmlookup/v1', '/setup_options', array(
            'methods' => 'GET',
            'callback' => array($this,'setup_options_request'),
        ));

        register_rest_route('vrmlookup/v1', '/add_car_from_others', array(
            'methods' => 'POST',
            'callback' => array($this,'add_car_from_others'),
        ));

        register_rest_route('vrmlookup/v1', '/check_car_exists', array(
            'methods' => 'POST',
            'callback' => array($this,'check_car_exists'),
        ));

        register_rest_route('vrmlookup/v1', '/insert_customer_data_from_others', array(
            'methods' => 'POST',
            'callback' => array($this,'insert_customer_data_from_others'),
        ));


        register_rest_route('vrmlookup/v1', '/save_image_from_others', array(
            'methods' => 'POST',
            'callback' => array($this,'save_image_from_others'),
        ));

        register_rest_route('vrmlookup/v1', '/update_table_from_others', array(
            'methods' => 'POST',
            'callback' => array($this,'update_table_from_others'),
        ));

        register_rest_route('vrmlookup/v1', '/logo_url', array(
            'methods' => 'GET',
            'callback' => array($this,'logo_url'),
        ));

        register_rest_route('vrmlookup/v1', '/get_all_data', array(
            'methods' => 'GET',
            'callback' => array($this,'get_all_data'),
        ));

        // Add new endpoint for collection drivers
        register_rest_route('vrmlookup/v1', '/get_all_drivers', array(
            'methods' => 'GET',
            'callback' => array($this,'get_all_drivers'),
        ));
    }

    function logo_url( $request ) {

        $options = get_option('vrm_lookup_data');
        $site_name = get_bloginfo('name');
        $logo_url = $options['logo_url']['value'];

        return array('logo_url' => $logo_url, 'site_name' => $site_name);
    }

    function update_table_from_others( $request ) {
        $data = $request->get_param('data');
        $secure_key = $request->get_param('secure_key');

        if(empty($data) || empty($secure_key)){
            return new WP_REST_Response('error', 400);
        }

        if($secure_key !== 'a7f9e3b2c1d5h8j6k4m0p2q9r7s5t3u1v8w6x4y2z0'){
            return new WP_REST_Response('error', 400);
        }

        global $wpdb;

        $tableName = $wpdb->prefix . 'vrm_lookup_data';
        $query = $wpdb->prepare(
            "UPDATE `{$tableName}` SET `" . esc_sql($data['updated_key']) . "` = %s WHERE `" . esc_sql($data['find_by']) . "` = %s",
            $data['updated_value'],
            $data['find_value']
        );

        $wpdb->query($query);

        return new WP_REST_Response('ok', 200);
    }


    function set_image_from_url($url,$reg_no) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');


        $tmp = download_url($url);

        $file_array = array(
            'name' => sanitize_file_name($reg_no).'_'.time().'.jpg',
            'tmp_name' => $tmp
        );
        if (is_wp_error($tmp)) {
            @unlink($file_array['tmp_name']);
            return $tmp;
        }
        $post_id = 0;
        $id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return $id;
        }
        return $id;
    }


    public function save_image_from_others( $request ) {
        $data = $request->get_param('data');
        $secure_key = $request->get_param('secure_key');

        if(empty($data) || empty($secure_key)){
            return new WP_REST_Response('error', 400);
        }

        if($secure_key !== 'a7f9e3b2c1d5h8j6k4m0p2q9r7s5t3u1v8w6x4y2z0'){
            return new WP_REST_Response('error', 400);
        }
        
        $image_id = $this->set_image_from_url($data['image_url'],$data['reg_no']);
        
        if(is_wp_error($image_id)){
            return new WP_REST_Response('error', 400);
        }

        return new WP_REST_Response($image_id, 200);
    }

    public function insert_customer_data_from_others( $request ) {
        $data = $request->get_param('data');
        $secure_key = $request->get_param('secure_key');

        if(empty($data) || empty($secure_key)){
            return new WP_REST_Response('error', 400);
        }

        if($secure_key !== 'a7f9e3b2c1d5h8j6k4m0p2q9r7s5t3u1v8w6x4y2z0'){
            return new WP_REST_Response('error', 400);
            }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'vrm_lookup_data',
            [
                'customer_info' => $data['customer_info'],
                'offer_status'  => $data['offer_status'],
                'status_id'     => $data['status_id'],
                'modified_at'   => current_time('mysql'),
                'sender_site'   => $data['sender_site'],
            ],
            ['id' => $data['id']]
        );

        return new WP_REST_Response('ok', 200);
    }

    public function check_car_exists( $request ) {
        $reg_no = $request->get_param('reg_no');

        if(empty($reg_no)){
            return new WP_REST_Response('error', 400);
        }

        global $wpdb;

        $car = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$wpdb->prefix}vrm_lookup_data` WHERE car_plate = %s",
                $reg_no
            ),
            'ARRAY_A'
        );

        if(!empty($car)){
            $car = $wpdb->get_row(
                'SELECT * FROM `' . $wpdb->prefix . 'vrm_lookup_data` WHERE car_plate = "' . $reg_no . '"',
                'ARRAY_A'
            );

            return new WP_REST_Response($car, 200);
        }

        return new WP_REST_Response('error', 400);
    }

    public function add_car_from_others( $request ) {
        $data = $request->get_param('data');
        $secure_key = $request->get_param('secure_key');

        if(empty($data) || empty($secure_key)){
            return new WP_REST_Response('error', 400);
        }

        if($secure_key !== 'a7f9e3b2c1d5h8j6k4m0p2q9r7s5t3u1v8w6x4y2z0'){
            return new WP_REST_Response('error', 400);
        }
        
        global $wpdb;

        // Check if car exists
        $car = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$wpdb->prefix}vrm_lookup_data` WHERE car_plate = %s",
                $data['car_plate']
            ),
            'ARRAY_A'
        );

        if(!empty($car)){
            return new WP_REST_Response('error', 400);
        }

        $wpdb->insert($wpdb->prefix . 'vrm_lookup_data', $data);

        return new WP_REST_Response('ok', 200);
    }

    function setup_options_request( $data ) {
        $options = get_option('vrm_lookup_data');
        unset($options['params']);
        return new WP_REST_Response($options, 200);
    }

    public function get_all_data( $request ) {
        global $wpdb;
        $tableName = $wpdb->prefix . 'vrm_lookup_data';
        
        // Get pagination parameters
        $per_page = $request->get_param('per_page') ? (int) $request->get_param('per_page') : 10;
        $page = $request->get_param('page') ? (int) $request->get_param('page') : 1;
        $offset = ($page - 1) * $per_page;
        
        $collection_date = $request->get_param('collection_date');
        $current_date = current_time('mysql');

        // First, get total count for pagination
        if (!empty($collection_date)) {
            $count_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$tableName}` WHERE `collection_date` IS NOT NULL AND `collection_date` >= %s",
                $current_date
            );
        } else {
            $count_query = "SELECT COUNT(*) FROM `{$tableName}`";
        }
        $total_items = $wpdb->get_var($count_query);
        $total_pages = ceil($total_items / $per_page);

        // Get paginated results
        if (!empty($collection_date)) {
            $query = $wpdb->prepare(
                "SELECT * FROM `{$tableName}` WHERE `collection_date` IS NOT NULL AND `collection_date` >= %s LIMIT %d OFFSET %d",
                $current_date,
                $per_page,
                $offset
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM `{$tableName}` LIMIT %d OFFSET %d",
                $per_page,
                $offset
            );
        }

        $results = $wpdb->get_results($query, 'ARRAY_A');

        // Return results with pagination metadata
        return new WP_REST_Response([
            'data' => $results,
            'meta' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_items' => (int) $total_items,
                'total_pages' => $total_pages
            ]
        ], 200);
    }

    // Add new function for getting all drivers
    public function get_all_drivers( $request ) {
        global $wpdb;
        $tableName = $wpdb->prefix . 'vrm_lookup_collection_driver';
        
        $query = "SELECT * FROM `{$tableName}`";
        $results = $wpdb->get_results($query, 'ARRAY_A');

        return new WP_REST_Response([
            'data' => $results
        ], 200);
    }
}

new Vrm_Lookup_API();