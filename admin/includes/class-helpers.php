<?php 

function sda_get_all_statuses() {
    $stat_array = get_transient('sda_all_statuses');
    if (false === $stat_array) {
        $response = wp_remote_get(SCRAP_DRIVER_API_URL . 'wp-json/vrmlookup/v1/get_all_statuses');
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $statuses = json_decode(wp_remote_retrieve_body($response), true);
            $stat_array = array();
            foreach ($statuses['data'] as $status) {
                $stat_array[$status['id']] = $status['name'];
            }
            set_transient('sda_all_statuses', $stat_array, 5);
        }
    }
    return $stat_array;
}