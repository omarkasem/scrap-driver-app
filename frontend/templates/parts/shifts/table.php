<?php
$current_user_id = get_current_user_id();


// Get all shifts for this driver
$args = array(
    'post_type' => 'sda-shift',
    'posts_per_page' => -1,
    'meta_query' => array(
        array(
            'key' => 'assigned_driver',
            'value' => $current_user_id
        )
    ),
);

$shifts = new WP_Query($args);
?>

<div class="wrap sda-shifts-list">
    <div class="sda-section">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Shift Date', 'scrap-driver'); ?></th>
                    <th><?php _e('Start Time', 'scrap-driver'); ?></th>
                    <th><?php _e('End Time', 'scrap-driver'); ?></th>
                    <th><?php _e('Collections', 'scrap-driver'); ?></th>
                    <th><?php _e('Actions', 'scrap-driver'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($shifts->have_posts()): while ($shifts->have_posts()): $shifts->the_post(); 
                    $shift_date = get_field('shift_date');
                    $shift_start = get_field('start_time');
                    $shift_end = get_field('end_time');
                    $completed_collections_count = ScrapDriver\Admin\Collection::get_collection_number_completed_by_driver(get_the_ID());
                ?>
                    <tr>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($shift_date)); ?></td>
                        <td><?php echo ($shift_start) ? date_i18n(get_option('time_format'), strtotime($shift_start)) : '-'; ?></td>
                        <td><?php echo ($shift_end) ? date_i18n(get_option('time_format'), strtotime($shift_end)) : '-'; ?></td>
                        <td><?php echo $completed_collections_count; ?></td>
                        <td>
                            <a href="<?php echo get_permalink(); ?>" class="button button-small">
                                <?php _e('View Details', 'scrap-driver'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr>
                        <td colspan="5"><?php _e('No shifts found', 'scrap-driver'); ?></td>
                    </tr>
                <?php endif; wp_reset_postdata(); ?>
            </tbody>
        </table>
    </div>
</div>