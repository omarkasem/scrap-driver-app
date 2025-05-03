<?php
$post_id = ScrapDriver\Frontend::get_driver_schedule();


// Get schedule data
$schedule = get_field('schedule', $post_id);

?>
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