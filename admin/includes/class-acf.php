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

    }


    function my_acf_json_load_point( $paths ) {
        // Remove the original path (optional).
        unset($paths[0]);
    
        // Append the new path and return it.
        $paths[] = SCRAP_DRIVER_ACF_PATH . 'acf-json';
    
        return $paths;    
    }
    

    function my_acf_json_save_point( $path ) {
        return SCRAP_DRIVER_ACF_PATH . 'acf-json';
    }


    // Customize the URL setting to fix incorrect asset URLs.
    public function acf_settings_url( $url ) {
        return SCRAP_DRIVER_ACF_URL;
    }


    public function show_admin( $show_admin ) {
        return SCRAP_DRIVER_ACF_SHOW;
    }

}

new Acf();