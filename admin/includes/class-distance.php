<?php
/**
 * Distance calculation functionality
 *
 * @package ScrapDriverApp
 */

namespace ScrapDriverApp\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Distance Class
 */
class Distance {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_distance_metabox'));
        add_action('wp_ajax_calculate_route_distance', array($this, 'calculate_route_distance'));
    }

    /**
     * Add distance calculation metabox
     */
    public function add_distance_metabox() {
        add_meta_box(
            'distance_calculation',
            __('Distance Calculation', 'scrap-driver-app'),
            array($this, 'render_distance_metabox'),
            'sda-shift',
            'side',
            'default'
        );
    }

    /**
     * Render distance calculation metabox
     *
     * @param WP_Post $post Post object.
     */
    public function render_distance_metabox($post) {
        ?>
        <div id="distance-calc">
            <h4>Move collections in route planning to recalculate the distance</h4>
            <b class="distance-total">
                <?php if(get_post_meta($post->ID, 'total_distance', true)) { ?>
                    Total Distance: <?php echo get_post_meta($post->ID, 'total_distance', true); ?> miles
                <?php } ?>
            </b>
            <!-- Distance calculation content will go here -->
        </div>
        <?php
    }

    /**
     * Get latitude and longitude from address using Google Geocoding API
     *
     * @param string $address  Street address.
     * @param string $postcode Postal code.
     * @param int $collection_id Collection ID.
     * @return array|WP_Error Array with lat/lng or WP_Error on failure.
     */
    public function get_lat_lng_by_address($address, $postcode = '', $collection_id = 0) {
        $api_key = get_field('google_maps_api_key','option');
        if (empty($api_key)) {
            return new \WP_Error('no_api_key', __('Google Maps API key is not set', 'scrap-driver-app'));
        }

        if ($collection_id) {
            // Create a hash of the address and postcode
            $location_hash = md5($address . '|' . $postcode);
            
            // Try to get cached coordinates
            $cached_coords = get_post_meta($collection_id, 'sda_geocoded_' . $location_hash, true);
            if ($cached_coords) {
                return json_decode($cached_coords, true);
            }
        }

        // Always include UK as the country component
        $components = 'country:GB';
        
        // Add postcode component if provided
        if (!empty($postcode)) {
            $components .= '|postal_code:' . urlencode(trim($postcode));
        }

        if (!empty($address)) {
            $address = urlencode(trim($address));
            $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&components={$components}&key={$api_key}";
        } else {
            $url = "https://maps.googleapis.com/maps/api/geocode/json?components={$components}&key={$api_key}";
        }

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['status'] !== 'OK') {
            return new \WP_Error('geocoding_error', $data['status']);
        }

        $location = $data['results'][0]['geometry']['location'];
        
        // Add viewport data for more precise location information
        $viewport = $data['results'][0]['geometry']['viewport'];

        $result = array(
            'lat' => $location['lat'],
            'lng' => $location['lng'],
            'viewport' => $viewport
        );

        // Cache the result if we have a collection ID
        if ($collection_id) {
            update_post_meta($collection_id, 'sda_geocoded_' . $location_hash, json_encode($result));
        }

        return $result;
    }

    /**
     * Calculate distance between multiple locations
     *
     * @param array  $locations Array of locations with lat/lng.
     * @param string $unit      Unit of measurement ('miles' or 'km').
     * @return float|WP_Error Total distance or WP_Error on failure.
     */
    public function calculate_distance($locations, $unit = 'miles') {
        $api_key = get_field('google_maps_api_key','option');
        if (empty($api_key)) {
            return new \WP_Error('no_api_key', __('Google Maps API key is not set', 'scrap-driver-app'));
        }

        if (count($locations) < 2) {
            return new \WP_Error('insufficient_locations', __('At least 2 locations are required', 'scrap-driver-app'));
        }

        $total_distance = 0;
        $waypoints = array();

        // Prepare waypoints for the API request
        foreach ($locations as $location) {
            $waypoints[] = "{$location['lat']},{$location['lng']}";
        }

        // Calculate distance between consecutive points
        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $origin = $waypoints[$i];
            $destination = $waypoints[$i + 1];

            $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins={$origin}&destinations={$destination}&key={$api_key}";

            $response = wp_remote_get($url);

            if (is_wp_error($response)) {
                return $response;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($data['status'] !== 'OK') {
                return new \WP_Error('distance_matrix_error', $data['status']);
            }

            // Get distance in meters
            $distance_meters = $data['rows'][0]['elements'][0]['distance']['value'];

            // Convert to requested unit
            if ($unit === 'miles') {
                $total_distance += $distance_meters * 0.000621371; // Convert meters to miles
            } else {
                $total_distance += $distance_meters / 1000; // Convert meters to kilometers
            }
        }

        return round($total_distance, 2);
    }

    /**
     * Calculate route distance for collections
     */
    public function calculate_route_distance() {
        check_ajax_referer('sda_route_nonce', 'nonce');

        // Get and validate required parameters
        $shift_date = sanitize_text_field($_POST['shift_date']);
        $driver_id = intval($_POST['driver_id']);
        $starting_point = json_decode(stripslashes($_POST['starting_point']), true);
        $ending_point = json_decode(stripslashes($_POST['ending_point']), true);
        $post_id = intval($_POST['post_id']);
        
        if (!$shift_date || !$driver_id || !$starting_point || !$ending_point) {
            wp_send_json_error('Missing required parameters');
            return;
        }

        // Get collections for this date and driver
        $shift_date = date('Y-m-d', strtotime($shift_date));
        $args = array(
            'post_type' => 'sda-collection',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'collection_date',
                    'value' => $shift_date,
                    'compare' => '='
                ),
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id,
                    'compare' => '='
                )
            ),
        );

        $collections = get_posts($args);

        if(empty($collections)) {
            wp_send_json_error('No collections found');
            return;
        }

        $collections = \ScrapDriver\Frontend\Collection::get_collections_order($collections);


        // Prepare locations array starting with the starting point
        $locations = array(array(
            'lat' => floatval($starting_point['lat']),
            'lng' => floatval($starting_point['lng'])
        ));

        // Add each collection location
        foreach ($collections as $collection_id => $order) {
            $address = get_field('customer_info_address', $collection_id);
            $postcode = get_field('customer_info_postcode', $collection_id);
            $lat_lng = $this->get_lat_lng_by_address($address, $postcode, $collection_id);
            if ($lat_lng && isset($lat_lng['lat'], $lat_lng['lng'])) {
                $locations[] = array(
                    'lat' => floatval($lat_lng['lat']),
                    'lng' => floatval($lat_lng['lng'])
                );
            }
        }

        // Add ending point
        $locations[] = array(
            'lat' => floatval($ending_point['lat']),
            'lng' => floatval($ending_point['lng'])
        );


        
        // Create a hash of the locations array
        $locations_hash = md5(json_encode($locations));
        $stored_hash = get_post_meta($post_id, 'locations_hash', true);
        $stored_distance = get_post_meta($post_id, 'total_distance', true);

        // If the hash matches and we have a stored distance, return the cached result
        if ($locations_hash === $stored_hash && !empty($stored_distance)) {
            wp_send_json_success(array(
                'distance' => $stored_distance,
                'unit' => 'miles',
                'cached' => true
            ));
            return;
        }

        // Calculate total distance
        $total_distance = $this->calculate_distance($locations);

        if (is_wp_error($total_distance)) {
            wp_send_json_error($total_distance->get_error_message());
            return;
        }

        // Store the new results
        update_post_meta($post_id, 'total_distance', $total_distance);
        update_post_meta($post_id, 'locations_hash', $locations_hash);
        $locations_json = json_encode($locations);
        update_post_meta($post_id, 'locations', $locations_json);

        wp_send_json_success(array(
            'distance' => $total_distance,
            'unit' => 'miles',
            'cached' => false
        ));
    }
} 
new Distance();