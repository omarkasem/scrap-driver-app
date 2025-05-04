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
        // add_filter( 'acf/settings/show_updates', '__return_false', 100 );

        // Add Google Maps API key
        add_filter('acf/settings/google_api_key', function() {
            return get_field('google_maps_api_key', 'option');
        });


        add_filter( 'acf/settings/save_json', array($this,'my_acf_json_save_point') );
        add_filter( 'acf/settings/load_json', array($this,'my_acf_json_load_point') );

        // Add filter for read-only fields
        add_filter('acf/load_field', array($this, 'make_vehicle_info_readonly'));

        // Add filter for loading field choices
        add_filter('acf/load_field', array($this, 'load_vehicle_statuses'));

        // Add filter to set default map center
        add_filter('acf/load_field', array($this, 'set_default_map_center'));

        // Add ACF Options Page under Settings
        if( function_exists('acf_add_options_page') ) {
            acf_add_options_page(array(
                'page_title'    => 'Scrap Driver Settings',
                'menu_title'    => 'Settings',
                'menu_slug'     => 'scrap-driver-settings',
                'capability'    => 'manage_options',
                'redirect'      => false,
                'parent_slug'   => 'driver-app'  // This places it under Settings
            ));
        }
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

            $statuses = sda_get_statuses_names();
            $field['choices'] = $statuses;
        }
        
        return $field;
    }

    public function set_default_map_center($field) {
                
        // Set default value for specific field if empty
        if ($field['key'] === 'field_67938b627f419') {
            $default_location = get_field('default_shifts_location', 'option');
            if ($default_location) {
                $field['center_lat'] = $default_location['lat'];
                $field['center_lng'] = $default_location['lng'];
                $field['value'] = empty($field['value']) ? $default_location : $field['value'];
            }
        }


        if ($field['key'] === 'field_67938b777f41b') {
            $default_location = get_field('default_end_location', 'option');
            if ($default_location) {
                $field['center_lat'] = $default_location['lat'];
                $field['center_lng'] = $default_location['lng'];
                $field['value'] = empty($field['value']) ? $default_location : $field['value'];
            }
        }

        return $field;
    }

}

new Acf();