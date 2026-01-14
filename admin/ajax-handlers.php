<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security check helper - verifies nonce and capabilities.
 */
function wrms_verify_ajax_request()
{
    check_ajax_referer('wrms_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
    }
}

/**
 * Get next unsynced posts by type (supports batch processing).
 */
function wrms_get_next_unsynced_posts($post_type, $post_status = 'publish', $batch_size = 10)
{
    return get_posts(array(
        'post_type' => $post_type,
        'post_status' => $post_status,
        'posts_per_page' => $batch_size,
        'meta_query' => array(
            'relation' => 'OR',
            array('key' => '_wrms_synced', 'compare' => 'NOT EXISTS'),
            array('key' => '_wrms_synced', 'value' => '0', 'compare' => '=')
        )
    ));
}

/**
 * Remove RankMath meta from posts.
 */
function wrms_remove_post_type_meta($post_type, $post_status = 'publish')
{
    $args = array(
        'post_type' => $post_type,
        'post_status' => $post_status,
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            array('key' => '_wrms_synced', 'compare' => 'EXISTS')
        )
    );
    $posts = get_posts($args);
    $removed = 0;

    foreach ($posts as $post_id) {
        delete_post_meta($post_id, 'rank_math_title');
        delete_post_meta($post_id, 'rank_math_description');
        delete_post_meta($post_id, 'rank_math_focus_keyword');
        delete_post_meta($post_id, '_wrms_synced');
        $removed++;
    }

    return array('total' => count($posts), 'removed' => $removed);
}

// =====================================================
// AUTO SYNC & STATS HANDLERS
// =====================================================

add_action('wp_ajax_wrms_update_auto_sync', 'wrms_update_auto_sync_handler');
function wrms_update_auto_sync_handler()
{
    wrms_verify_ajax_request();
    $auto_sync = isset($_POST['auto_sync']) ? sanitize_text_field($_POST['auto_sync']) : '0';
    update_option('wrms_auto_sync', $auto_sync);
    wp_send_json_success();
}

add_action('wp_ajax_wrms_update_stats', 'wrms_update_stats_handler');
function wrms_update_stats_handler()
{
    wrms_verify_ajax_request();
    $stats = wrms_calculate_and_cache_stats();
    $stats['timestamp'] = time();
    wp_send_json_success($stats);
}

// =====================================================
// COUNT HANDLERS
// =====================================================

add_action('wp_ajax_wrms_get_product_count', 'wrms_get_product_count_handler');
function wrms_get_product_count_handler()
{
    wrms_verify_ajax_request();
    wp_send_json_success(array('count' => (int) wp_count_posts('product')->publish));
}

add_action('wp_ajax_wrms_get_category_count', 'wrms_get_category_count_handler');
function wrms_get_category_count_handler()
{
    wrms_verify_ajax_request();
    wp_send_json_success(array('count' => (int) wp_count_terms('product_cat')));
}

add_action('wp_ajax_wrms_get_page_count', 'wrms_get_page_count_handler');
function wrms_get_page_count_handler()
{
    wrms_verify_ajax_request();
    wp_send_json_success(array('count' => (int) wp_count_posts('page')->publish));
}

add_action('wp_ajax_wrms_get_media_count', 'wrms_get_media_count_handler');
function wrms_get_media_count_handler()
{
    wrms_verify_ajax_request();
    wp_send_json_success(array('count' => (int) wp_count_posts('attachment')->inherit));
}

add_action('wp_ajax_wrms_get_post_count', 'wrms_get_post_count_handler');
function wrms_get_post_count_handler()
{
    wrms_verify_ajax_request();
    wp_send_json_success(array('count' => (int) wp_count_posts('post')->publish));
}

// =====================================================
// SYNC HANDLERS
// =====================================================

