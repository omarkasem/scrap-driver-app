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
            <b class="distance-total"></b>
            <!-- Distance calculation content will go here -->
        </div>
        <?php
    }

    /**
     * Get latitude and longitude from address using Google Geocoding API
     *
     * @param string $address  Street address.
     * @param string $postcode Postal code.
     * @return array|WP_Error Array with lat/lng or WP_Error on failure.
     */
    public function get_lat_lng_by_address($address, $postcode = '') {
        $api_key = get_field('google_maps_api_key','option');
        if (empty($api_key)) {
            return new \WP_Error('no_api_key', __('Google Maps API key is not set', 'scrap-driver-app'));
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

        return array(
            'lat' => $location['lat'],
            'lng' => $location['lng'],
            'viewport' => $viewport // This can be useful for determining the precision of the location
        );
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
} 
new Distance();