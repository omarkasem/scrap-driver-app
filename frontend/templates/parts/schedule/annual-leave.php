<?php
$post_id = ScrapDriver\Frontend::get_driver_schedule();


// Get schedule data
$total_allowance = get_field('total_annual_leave_allowance_days', $post_id);
$requests = get_post_meta($post_id, 'holiday_requests', true);

?>

<div class="sda-section leave-allowance-box">
    <div class="leave-info">
        <h3>Annual Leave Information</h3>
    
        <?php
        $days_taken = 0;
        
        if (is_array($requests)) {
            foreach ($requests as $request) {
                if (isset($request['status']) && $request['status'] === 'approved') {
                    $days_taken += $request['days'];
                }
            }
        }
        
        $days_remaining = $total_allowance - $days_taken;
        ?>
    
        <div class="leave-summary">
            <div class="leave-stat">
                <span class="label"><?php _e('Total Allowance:', 'scrap-driver'); ?></span>
                <span class="value"><?php echo esc_html($total_allowance); ?> <?php _e('days', 'scrap-driver'); ?></span>
            </div>
            <div class="leave-stat">
                <span class="label"><?php _e('Days Taken:', 'scrap-driver'); ?></span>
                <span class="value"><?php echo esc_html($days_taken); ?> <?php _e('days', 'scrap-driver'); ?></span>
            </div>
            <div class="leave-stat">
                <span class="label"><?php _e('Days Remaining:', 'scrap-driver'); ?></span>
                <span class="value"><?php echo esc_html($days_remaining); ?> <?php _e('days', 'scrap-driver'); ?></span>
            </div>
        </div>
    </div>
</div>