add_action('wp_ajax_wrms_sync_next_product', 'wrms_sync_next_product_handler');
function wrms_sync_next_product_handler()
{
    wrms_verify_ajax_request();

    $batch_size = isset($_POST['batch_size']) ? min(50, max(1, intval($_POST['batch_size']))) : 10;
    $posts = wrms_get_next_unsynced_posts('product', 'publish', $batch_size);
    $processed_items = array();

    foreach ($posts as $post) {
        $product = wc_get_product($post->ID);
        if ($product) {
            wrms_maybe_sync_product($post->ID, null, true);
            update_post_meta($post->ID, '_wrms_synced', '1');
            $processed_items[] = array('id' => $post->ID, 'title' => $product->get_name());
        }
    }

    if (!empty($processed_items)) {
        $last_item = end($processed_items);
        wp_send_json_success(array(
            'processed' => count($processed_items),
            'product' => $last_item,
            'items' => $processed_items
        ));
    } else {
        wp_send_json_success(array('processed' => 0));
    }
}

add_action('wp_ajax_wrms_sync_next_category', 'wrms_sync_next_category_handler');
function wrms_sync_next_category_handler()
{
    wrms_verify_ajax_request();

    $batch_size = isset($_POST['batch_size']) ? min(50, max(1, intval($_POST['batch_size']))) : 10;
    $processed_ids = get_option('wrms_processed_categories', array());

    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'number' => $batch_size,
        'exclude' => $processed_ids,
        'orderby' => 'id',
        'order' => 'ASC'
    ));

    $processed_items = array();

    if (!empty($categories)) {
        foreach ($categories as $category) {
            wrms_maybe_sync_category($category->term_id, $category->term_taxonomy_id);
            $processed_ids[] = $category->term_id;
            $processed_items[] = array('id' => $category->term_id, 'name' => $category->name);
        }
        update_option('wrms_processed_categories', $processed_ids);

        $last_item = end($processed_items);
        wp_send_json_success(array(
            'processed' => count($processed_items),
            'category' => $last_item,
            'items' => $processed_items
        ));
    } else {
        delete_option('wrms_processed_categories');
        wp_send_json_success(array('processed' => 0));
    }
}

add_action('wp_ajax_wrms_sync_next_page', 'wrms_sync_next_page_handler');
function wrms_sync_next_page_handler()
{
    wrms_verify_ajax_request();

    $batch_size = isset($_POST['batch_size']) ? min(50, max(1, intval($_POST['batch_size']))) : 10;
    $pages = wrms_get_next_unsynced_posts('page', 'publish', $batch_size);
    $processed_items = array();

    foreach ($pages as $page) {
        wrms_maybe_sync_page($page->ID, $page, true);
        update_post_meta($page->ID, '_wrms_synced', '1');
        $processed_items[] = array('id' => $page->ID, 'title' => $page->post_title);
    }

    if (!empty($processed_items)) {
        $last_item = end($processed_items);
        wp_send_json_success(array(
            'processed' => count($processed_items),
            'page' => $last_item,
            'items' => $processed_items
        ));
    } else {
        wp_send_json_success(array('processed' => 0));
    }
}

add_action('wp_ajax_wrms_sync_next_media', 'wrms_sync_next_media_handler');
function wrms_sync_next_media_handler()
{
    wrms_verify_ajax_request();

    $batch_size = isset($_POST['batch_size']) ? min(50, max(1, intval($_POST['batch_size']))) : 10;
    $media_items = wrms_get_next_unsynced_posts('attachment', 'inherit', $batch_size);
    $processed_items = array();

    foreach ($media_items as $media) {
        wrms_maybe_sync_media($media->ID);
        update_post_meta($media->ID, '_wrms_synced', '1');
        $processed_items[] = array('id' => $media->ID, 'title' => $media->post_title);
    }

    if (!empty($processed_items)) {
        $last_item = end($processed_items);
        wp_send_json_success(array(
            'processed' => count($processed_items),
            'media' => $last_item,
            'items' => $processed_items
        ));
    } else {
        wp_send_json_success(array('processed' => 0));
    }
}

add_action('wp_ajax_wrms_sync_next_post', 'wrms_sync_next_post_handler');
function wrms_sync_next_post_handler()
{
    wrms_verify_ajax_request();

    $batch_size = isset($_POST['batch_size']) ? min(50, max(1, intval($_POST['batch_size']))) : 10;
    $posts = wrms_get_next_unsynced_posts('post', 'publish', $batch_size);
    $processed_items = array();

    foreach ($posts as $post) {
        wrms_maybe_sync_post($post->ID, $post, true);
        update_post_meta($post->ID, '_wrms_synced', '1');
        $processed_items[] = array('id' => $post->ID, 'title' => $post->post_title);
    }

    if (!empty($processed_items)) {
        $last_item = end($processed_items);
        wp_send_json_success(array(
            'processed' => count($processed_items),
            'post' => $last_item,
            'items' => $processed_items
        ));
    } else {
        wp_send_json_success(array('processed' => 0));
    }
}

