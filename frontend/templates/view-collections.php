<?php
/**
 * Template Name: Collections List
 * 
 * Template for viewing collections list
 */

get_header();

// Get the current user ID and role
$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_options');
$is_driver = in_array('driver', $current_user->roles);

// Check if user has permission to view
if (!$is_admin && !$is_driver) {
    ?>
    <div class="wrap sda-collection-single">
        <div class="sda-section sda-error-message">
            <h1><?php _e('Access Denied', 'scrap-driver'); ?></h1>
            <p><?php _e('Sorry, you do not have permission to view collections. Only administrators and drivers can access this page.', 'scrap-driver'); ?></p>
            <a href="<?php echo esc_url(home_url()); ?>" class="button">
                <?php _e('Return to Homepage', 'scrap-driver'); ?>
            </a>
        </div>
    </div>
    <?php
    get_footer();
    exit;
}

// Handle any POST updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collection_update'])) {
    // Verify nonce
    if (!isset($_POST['sda_collection_nonce']) || 
        !wp_verify_nonce($_POST['sda_collection_nonce'], 'sda_update_collection')) {
        wp_die('Security check failed');
    }

    $collection_id = intval($_POST['collection_id']);
    
    // Update the collection fields
    if (isset($_POST['status'])) {
        update_field('status', sanitize_text_field($_POST['status']), $collection_id);
    }
    
    if (isset($_POST['assigned_driver'])) {
        update_field('assigned_driver', sanitize_text_field($_POST['assigned_driver']), $collection_id);
    }
    
    // Trigger the sync
    $generator = new \ScrapDriver\Admin\Generator();
    $generator->sync_collection_to_api($collection_id);
    
    // Redirect to prevent form resubmission
    wp_redirect(add_query_arg('updated', '1', wp_get_referer()));
    exit;
}

// Get collections based on user role
$args = array(
    'post_type' => 'sda-collection',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC'
);

// If user is a driver, only show their assigned collections
if ($is_driver) {
    $args['meta_query'] = array(
        array(
            'key' => 'assigned_driver',
            'value' => $current_user_id,
            'compare' => '='
        )
    );
}

$collections = new WP_Query($args);
?>

<div class="wrap sda-collections-list">
    <h1><?php _e('Collections', 'scrap-driver'); ?></h1>

    <div class="sda-section">
        <?php
        // Add this before the table to pass translations to JS
        $datatable_translations = array(
            'search' => __('Search:', 'scrap-driver'),
            'lengthMenu' => __('Show _MENU_ entries', 'scrap-driver'),
            'info' => __('Showing _START_ to _END_ of _TOTAL_ entries', 'scrap-driver'),
            'infoEmpty' => __('Showing 0 to 0 of 0 entries', 'scrap-driver'),
            'infoFiltered' => __('(filtered from _MAX_ total entries)', 'scrap-driver'),
            'emptyTable' => __('No collections available', 'scrap-driver'),
            'first' => __('First', 'scrap-driver'),
            'last' => __('Last', 'scrap-driver'),
            'next' => __('Next', 'scrap-driver'),
            'previous' => __('Previous', 'scrap-driver')
        );
        ?>
        <script>
            var sdaDataTableTranslations = <?php echo json_encode($datatable_translations); ?>;
        </script>
        <table id="collections-table" class="display">
            <thead>
                <tr>
                    <th><?php _e('Order', 'scrap-driver'); ?></th>
                    <th><?php _e('ID', 'scrap-driver'); ?></th>
                    <th><?php _e('Driver', 'scrap-driver'); ?></th>
                    <th><?php _e('Vehicle', 'scrap-driver'); ?></th>
                    <th><?php _e('Collection Date', 'scrap-driver'); ?></th>
                    <th><?php _e('Status', 'scrap-driver'); ?></th>
                    <th><?php _e('Actions', 'scrap-driver'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($collections->have_posts()) :
                    // Get all collection IDs for ordering
                    $collection_ids = wp_list_pluck($collections->posts, 'ID');
                    $collection_orders = \ScrapDriver\Frontend\Collection::get_collections_order($collection_ids);

                    while ($collections->have_posts()) : $collections->the_post();
                        $collection_id = get_the_ID();
                        $driver_id = get_field('assigned_driver');
                        $driver_name = $driver_id ? get_user_by('id', $driver_id)->display_name : __('Unassigned', 'scrap-driver');
                        $vehicle_make = get_field('vehicle_info_make');
                        $vehicle_model = get_field('vehicle_info_model');
                        $vehicle_plate = get_field('vehicle_info_plate');
                        $collection_date = get_field('collection_date');
                        $status = get_post_meta($collection_id, '_collection_status', true) ?: 'pending';
                        ?>
                        <tr>
                            <td><?php echo esc_html($collection_orders[$collection_id]); ?></td>
                            <td><?php echo esc_html($collection_id); ?></td>
                            <td><?php echo esc_html($driver_name); ?></td>
                            <td>
                                <?php 
                                echo esc_html(sprintf(
                                    '%s %s (%s)',
                                    $vehicle_make,
                                    $vehicle_model,
                                    $vehicle_plate
                                )); 
                                ?>
                            </td>
                            <td data-sort="<?php echo esc_attr($collection_date); ?>">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($collection_date))); ?>
                            </td>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_permalink($collection_id)); ?>" 
                                   class="button button-small">
                                    <?php _e('View', 'scrap-driver'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php
                    endwhile;
                endif;
                wp_reset_postdata();
                ?>
            </tbody>
        </table>
    </div>
</div>


<?php get_footer(); ?> 