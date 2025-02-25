<?php

namespace ScrapDriver\Admin;

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
        add_action('wp_ajax_optimize_route', array($this, 'optimize_route'));
    
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
        // Get the stored values
        $total_distance = get_post_meta($post->ID, 'total_distance', true);
        $total_time = get_post_meta($post->ID, 'total_time', true);
        $total_time_formatted = get_post_meta($post->ID, 'total_time_formatted', true);
        
        // If we have total_time but not formatted time, format it now
        if ($total_time && !$total_time_formatted) {
            $total_time_formatted = $this->format_time_duration($total_time);
            // Store it for future use
            update_post_meta($post->ID, 'total_time_formatted', $total_time_formatted);
        }
        
        ?>
        <div id="distance-calc">
            <h4>Move collections in route planning to recalculate the distance</h4>
            <div class="route-actions">
                <button type="button" id="ai-reorder-route" class="button button-secondary" data-tooltip="Click this button to automatically re-order the collections and shift route based on all collection locations to get the fastest route. Once done, you can make manual adjustments if needed.">
                    <span class="dashicons dashicons-superhero"></span> AI Re-Order Route
                </button>
            </div>
            <b class="distance-total">
                <?php if ($total_distance) { ?>
                    <div>Total Distance: <?php echo $total_distance; ?> miles</div>
                <?php } ?>
                <?php if ($total_time_formatted) { ?>
                    <div>Total Time: <?php echo $total_time_formatted; ?></div>
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

        error_log(json_encode(array(
            'Response' => $response
        ), JSON_PRETTY_PRINT));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body, true);
        
        if($data === false) {
            return new \WP_Error('json_decode_error', 'Failed to decode response from Google Geocoding API');
        }

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
     * @return array|WP_Error Total distance, time or WP_Error on failure.
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
        $total_time = 0; // Total time in seconds
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
            
            // Get time in seconds
            $time_seconds = $data['rows'][0]['elements'][0]['duration']['value'];
            $total_time += $time_seconds;

            // Convert to requested unit
            if ($unit === 'miles') {
                $total_distance += $distance_meters * 0.000621371; // Convert meters to miles
            } else {
                $total_distance += $distance_meters / 1000; // Convert meters to kilometers
            }
        }

        return array(
            'distance' => round($total_distance, 2),
            'time' => $total_time,
            'time_formatted' => $this->format_time_duration($total_time)
        );
    }

    /**
     * Format time duration from seconds to human readable format
     * 
     * @param int $seconds Time in seconds
     * @return string Formatted time (e.g. "2 hours 15 minutes")
     */
    public function format_time_duration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = round(($seconds % 3600) / 60);
        
        $formatted = '';
        if ($hours > 0) {
            $formatted .= $hours . ' ' . ($hours == 1 ? 'hour' : 'hours');
        }
        
        if ($minutes > 0) {
            if ($formatted) {
                $formatted .= ' ';
            }
            $formatted .= $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes');
        }
        
        if (empty($formatted)) {
            $formatted = 'Less than a minute';
        }
        
        return $formatted;
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
                    'compare' => '=',
                    'type' => 'DATE'
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
            if(is_wp_error($lat_lng)) {
                continue;
            }
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
        $stored_time = get_post_meta($post_id, 'total_time', true);

        // If the hash matches and we have a stored distance, return the cached result
        if ($locations_hash === $stored_hash && !empty($stored_distance) && !empty($stored_time)) {
            wp_send_json_success(array(
                'distance' => $stored_distance,
                'time' => $stored_time,
                'time_formatted' => get_post_meta($post_id, 'total_time_formatted', true),
                'unit' => 'miles',
                'cached' => true
            ));
            return;
        }

        // Calculate total distance and time
        $result = $this->calculate_distance($locations);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        // Store the new results
        update_post_meta($post_id, 'total_distance', $result['distance']);
        update_post_meta($post_id, 'total_time', $result['time']);
        update_post_meta($post_id, 'total_time_formatted', $result['time_formatted']);
        update_post_meta($post_id, 'locations_hash', $locations_hash);
        $locations_json = json_encode($locations);
        update_post_meta($post_id, 'locations', $locations_json);

        wp_send_json_success(array(
            'distance' => $result['distance'],
            'time' => $result['time'],
            'time_formatted' => $result['time_formatted'],
            'unit' => 'miles',
            'cached' => false
        ));
    }

    /**
     * AI Route Optimization
     * Reorders collections based on the most efficient route
     */
    public function optimize_route() {
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
            'meta_query' => array(
                array(
                    'key' => 'collection_date',
                    'value' => $shift_date,
                    'compare' => '=',
                    'type' => 'DATE'
                ),
                array(
                    'key' => 'assigned_driver',
                    'value' => $driver_id,
                    'compare' => '='
                )
            ),
        );

        $collections = get_posts($args);

        if (empty($collections)) {
            wp_send_json_error('No collections found');
            return;
        }

        // Get geocoded locations for all collections
        $collection_locations = array();
        foreach ($collections as $collection) {
            $address = get_field('customer_info_address', $collection->ID);
            $postcode = get_field('customer_info_postcode', $collection->ID);
            $lat_lng = $this->get_lat_lng_by_address($address, $postcode, $collection->ID);
            
            if (!is_wp_error($lat_lng) && isset($lat_lng['lat'], $lat_lng['lng'])) {
                $collection_locations[$collection->ID] = array(
                    'lat' => floatval($lat_lng['lat']),
                    'lng' => floatval($lat_lng['lng'])
                );
            }
        }

        if (empty($collection_locations)) {
            wp_send_json_error('Could not geocode collection addresses');
            return;
        }

        // Use Google Directions API to optimize the route
        $api_key = get_field('google_maps_api_key', 'option');
        if (empty($api_key)) {
            wp_send_json_error('Google Maps API key is not set');
            return;
        }

        // Starting point
        $origin = "{$starting_point['lat']},{$starting_point['lng']}";
        
        // Ending point
        $destination = "{$ending_point['lat']},{$ending_point['lng']}";
        
        // Waypoints (collection locations)
        $waypoints = array();
        foreach ($collection_locations as $id => $location) {
            $waypoints[] = "{$location['lat']},{$location['lng']}";
        }
        
        $waypoints_str = implode('|', $waypoints);
        
        // Build the API URL
        $url = "https://maps.googleapis.com/maps/api/directions/json?origin={$origin}&destination={$destination}&waypoints=optimize:true|{$waypoints_str}&key={$api_key}";
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data['status'] !== 'OK') {
            wp_send_json_error('Google Directions API error: ' . $data['status']);
            return;
        }
        
        // Get the optimized waypoint order
        $optimized_order = $data['routes'][0]['waypoint_order'];
        
        // Map the optimized order back to collection IDs
        $collection_ids = array_keys($collection_locations);
        $new_order = array();
        
        foreach ($optimized_order as $index) {
            $new_order[] = $collection_ids[$index];
        }
        
        // Get shift start time to use as base for collection times
        $shift_start_time = get_field('start_time', $post_id);
        if (!$shift_start_time) {
            $shift_start_time = '08:00:00'; // Default start time if not set
        }
        
        // Parse the shift start time
        $start_time_parts = explode(':', $shift_start_time);
        $current_hour = intval($start_time_parts[0]);
        $current_minute = intval($start_time_parts[1]);
        
        // Update the collection order and times
        $order = 1;
        foreach ($new_order as $collection_id) {
            // Update collection order
            update_post_meta($collection_id, 'collection_order', $order);
            update_post_meta($collection_id, 'route_order', $order); // Also update route_order for compatibility
            
            // Calculate start and end times for this collection
            // Each collection gets a 1-hour slot
            $start_time = sprintf('%02d:%02d:00', $current_hour, $current_minute);
            
            // Move time forward by 1 hour for the next collection
            $current_minute += 60;
            if ($current_minute >= 60) {
                $current_hour += floor($current_minute / 60);
                $current_minute = $current_minute % 60;
            }
            
            $end_time = sprintf('%02d:%02d:00', $current_hour, $current_minute);
            
            // Update collection times with the correct field names
            update_post_meta($collection_id, 'collection_start_time', $start_time);
            update_post_meta($collection_id, 'collection_end_time', $end_time);
            
            // Also update ACF fields if they exist
            update_field('collection_start_time', $start_time, $collection_id);
            update_field('collection_end_time', $end_time, $collection_id);
            
            $order++;
        }
        
        // Calculate and update the new route distance and time
        $locations = array(array(
            'lat' => floatval($starting_point['lat']),
            'lng' => floatval($starting_point['lng'])
        ));
        
        foreach ($new_order as $collection_id) {
            $locations[] = $collection_locations[$collection_id];
        }
        
        $locations[] = array(
            'lat' => floatval($ending_point['lat']),
            'lng' => floatval($ending_point['lng'])
        );
        
        $result = $this->calculate_distance($locations);
        
        if (!is_wp_error($result)) {
            update_post_meta($post_id, 'total_distance', $result['distance']);
            update_post_meta($post_id, 'total_time', $result['time']);
            update_post_meta($post_id, 'total_time_formatted', $result['time_formatted']);
            
            $locations_hash = md5(json_encode($locations));
            update_post_meta($post_id, 'locations_hash', $locations_hash);
            update_post_meta($post_id, 'locations', json_encode($locations));
        }
        
        wp_send_json_success(array(
            'message' => 'Route optimized successfully',
            'new_order' => $new_order,
            'distance' => $result['distance'],
            'time' => $result['time'],
            'time_formatted' => $result['time_formatted']
        ));
    }

} 
new Distance();