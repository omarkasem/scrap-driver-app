<?php
/**
 * Template Name: Driver Shifts List
 */

get_header();

// Get current user info
$current_user = wp_get_current_user();
$current_user_id = get_current_user_id();
$is_driver = in_array('driver', $current_user->roles);

// Check if user has permission to view
if (!$is_driver) {
    ?>
    <div class="wrap sda-shifts-list">
        <div class="sda-section sda-error-message">
            <h1><?php _e('Access Denied', 'scrap-driver'); ?></h1>
            <p><?php _e('Sorry, you do not have permission to view shifts. Only drivers can access this page.', 'scrap-driver'); ?></p>
            <a href="<?php echo esc_url(home_url()); ?>" class="button">
                <?php _e('Return to Homepage', 'scrap-driver'); ?>
            </a>
        </div>
    </div>
    <?php
    get_footer();
    exit;
}

// Get active shift status
$active_shift_start = get_user_meta($current_user_id, 'shift_start_time', true);
$active_shift = !empty($active_shift_start);

// Check if user has a shift today
$today = date('Ymd');
$today_shift = get_posts(array(
    'post_type' => 'sda-shift',
    'meta_query' => array(
        'relation' => 'AND',
        array(
            'key' => 'assigned_driver',
            'value' => $current_user_id
        ),
        array(
            'key' => 'shift_date',
            'value' => $today
        )
    ),
    'posts_per_page' => 1
));

$has_shift_today = !empty($today_shift);

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
    <h1><?php _e('My Shifts', 'scrap-driver'); ?></h1>

    <div class="sda-section">
        <?php 
        // Get the end time for today's shift if it exists
        $shift_end_time = '';
        if ($has_shift_today && !empty($today_shift)) {
            $shift_end_time = get_field('end_time', $today_shift[0]->ID);
        }

        if ($has_shift_today && empty($shift_end_time)): ?>
            <form method="post" class="sda-shift-control">
                <?php if (!$active_shift): ?>
                    <input type="hidden" name="shift_action" value="start">
                    <button type="submit" class="button button-primary">
                        <?php _e('Start Shift', 'scrap-driver'); ?>
                    </button>
                <?php else: ?>
                    <input type="hidden" name="shift_action" value="end">
                    <button type="submit" class="button button-primary">
                        <?php _e('End Shift', 'scrap-driver'); ?>
                    </button>
                    <p class="shift-status">
                        <?php 
                        printf(
                            __('Shift started at: %s', 'scrap-driver'),
                            date_i18n(get_option('time_format'), strtotime($active_shift_start))
                        ); 
                        ?>
                    </p>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div class="sda-notice sda-notice-info">
                <p><?php _e('You don\'t have any active shifts assigned for today.', 'scrap-driver'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="sda-section">
        <h2><?php _e('My Past Shifts', 'scrap-driver'); ?></h2>
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
                    $shift_start = get_field('start_time');
                    $shift_end = get_field('end_time');
                    $collections = get_field('shift_collections');
                    $collections_count = is_array($collections) ? count($collections) : 0;
                ?>
                    <tr>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($shift_start)); ?></td>
                        <td><?php echo date_i18n(get_option('time_format'), strtotime($shift_start)); ?></td>
                        <td><?php echo $shift_end ? date_i18n(get_option('time_format'), strtotime($shift_end)) : '-'; ?></td>
                        <td><?php echo $collections_count; ?></td>
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

<?php get_footer(); ?> 