// =====================================================
// REMOVE META HANDLERS
// =====================================================

add_action('wp_ajax_wrms_remove_product_meta', 'wrms_remove_product_meta_handler');
function wrms_remove_product_meta_handler()
{
    wrms_verify_ajax_request();
    $result = wrms_remove_post_type_meta('product');
    wp_send_json_success($result);
}

add_action('wp_ajax_wrms_remove_category_meta', 'wrms_remove_category_meta_handler');
function wrms_remove_category_meta_handler()
{
    wrms_verify_ajax_request();

    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'fields' => 'ids',
        'meta_query' => array(
            array('key' => '_wrms_synced', 'compare' => 'EXISTS')
        )
    ));

    $removed = 0;
    foreach ($categories as $term_id) {
        delete_term_meta($term_id, 'rank_math_title');
        delete_term_meta($term_id, 'rank_math_description');
        delete_term_meta($term_id, 'rank_math_focus_keyword');
        delete_term_meta($term_id, '_wrms_synced');
        $removed++;
    }

    wp_send_json_success(array('total' => count($categories), 'removed' => $removed));
}

add_action('wp_ajax_wrms_remove_page_meta', 'wrms_remove_page_meta_handler');
function wrms_remove_page_meta_handler()
{
    wrms_verify_ajax_request();
    $result = wrms_remove_post_type_meta('page');
    wp_send_json_success($result);
}

add_action('wp_ajax_wrms_remove_media_meta', 'wrms_remove_media_meta_handler');
function wrms_remove_media_meta_handler()
{
    wrms_verify_ajax_request();
    $result = wrms_remove_post_type_meta('attachment', 'inherit');
    wp_send_json_success($result);
}

add_action('wp_ajax_wrms_remove_post_meta', 'wrms_remove_post_meta_handler');
function wrms_remove_post_meta_handler()
{
    wrms_verify_ajax_request();
    $result = wrms_remove_post_type_meta('post');
    wp_send_json_success($result);
}

// =====================================================
// URL & UTILITY HANDLERS
// =====================================================

add_action('wp_ajax_wrms_get_urls', 'wrms_get_urls_handler');
function wrms_get_urls_handler()
{
    wrms_verify_ajax_request();

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $chunk_size = isset($_POST['chunk_size']) ? intval($_POST['chunk_size']) : 100;
    $url_types = isset($_POST['url_types']) ? (array) $_POST['url_types'] : array();

    $urls = array();
    $total = 0;

    $type_handlers = array(
        'product' => 'wrms_get_product_urls',
        'page' => 'wrms_get_page_urls',
        'category' => 'wrms_get_category_urls',
        'tag' => 'wrms_get_tag_urls',
        'post' => 'wrms_get_post_urls'
    );

    foreach ($url_types as $type) {
        if (isset($type_handlers[$type]) && function_exists($type_handlers[$type])) {
            $result = call_user_func($type_handlers[$type], $offset, $chunk_size);
            $urls = array_merge($urls, $result['urls']);
            $total += $result['total'];
        }
    }

    wp_send_json_success(array('urls' => $urls, 'total' => $total));
}

add_action('wp_ajax_wrms_get_last_sync_time', 'wrms_get_last_sync_time_handler');
function wrms_get_last_sync_time_handler()
{
    wrms_verify_ajax_request();
    wp_send_json_success(array('last_sync_time' => wrms_get_last_sync_time()));
}

add_action('wp_ajax_wrms_update_last_sync_time', 'wrms_update_last_sync_time_handler');
function wrms_update_last_sync_time_handler()
{
    wrms_verify_ajax_request();
    wrms_update_last_sync_time();
    wp_send_json_success(array('message' => 'Last sync time updated.'));
}
