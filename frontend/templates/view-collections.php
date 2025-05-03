<?php
$view_all = (isset($view_all)) ? $view_all : false;

$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();
$is_driver = in_array('driver', $current_user->roles);
$today = date('Y-m-d');

// Get collections based on user role
$args = array(
    'post_type' => 'sda-collection',
    'posts_per_page' => -1,
    'orderby' => 'meta_value',
    'meta_key' => 'collection_date',
    'order' => 'ASC',
    'meta_query' => array(
        array(
            'key' => 'collection_date',
            'value' => $today,
            'compare' => '=',
            'type' => 'DATE'
        )
    )
);

if($view_all) {
    unset($args['meta_query']);
}

// If user is a driver, only show their assigned collections
if ($is_driver) {
    $args['meta_query'][] = array(
        'key' => 'assigned_driver',
        'value' => $current_user_id,
        'compare' => '='
    );
}

$collections = new WP_Query($args);
?>

<?php require_once SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/parts/collection/table.php'; ?>