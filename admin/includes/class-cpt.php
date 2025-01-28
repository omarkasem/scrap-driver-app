<?php
namespace ScrapDriver\Admin;

class CPT {
    public function __construct() {
        add_action('init', array($this, 'register_collection_post_type'));
        add_action('init', array($this, 'register_shift_post_type'));
        add_action('admin_notices', array($this, 'add_manual_sync_button'));
        add_filter('manage_sda-collection_posts_columns', array($this, 'set_collection_columns'));
        add_action('manage_sda-collection_posts_custom_column', array($this, 'render_collection_columns'), 10, 2);
        add_filter('manage_edit-sda-collection_sortable_columns', array($this, 'set_sortable_columns'));
        add_action('restrict_manage_posts', array($this, 'add_collection_filters'));
        add_action('pre_get_posts', array($this, 'filter_collections_by_meta'));
        add_action('bulk_edit_custom_box', array($this, 'add_bulk_edit_fields'), 10, 2);
        add_action('save_post', array($this, 'save_bulk_edit_fields'));
        add_filter('manage_sda-shift_posts_columns', array($this, 'set_shift_columns'));
        add_action('manage_sda-shift_posts_custom_column', array($this, 'render_shift_columns'), 10, 2);
    }

    public function register_collection_post_type() {
        $labels = array(
            'name'               => _x('Collections', 'post type general name', 'scrap-driver'),
            'singular_name'      => _x('Collection', 'post type singular name', 'scrap-driver'),
            'menu_name'          => _x('Collections', 'admin menu', 'scrap-driver'),
            'add_new'            => _x('Add New', 'collection', 'scrap-driver'),
            'add_new_item'       => __('Add New Collection', 'scrap-driver'),
            'edit_item'          => __('Edit Collection', 'scrap-driver'),
            'new_item'           => __('New Collection', 'scrap-driver'),
            'view_item'          => __('View Collection', 'scrap-driver'),
            'search_items'       => __('Search Collections', 'scrap-driver'),
            'not_found'          => __('No collections found', 'scrap-driver'),
            'not_found_in_trash' => __('No collections found in Trash', 'scrap-driver'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_nav_menus'   => false,
            'supports'            => array('title'),
            'has_archive'         => false,
            'rewrite'             => array('slug' => 'collection', 'with_front' => false),
            'menu_icon'           => 'dashicons-database',
        );

        register_post_type('sda-collection', $args);
    }

    public function register_shift_post_type() {
        $labels = array(
            'name'               => _x('Driver Shifts', 'post type general name', 'scrap-driver'),
            'singular_name'      => _x('Driver Shift', 'post type singular name', 'scrap-driver'),
            'menu_name'          => _x('Driver Shifts', 'admin menu', 'scrap-driver'),
            'add_new'            => _x('Add New', 'shift', 'scrap-driver'),
            'add_new_item'       => __('Add New Shift', 'scrap-driver'),
            'edit_item'          => __('Edit Shift', 'scrap-driver'),
            'new_item'           => __('New Shift', 'scrap-driver'),
            'view_item'          => __('View Shift', 'scrap-driver'),
            'search_items'       => __('Search Shifts', 'scrap-driver'),
            'not_found'          => __('No shifts found', 'scrap-driver'),
            'not_found_in_trash' => __('No shifts found in Trash', 'scrap-driver'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_nav_menus'   => false,
            'supports'            => array('title'),
            'has_archive'         => false,
            'rewrite'             => array('slug' => 'shift', 'with_front' => false),
            'menu_icon'           => 'dashicons-clock',
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        );

        register_post_type('sda-shift', $args);
    }

    /**
     * Adds a Manual Sync button in the collections list page
     */
    public function add_manual_sync_button() {
        $screen = get_current_screen();
        
        // Only show on the collections list page
        if ($screen->post_type === 'sda-collection' && $screen->id === 'edit-sda-collection') {
            // Show success message if sync was performed
            if (isset($_GET['sync']) && $_GET['sync'] === 'success') {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Collections synchronized successfully!', 'scrap-driver'); ?></p>
                </div>
                <?php
            }
            ?>
            <div class="wrap">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=sda-collection&sda-manual-sync-collections=1')); ?>" 
                   class="page-title-action button button-primary"
                   onclick="return confirm('<?php echo esc_js(__('Are you sure you want to manually sync?', 'scrap-driver')); ?>');">
                    <?php esc_html_e('Manual Sync', 'scrap-driver'); ?>
                </a>
            </div>
            <?php
        }
    }

    public function set_collection_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['driver'] = __('Driver', 'scrap-driver');
        $new_columns['collection_date'] = __('Collection Date', 'scrap-driver');
        $new_columns['status'] = __('Status', 'scrap-driver');
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    public function render_collection_columns($column, $post_id) {
        switch ($column) {
            case 'driver':
                $driver_id = get_post_meta($post_id, 'assigned_driver', true);
                $driver = get_user_by('id', $driver_id);
                echo $driver ? esc_html($driver->display_name) : '—';
                break;
            case 'collection_date':
                $date = get_post_meta($post_id, 'collection_date', true);
                echo $date ? esc_html(date('Y-m-d', strtotime($date))) : '—';
                break;
            case 'status':
                $status = get_field('status', $post_id);
                if ($status) {
                    echo $status;
                } else {
                    echo '—';
                }
                break;
        }
    }

    public function set_sortable_columns($columns) {
        $columns['collection_date'] = 'collection_date';
        $columns['status'] = 'status';
        return $columns;
    }

    public function add_collection_filters($post_type) {
        if ($post_type !== 'sda-collection') {
            return;
        }

        // Driver filter
        $drivers = get_users(array('role' => 'driver'));
        $current_driver = isset($_GET['driver_filter']) ? $_GET['driver_filter'] : '';
        ?>
        <select name="driver_filter">
            <option value=""><?php _e('All Drivers', 'scrap-driver'); ?></option>
            <?php foreach ($drivers as $driver) : ?>
                <option value="<?php echo esc_attr($driver->ID); ?>" <?php selected($current_driver, $driver->ID); ?>>
                    <?php echo esc_html($driver->display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php

        // Status filter
        $current_status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
        $statuses = sda_get_statuses_names();
        ?>
        <select name="status_filter">
            <option value=""><?php _e('All Statuses', 'scrap-driver'); ?></option>
            <?php foreach ($statuses as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_status, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php

        // Collection date filter (existing code)
        global $wpdb;
        $dates = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = %s 
                AND meta_value != '' 
                ORDER BY meta_value DESC",
                'collection_date'
            )
        );

        $current_date = isset($_GET['collection_date_filter']) ? $_GET['collection_date_filter'] : '';
        ?>
        <select name="collection_date_filter">
            <option value=""><?php _e('Collection Dates', 'scrap-driver'); ?></option>
            <?php foreach ($dates as $date) : ?>
                <option value="<?php echo esc_attr($date); ?>" <?php selected($current_date, $date); ?>>
                    <?php echo esc_html(date('F j, Y', strtotime($date))); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function filter_collections_by_meta($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'sda-collection') {
            return;
        }

        $meta_query = array();

        // Filter by driver
        if (!empty($_GET['driver_filter'])) {
            $meta_query[] = array(
                'key' => 'assigned_driver',
                'value' => sanitize_text_field($_GET['driver_filter']),
            );
        }

        // Filter by status
        if (!empty($_GET['status_filter'])) {
            $meta_query[] = array(
                'key' => 'status',
                'value' => sanitize_text_field($_GET['status_filter']),
            );
        }

        // Filter by date
        if (!empty($_GET['collection_date_filter'])) {
            $meta_query[] = array(
                'key' => 'collection_date',
                'value' => sanitize_text_field($_GET['collection_date_filter']),
                'compare' => '=',
                'type' => 'DATE'
            );
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }

        // Handle sorting
        if ($query->get('orderby') === 'collection_date') {
            $query->set('meta_key', 'collection_date');
            $query->set('orderby', 'meta_value');
            $query->set('order', 'DESC');
        } elseif ($query->get('orderby') === 'status') {
            $query->set('meta_key', 'status');
            $query->set('orderby', 'meta_value');
        }
    }

    /**
     * Add custom fields to bulk edit section
     */
    public function add_bulk_edit_fields($column_name, $post_type) {
        if ($post_type !== 'sda-collection') return;

        switch ($column_name) {
            case 'driver':
                ?>
                <fieldset class="inline-edit-col-right">
                    <div class="inline-edit-col">
                        <label class="inline-edit-group">
                            <span class="title"><?php _e('Driver', 'scrap-driver'); ?></span>
                            <select name="assigned_driver">
                                <option value="-1"><?php _e('— No Change —', 'scrap-driver'); ?></option>
                                <?php
                                $drivers = get_users(array('role' => 'driver'));
                                foreach ($drivers as $driver) {
                                    printf(
                                        '<option value="%s">%s</option>',
                                        esc_attr($driver->ID),
                                        esc_html($driver->display_name)
                                    );
                                }
                                ?>
                            </select>
                        </label>
                    </div>
                </fieldset>
                <?php
                break;

            case 'collection_date':
                ?>
                <fieldset class="inline-edit-col-right">
                    <div class="inline-edit-col">
                        <label class="inline-edit-group">
                            <span class="title"><?php _e('Collection Date', 'scrap-driver'); ?></span>
                            <input type="date" name="collection_date" value="">
                        </label>
                    </div>
                </fieldset>
                <?php
                break;
        }
    }

    /**
     * Save bulk edit changes
     */
    public function save_bulk_edit_fields($post_id) {
        // Check if this is a bulk edit action
        if (!isset($_REQUEST['bulk_edit'])) {
            return;
        }

        // Check post type
        if (get_post_type($post_id) !== 'sda-collection') {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $updated = false;

        // Update assigned driver if changed
        if (isset($_REQUEST['assigned_driver']) && $_REQUEST['assigned_driver'] !== '-1') {
            update_field('assigned_driver', sanitize_text_field($_REQUEST['assigned_driver']), $post_id);
            $updated = true;
        }

        // Update collection date if changed
        if (!empty($_REQUEST['collection_date'])) {
            update_field('collection_date', sanitize_text_field($_REQUEST['collection_date']), $post_id);
            $updated = true;
        }

        // If any fields were updated, trigger the sync
        if ($updated) {
            // Get the Generator instance and sync
            $API = new \ScrapDriver\Admin\Sync();
            $API->sync_to_vrm($post_id);
        }
    }

    public function set_shift_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            if ($key === 'date') {
                continue; // Skip the date column
            }
            $new_columns[$key] = $value;
            
            // Add Adjustment Requests column after title
            if ($key === 'title') {
                $new_columns['adjustment_requests'] = __('Adjustment Requests', 'scrap-driver');
            }
        }
        
        return $new_columns;
    }

    public function render_shift_columns($column, $post_id) {
        if ($column === 'adjustment_requests') {
            $requests = get_post_meta($post_id, 'shift_adjustment_request', true);
            
            if (!is_array($requests) || empty($requests)) {
                echo '<span class="adjustment-status none">' . __('None', 'scrap-driver') . '</span>';
                return;
            }
            
            $has_pending = false;
            foreach ($requests as $request) {
                if (isset($request['status']) && $request['status'] === 'pending') {
                    $has_pending = true;
                    break;
                }
            }
            
            if ($has_pending) {
                echo '<span class="adjustment-status pending">' . __('Pending', 'scrap-driver') . '</span>';
            } else {
                echo '<span class="adjustment-status none">' . __('None', 'scrap-driver') . '</span>';
            }
        }
    }
}

new CPT();