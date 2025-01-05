<?php
namespace ScrapDriver\Admin;

class CPT {
    public function __construct() {
        add_action('init', array($this, 'register_collection_post_type'));
        add_action('admin_notices', array($this, 'add_manual_sync_button'));
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
            'show_in_nav_menus'   => true,
            'supports'            => array('title'),
            'has_archive'         => true,
            'rewrite'             => array('slug' => 'collection', 'with_front' => false),
            'menu_icon'           => 'dashicons-database',
        );

        register_post_type('sda-collection', $args);
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

}

new CPT();