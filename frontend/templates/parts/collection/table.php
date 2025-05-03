<div class="wrap sda-collections-list">
    <div class="sda-section">
        <?php
        // Add this before the table to pass translations to JS
        $datatable_translations = array(
            'search' => __('Search:', 'scrap-driver'),
            'lengthMenu' => __('Show _MENU_ entries', 'scrap-driver'),
            'info' => __('Showing _START_ to _END_ of _TOTAL_ entries', 'scrap-driver'),
            'infoEmpty' => __('Showing 0 to 0 of 0 entries', 'scrap-driver'),
            'infoFiltered' => __('(filtered from _MAX_ total entries)', 'scrap-driver'),
            'emptyTable' => __('No collections scheduled for today', 'scrap-driver'),
            'first' => __('First', 'scrap-driver'),
            'last' => __('Last', 'scrap-driver'),
            'next' => __('Next', 'scrap-driver'),
            'previous' => __('Previous', 'scrap-driver')
        );
        ?>
        <script>
            var sdaDataTableTranslations = <?php echo json_encode($datatable_translations); ?>;
        </script>
        <table id="collections-table" class="display">
            <thead>
                <tr>
                    <th><?php _e('Order', 'scrap-driver'); ?></th>
                    <th><?php _e('Driver', 'scrap-driver'); ?></th>
                    <th><?php _e('Vehicle', 'scrap-driver'); ?></th>
                    <th><?php _e('Status', 'scrap-driver'); ?></th>
                    <th><?php _e('Actions', 'scrap-driver'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($collections->have_posts()) :
                    // Get all collection IDs for ordering
                    $collection_ids = wp_list_pluck($collections->posts, 'ID');
                    $collection_orders = \ScrapDriver\Frontend\Collection::get_collections_order($collection_ids);

                    while ($collections->have_posts()) : $collections->the_post();
                        $collection_id = get_the_ID();
                        $driver_id = get_field('assigned_driver');
                        $driver_name = $driver_id ? get_user_by('id', $driver_id)->display_name : __('Unassigned', 'scrap-driver');
                        $vehicle_make = get_field('vehicle_info_make');
                        $vehicle_model = get_field('vehicle_info_model');
                        $vehicle_plate = get_field('vehicle_info_plate');
                        $status = get_post_meta($collection_id, 'status', true);
                        ?>
                        <tr>
                            <td><?php echo esc_html($collection_orders[$collection_id]); ?></td>
                            <td><?php echo esc_html($driver_name); ?></td>
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
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_permalink($collection_id)); ?>" 
                                   class="button button-small">
                                    <?php _e('View', 'scrap-driver'); ?>
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