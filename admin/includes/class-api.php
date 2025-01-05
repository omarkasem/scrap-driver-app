<?php
namespace ScrapDriver\Admin;

class Api {
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        // Register your REST API endpoints here
        register_rest_route('scrap-driver/v1', '/example', array(
            'methods' => 'GET',
            'callback' => array($this, 'example_endpoint'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }

    public function example_endpoint($request) {
        // Handle the API request
        return rest_ensure_response(array('status' => 'success'));
    }

    public function check_permissions() {
        return current_user_can('manage_options');
    }
}

new Api();