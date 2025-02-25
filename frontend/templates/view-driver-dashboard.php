<?php
/**
 * Template Name: Driver Dashboard
 * 
 * Template for viewing combined shifts and collections
 */

get_header();

// Get current user info
$current_user = wp_get_current_user();
$current_user_id = get_current_user_id();
$is_driver = in_array( 'driver', $current_user->roles );
$is_admin = current_user_can( 'manage_options' );

// Check if user has permission to view
if ( !$is_admin && !$is_driver ) {
    ?>
    <div class="wrap sda-dashboard">
        <div class="sda-section sda-error-message">
            <h1><?php _e( 'Access Denied', 'scrap-driver' ); ?></h1>
            <p><?php _e( 'Sorry, you do not have permission to view this page. Only administrators and drivers can access this page.', 'scrap-driver' ); ?></p>
            <a href="<?php echo esc_url( home_url() ); ?>" class="button">
                <?php _e( 'Return to Homepage', 'scrap-driver' ); ?>
            </a>
        </div>
    </div>
    <?php
    get_footer();
    exit;
}

// Get active shift status
$active_shift_start = get_user_meta( $current_user_id, 'shift_start_time', true );
$active_shift = !empty( $active_shift_start );

// Check if user has a shift today
$today = date( 'Ymd' );
$today_shift = get_posts( array(
    'post_type' => 'sda-shift',
    'meta_query' => array(
        'relation' => 'AND',
        array(
            'key' => 'assigned_driver',
            'value' => $current_user_id
        ),
        array(
            'key' => 'shift_date',
            'value' => $today
        )
    ),
    'posts_per_page' => 1
) );

$has_shift_today = !empty( $today_shift );

// Get all shifts for this driver
$shifts_args = array(
    'post_type' => 'sda-shift',
    'posts_per_page' => -1,
    'meta_query' => array(
        array(
            'key' => 'assigned_driver',
            'value' => $current_user_id
        )
    ),
);

$shifts = new WP_Query( $shifts_args );

// Get today's collections
$today_collections_args = array(
    'post_type' => 'sda-collection',
    'posts_per_page' => -1,
    'orderby' => 'meta_value',
    'meta_key' => 'collection_date',
    'order' => 'ASC',
    'meta_query' => array(
        array(
            'key' => 'collection_date',
            'value' => date( 'Y-m-d' ),
            'compare' => '=',
            'type' => 'DATE'
        )
    )
);

// If user is a driver, only show their assigned collections
if ( $is_driver && !$is_admin ) {
    $today_collections_args['meta_query'][] = array(
        'key' => 'assigned_driver',
        'value' => $current_user_id,
        'compare' => '='
    );
}

$today_collections = new WP_Query( $today_collections_args );

// Get all collections
$all_collections_args = array(
    'post_type' => 'sda-collection',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC'
);

// If user is a driver, only show their assigned collections
if ( $is_driver && !$is_admin ) {
    $all_collections_args['meta_query'] = array(
        array(
            'key' => 'assigned_driver',
            'value' => $current_user_id,
            'compare' => '='
        )
    );
}

$all_collections = new WP_Query( $all_collections_args );

// DataTable translations
$datatable_translations = array(
    'search' => __( 'Search:', 'scrap-driver' ),
    'lengthMenu' => __( 'Show _MENU_ entries', 'scrap-driver' ),
    'info' => __( 'Showing _START_ to _END_ of _TOTAL_ entries', 'scrap-driver' ),
    'infoEmpty' => __( 'Showing 0 to 0 of 0 entries', 'scrap-driver' ),
    'infoFiltered' => __( '(filtered from _MAX_ total entries)', 'scrap-driver' ),
    'emptyTable' => __( 'No data available', 'scrap-driver' ),
    'first' => __( 'First', 'scrap-driver' ),
    'last' => __( 'Last', 'scrap-driver' ),
    'next' => __( 'Next', 'scrap-driver' ),
    'previous' => __( 'Previous', 'scrap-driver' )
);
?>

