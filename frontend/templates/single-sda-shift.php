<?php
/**
 * Template for displaying single shift
 */

get_header();

// Get current user info
$current_user_id = get_current_user_id();
$shift_driver_id = get_post_meta(get_the_ID(), 'assigned_driver', true);

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
    $shift_start = get_field('start_time');
    $shift_end = get_field('end_time');
    $collections = get_field('shift_collections');
    $completion_times = get_post_meta(get_the_ID(), 'collections_completed', true);
    ?>

    <div class="wrap sda-shift-single">
        <h1><?php the_title(); ?></h1>

        <div class="sda-shift-details">
            <div class="sda-section">
                <h2><?php _e('Shift Information', 'scrap-driver'); ?></h2>
                <div class="sda-info-grid">
                    <div class="sda-info-item">
                        <strong><?php _e('Start Time:', 'scrap-driver'); ?></strong>
                        <span><?php echo $shift_start; ?></span>
                    </div>
                    <?php if ($shift_end): ?>
                    <div class="sda-info-item">
                        <strong><?php _e('End Time:', 'scrap-driver'); ?></strong>
                        <span><?php echo $shift_end; ?></span>
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
                                <th><?php _e('Vehicle', 'scrap-driver'); ?></th>
                                <th><?php _e('Time Completed', 'scrap-driver'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collections as $collection): 
                                if ($collection):
                                    $collection_id = $collection->ID;
                                    $vehicle_make = get_field('vehicle_info_make', $collection_id);
                                    $vehicle_model = get_field('vehicle_info_model', $collection_id);
                                    $vehicle_plate = get_field('vehicle_info_plate', $collection_id);
                                    $completion_time = isset($completion_times[$collection_id]) ? $completion_times[$collection_id] : '';
                            ?>
                                <tr>
                                    <td><?php echo esc_html($collection_id); ?></td>
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
                                    <td><?php echo $completion_time ? date_i18n(get_option('time_format'), strtotime($completion_time)) : '-'; ?></td>
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

        <?php if ($current_user_id == $shift_driver_id): ?>
            <div class="sda-section">
                <?php 
                // Show success message if request was just submitted
                if (isset($_GET['adjustment_requested']) && $_GET['adjustment_requested'] == '1'): ?>
                    <div class="sda-notice sda-notice-success">
                        <p><?php _e('Your adjustment request has been submitted and sent to administrators for review.', 'scrap-driver'); ?></p>
                    </div>
                <?php endif; ?>

                <h2><?php _e('Request Shift Adjustment', 'scrap-driver'); ?></h2>
                
                <?php
                // Show existing requests
                $requests = get_post_meta(get_the_ID(), 'shift_adjustment_request', true);
                if (!empty($requests)) {
                    if (!isset($requests[0])) {
                        $requests = array($requests);
                    }
                    
                    echo '<div class="sda-previous-requests">';
                    echo '<h3>' . __('Previous Requests', 'scrap-driver') . '</h3>';
                    
                    foreach ($requests as $request) {
                        $status_class = 'status-' . $request['status'];
                        $status_label = ucfirst($request['status']);
                        ?>
                        <div class="sda-request-item">
                            <div class="request-meta">
                                <span class="request-date">
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), 
                                        strtotime($request['requested_at'])); ?>
                                </span>
                                <span class="request-status <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </div>
                            <div class="request-content">
                                <strong><?php _e('Request:', 'scrap-driver'); ?></strong>
                                <p><?php echo esc_html($request['comments']); ?></p>
                                
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

                <form method="post" class="sda-shift-adjustment-form">
                    <?php wp_nonce_field('shift_adjustment_request', 'shift_adjustment_nonce'); ?>
                    <input type="hidden" name="action" value="request_shift_adjustment">
                    <input type="hidden" name="shift_id" value="<?php echo get_the_ID(); ?>">
                    
                    <div class="form-field">
                        <label for="adjustment_comments"><?php _e('Reason for Adjustment:', 'scrap-driver'); ?></label>
                        <textarea id="adjustment_comments" name="adjustment_comments" rows="4" required></textarea>
                    </div>
                    
                    <button type="submit" class="button button-primary">
                        <?php _e('Submit Adjustment Request', 'scrap-driver'); ?>
                    </button>
                </form>
            </div>

            <style>
                .sda-notice {
                    padding: 12px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                }
                .sda-notice-success {
                    background-color: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                .sda-previous-requests {
                    margin-bottom: 30px;
                }
                .sda-request-item {
                    border: 1px solid #ddd;
                    padding: 15px;
                    margin-bottom: 15px;
                    border-radius: 4px;
                }
                .request-meta {
                    margin-bottom: 10px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .request-status {
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: 500;
                }
                .status-pending {
                    background: #ffeeba;
                    color: #856404;
                }
                .status-approved {
                    background: #d4edda;
                    color: #155724;
                }
                .status-denied {
                    background: #f8d7da;
                    color: #721c24;
                }
                .admin-response {
                    margin-top: 10px;
                    padding-top: 10px;
                    border-top: 1px solid #eee;
                }
            </style>
        <?php endif; ?>
    </div>

    <?php
endwhile;

get_footer(); 