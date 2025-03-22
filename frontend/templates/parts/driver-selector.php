<?php
/**
 * Driver selector template part
 */

// Get all drivers
$driver_users = get_users( array( 'role' => 'driver' ) );
?>

<select id="driver-selector" name="driver_ids[]" multiple="multiple">
    <?php foreach ( $driver_users as $driver ) : ?>
        <option value="<?php echo esc_attr( $driver->ID ); ?>">
            <?php echo esc_html( $driver->display_name ); ?>
        </option>
    <?php endforeach; ?>
</select>
<p class="description"><?php _e( 'Hold Ctrl/Cmd to select multiple drivers', 'scrap-driver' ); ?></p> 