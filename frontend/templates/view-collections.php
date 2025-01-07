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

// Enqueue DataTables
wp_enqueue_style('datatables', SCRAP_DRIVER_PLUGIN_DIR . 'frontend/assets/css/datatables.min.css');
wp_enqueue_script('datatables', SCRAP_DRIVER_PLUGIN_DIR . 'frontend/assets/js/datatables.min.js', array('jquery'), null, true);

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
        <table id="collections-table" class="display">
            <thead>
                <tr>
                    <th><?php _e('ID', 'scrap-driver'); ?></th>
                    <th><?php _e('Customer', 'scrap-driver'); ?></th>
                    <th><?php _e('Vehicle', 'scrap-driver'); ?></th>
                    <th><?php _e('Collection Date', 'scrap-driver'); ?></th>
                    <th><?php _e('Status', 'scrap-driver'); ?></th>
                    <th><?php _e('Actions', 'scrap-driver'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($collections->have_posts()) :
                    while ($collections->have_posts()) : $collections->the_post();
                        $collection_id = get_the_ID();
                        $customer_name = get_field('customer_name');
                        $vehicle_make = get_field('vehicle_info_make');
                        $vehicle_model = get_field('vehicle_info_model');
                        $vehicle_plate = get_field('vehicle_info_plate');
                        $collection_date = get_field('collection_date');
                        $status = get_post_meta($collection_id, '_collection_status', true) ?: 'pending';
                        ?>
                        <tr>
                            <td><?php echo esc_html($collection_id); ?></td>
                            <td><?php echo esc_html($customer_name); ?></td>
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

<script>
jQuery(document).ready(function($) {
    $('#collections-table').DataTable({
        order: [[3, 'asc']], // Sort by collection date by default
        pageLength: 25,
        language: {
            search: "<?php _e('Search:', 'scrap-driver'); ?>",
            lengthMenu: "<?php _e('Show _MENU_ entries', 'scrap-driver'); ?>",
            info: "<?php _e('Showing _START_ to _END_ of _TOTAL_ entries', 'scrap-driver'); ?>",
            infoEmpty: "<?php _e('Showing 0 to 0 of 0 entries', 'scrap-driver'); ?>",
            infoFiltered: "<?php _e('(filtered from _MAX_ total entries)', 'scrap-driver'); ?>",
            emptyTable: "<?php _e('No collections available', 'scrap-driver'); ?>",
            paginate: {
                first: "<?php _e('First', 'scrap-driver'); ?>",
                last: "<?php _e('Last', 'scrap-driver'); ?>",
                next: "<?php _e('Next', 'scrap-driver'); ?>",
                previous: "<?php _e('Previous', 'scrap-driver'); ?>"
            }
        }
    });
});
</script>

<?php get_footer(); ?> 