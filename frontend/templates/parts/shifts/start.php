<?php
$current_user_id = get_current_user_id();

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

?>

<div class="wrap sda-shifts-list">
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

</div>