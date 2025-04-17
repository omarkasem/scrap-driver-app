<?php
/**
 * Driver statistics filters template part
 */

// Get current user info
$current_user_id = get_current_user_id();
$is_admin = current_user_can( 'administrator' );

// Default date range (last 30 days)
$end_date = date( 'Y-m-d' );
$start_date = date( 'Y-m-d' );
?>

<div class="statistics-filters">
    <form id="statistics-filter-form">
        <?php wp_nonce_field( 'driver_statistics_nonce', 'statistics_nonce' ); ?>
        
        <div class="filter-row">
            <?php if ( $is_admin ) : ?>
                <div class="filter-group">
                    <label for="driver-selector"><?php _e( 'Select Drivers', 'scrap-driver' ); ?></label>
                    <?php include( plugin_dir_path( __FILE__ ) . 'driver-selector.php' ); ?>
                </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <label for="start-date"><?php _e( 'Start Date', 'scrap-driver' ); ?></label>
                <input type="date" id="start-date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" max="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
            </div>
            
            <div class="filter-group">
                <label for="end-date"><?php _e( 'End Date', 'scrap-driver' ); ?></label>
                <input type="date" id="end-date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" max="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
            </div>
            
            <div class="filter-group">
                <label for="interval"><?php _e( 'Group By', 'scrap-driver' ); ?></label>
                <select id="interval" name="interval">
                    <option value="day"><?php _e( 'Day', 'scrap-driver' ); ?></option>
                    <option value="week"><?php _e( 'Week', 'scrap-driver' ); ?></option>
                    <option value="month"><?php _e( 'Month', 'scrap-driver' ); ?></option>
                </select>
            </div>
            
            <div class="filter-group filter-actions" style="flex-direction: row;">
                <button type="submit" id="apply-filters" class="button button-primary"><?php _e( 'Apply Filters', 'scrap-driver' ); ?></button>
                <button type="button" id="reset-filters" class="button"><?php _e( 'Reset', 'scrap-driver' ); ?></button>
            </div>
        </div>
    </form>
</div> 