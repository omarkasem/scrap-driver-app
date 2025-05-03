<?php

$post_id = ScrapDriver\Frontend::get_driver_schedule();
// Get current user and post data
$current_user_id = get_current_user_id();
$driver_id = get_current_user_id();

// Get schedule data
$requests = get_post_meta($post_id, 'holiday_requests', true);

// Get all approved holiday dates
$approved_dates = array();
if (is_array($requests)) {
    foreach ($requests as $request) {
        if (isset($request['status']) && $request['status'] === 'approved') {
            $start = new \DateTime($request['start_date']);
            $end = new \DateTime($request['end_date']);
            $end->modify('+1 day');
            $interval = new \DateInterval('P1D');
            $daterange = new \DatePeriod($start, $interval, $end);
            
            foreach ($daterange as $date) {
                if ($date->format('N') < 6) { // Only add weekdays
                    $approved_dates[] = $date->format('Y-m-d');
                }
            }
        }
    }
}

// Convert to JSON for JavaScript
$approved_dates_json = json_encode($approved_dates);

?>

<div class="sda-section holiday-requests">
    <h3><?php _e('Holiday Requests', 'scrap-driver'); ?></h3>

    <?php if ($current_user_id == $driver_id): ?>
        <?php 
        // Show success message if request was just submitted
        if (isset($_GET['holiday_requested']) && $_GET['holiday_requested'] == '1'): ?>
            <div class="sda-notice sda-notice-success">
                <p><?php _e('Your holiday request has been submitted and sent to administrators for review.', 'scrap-driver'); ?></p>
            </div>
        <?php endif; ?>

        <?php
        // Show existing requests
        if (!empty($requests)) {
            if (!isset($requests[0])) {
                $requests = array($requests);
            }
            
            // Sort requests by start date (newest first)
            usort($requests, function($a, $b) {
                return strtotime($b['start_date']) - strtotime($a['start_date']);
            });
            
            echo '<div class="sda-accordion-section">';
            echo '<div class="sda-accordion-header">';
            echo '<h2>' . __('Previous Requests', 'scrap-driver') . '</h2>';
            echo '<div class="sda-accordion-icon"></div>';
            echo '</div>';
            
            echo '<div class="sda-accordion-content">';
            foreach ($requests as $request) {
                $status_class = 'status-' . $request['status'];
                $status_label = ucfirst($request['status']);
                ?>
                <div class="holiday-request-item <?php echo esc_attr($status_class); ?>">
                    <div class="request-meta">
                        <span class="request-period">
                            <?php echo date_i18n(get_option('date_format'), strtotime($request['start_date'])); ?> - 
                            <?php echo date_i18n(get_option('date_format'), strtotime($request['end_date'])); ?>
                            (<?php echo $request['days']; ?> <?php _e('days', 'scrap-driver'); ?>)
                        </span>
                        <span class="request-status">
                            <?php echo esc_html($status_label); ?>
                        </span>
                    </div>
                    <div class="request-content">
                        <p class="request-comments"><?php echo esc_html($request['comments']); ?></p>
                        <?php if (!empty($request['admin_response'])): ?>
                            <div class="admin-response">
                                <strong><?php _e('Admin Response:', 'scrap-driver'); ?></strong>
                                <p><?php echo esc_html($request['admin_response']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <hr class="holiday-request-divider">
                <?php
            }
            echo '</div>'; // Close accordion content
            echo '</div>'; // Close accordion section
        }
        ?>

        <div class="sda-accordion-section">
            <div class="sda-accordion-header">
                <h2><?php _e('Request Annual Leave', 'scrap-driver'); ?></h2>
                <div class="sda-accordion-icon"></div>
            </div>
            <div class="sda-accordion-content">
                <form method="post" class="holiday-request-form">
                    <?php 
                    $today = date('Y-m-d');
                    wp_nonce_field('holiday_request', 'holiday_request_nonce'); 
                    ?>
                    <input type="hidden" name="action" value="request_holiday">
                    <input type="hidden" name="schedule_id" value="<?php echo $post_id; ?>">
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="start_date"><?php _e('Start Date:', 'scrap-driver'); ?></label>
                            <input type="text" id="start_date" name="start_date" min="<?php echo $today; ?>" required>
                        </div>
                        
                        <div class="form-field">
                            <label for="end_date"><?php _e('End Date:', 'scrap-driver'); ?></label>
                            <input type="text" id="end_date" name="end_date" min="<?php echo $today; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-field">
                        <label for="comments"><?php _e('Comments:', 'scrap-driver'); ?></label>
                        <textarea id="comments" name="comments" rows="4" required></textarea>
                    </div>
                    
                    <button type="submit" class="button button-primary">
                        <?php _e('Submit Request', 'scrap-driver'); ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Replace the entire script section at the bottom of the file -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const approvedDates = <?php echo $approved_dates_json; ?>;
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    // Function to check if a date should be disabled
    function isDateDisabled(dateStr) {
        const date = new Date(dateStr);
        const day = date.getDay();
        
        // Disable weekends
        if (day === 0 || day === 6) {
            return true;
        }
        
        // Disable approved holiday dates
        return approvedDates.includes(dateStr);
    }

    // Set min date to today
    const today = new Date().toISOString().split('T')[0];
    startDate.min = today;
    endDate.min = today;

    // Initialize datepickers
    jQuery(startDate).datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: 0,
        beforeShowDay: function(date) {
            const dateStr = date.toISOString().split('T')[0];
            return [!isDateDisabled(dateStr), ''];
        },
        onSelect: function(selectedDate) {
            jQuery(endDate).datepicker('option', 'minDate', selectedDate);
        }
    });

    jQuery(endDate).datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: 0,
        beforeShowDay: function(date) {
            const dateStr = date.toISOString().split('T')[0];
            return [!isDateDisabled(dateStr), ''];
        }
    });
});
</script>