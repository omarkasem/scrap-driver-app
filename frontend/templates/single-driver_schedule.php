<?php
namespace ScrapDriver;

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

// Get current user and post data
$current_user_id = get_current_user_id();
$driver_id = get_post_meta(get_the_ID(), 'driver_id', true);

// Check if user has permission to view this schedule
if (!current_user_can('administrator') && $current_user_id != $driver_id) {
    wp_redirect(home_url());
    exit;
}

get_header();


// Get schedule data
$schedule = get_field('schedule', get_the_ID());
$total_leave = get_field('total_annual_leave_allowance_days', get_the_ID());
$leave_start = get_field('leave_year_start_date', get_the_ID());
$total_allowance = get_field('total_annual_leave_allowance_days', get_the_ID());
$requests = get_post_meta(get_the_ID(), 'holiday_requests', true);



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

<div class="wrap sda-driver-schedule">
    <h1 class="schedule-title"><?php echo get_the_title(); ?>'s Schedule</h1>
    
    <!-- Leave Allowance Section -->
    <div class="sda-section leave-allowance-box">
        <div class="leave-info">
            <h3>Annual Leave Information</h3>
            <p class="leave-days">
                <strong>Total Annual Leave:</strong> 
                <span class="days-count"><?php echo esc_html($total_leave); ?></span> days
            </p>
            <p class="leave-year">
                <strong>Leave Year Starts:</strong> 
                <?php echo date('F j, Y', strtotime($leave_start)); ?>
            </p>
        </div>
    </div>

    <!-- Weekly Schedule Section -->
    <div class="sda-section weekly-schedule">
        <h3>Weekly Working Schedule</h3>
        <div class="schedule-grid">
            <?php
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            foreach ($days as $day) {
                $status = isset($schedule[$day]) ? $schedule[$day] : 'Not Working';
                $status_class = strtolower(str_replace(' ', '-', $status));
                ?>
                <div class="schedule-day">
                    <h4><?php echo ucfirst($day); ?></h4>
                    <div class="status-badge <?php echo $status_class; ?>">
                        <?php echo esc_html($status); ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>

    <div class="sda-section holiday-requests">
        <h3><?php _e('Annual Leave', 'scrap-driver'); ?></h3>
        
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
                
                echo '<div class="holiday-previous-requests">';
                echo '<h4>' . __('Previous Requests', 'scrap-driver') . '</h4>';
                
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
                    <?php
                }
                echo '</div>';
            }
            ?>

            <h4><?php _e('Request Annual Leave', 'scrap-driver'); ?></h4>
            <form method="post" class="holiday-request-form">
                <?php 
                $today = date('Y-m-d');
                wp_nonce_field('holiday_request', 'holiday_request_nonce'); 
                ?>
                <input type="hidden" name="action" value="request_holiday">
                <input type="hidden" name="schedule_id" value="<?php echo get_the_ID(); ?>">
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="start_date"><?php _e('Start Date:', 'scrap-driver'); ?></label>
                        <input type="date" id="start_date" name="start_date" min="<?php echo $today; ?>" required>
                    </div>
                    
                    <div class="form-field">
                        <label for="end_date"><?php _e('End Date:', 'scrap-driver'); ?></label>
                        <input type="date" id="end_date" name="end_date" min="<?php echo $today; ?>" required>
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
        <?php endif; ?>
    </div>
</div>


<?php get_footer(); ?> 


<!-- Replace the existing JavaScript section with this simpler version -->
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
            $(endDate).datepicker('option', 'minDate', selectedDate);
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