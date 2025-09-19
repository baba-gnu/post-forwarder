<?php
/**
 * Plugin Name: Post forwarder
 * Description: Forwards post to other wordpress sites
 * Version: 2.1
 * Author: Sylwester Ulatowski
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register settings
add_action('admin_menu', function () {
    add_options_page('Post Forwarding', 'Post Forwarding', 'manage_options', 'post-forwarding', 'post_forwarding_settings_page');
});

add_action('admin_init', function () {
    register_setting('post_forwarding', 'post_forwarding_options');
});

// Add meta box to post editor
add_action('add_meta_boxes', function() {
    $post_types = get_post_types(array('public' => true), 'names');
    foreach ($post_types as $post_type) {
        add_meta_box(
            'post_forwarding_meta_box',
            'Post Forwarder',
            'post_forwarding_meta_box_callback',
            $post_type,
            'side',
            'default'
        );
    }
});

// Meta box callback function
function post_forwarding_meta_box_callback($post) {
    wp_nonce_field('post_forwarding_meta_box', 'post_forwarding_meta_box_nonce');
    
    $options = get_option('post_forwarding_options', array());
    $mappings_json = isset($options['mappings']) ? $options['mappings'] : '{}';
    $mappings = json_decode($mappings_json, true);
    $selected_products = get_post_meta($post->ID, 'product', false);
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<strong>Select Portals to Forward:</strong>';
    echo '</div>';
    
    if (!empty($mappings) && is_array($mappings)) {
        foreach ($mappings as $product_key => $mapping) {
            $portal_name = isset($mapping['name']) ? $mapping['name'] : $product_key;
            $portal_url = isset($mapping['url']) ? $mapping['url'] : '';
            $display_name = $portal_name . ($portal_url ? ' (' . parse_url($portal_url, PHP_URL_HOST) . ')' : '');
            
            $is_selected = in_array($product_key, $selected_products);
            
            echo '<div style="margin-bottom: 8px;">';
            echo '<label style="display: flex; align-items: center;">';
            echo '<input type="checkbox" name="post_forwarding_product[]" value="' . esc_attr($product_key) . '"' . ($is_selected ? ' checked' : '') . ' style="margin-right: 8px;">';
            echo '<span>' . esc_html($display_name) . '</span>';
            echo '</label>';
            echo '</div>';
        }
    } else {
        echo '<p style="color: #666; font-style: italic;">No portals configured</p>';
    }
    
    echo '<p style="font-size: 11px; color: #666; margin-top: 10px; border-top: 1px solid #ddd; padding-top: 8px;">Configure portals in Settings → Post Forwarding</p>';
}

// Save meta box data
add_action('save_post', function($post_id) {
    if (!isset($_POST['post_forwarding_meta_box_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['post_forwarding_meta_box_nonce'], 'post_forwarding_meta_box')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Clear existing product meta
    delete_post_meta($post_id, 'product');

    if (isset($_POST['post_forwarding_product']) && is_array($_POST['post_forwarding_product'])) {
        foreach ($_POST['post_forwarding_product'] as $product) {
            $product = sanitize_text_field($product);
            if (!empty($product)) {
                add_post_meta($post_id, 'product', $product);
            }
        }
    }
}, 5, 1);

// Settings page HTML
function post_forwarding_settings_page() {
    $options = get_option('post_forwarding_options', array());
    ?>
    <div class="wrap">
        <h1>Post Forwarding</h1>
        <form method="post" action="options.php">
            <?php settings_fields('post_forwarding'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Forwarding</th>
                    <td><input type="checkbox" name="post_forwarding_options[enabled]" value="1" <?php checked(isset($options['enabled']) ? $options['enabled'] : '', 1); ?>></td>
                </tr>
                <tr>
                    <th scope="row">Forwarded Post Status</th>
                    <td>
                        <select name="post_forwarding_options[post_status]">
                            <option value="publish" <?php selected(isset($options['post_status']) ? $options['post_status'] : '', 'publish'); ?>>Publish</option>
                            <option value="draft" <?php selected(isset($options['post_status']) ? $options['post_status'] : '', 'draft'); ?>>Draft</option>
                        </select>
                    </td>
                </tr>
                <tr><th colspan="2"><strong>Destination Mapping (product → site)</strong></th></tr>
                <tr>
                    <th scope="row">Mappings (JSON)</th>
                    <td>
                        <textarea name="post_forwarding_options[mappings]" rows="10" cols="70"><?php echo esc_textarea(isset($options['mappings']) ? $options['mappings'] : ''); ?></textarea><br>
                        <small>Example: {"portal1": {"name": "My Portal", "url": "https://example.com", "user": "1234", "password": "abcd-efgh-ijkl-mnop"}}</small>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Helper function to upload and set featured image
function post_forwarder_set_featured_image($remote_post_id, $image_url, $target, $post_type = 'post') {
    error_log("[Post Forwarder] Attempting to set featured image for post $remote_post_id from $image_url");

    // First, upload the image to the remote site
    $media_api_url = rtrim($target['url'], '/') . '/wp-json/wp/v2/media';
    $auth = base64_encode($target['user'] . ':' . $target['password']);

    // Download the image content
    $image_response = wp_remote_get($image_url, array('timeout' => 30));
    if (is_wp_error($image_response)) {
        error_log("[Post Forwarder] Failed to download image: " . $image_response->get_error_message());
        return;
    }

    $image_data = wp_remote_retrieve_body($image_response);
    $image_content_type = wp_remote_retrieve_header($image_response, 'content-type');
    
    // Get filename from URL
    $filename = basename(parse_url($image_url, PHP_URL_PATH));
    if (empty($filename) || strpos($filename, '.') === false) {
        $filename = 'featured-image.jpg';
    }

    // Upload image to remote site
    $boundary = wp_generate_password(24);
    $headers = array(
        'Authorization' => 'Basic ' . $auth,
        'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        'Content-Disposition' => 'attachment; filename="' . $filename . '"'
    );

    $body = "--$boundary\r\n";
    $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"$filename\"\r\n";
    $body .= "Content-Type: $image_content_type\r\n\r\n";
    $body .= $image_data . "\r\n";
    $body .= "--$boundary--\r\n";

    $upload_response = wp_remote_post($media_api_url, array(
        'headers' => $headers,
        'body' => $body,
        'timeout' => 60
    ));

    $upload_code = wp_remote_retrieve_response_code($upload_response);
    $upload_body = wp_remote_retrieve_body($upload_response);

    if ($upload_code >= 200 && $upload_code < 300) {
        $uploaded_media = json_decode($upload_body, true);
        if (isset($uploaded_media['id'])) {
            $media_id = $uploaded_media['id'];
            error_log("[Post Forwarder] Image uploaded successfully with ID: $media_id");

            // Now set it as featured image - construct the correct URL
            if ($post_type === 'post') {
                $post_update_url = rtrim($target['url'], '/') . '/wp-json/wp/v2/posts/' . $remote_post_id;
            } else {
                $post_update_url = rtrim($target['url'], '/') . '/wp-json/wp/v2/' . $post_type . '/' . $remote_post_id;
            }
            
            error_log("[Post Forwarder] Setting featured image via: $post_update_url");
            
            $update_response = wp_remote_request($post_update_url, array(
                'method' => 'PUT',
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array('featured_media' => $media_id)),
                'timeout' => 30
            ));

            $update_code = wp_remote_retrieve_response_code($update_response);
            $update_body = wp_remote_retrieve_body($update_response);
            
            if ($update_code >= 200 && $update_code < 300) {
                error_log("[Post Forwarder] Featured image set successfully for post $remote_post_id");
            } else {
                error_log("[Post Forwarder] Failed to set featured image: $update_code Body: $update_body");
                
                // Try alternative method - POST to the same endpoint
                $update_response_alt = wp_remote_post($post_update_url, array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . $auth,
                        'Content-Type' => 'application/json',
                    ),
                    'body' => json_encode(array('featured_media' => $media_id)),
                    'timeout' => 30
                ));
                
                $alt_code = wp_remote_retrieve_response_code($update_response_alt);
                $alt_body = wp_remote_retrieve_body($update_response_alt);
                error_log("[Post Forwarder] Alternative POST method result: $alt_code Body: $alt_body");
            }
        }
    } else {
        error_log("[Post Forwarder] Failed to upload image: $upload_code $upload_body");
    }
}

// Forward post after import
function post_forward_post($post_id) {
    error_log("[Post Forwarder] post_forward_post called for post_id $post_id");

    // More robust duplicate prevention
    $processing_key = 'post_forwarding_processing_' . $post_id;
    $lock_key = 'post_forwarding_lock_' . $post_id;
    
    // Check if we're already processing this post
    if (get_transient($processing_key)) {
        error_log("[Post Forwarder] Already processing post $post_id, exiting");
        return;
    }
    
    // Try to acquire a lock - if it fails, another process is already working on it
    if (get_transient($lock_key)) {
        error_log("[Post Forwarder] Lock exists for post $post_id, exiting");
        return;
    }
    
    // Set both the processing flag and lock (lock expires faster)
    set_transient($lock_key, true, 30); // 30 seconds lock
    set_transient($processing_key, true, 120); // 2 minutes processing flag

    // Additional check - has this post been forwarded recently?
    $recent_forward_key = 'post_forwarded_' . $post_id;
    if (get_transient($recent_forward_key)) {
        error_log("[Post Forwarder] Post $post_id was recently forwarded, skipping");
        delete_transient($lock_key);
        delete_transient($processing_key);
        return;
    }

    if (defined('WP_IMPORTING')) {
        error_log("[Post Forwarder] WP_IMPORTING defined, exiting");
        delete_transient($lock_key);
        delete_transient($processing_key);
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        error_log("[Post Forwarder] Revision or autosave, exiting");
        delete_transient($lock_key);
        delete_transient($processing_key);
        return;
    }

    $options = get_option('post_forwarding_options', array());
    if (empty($options['enabled'])) {
        error_log("[Post Forwarder] Forwarding not enabled, exiting");
        delete_transient($lock_key);
        delete_transient($processing_key);
        return;
    }

    $xproducts = get_post_meta($post_id, 'product', false);
    if (empty($xproducts)) {
        error_log("[Post Forwarder] No product meta, exiting");
        delete_transient($lock_key);
        delete_transient($processing_key);
        return;
    }

    $mappings_json = isset($options['mappings']) ? $options['mappings'] : '';
    $mappings = json_decode($mappings_json, true);
    $post = get_post($post_id);
    if (!$post) {
        error_log("[Post Forwarder] No post object, exiting");
        delete_transient($lock_key);
        delete_transient($processing_key);
        return;
    }

    // Set the "recently forwarded" flag early to prevent other instances
    set_transient($recent_forward_key, true, 300); // 5 minutes

    // Get the original post type
    $original_post_type = $post->post_type;
    error_log("[Post Forwarder] Original post type: $original_post_type");

    // Prepare data once for all requests
    $categories = wp_get_object_terms($post_id, 'category', array('fields' => 'ids'));
    $tags = wp_get_object_terms($post_id, 'post_tag', array('fields' => 'ids'));

    // Get featured image/thumbnail
    $featured_image_id = get_post_thumbnail_id($post_id);
    $featured_image_url = null;
    if ($featured_image_id) {
        $featured_image_url = wp_get_attachment_image_src($featured_image_id, 'full');
        $featured_image_url = $featured_image_url ? $featured_image_url[0] : null;
        error_log("[Post Forwarder] Found featured image: $featured_image_url");
    }

    // Get all meta fields
    $meta = get_post_meta($post_id, '', true);
    
    // Remove WordPress internal meta keys and product meta
    $reserved = array('_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', 'product', '_thumbnail_id');
    foreach ($reserved as $key) {
        unset($meta[$key]);
    }

    // Get ACF fields separately and include them in meta
    $acf_fields = array();
    if (function_exists('get_fields')) {
        $acf_fields = get_fields($post_id);
        if (!$acf_fields) $acf_fields = array();
        error_log("[Post Forwarder] Found " . count($acf_fields) . " ACF fields");
    }

    // Flatten remaining meta array
    $meta_flattened = array();
    foreach ($meta as $key => $value) {
        // Skip ACF-related meta keys to avoid duplication
        if (strpos($key, 'field_') === 0 || strpos($key, '_field_') === 0) {
            continue;
        }
        
        if (is_array($value) && count($value) === 1) {
            $meta_flattened[$key] = $value[0];
        } elseif (!empty($value)) {
            $meta_flattened[$key] = $value;
        }
    }

    // Add ACF fields to meta (more reliable than separate acf parameter)
    if (!empty($acf_fields)) {
        foreach ($acf_fields as $field_key => $field_value) {
            $meta_flattened[$field_key] = $field_value;
        }
    }

    // Build request body for WordPress REST API
    $post_status = isset($options['post_status']) ? $options['post_status'] : 'draft';
    $body = array(
        'title'   => $post->post_title,
        'content' => $post->post_content,
        'excerpt' => $post->post_excerpt,
        'status'  => $post_status
    );

    // Add categories and tags if they exist
    if (!empty($categories) && !is_wp_error($categories)) {
        $body['categories'] = $categories;
    }
    if (!empty($tags) && !is_wp_error($tags)) {
        $body['tags'] = $tags;
    }

    // Add meta if there are any
    if (!empty($meta_flattened)) {
        $body['meta'] = $meta_flattened;
    }

    $forwarding_successful = false;

    // Loop through each selected product and send to corresponding portal
    foreach ($xproducts as $xproduct) {
        if (!isset($mappings[$xproduct])) {
            error_log("[Post Forwarder] No mapping for product $xproduct, skipping");
            continue;
        }

        $target = $mappings[$xproduct];
        
        // Determine the correct REST API endpoint based on post type
        if ($original_post_type === 'post') {
            $api_url = rtrim($target['url'], '/') . '/wp-json/wp/v2/posts';
        } else {
            // For custom post types, use the post type name in the endpoint
            $api_url = rtrim($target['url'], '/') . '/wp-json/wp/v2/' . $original_post_type;
        }
        
        $auth = base64_encode($target['user'] . ':' . $target['password']);

        error_log("[Post Forwarder] Using API endpoint: $api_url for post type: $original_post_type");
        error_log("[Post Forwarder] Request body: " . json_encode($body, JSON_PRETTY_PRINT));

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . $auth,
                'Content-Type'  => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // If custom post type endpoint returns 404, try with the posts endpoint but include type parameter
        if ($response_code === 404 && $original_post_type !== 'post') {
            error_log("[Post Forwarder] Custom post type endpoint failed (404), trying posts endpoint with type parameter");
            
            $fallback_url = rtrim($target['url'], '/') . '/wp-json/wp/v2/posts';
            $body_with_type = $body;
            $body_with_type['type'] = $original_post_type;
            
            $response = wp_remote_post($fallback_url, array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type'  => 'application/json',
                ),
                'body' => json_encode($body_with_type),
                'timeout' => 30
            ));

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            error_log("[Post Forwarder] Fallback attempt to $fallback_url with type=$original_post_type. Response: $response_code");
        }

        // If post was created successfully and we have a featured image, upload and set it
        if ($response_code >= 200 && $response_code < 300) {
            $forwarding_successful = true;
            
            if ($featured_image_url) {
                $created_post = json_decode($response_body, true);
                if (isset($created_post['id'])) {
                    $remote_post_id = $created_post['id'];
                    // Pass the post type to the function
                    post_forwarder_set_featured_image($remote_post_id, $featured_image_url, $target, $original_post_type);
                }
            }
        }

        // Log response
        $log_entry = "[Post Forwarder] Sent post {$post_id} (type: $original_post_type) to {$api_url} (product: $xproduct). Response: " .
            $response_code . " Body: " . $response_body;
        error_log($log_entry);

        // Log errors
        if (is_wp_error($response)) {
            error_log("[Post Forwarder ERROR] Product $xproduct: " . $response->get_error_message());
        } else {
            if ($response_code < 200 || $response_code >= 300) {
                error_log("[Post Forwarder HTTP ERROR] Product $xproduct: $response_code Body: " . $response_body);
            } else {
                error_log("[Post Forwarder SUCCESS] Product $xproduct: Post forwarded successfully as $original_post_type");
            }
        }
    }

    // If no forwarding was successful, remove the "recently forwarded" flag so it can be tried again
    if (!$forwarding_successful) {
        delete_transient($recent_forward_key);
        error_log("[Post Forwarder] No successful forwards, allowing retry");
    }

    // Clean up the transients at the end
    delete_transient($lock_key);
    delete_transient($processing_key);
    
    error_log("[Post Forwarder] Finished processing post $post_id");
}

add_action('save_post', 'post_forward_post', 20, 1);
?>
