<?php
namespace ScrapDriver;

class Frontend {
    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        add_filter('single_template', array($this, 'load_collection_template'));
        
        // Change to theme_page_templates filter
        add_filter('theme_page_templates', array($this, 'add_collections_template'));
        add_filter('template_include', array($this, 'load_collections_list_template'));
    }

    /**
     * Add the collections template to the page template dropdown
     */
    public function add_collections_template($templates) {
        $templates['view-collections.php'] = __('Collections List', 'scrap-driver');
        $templates['view-todays-collections.php'] = __('Today\'s Collections', 'scrap-driver');
        return $templates;
    }

    public function load_collections_list_template($template) {
        // Get the template selected for the page
        if (is_page()) {
            $page_template = get_page_template_slug();
            
            if ('view-collections.php' === $page_template) {
                $custom_template = SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/view-collections.php';
                if (file_exists($custom_template)) {
                    return $custom_template;
                }
            }

            if ('view-todays-collections.php' === $page_template) {
                $custom_template = SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/view-todays-collections.php';
                if (file_exists($custom_template)) {
                    return $custom_template;
                }
            }
        }
        
        return $template;
    }

    public function load_collection_template($template) {
        global $post;
        if ($post->post_type === 'sda-collection') {
            $custom_template = SCRAP_DRIVER_PLUGIN_DIR . 'frontend/templates/single-collection.php';

            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }


    public function enqueue_assets() {
        if (is_page()) {
            $page_template = get_page_template_slug();

            if ('view-collections.php' === $page_template || 'view-todays-collections.php' === $page_template) {
                wp_enqueue_style('datatables', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/css/datatables.min.css');
                wp_enqueue_script('datatables', SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/datatables.min.js', array('jquery'), null, true);
            }
        }



        // Enqueue frontend CSS
        wp_enqueue_style(
            'scrap-driver-frontend',
            SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/css/frontend.css',
            array(),
            SCRAP_DRIVER_VERSION
        );

        // Enqueue frontend JavaScript
        wp_enqueue_script(
            'scrap-driver-frontend',
            SCRAP_DRIVER_PLUGIN_URL . 'frontend/assets/js/frontend.js',
            array('jquery'),
            SCRAP_DRIVER_VERSION,
            true
        );
    }
} 