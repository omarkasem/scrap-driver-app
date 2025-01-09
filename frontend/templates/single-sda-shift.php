<?php
/**
 * Template for displaying single shift
 */

get_header();

// Get current user info
$current_user_id = get_current_user_id();
$shift_driver_id = get_post_meta(get_the_ID(), 'driver_id', true);

// Check if user has permission to view
if ($current_user_id != $shift_driver_id && !current_user_can('manage_options')) {
    ?>
    <div class="wrap sda-shift-single">
        <div class="sda-section sda-error-message">
            <h1><?php _e('Access Denied', 'scrap-driver'); ?></h1>
            <p><?php _e('Sorry, you do not have permission to view this shift.', 'scrap-driver'); ?></p>
            <a href="<?php echo esc_url(home_url()); ?>" class="button">
                <?php _e('Return to Homepage', 'scrap-driver'); ?>
            </a>
        </div>
    </div>
    <?php
    get_footer();
    exit;
}

while (have_posts()) : the_post();
    $shift_start = get_post_meta(get_the_ID(), 'shift_start', true);
    $shift_end = get_post_meta(get_the_ID(), 'shift_end', true);
    $collections = get_post_meta(get_the_ID(), 'collections_completed', true);
    ?>

    <div class="wrap sda-shift-single">
        <h1><?php the_title(); ?></h1>

        <div class="sda-shift-details">
            <div class="sda-section">
                <h2><?php _e('Shift Information', 'scrap-driver'); ?></h2>
                <div class="sda-info-grid">
                    <div class="sda-info-item">
                        <strong><?php _e('Start Time:', 'scrap-driver'); ?></strong>
                        <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($shift_start)); ?></span>
                    </div>
                    <?php if ($shift_end): ?>
                    <div class="sda-info-item">
                        <strong><?php _e('End Time:', 'scrap-driver'); ?></strong>
                        <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($shift_end)); ?></span>
                    </div>
                    <div class="sda-info-item">
                        <strong><?php _e('Duration:', 'scrap-driver'); ?></strong>
                        <span>
                            <?php 
                            $duration = strtotime($shift_end) - strtotime($shift_start);
                            echo sprintf(
                                __('%d hours %d minutes', 'scrap-driver'),
                                floor($duration / 3600),
                                floor(($duration % 3600) / 60)
                            );
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sda-section">
                <h2><?php _e('Collections Completed', 'scrap-driver'); ?></h2>
                <?php if (!empty($collections)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Collection ID', 'scrap-driver'); ?></th>
                                <th><?php _e('Customer', 'scrap-driver'); ?></th>
                                <th><?php _e('Vehicle', 'scrap-driver'); ?></th>
                                <th><?php _e('Time Completed', 'scrap-driver'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collections as $collection_id => $completion_time): 
                                $collection = get_post($collection_id);
                                if ($collection):
                                    $customer_name = get_field('customer_name', $collection_id);
                                    $vehicle_make = get_field('vehicle_info_make', $collection_id);
                                    $vehicle_model = get_field('vehicle_info_model', $collection_id);
                                    $vehicle_plate = get_field('vehicle_info_plate', $collection_id);
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
                                    <td><?php echo date_i18n(get_option('time_format'), strtotime($completion_time)); ?></td>
                                </tr>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No collections completed during this shift.', 'scrap-driver'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
endwhile;

get_footer(); 