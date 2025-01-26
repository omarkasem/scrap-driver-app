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

?>

<div class="driver-schedule-wrapper">
    <div class="container">
        <h1 class="schedule-title"><?php echo get_the_title(); ?>'s Schedule</h1>
        
        <!-- Leave Allowance Section -->
        <div class="leave-allowance-box">
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
        <div class="weekly-schedule">
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
    </div>
</div>


<?php get_footer(); ?> 