<div class="wrap sda-dashboard">
    <h1><?php _e( 'Driver Dashboard', 'scrap-driver' ); ?></h1>

    <script>
        var sdaDataTableTranslations = <?php echo json_encode( $datatable_translations ); ?>;
    </script>

    <!-- Today's Shift Section (Open by default) -->
    <div class="sda-accordion-section open">
        <div class="sda-accordion-header">
            <h2><?php _e( "Today's Shift", 'scrap-driver' ); ?></h2>
            <span class="sda-accordion-icon"></span>
        </div>
        <div class="sda-accordion-content">
            <div class="sda-section">
                <?php 
                // Get the end time for today's shift if it exists
                $shift_end_time = '';
                $shift_start_time = '';
                if ( $has_shift_today && !empty( $today_shift ) ) {
                    $shift_end_time = get_field( 'end_time', $today_shift[0]->ID );
                    $shift_start_time = get_field( 'start_time', $today_shift[0]->ID );
                }

                // Check if shift is active based on both user meta and shift post data
                $active_shift = !empty( $active_shift_start ) && !empty( $shift_start_time ) && empty( $shift_end_time );

                if ( $has_shift_today && empty( $shift_end_time ) ): ?>
                    <form method="post" class="sda-shift-control">
                        <?php if ( empty( $shift_start_time ) ): ?>
                            <input type="hidden" name="shift_action" value="start">
                            <button type="submit" class="button button-primary">
                                <?php _e( 'Start Shift', 'scrap-driver' ); ?>
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="shift_action" value="end">
                            <button type="submit" class="button button-primary">
                                <?php _e( 'End Shift', 'scrap-driver' ); ?>
                            </button>
                            <p class="shift-status">
                                <?php 
                                printf(
                                    __( 'Shift started at: %s', 'scrap-driver' ),
                                    date_i18n( get_option( 'time_format' ), strtotime( $shift_start_time ) )
                                ); 
                                ?>
                            </p>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <div class="sda-notice sda-notice-info">
                        <p><?php _e( "You don't have any active shifts assigned for today.", 'scrap-driver' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Today's Collections Section -->
    <div class="sda-accordion-section open">
        <div class="sda-accordion-header">
            <h2><?php _e( "Today's Collections", 'scrap-driver' ); ?></h2>
            <span class="sda-accordion-icon"></span>
        </div>
        <div class="sda-accordion-content">
            <div class="sda-section">
                <table id="today-collections-table" class="display">
                    <thead>
                        <tr>
                            <th><?php _e( 'Order', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Driver', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Vehicle', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Status', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Actions', 'scrap-driver' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ( $today_collections->have_posts() ) :
                            // Get all collection IDs for ordering
                            $collection_ids = wp_list_pluck( $today_collections->posts, 'ID' );
                            $collection_orders = \ScrapDriver\Frontend\Collection::get_collections_order( $collection_ids );

                            while ( $today_collections->have_posts() ) : $today_collections->the_post();
                                $collection_id = get_the_ID();
                                $driver_id = get_field( 'assigned_driver' );
                                $driver_name = $driver_id ? get_user_by( 'id', $driver_id )->display_name : __( 'Unassigned', 'scrap-driver' );
                                $vehicle_make = get_field( 'vehicle_info_make' );
                                $vehicle_model = get_field( 'vehicle_info_model' );
                                $vehicle_plate = get_field( 'vehicle_info_plate' );
                                $status = get_post_meta( $collection_id, 'status', true );
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $collection_orders[$collection_id] ); ?></td>
                                    <td><?php echo esc_html( $driver_name ); ?></td>
                                    <td>
                                        <?php 
                                        echo esc_html( sprintf(
                                            '%s %s (%s)',
                                            $vehicle_make,
                                            $vehicle_model,
                                            $vehicle_plate
                                        ) ); 
                                        ?>
                                    </td>
                                    <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( get_permalink( $collection_id ) ); ?>" 
                                           class="button button-small">
                                            <?php _e( 'View', 'scrap-driver' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php
                            endwhile;
                        endif;
                        wp_reset_postdata();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- All Shifts Section -->
    <div class="sda-accordion-section">
        <div class="sda-accordion-header">
            <h2><?php _e( 'All Shifts', 'scrap-driver' ); ?></h2>
            <span class="sda-accordion-icon"></span>
        </div>
        <div class="sda-accordion-content">
            <div class="sda-section">
                <table id="shifts-table" class="display">
                    <thead>
                        <tr>
                            <th><?php _e( 'Shift Date', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Start Time', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'End Time', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Collections', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Actions', 'scrap-driver' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $shifts->have_posts() ): while ( $shifts->have_posts() ): $shifts->the_post(); 
                            $shift_date = get_field( 'shift_date' );
                            $shift_start = get_field( 'start_time' );
                            $shift_end = get_field( 'end_time' );
                            $completed_collections_count = ScrapDriver\Admin\Collection::get_collection_number_completed_by_driver( get_the_ID() );
                        ?>
                            <tr>
                                <td data-sort="<?php echo esc_attr( $shift_date ); ?>">
                                    <?php echo date_i18n( get_option( 'date_format' ), strtotime( $shift_date ) ); ?>
                                </td>
                                <td><?php echo ( $shift_start ) ? date_i18n( get_option( 'time_format' ), strtotime( $shift_start ) ) : '-'; ?></td>
                                <td><?php echo ( $shift_end ) ? date_i18n( get_option( 'time_format' ), strtotime( $shift_end ) ) : '-'; ?></td>
                                <td><?php echo $completed_collections_count; ?></td>
                                <td>
                                    <a href="<?php echo get_permalink(); ?>" class="button button-small">
                                        <?php _e( 'View Details', 'scrap-driver' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php
                        endwhile; endif;
                        wp_reset_postdata();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- All Collections Section -->
    <div class="sda-accordion-section">
        <div class="sda-accordion-header">
            <h2><?php _e( 'All Collections', 'scrap-driver' ); ?></h2>
            <span class="sda-accordion-icon"></span>
        </div>
        <div class="sda-accordion-content">
            <div class="sda-section">
                <table id="all-collections-table" class="display">
                    <thead>
                        <tr>
                            <th><?php _e( 'Order', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Driver', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Vehicle', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Collection Date', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Status', 'scrap-driver' ); ?></th>
                            <th><?php _e( 'Actions', 'scrap-driver' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ( $all_collections->have_posts() ) :
                            // Get all collection IDs for ordering
                            $collection_ids = wp_list_pluck( $all_collections->posts, 'ID' );
                            $collection_orders = \ScrapDriver\Frontend\Collection::get_collections_order( $collection_ids );

                            while ( $all_collections->have_posts() ) : $all_collections->the_post();
                                $collection_id = get_the_ID();
                                $driver_id = get_field( 'assigned_driver' );
                                $driver_name = $driver_id ? get_user_by( 'id', $driver_id )->display_name : __( 'Unassigned', 'scrap-driver' );
                                $vehicle_make = get_field( 'vehicle_info_make' );
                                $vehicle_model = get_field( 'vehicle_info_model' );
                                $vehicle_plate = get_field( 'vehicle_info_plate' );
                                $collection_date = get_field( 'collection_date' );
                                $status = get_post_meta( $collection_id, 'status', true );
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $collection_orders[$collection_id] ); ?></td>
                                    <td><?php echo esc_html( $driver_name ); ?></td>
                                    <td>
                                        <?php 
                                        echo esc_html( sprintf(
                                            '%s %s (%s)',
                                            $vehicle_make,
                                            $vehicle_model,
                                            $vehicle_plate
                                        ) ); 
                                        ?>
                                    </td>
                                    <td data-sort="<?php echo esc_attr( $collection_date ); ?>">
                                        <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $collection_date ) ) ); ?>
                                    </td>
                                    <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( get_permalink( $collection_id ) ); ?>" 
                                           class="button button-small">
                                            <?php _e( 'View', 'scrap-driver' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php
                            endwhile;
                        endif;
                        wp_reset_postdata();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?> 