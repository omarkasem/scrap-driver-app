<?php
/**
 * Template for displaying single collection
 */

use ScrapDriver\Frontend\Collection;

// Process start collection - Move this to the top before any output
if (isset($_POST['start_collection']) && isset($_POST['start_collection_nonce']) && 
    wp_verify_nonce($_POST['start_collection_nonce'], 'start_collection')) {
    Collection::start_collection(get_the_ID());
    wp_safe_redirect(add_query_arg('started', 'true', get_permalink()));
    exit;
}

acf_form_head();
get_header();
while (have_posts()) :
    the_post();
    $collection_id = get_the_ID();
    
    // Get the assigned driver ID from user meta
    $assigned_driver_id = get_field('assigned_driver');
    $current_user_id = get_current_user_id();
    
    // Check if user has permission to view/edit
    $can_edit = current_user_can('manage_options') || 
                ($assigned_driver_id && $assigned_driver_id == $current_user_id);
    
    // Add check for active shift for drivers
    if ($assigned_driver_id && $assigned_driver_id == $current_user_id && !current_user_can('manage_options')) {
        $has_active_shift = get_user_meta($current_user_id, 'current_shift_id', true);
        if (!$has_active_shift) {
            ?>
            <div class="wrap sda-collection-single">
                <div class="sda-section sda-error-message">
                    <h1><?php _e('No Active Shift', 'scrap-driver'); ?></h1>
                    <p><?php _e('You must start your shift before viewing collections.', 'scrap-driver'); ?></p>
                    <?php
                    $shifts_page = get_pages(array(
                        'meta_key' => '_wp_page_template',
                        'meta_value' => 'view-shifts.php'
                    ));
                    if (!empty($shifts_page)) {
                        $shifts_page_url = get_permalink($shifts_page[0]->ID);
                    } else {
                        $shifts_page_url = home_url();
                    }
                    ?>
                    <a href="<?php echo esc_url($shifts_page_url); ?>" class="button">
                        <?php _e('Go to My Shifts Page', 'scrap-driver'); ?>
                    </a>
                </div>
            </div>
            <?php
            get_footer();
            exit;
        }
    }
    
    if (!$can_edit) {
        ?>
        <div class="wrap sda-collection-single">
            <div class="sda-section sda-error-message">
                <h1><?php _e('Access Denied', 'scrap-driver'); ?></h1>
                <p><?php _e('Sorry, you do not have permission to view this collection. Only administrators and the assigned driver can access this page.', 'scrap-driver'); ?></p>
                <a href="<?php echo esc_url(home_url()); ?>" class="button">
                    <?php _e('Return to Homepage', 'scrap-driver'); ?>
                </a>
            </div>
        </div>
        <?php
        get_footer();
        exit;
    }
    
    $vehicle_info = array(
        'make' => get_field('vehicle_info_make'),
        'model' => get_field('vehicle_info_model'),
        'year' => get_field('vehicle_info_year'),
        'plate' => get_field('vehicle_info_plate')
    );
    $customer = array(
        'name' => get_field('customer_info_name'),
        'phone' => get_field('customer_info_phone')
    );
    $address = get_field('customer_info_address');
    ?>

    <div class="wrap sda-collection-single">
        <?php if (isset($_GET['updated']) && $_GET['updated'] == 'true') : ?>
            <div class="sda-notice sda-notice-success">
                <?php _e('Collection updated successfully.', 'scrap-driver'); ?>
            </div>
            <script>
                if (window.history.replaceState) {
                    const url = new URL(window.location);
                    url.searchParams.delete('updated');
                    window.history.replaceState({}, document.title, url.toString());
                }
            </script>
        <?php endif; ?>

        <?php if (isset($_GET['started']) && $_GET['started'] == 'true') : ?>
            <div class="sda-notice sda-notice-success">
                <?php _e('Collection started successfully.', 'scrap-driver'); ?>
            </div>
            <script>
                if (window.history.replaceState) {
                    const url = new URL(window.location);
                    url.searchParams.delete('started');
                    window.history.replaceState({}, document.title, url.toString());
                }
            </script>
        <?php endif; ?>

        <?php if (isset($_POST['complete_collection']) && wp_verify_nonce($_POST['complete_collection_nonce'], 'complete_collection')) {?>
            <div class="sda-notice sda-notice-success">
                <?php _e('Collection completed successfully.', 'scrap-driver'); ?>
            </div>
        <?php } ?>


        <?php
        $collection_status = get_field('status', $collection_id);
        
        // Show start collection button if not started
        if (!in_array($collection_status, array('Completed', 'Collection in Progress'))) {
            $can_start = Collection::can_start_collection($collection_id, $current_user_id);
            
            if ($can_start === true) {
                ?>
                <div class="sda-section sda-start-collection">
                    <form method="POST">
                        <?php wp_nonce_field('start_collection', 'start_collection_nonce'); ?>
                        <button type="submit" name="start_collection" class="button button-primary button-large">
                            <?php _e('Start Collection', 'scrap-driver'); ?>
                        </button>
                    </form>
                </div>
                <?php
            } else {
                ?>
                <div class="sda-section sda-error-message">
                    <p><?php echo $can_start; ?></p>
                </div>
                <?php
            }
        }
        ?>

        <h1><?php the_title(); ?></h1>

        <div class="sda-collection-details">
            <!-- Vehicle Information -->
            <div class="sda-section">
                <h2><?php _e('Vehicle Information', 'scrap-driver'); ?></h2>
                <div class="sda-info-grid">
                    <?php foreach ($vehicle_info as $key => $value) : ?>
                        <div class="sda-info-item">
                            <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</strong>
                            <span><?php echo esc_html($value); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Customer Details -->
            <div class="sda-section">
                <h2><?php _e('Customer Details', 'scrap-driver'); ?></h2>
                <div class="sda-info-grid">
                    <?php foreach ($customer as $key => $value) : ?>
                        <div class="sda-info-item">
                            <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</strong>
                            <span class="copyable" data-copy="<?php echo esc_attr($value); ?>">
                                <?php echo esc_html($value); ?>
                                <button class="copy-btn" title="<?php esc_attr_e('Copy to clipboard', 'scrap-driver'); ?>">📋</button>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Address -->
            <div class="sda-section">
                <h2><?php _e('Collection Address', 'scrap-driver'); ?></h2>
                <div class="sda-address">
                    <p class="copyable" data-copy="<?php echo esc_attr($address); ?>">
                        <?php echo nl2br(esc_html($address)); ?>
                        <button class="copy-btn" title="<?php esc_attr_e('Copy to clipboard', 'scrap-driver'); ?>">📋</button>
                    </p>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($address); ?>"
                       class="button" target="_blank">
                        <?php _e('Open in Google Maps', 'scrap-driver'); ?>
                    </a>
                </div>
            </div>

            <div class="edit-section" <?php echo (!in_array($collection_status, array('Completed', 'Collection in Progress'))) ? ' style="display: none;"' : ''; ?>>
                <?php if ($can_edit) :
                    // Handle form submission
                    if (isset($_POST['complete_collection']) && wp_verify_nonce($_POST['complete_collection_nonce'], 'complete_collection')) {
                        // Trigger collection completion action
                        do_action('sda_collection_completed', $collection_id, $current_user_id);

                    }
                    
                    echo '<div class="sda-section">';
                    // Show ACF form
                    acf_form([
                        'fields'=>['status','driver_notes','driver_uploaded_photos'],
                        'uploader' => 'basic',
                        'new_post' => false,
                        'updated_message' => '',
                    ]); 
                    echo '</div>';
                    
                    // Only show complete button if collection is not already completed
                    $status = get_field('status', $collection_id);
                    if ($status !== 'Completed') :
                    ?>
                        <!-- Complete Collection -->
                        <div class="sda-section">
                            <form method="POST" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to complete this collection?', 'scrap-driver')); ?>');">
                                <?php wp_nonce_field('complete_collection', 'complete_collection_nonce'); ?>
                                <button type="submit" name="complete_collection" class="button button-primary button-large">
                                    <?php _e('Complete Collection', 'scrap-driver'); ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
endwhile;

get_footer(); 