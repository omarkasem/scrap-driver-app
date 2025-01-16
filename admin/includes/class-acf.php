<?php
namespace ScrapDriver\Admin;

class Acf {
    public function __construct() {

        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Check if ACF PRO is active
        if ( is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) {
            return;
        }

        if ( defined( 'MY_ACF_PATH' ) ) {
            return;
        }

        include_once( SCRAP_DRIVER_ACF_PATH . 'acf.php' );

        $this->init();

    }

    public function init(){
        add_filter('acf/settings/url', array($this,'acf_settings_url'));
        add_filter('acf/settings/show_admin', array($this,'show_admin'));

        // Hide the ACF Updates menu
        add_filter( 'acf/settings/show_updates', '__return_false', 100 );

        add_filter( 'acf/settings/save_json', array($this,'my_acf_json_save_point') );
        add_filter( 'acf/settings/load_json', array($this,'my_acf_json_load_point') );

        // Add filter for read-only fields
        add_filter('acf/load_field', array($this, 'make_vehicle_info_readonly'));

        // Add filter for loading field choices
        add_filter('acf/load_field', array($this, 'load_vehicle_statuses'));
    }

    function my_acf_json_load_point( $paths ) {
        // Remove the original path (optional).
        unset($paths[0]);
    
        // Append the new path and return it.
        $paths[] = SCRAP_DRIVER_PLUGIN_DIR . '/lib/acf-json';
    
        return $paths;    
    }
    

    function my_acf_json_save_point( $path ) {
        return SCRAP_DRIVER_PLUGIN_DIR . '/lib/acf-json';
    }


    // Customize the URL setting to fix incorrect asset URLs.
    public function acf_settings_url( $url ) {
        return SCRAP_DRIVER_ACF_URL;
    }


    public function show_admin( $show_admin ) {
        return SCRAP_DRIVER_ACF_SHOW;
    }

    // Add this new method
    public function make_vehicle_info_readonly($field) {
        // Check if the field is part of the vehicle_info group
        if (isset($field['parent']) && $field['parent'] === 'field_677a4dbb75eb6') {
            $field['readonly'] = true;
        }
        // Check if the field is part of the customer_info group
        if (isset($field['parent']) && $field['parent'] === 'field_6787f37c439ee') {
            $field['readonly'] = true;
        }
        return $field;
    }

    // Update the existing method to include status field handling
    public function load_vehicle_statuses($field) {
        // Handle status select field
        if ($field['key'] === 'field_677a4dbb75eb2') {
            // Make API request to get statuses
            $response = wp_remote_get(SCRAP_DRIVER_API_URL . 'wp-json/vrmlookup/v1/get_all_statuses');

            // Debug the API response
            error_log('API Response: ' . print_r($response, true));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $statuses = json_decode(wp_remote_retrieve_body($response), true);
                
                // Debug the decoded statuses
                error_log('Decoded Statuses: ' . print_r($statuses, true));
                
                // Format the choices
                $field['choices'] = array();
                foreach ($statuses['data'] as $status) {
                    $field['choices'][$status['id']] = $status['name'];
                }
                
                // Debug the final choices
                error_log('Field Choices: ' . print_r($field['choices'], true));
            } else {
                error_log('API Error: ' . print_r($response, true));
            }
        }
        
        return $field;
    }

}

new Acf();