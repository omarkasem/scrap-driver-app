<?php
namespace ScrapDriver\Admin;

class Api {
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        // Update collection status
        register_rest_route('scrap-driver/v1', '/collections/(?P<id>\d+)/status', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_collection_status'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'status' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'notes' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));

        // Handle photo upload
        register_rest_route('scrap-driver/v1', '/collections/(?P<id>\d+)/photos', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_collection_photo'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }

    public function update_collection_status($request) {
        $post_id = $request->get_param('id');
        $status = $request->get_param('status');
        $notes = $request->get_param('notes');

        update_post_meta($post_id, '_collection_status', sanitize_text_field($status));
        if ($notes) {
            update_post_meta($post_id, '_collection_notes', sanitize_textarea_field($notes));
        }

        return rest_ensure_response(array(
            'status' => 'success',
            'message' => 'Collection status updated'
        ));
    }

    public function upload_collection_photo($request) {
        $post_id = $request->get_param('id');
        $files = $request->get_file_params();

        if (!empty($files['photo'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('photo', $post_id);

            if (is_wp_error($attachment_id)) {
                return new \WP_Error('upload_error', $attachment_id->get_error_message());
            }

            // Add the photo to collection gallery
            $gallery = get_post_meta($post_id, '_collection_photos', true);
            if (empty($gallery)) {
                $gallery = array();
            }
            $gallery[] = $attachment_id;
            update_post_meta($post_id, '_collection_photos', $gallery);

            return rest_ensure_response(array(
                'status' => 'success',
                'attachment_id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id)
            ));
        }

        return new \WP_Error('no_photo', 'No photo was uploaded');
    }

    public function check_permissions() {
        return current_user_can('manage_options');
    }
}

new Api();