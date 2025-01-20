<?php
/**
 * Class for handling the Scrap Driver Settings page
 */
class SD_Option_Page {

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_options_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Add the options page to the menu
     */
    public function add_options_page() {
        add_options_page(
            'Scrap Driver Settings',
            'Scrap Driver',
            'manage_options',
            'scrap-driver-settings',
            array($this, 'render_options_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('scrap_driver_options', 'sd_google_maps_api_key');
        register_setting('scrap_driver_options', 'sd_default_address');

        add_settings_section(
            'sd_main_section',
            'Main Settings',
            null,
            'scrap-driver-settings'
        );

        add_settings_field(
            'sd_google_maps_api_key',
            'Google Maps API Key',
            array($this, 'render_api_key_field'),
            'scrap-driver-settings',
            'sd_main_section'
        );

        add_settings_field(
            'sd_default_address',
            'Default Address',
            array($this, 'render_address_field'),
            'scrap-driver-settings',
            'sd_main_section'
        );
    }

    /**
     * Render the options page
     */
    public function render_options_page() {
        ?>
        <div class="wrap">
            <h1>Scrap Driver Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('scrap_driver_options');
                do_settings_sections('scrap-driver-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $api_key = get_option('sd_google_maps_api_key');
        ?>
        <input type="text" 
               name="sd_google_maps_api_key" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text"
               id="sd_google_maps_api_key">
        <p class="description">Enter your Google Maps API key</p>
        <?php
    }

    /**
     * Render address field
     */
    public function render_address_field() {
        $address = get_option('sd_default_address');
        $api_key = get_option('sd_google_maps_api_key');
        $display = empty($api_key) ? 'style="display:none;"' : '';
        ?>
        <div class="sd-address-wrapper" <?php echo $display; ?>>
            <input type="text" 
                   name="sd_default_address" 
                   value="<?php echo esc_attr($address); ?>" 
                   class="regular-text"
                   id="sd_address_autocomplete">
            <p class="description">Enter your default address</p>
        </div>
        <?php
    }

    /**
     * Enqueue necessary scripts
     */
    public function enqueue_scripts($hook) {
        if ('settings_page_scrap-driver-settings' !== $hook) {
            return;
        }

        $api_key = get_option('sd_google_maps_api_key');
        
        if (!empty($api_key)) {
            wp_enqueue_script(
                'google-maps',
                "https://maps.googleapis.com/maps/api/js?key={$api_key}&libraries=places",
                array(),
                null,
                true
            );
        }

        wp_enqueue_script(
            'sd-admin-options',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin-options.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('sd-admin-options', 'sdAdminOptions', array(
            'apiKeyField' => '#sd_google_maps_api_key',
            'addressWrapper' => '.sd-address-wrapper'
        ));
    }
}

// Initialize the class
new SD_Option_Page(); 