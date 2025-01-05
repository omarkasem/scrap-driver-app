<?php
/**
 * Template for displaying single collection
 */

get_header();

while (have_posts()) :
    the_post();
    $collection_id = get_the_ID();
    $vehicle_info = array(
        'make' => get_field('vehicle_info_make'),
        'model' => get_field('vehicle_info_model'),
        'year' => get_field('vehicle_info_year'),
        'plate' => get_field('vehicle_info_plate')
    );
    $customer = array(
        'name' => get_field('customer_name'),
        'phone' => get_field('phone')
    );
    $address = get_field('address');
    $status = get_post_meta($collection_id, '_collection_status', true);
    $notes = get_post_meta($collection_id, '_collection_notes', true);
    $photos = get_post_meta($collection_id, '_collection_photos', true);
    ?>

    <div class="wrap sda-collection-single">
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

            <!-- Status Update Form -->
            <div class="sda-section">
                <h2><?php _e('Update Status', 'scrap-driver'); ?></h2>
                <form id="sda-status-form" class="sda-form">
                    <select name="status" required>
                        <option value=""><?php _e('Select Status', 'scrap-driver'); ?></option>
                        <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                        <option value="in_progress" <?php selected($status, 'in_progress'); ?>>In Progress</option>
                        <option value="completed" <?php selected($status, 'completed'); ?>>Completed</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                    </select>
                    <textarea name="notes" placeholder="Add notes..."><?php echo esc_textarea($notes); ?></textarea>
                    <button type="submit" class="button button-primary">
                        <?php _e('Update Status', 'scrap-driver'); ?>
                    </button>
                </form>
            </div>

            <!-- Photo Upload -->
            <div class="sda-section">
                <h2><?php _e('Collection Photos', 'scrap-driver'); ?></h2>
                <form id="sda-photo-form" class="sda-form">
                    <input type="file" name="photo" accept="image/*" required>
                    <button type="submit" class="button">
                        <?php _e('Upload Photo', 'scrap-driver'); ?>
                    </button>
                </form>
                <div id="sda-photo-gallery" class="sda-gallery">
                    <?php
                    if (!empty($photos)) {
                        foreach ($photos as $photo_id) {
                            echo wp_get_attachment_image($photo_id, 'medium');
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- Complete Collection -->
            <div class="sda-section">
                <button id="sda-complete-collection" class="button button-primary button-large">
                    <?php _e('Complete Collection', 'scrap-driver'); ?>
                </button>
            </div>
        </div>
    </div>

    <?php
endwhile;

get_footer(); 