<?php 

function sda_get_statuses_names() {
    $stat_array = get_transient('sda_statuses_names');
    if (false === $stat_array) {
        $response = wp_remote_get(SCRAP_DRIVER_API_URL . 'wp-json/vrmlookup/v1/get_all_statuses');
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $statuses = json_decode(wp_remote_retrieve_body($response), true);
            $stat_array = array();
            foreach ($statuses['data'] as $status) {
                $stat_array[$status['name']] = $status['name'];
            }
            set_transient('sda_statuses_names', $stat_array, 5);
        }
    }
    return $stat_array;
}

function sda_get_statuses_ids() {
    $stat_array = get_transient('sda_statuses_ids');
    if (false === $stat_array) {
        $response = wp_remote_get(SCRAP_DRIVER_API_URL . 'wp-json/vrmlookup/v1/get_all_statuses');
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $statuses = json_decode(wp_remote_retrieve_body($response), true);
            $stat_array = array();
            foreach ($statuses['data'] as $status) {
                $stat_array[$status['id']] = $status['name'];
            }
            set_transient('sda_statuses_ids', $stat_array, 5);
        }
    }
    return $stat_array;
}