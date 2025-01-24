<?php
namespace ScrapDriver;
/**
 * Class for handling custom roles and capabilities
 *
 * @package ScrapDriverApp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Caps {

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action( 'init', array( $this, 'create_driver_role' ) );
        // Add hooks for user profile fields
        add_action( 'show_user_profile', array( $this, 'add_collection_driver_field' ) );
        add_action( 'edit_user_profile', array( $this, 'add_collection_driver_field' ) );
        // Add hook for new user creation form
        add_action( 'user_new_form', array( $this, 'add_collection_driver_field' ) );
        // Save the custom field
        add_action( 'personal_options_update', array( $this, 'save_collection_driver_field' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_collection_driver_field' ) );
        // Save for new user creation
        add_action( 'user_register', array( $this, 'save_collection_driver_field' ) );
    }

    /**
     * Create the driver role if it doesn't exist
     */
    public function create_driver_role() {
        // Only create the role if it doesn't exist
        if ( ! get_role( 'driver' ) ) {
            // Get the subscriber role capabilities
            $subscriber = get_role( 'subscriber' );
            $subscriber_caps = $subscriber->capabilities;

            // Add the new role with subscriber capabilities
            add_role(
                'driver',
                __( 'Driver', 'scrap-driver-app' ),
                $subscriber_caps
            );
        }
    }

    /**
     * Remove the driver role on plugin deactivation
     */
    public static function remove_driver_role() {
        remove_role( 'driver' );
    }

    /**
     * Get drivers from the API endpoint
     */
    private function get_drivers() {
        $response = wp_remote_get( 'https://scrapmycaronline.co.uk/dev/wp-json/vrmlookup/v1/get_all_drivers' );
        
        if ( is_wp_error( $response ) ) {
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        return isset( $data['data'] ) ? $data['data'] : array();
    }

    /**
     * Get already assigned driver IDs
     */
    private function get_assigned_drivers() {
        global $wpdb;
        
        $assigned_drivers = $wpdb->get_col(
            "SELECT DISTINCT meta_value 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'collection_driver' 
            AND meta_value != ''"
        );
        
        return array_filter($assigned_drivers); // Remove empty values
    }

    /**
     * Add Collection Driver field to user profile
     *
     * @param WP_User|string $user User object or 'add-new-user' when creating new user
     */
    public function add_collection_driver_field( $user ) {
        // Only show field if current user is an administrator
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $drivers = $this->get_drivers();
        $assigned_drivers = $this->get_assigned_drivers();
        
        // Handle both edit profile and new user creation
        $current_driver = '';
        if ( is_object( $user ) ) {
            $current_driver = get_user_meta( $user->ID, 'collection_driver', true );
        }
        ?>
        <h3><?php _e( 'Collection Driver Information', 'scrap-driver-app' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="collection_driver"><?php _e( 'Collection Driver', 'scrap-driver-app' ); ?></label></th>
                <td>
                    <select name="collection_driver" id="collection_driver">
                        <option value=""><?php _e( 'Select a driver', 'scrap-driver-app' ); ?></option>
                        <?php foreach ( $drivers as $driver ) : 
                            $is_assigned = in_array($driver['id'], $assigned_drivers);
                            $is_current = $current_driver == $driver['id'];
                            $disabled = $is_assigned && !$is_current ? 'disabled' : '';
                            ?>
                            <option 
                                value="<?php echo esc_attr( $driver['id'] ); ?>" 
                                <?php selected( $current_driver, $driver['id'] ); ?>
                                <?php echo $disabled; ?>
                            >
                                <?php 
                                echo esc_html( $driver['name'] );
                                if ($is_assigned && !$is_current) {
                                    echo ' (' . __('Already assigned', 'scrap-driver-app') . ')';
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($drivers)) : ?>
                        <p class="description"><?php _e('No drivers available from the API.', 'scrap-driver-app'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save Collection Driver field
     *
     * @param int $user_id User ID
     */
    public function save_collection_driver_field( $user_id ) {
        // Only allow administrators to save this field
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( isset( $_POST['collection_driver'] ) ) {
            update_user_meta( $user_id, 'collection_driver', sanitize_text_field( $_POST['collection_driver'] ) );
        }
    }
} 

new Caps();