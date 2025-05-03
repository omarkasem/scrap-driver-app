<?php
/**
 * Access denied template part
 */
?>

<div class="wrap sda-collection-single">
    <div class="sda-section sda-error-message">
        <h1><?php _e('Access Denied', 'scrap-driver'); ?></h1>
        <p><?php _e('Sorry, you do not have permission to view this page. Only administrators and drivers can access this page.', 'scrap-driver'); ?></p>
        <a href="<?php echo esc_url(home_url()); ?>" class="button">
            <?php _e('Return to Homepage', 'scrap-driver'); ?>
        </a>
    </div>
</div>